<?php
/**
 * RegenerateServiceTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model;

use Magento\Config\Console\Command\EmulatedAdminhtmlAreaProcessor;
use Magento\Config\Model\Config;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Config\Model\Config\Reader\Source\Deployed\SettingChecker;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Indexer\ConfigInterface as IndexerConfig;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\StateInterface;
use Magento\Indexer\Model\Processor\MakeSharedIndexValid;
use Magento\Store\Model\StoreManagerInterface;
use OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateCategoryRewrites;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateProductRewrites;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateService;
use OlegKoval\RegenerateUrlRewrites\Model\RunOptionsBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RegenerateServiceTest extends TestCase
{
    /**
     * @var RegenerateProductRewrites
     */
    private $productModel;

    /**
     * @var AppState&MockObject
     */
    private $appState;

    /**
     * @var SettingChecker&MockObject
     */
    private $settingChecker;

    /**
     * config path => [[scope, scope code, value], ...] saved through the config factory
     * @var array<string, array>
     */
    private array $savedConfig = [];

    /**
     * @var \Exception|null thrown by the config save
     */
    private ?\Exception $configSaveError = null;

    /**
     * indexer id => [status, shared index, exception thrown by reindexAll()]
     * @var array<string, array>
     */
    private array $indexers = [];

    /**
     * indexer ids reindexAll() was called for
     * @var string[]
     */
    private array $reindexed = [];

    /**
     * @var CacheManager&MockObject
     */
    private $cacheManager;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * stores passed to setCurrentStore(), in order
     * @var array
     */
    private array $currentStores = [];

    /**
     * store current while each indexer was rebuilt
     * @var array
     */
    private array $storeDuringReindex = [];

    /**
     * @var EmulatedAdminhtmlAreaProcessor&MockObject
     */
    private $areaProcessor;

    /**
     * callbacks run through the adminhtml area processor
     * @var int
     */
    private int $adminhtmlCalls = 0;

    /**
     * @var \Magento\Framework\Event\ManagerInterface&MockObject
     */
    private $eventManager;

    /**
     * cache events and operations, in order
     * @var string[]
     */
    private array $cacheSteps = [];

    /**
     * rows of the store table
     * @var array
     */
    private array $storeRows = [
        ['store_id' => '0', 'code' => 'admin'],
        ['store_id' => '1', 'code' => 'default'],
        ['store_id' => '2', 'code' => 'second'],
    ];

    /**
     * @var RegenerateService
     */
    private RegenerateService $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnCallback(fn (): array => $this->storeRows);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->appState = $this->createMock(AppState::class);
        $this->appState->method('getAreaCode')->willReturn('adminhtml');

        $this->productModel = new class () extends RegenerateProductRewrites {
            /**
             * store id => regenerate options it ran with
             * @var array<int, array>
             */
            public array $runs = [];

            /**
             * @var bool
             */
            public bool $orphansDeleted = false;

            /**
             * @var bool
             */
            public bool $hadReporter = false;

            public function __construct()
            {
                $this->regenerateOptions = $this->defaultRegenerateOptions;
            }

            /**
             * @param int $storeId
             * @return $this
             */
            public function regenerate(int $storeId = 0): static
            {
                $this->runs[$storeId] = $this->regenerateOptions;
                $this->hadReporter = $this->progressReporter !== null;
                if ($storeId === 2) {
                    $this->_addFailure('product', 5, 2, 'bad');
                }

                return $this;
            }

            /**
             * @return $this
             */
            public function deleteOrphanedRewrites(): static
            {
                $this->orphansDeleted = true;

                return $this;
            }

            /**
             * @return \OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface|null
             */
            public function getReporter(): ?ProgressReporterInterface
            {
                return $this->progressReporter;
            }
        };
        $categoryModel = (new \ReflectionClass(RegenerateCategoryRewrites::class))->newInstanceWithoutConstructor();

        $this->settingChecker = $this->createMock(SettingChecker::class);

        $configFactory = $this->createMock(ConfigFactory::class);
        $configFactory->method('create')->willReturnCallback(function (array $args): Config {
            $config = $this->createMock(Config::class);
            $config->method('setDataByPath')->willReturnCallback(
                function (string $path, $value) use ($args, $config): Config {
                    $this->savedConfig[$path][] = [$args['data']['scope'], $args['data']['scope_code'], $value];
                    return $config;
                }
            );
            $config->method('save')->willReturnCallback(function () use ($config): Config {
                if ($this->configSaveError !== null) {
                    throw $this->configSaveError;
                }
                return $config;
            });
            return $config;
        });

        $indexerConfig = $this->createMock(IndexerConfig::class);
        $indexerConfig->method('getIndexers')->willReturnCallback(fn (): array => $this->indexers);
        $indexerConfig->method('getIndexer')->willReturnCallback(
            fn (string $id): array => ['shared_index' => $this->indexers[$id][1]]
        );
        $indexerRegistry = $this->createMock(IndexerRegistry::class);
        $indexerRegistry->method('get')->willReturnCallback(function (string $id): IndexerInterface {
            $indexer = $this->createMock(IndexerInterface::class);
            $indexer->method('getStatus')->willReturn($this->indexers[$id][0]);
            $indexer->method('getTitle')->willReturn(ucfirst($id));
            $indexer->method('reindexAll')->willReturnCallback(function () use ($id): void {
                if ($this->indexers[$id][2] !== null) {
                    throw $this->indexers[$id][2];
                }
                $this->reindexed[] = $id;
                $this->storeDuringReindex[] = end($this->currentStores);
            });
            return $indexer;
        });
        $makeSharedIndexValid = $this->createMock(MakeSharedIndexValid::class);
        $makeSharedIndexValid->method('execute')->willReturn(true);

        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->cacheManager->method('getAvailableTypes')->willReturn(['config', 'full_page']);

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $callerStore = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $callerStore->method('getId')->willReturn('1');
        $this->storeManager->method('getStore')->willReturn($callerStore);
        $this->storeManager->method('setCurrentStore')->willReturnCallback(function ($store): void {
            $this->currentStores[] = $store;
        });

        $this->eventManager = $this->createMock(\Magento\Framework\Event\ManagerInterface::class);
        $this->eventManager->method('dispatch')->willReturnCallback(function (string $event): void {
            $this->cacheSteps[] = $event;
        });
        $this->cacheManager->method('clean')->willReturnCallback(function (): void {
            $this->cacheSteps[] = 'clean';
        });
        $this->cacheManager->method('flush')->willReturnCallback(function (): void {
            $this->cacheSteps[] = 'flush';
        });

        $this->areaProcessor = $this->createMock(EmulatedAdminhtmlAreaProcessor::class);
        $this->areaProcessor->method('process')->willReturnCallback(function (callable $callback) {
            $this->adminhtmlCalls++;
            return $callback();
        });

        $this->service = new RegenerateService(
            $resource,
            $this->appState,
            $this->storeManager,
            $this->productModel,
            $categoryModel,
            $configFactory,
            $this->settingChecker,
            $indexerRegistry,
            $indexerConfig,
            $makeSharedIndexValid,
            $this->cacheManager,
            $this->areaProcessor,
            $this->eventManager
        );
    }

    /**
     * @return void
     */
    public function testDefaultOptionsAreValid(): void
    {
        self::assertSame([], $this->service->validate($this->options()->create()));
    }

    /**
     * @return void
     */
    public function testValidateReportsEveryProblem(): void
    {
        $errors = $this->service->validate(
            $this->options()->setCategoryIds([3])->setProductIds([0])->setStoreIds([1, 9])->create()
        );

        self::assertSame([
            'ERROR: category IDs can not be used in a product run.',
            'ERROR: product ID should be greater than 0.',
            'ERROR: store with this ID not exists.',
        ], $errors);
        self::assertSame(
            ['ERROR: entity type should be "product" or "category".'],
            $this->service->validate($this->options()->setEntityType('cms')->create())
        );
    }

    /**
     * @return void
     */
    public function testValidateReportsTheFirstLockedScopeOfASuffix(): void
    {
        $this->settingChecker->method('isReadOnly')->willReturnCallback(
            fn (string $path, string $scope, string $code): bool => $scope === 'stores' && $code === 'default'
        );

        self::assertSame(
            [
                'ERROR: could not save product URL suffix: '
                . 'value is locked via app/etc/config.php (scope: stores/default)',
            ],
            $this->service->validate($this->options()->setProductUrlSuffix('.htm')->create())
        );
    }

    /**
     * @return void
     */
    public function testInvalidOptionsThrowBeforeAnythingRuns(): void
    {
        try {
            $this->service->run($this->options()->setStoreIds([9])->setProductUrlSuffix('.htm')->create());
            self::fail('InputException expected');
        } catch (InputException $e) {
            self::assertSame('ERROR: store with this ID not exists.', $e->getMessage());
        }

        self::assertSame([], $this->productModel->runs);
        self::assertSame([], $this->savedConfig);
    }

    /**
     * @return void
     */
    public function testAllStoresRunLimitsStoreZeroToTheDefaultScope(): void
    {
        $result = $this->service->run($this->options()->setProductIds([4, 5])->setSkipExisting(true)->create());

        self::assertSame([0, 1, 2], $result->getProcessedStoreIds());
        self::assertSame(
            [0 => true, 1 => false, 2 => false],
            array_map(fn (array $options): bool => $options['defaultScopeOnly'], $this->productModel->runs)
        );
        self::assertSame([4, 5], $this->productModel->runs[1]['productsFilter']);
        self::assertTrue($this->productModel->runs[1]['skipExisting']);
        self::assertTrue($this->productModel->runs[1]['showProgress']);
    }

    /**
     * @return void
     */
    public function testAStoreViewAddedBetweenRunsIsSeenByTheNextRun(): void
    {
        $this->service->run($this->options()->create());
        $this->storeRows[] = ['store_id' => '3', 'code' => 'third'];

        self::assertSame([], $this->service->validate($this->options()->setStoreIds([3])->create()));
        self::assertSame([0, 1, 2, 3], $this->service->run($this->options()->create())->getProcessedStoreIds());
    }

    /**
     * @return void
     */
    public function testExplicitStoreZeroRunStillGeneratesGlobally(): void
    {
        $result = $this->service->run($this->options()->setStoreIds([0])->create());

        self::assertSame([0], $result->getProcessedStoreIds());
        self::assertFalse($this->productModel->runs[0]['defaultScopeOnly']);
    }

    /**
     * @return void
     */
    public function testRunReportsEachStepAndDetachesTheReporter(): void
    {
        $reporter = $this->reporter();
        $this->indexers = ['catalog' => [StateInterface::STATUS_VALID, null, null]];

        $this->service->run(
            (new RunOptionsBuilder())->setStoreIds([1])->setDeleteOrphanedRewrites(true)->create(),
            $reporter
        );

        self::assertSame(
            "\n[Type: product, Store ID: 1, Store View code: default]:\n"
            . "Deleting orphaned url_rewrite rows... Done\n\n\n"
            . "Reindexation... Done\n"
            . "Cache refreshing... Done\n"
            . "If you use some external cache mechanisms (e.g.: Redis, Varnish, etc.)"
            . " - please, refresh this external cache.\n",
            $reporter->text
        );
        self::assertTrue($this->productModel->hadReporter);
        self::assertTrue($this->productModel->orphansDeleted);
        self::assertNull($this->productModel->getReporter());
    }

    /**
     * @return void
     */
    public function testResultHasModelAndPostRunFailures(): void
    {
        $this->indexers = [
            'locked' => [StateInterface::STATUS_WORKING, null, null],
            'broken' => [StateInterface::STATUS_VALID, null, new \RuntimeException('disk full')],
        ];

        $result = $this->service->run((new RunOptionsBuilder())->setCleanCache(false)->setFlushCache(false)->create());

        self::assertTrue($result->hasFailures());
        self::assertSame(['product' => 1, 'indexer' => 2], $result->getFailureCounts());
        self::assertSame([
            'bad',
            'Locked index is locked by another reindex process. Skipping.',
            'broken: disk full',
        ], array_column($result->getFailures(), 'message'));
    }

    /**
     * @return void
     */
    public function testASharedIndexIsRebuiltOnce(): void
    {
        $this->indexers = [
            'first' => [StateInterface::STATUS_VALID, 'shared', null],
            'second' => [StateInterface::STATUS_VALID, 'shared', null],
            'own' => [StateInterface::STATUS_VALID, null, null],
        ];

        $this->service->run((new RunOptionsBuilder())->setStoreIds([1])->create());

        self::assertSame(['first', 'own'], $this->reindexed);
    }

    /**
     * @return void
     */
    public function testTheCallersStoreIsBackBeforeReindexAndAfterTheRun(): void
    {
        $this->indexers = ['catalog' => [StateInterface::STATUS_VALID, null, null]];

        $this->service->run((new RunOptionsBuilder())->setCleanCache(false)->setFlushCache(false)->create());

        self::assertSame([0, 1, 2, 1], $this->currentStores);
        self::assertSame([1], $this->storeDuringReindex);
    }

    /**
     * @return void
     */
    public function testCacheIsCleanedAndFlushedOnlyWhenAsked(): void
    {
        $this->cacheManager->expects(self::once())->method('clean')->with(['config', 'full_page']);

        $this->service->run($this->options()->setCleanCache(true)->create());

        self::assertSame(['adminhtml_cache_flush_system', 'clean'], $this->cacheSteps);
    }

    /**
     * @return void
     */
    public function testCacheStepsDispatchTheEventsOfTheCacheCommands(): void
    {
        $this->service->run($this->options()->setCleanCache(true)->setFlushCache(true)->create());

        self::assertSame(
            ['adminhtml_cache_flush_system', 'clean', 'adminhtml_cache_flush_all', 'flush'],
            $this->cacheSteps
        );
    }

    /**
     * @return void
     */
    public function testRunWithoutAnAreaRunsInTheAdminhtmlAreaAndConfigScope(): void
    {
        $appState = $this->createMock(AppState::class);
        $appState->method('getAreaCode')->willThrowException(new LocalizedException(__('Area code is not set')));
        (fn () => $this->appState = $appState)->call($this->service);

        self::assertSame([0, 1, 2], $this->service->run($this->options()->create())->getProcessedStoreIds());
        self::assertSame(1, $this->adminhtmlCalls);
    }

    /**
     * @return void
     */
    public function testRunInAnAreaKeepsItButSavesSuffixesInTheAdminhtmlConfigScope(): void
    {
        $this->service->run($this->options()->create());
        self::assertSame(0, $this->adminhtmlCalls);

        $this->service->run($this->options()->setProductUrlSuffix('.htm')->setCategoryUrlSuffix('/')->create());
        self::assertSame(2, $this->adminhtmlCalls);
    }

    /**
     * @return void
     */
    public function testAFailingCacheObserverDoesNotSkipTheCacheOperation(): void
    {
        $eventManager = $this->createMock(\Magento\Framework\Event\ManagerInterface::class);
        $eventManager->method('dispatch')->willThrowException(new \RuntimeException('varnish unreachable'));
        (fn () => $this->eventManager = $eventManager)->call($this->service);

        $result = $this->service->run($this->options()->setCleanCache(true)->setFlushCache(true)->create());

        self::assertSame(['clean', 'flush'], $this->cacheSteps);
        self::assertSame(['product' => 1, 'cache' => 2], $result->getFailureCounts());
    }

    /**
     * @return void
     */
    public function testAFailedCacheCleanStillFlushes(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getAvailableTypes')->willReturn(['config']);
        $cacheManager->method('clean')->willThrowException(new \RuntimeException('redis down'));
        $cacheManager->expects(self::once())->method('flush');
        (fn () => $this->cacheManager = $cacheManager)->call($this->service);

        $result = $this->service->run($this->options()->setCleanCache(true)->setFlushCache(true)->create());

        self::assertSame(['product' => 1, 'cache' => 1], $result->getFailureCounts());
        self::assertSame('cleaning failed: redis down', $result->getFailures()[1]['message']);
    }

    /**
     * @return void
     */
    public function testSuffixIsSavedToDefaultConfigAndEveryStoreViewOfAnAllStoresRun(): void
    {
        $this->service->run($this->options()->setCategoryUrlSuffix('/')->create());

        self::assertSame(
            [['default', '', '/'], ['stores', 'default', '/'], ['stores', 'second', '/']],
            $this->savedConfig['catalog/seo/category_url_suffix']
        );
    }

    /**
     * @return void
     */
    public function testSuffixIsSavedOnlyToTheSelectedStoreView(): void
    {
        $this->service->run($this->options()->setStoreIds([2])->setProductUrlSuffix('.htm')->create());

        self::assertSame([['stores', 'second', '.htm']], $this->savedConfig['catalog/seo/product_url_suffix']);
    }

    /**
     * @return void
     */
    public function testAFailedSuffixSaveStopsTheRun(): void
    {
        $this->configSaveError = new \Exception('invalid suffix');

        try {
            $this->service->run($this->options()->setProductUrlSuffix('#')->setCategoryUrlSuffix('#')->create());
            self::fail('InputException expected');
        } catch (InputException $e) {
            self::assertSame([
                'ERROR: could not save product URL suffix: invalid suffix',
                'ERROR: could not save category URL suffix: invalid suffix',
            ], array_map(fn (LocalizedException $error): string => $error->getMessage(), $e->getErrors()));
        }

        self::assertSame([], $this->productModel->runs);
    }

    /**
     * Options without the post-run steps, so a test only sees the step it enables
     *
     * @return RunOptionsBuilder
     */
    private function options(): RunOptionsBuilder
    {
        return (new RunOptionsBuilder())->setReindex(false)->setCleanCache(false)->setFlushCache(false);
    }

    /**
     * @return ProgressReporterInterface&object{text: string}
     */
    private function reporter(): ProgressReporterInterface
    {
        return new class () implements ProgressReporterInterface {
            /**
             * @var string
             */
            public string $text = '';

            /**
             * @param string $entityType
             * @param int $storeId
             * @param int $total
             * @return void
             */
            public function start(string $entityType, int $storeId, int $total): void
            {
            }

            /**
             * @param int $steps
             * @return void
             */
            public function advance(int $steps = 1): void
            {
            }

            /**
             * @return void
             */
            public function finish(): void
            {
            }

            /**
             * @param string $text
             * @param bool $newLine
             * @return void
             */
            public function message(string $text, bool $newLine = true): void
            {
                $this->text .= $text . ($newLine ? "\n" : '');
            }
        };
    }
}
