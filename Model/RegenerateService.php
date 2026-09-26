<?php
/**
 * RegenerateService.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use Magento\Config\Console\Command\EmulatedAdminhtmlAreaProcessor;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Config\Model\Config\Reader\Source\Deployed\SettingChecker;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Indexer\ConfigInterface as IndexerConfig;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\StateInterface;
use Magento\Framework\Phrase;
use Magento\Indexer\Model\Processor\MakeSharedIndexValid;
use Magento\Store\Model\StoreManagerInterface;
use OlegKoval\RegenerateUrlRewrites\Api\Data\RunOptionsInterface;
use OlegKoval\RegenerateUrlRewrites\Api\Data\RunResultInterface;
use OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface;
use OlegKoval\RegenerateUrlRewrites\Api\RegenerateServiceInterface;

class RegenerateService implements RegenerateServiceInterface
{
    /**
     * Max failure details in a result, as on the models
     */
    private const FAILURE_DETAILS_LIMIT = 1000;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @var AppState
     */
    private AppState $appState;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var RegenerateProductRewrites
     */
    private RegenerateProductRewrites $regenerateProductRewrites;

    /**
     * @var RegenerateCategoryRewrites
     */
    private RegenerateCategoryRewrites $regenerateCategoryRewrites;

    /**
     * @var ConfigFactory
     */
    private ConfigFactory $configFactory;

    /**
     * @var SettingChecker
     */
    private SettingChecker $settingChecker;

    /**
     * @var IndexerRegistry
     */
    private IndexerRegistry $indexerRegistry;

    /**
     * @var IndexerConfig
     */
    private IndexerConfig $indexerConfig;

    /**
     * @var MakeSharedIndexValid
     */
    private MakeSharedIndexValid $makeSharedIndexValid;

    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;

    /**
     * @var EmulatedAdminhtmlAreaProcessor
     */
    private EmulatedAdminhtmlAreaProcessor $adminhtmlAreaProcessor;

    /**
     * @var EventManager
     */
    private EventManager $eventManager;

    /**
     * store_id => code of every store, incl. store 0 ("admin"), as of the last validate()
     * @var array<int, string>|null
     */
    private ?array $allStores = null;

    /**
     * Failures of the post-run steps (reindex, cache) of the current run
     * @var array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}>
     */
    private array $failures = [];

    /**
     * @var array<string, int>
     */
    private array $failureCounts = [];

    /**
     * @param ResourceConnection $resource
     * @param AppState $appState
     * @param StoreManagerInterface $storeManager
     * @param RegenerateProductRewrites $regenerateProductRewrites
     * @param RegenerateCategoryRewrites $regenerateCategoryRewrites
     * @param ConfigFactory $configFactory
     * @param SettingChecker $settingChecker
     * @param IndexerRegistry $indexerRegistry
     * @param IndexerConfig $indexerConfig
     * @param MakeSharedIndexValid $makeSharedIndexValid
     * @param CacheManager $cacheManager
     * @param EmulatedAdminhtmlAreaProcessor $adminhtmlAreaProcessor
     * @param EventManager $eventManager
     */
    public function __construct(
        ResourceConnection $resource,
        AppState $appState,
        StoreManagerInterface $storeManager,
        RegenerateProductRewrites $regenerateProductRewrites,
        RegenerateCategoryRewrites $regenerateCategoryRewrites,
        ConfigFactory $configFactory,
        SettingChecker $settingChecker,
        IndexerRegistry $indexerRegistry,
        IndexerConfig $indexerConfig,
        MakeSharedIndexValid $makeSharedIndexValid,
        CacheManager $cacheManager,
        EmulatedAdminhtmlAreaProcessor $adminhtmlAreaProcessor,
        EventManager $eventManager
    ) {
        $this->resource = $resource;
        $this->appState = $appState;
        $this->storeManager = $storeManager;
        $this->regenerateProductRewrites = $regenerateProductRewrites;
        $this->regenerateCategoryRewrites = $regenerateCategoryRewrites;
        $this->configFactory = $configFactory;
        $this->settingChecker = $settingChecker;
        $this->indexerRegistry = $indexerRegistry;
        $this->indexerConfig = $indexerConfig;
        $this->makeSharedIndexValid = $makeSharedIndexValid;
        $this->cacheManager = $cacheManager;
        $this->adminhtmlAreaProcessor = $adminhtmlAreaProcessor;
        $this->eventManager = $eventManager;
    }

    /**
     * @param RunOptionsInterface $options
     * @return string[]
     */
    public function validate(RunOptionsInterface $options): array
    {
        // the service is shared (e.g. a long-running consumer): reload the stores per call; run() calls this first
        $this->allStores = null;
        $errors = [];
        $isProductRun = $options->getEntityType() === RunOptionsInterface::ENTITY_TYPE_PRODUCT;

        if (!$isProductRun && $options->getEntityType() !== RunOptionsInterface::ENTITY_TYPE_CATEGORY) {
            $errors[] = (string)__('ERROR: entity type should be "product" or "category".');
        }
        if ($isProductRun && count($options->getCategoryIds()) > 0) {
            $errors[] = (string)__('ERROR: category IDs can not be used in a product run.');
        }
        if (!$isProductRun && count($options->getProductIds()) > 0) {
            $errors[] = (string)__('ERROR: product IDs can not be used in a category run.');
        }
        if (count(array_filter($options->getProductIds(), fn (int $id): bool => $id <= 0)) > 0) {
            $errors[] = (string)__('ERROR: product ID should be greater than 0.');
        }
        if (count(array_filter($options->getCategoryIds(), fn (int $id): bool => $id <= 0)) > 0) {
            $errors[] = (string)__('ERROR: category ID should be greater than 0.');
        }
        if (count(array_diff($options->getStoreIds(), array_keys($this->_getAllStores()))) > 0) {
            $errors[] = (string)__('ERROR: store with this ID not exists.');
        }

        // a locked (app/etc/config.php) suffix would be skipped silently by the config save
        foreach ($this->_getSuffixesToSave($options) as $configPath => [$value, $label]) {
            foreach ($this->_getSuffixScopes($options) as [$scope, $scopeCode]) {
                if ($this->settingChecker->isReadOnly($configPath, $scope, $scopeCode)) {
                    $errors[] = $this->_suffixError($label, $this->_lockedMessage($scope, $scopeCode));
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @param RunOptionsInterface $options
     * @param ProgressReporterInterface|null $reporter
     * @return RunResultInterface
     * @throws InputException
     */
    public function run(RunOptionsInterface $options, ?ProgressReporterInterface $reporter = null): RunResultInterface
    {
        $this->_throwIfErrors($this->validate($options));
        $reporter = $reporter ?? new NullProgressReporter();

        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException $e) {
            // no area yet (e.g. a plain CLI process): run as the admin does — area code and config scope, so
            // adminhtml plugins/observers and system.xml backend models apply, as before 1.11.0
            return $this->adminhtmlAreaProcessor->process(
                fn (): RunResultInterface => $this->_run($options, $reporter)
            );
        }

        return $this->_run($options, $reporter);
    }

    /**
     * @param RunOptionsInterface $options
     * @param ProgressReporterInterface $reporter
     * @return RunResultInterface
     * @throws InputException
     */
    private function _run(RunOptionsInterface $options, ProgressReporterInterface $reporter): RunResultInterface
    {
        $this->_saveSuffixes($options);

        $this->failures = [];
        $this->failureCounts = [];
        $regenerator = $options->getEntityType() === RunOptionsInterface::ENTITY_TYPE_CATEGORY
            ? $this->regenerateCategoryRewrites
            : $this->regenerateProductRewrites;
        $regenerator->resetFailures();
        $regenerator->setProgressReporter($reporter);

        $isAllStoresRun = count($options->getStoreIds()) === 0;
        $processedStoreIds = [];
        // the loop switches Magento's current store; the post-run steps and the caller get theirs back
        $callerStoreId = (int)$this->storeManager->getStore()->getId();

        try {
            foreach ($this->_getStoresToProcess($options) as $storeId => $storeCode) {
                $reporter->message('');
                $reporter->message(
                    "[Type: {$options->getEntityType()}, Store ID: {$storeId}, Store View code: {$storeCode}]:"
                );
                $this->storeManager->setCurrentStore($storeId);

                // in an all-stores run every store view is processed below, so store 0 (processed first) only needs
                // its default-scope url_key/url_path updates, not a global-scope regeneration of all store views
                $regenerator->setRegenerateOptions(
                    $this->_getModelOptions($options, $isAllStoresRun && $storeId === 0)
                );
                $regenerator->regenerate($storeId);
                $processedStoreIds[] = $storeId;
            }

            if ($options->isDeleteOrphanedRewrites()) {
                $reporter->message('Deleting orphaned url_rewrite rows...', false);
                $regenerator->deleteOrphanedRewrites();
                $reporter->message(' Done');
            }
        } finally {
            $regenerator->setProgressReporter(null);
            $this->storeManager->setCurrentStore($callerStoreId);
        }

        $reporter->message('');
        $reporter->message('');

        if ($options->isReindex()) {
            $reporter->message('Reindexation...', false);
            $this->_reindexAll();
            $reporter->message(' Done');
        }

        if ($options->isCleanCache() || $options->isFlushCache()) {
            $reporter->message('Cache refreshing...', false);
            $this->_refreshCache($options);
            $reporter->message(' Done');
            $reporter->message(
                'If you use some external cache mechanisms (e.g.: Redis, Varnish, etc.)'
                . ' - please, refresh this external cache.'
            );
        }

        return $this->_createResult($regenerator, $processedStoreIds);
    }

    /**
     * @param RunOptionsInterface $options
     * @param bool $defaultScopeOnly
     * @return array
     */
    private function _getModelOptions(RunOptionsInterface $options, bool $defaultScopeOnly): array
    {
        return [
            'saveOldUrls' => $options->isSaveOldUrls(),
            'categoriesFilter' => $options->getCategoryIds(),
            'productsFilter' => $options->getProductIds(),
            'regenUrlKey' => $options->isRegenUrlKey(),
            'showProgress' => true,
            'skipProducts' => $options->isSkipProducts(),
            'skipExisting' => $options->isSkipExisting(),
            'includeNotVisible' => $options->isIncludeNotVisible(),
            'addSkuToUrl' => $options->isAddSkuToUrl(),
            'defaultScopeOnly' => $defaultScopeOnly,
        ];
    }

    /**
     * Reindex every indexer, as `bin/magento indexer:reindex` does (skips a locked one, rebuilds a shared index once)
     *
     * @return void
     */
    private function _reindexAll(): void
    {
        $completedSharedIndexes = [];
        foreach (array_keys($this->indexerConfig->getIndexers()) as $indexerId) {
            try {
                $indexer = $this->indexerRegistry->get($indexerId);
                if ($indexer->getStatus() === StateInterface::STATUS_WORKING) {
                    $this->_addFailure('indexer', (string)__(
                        '%1 index is locked by another reindex process. Skipping.',
                        $indexer->getTitle()
                    ));
                    continue;
                }

                $sharedIndex = $this->indexerConfig->getIndexer($indexerId)['shared_index'] ?? null;
                if (in_array($sharedIndex, $completedSharedIndexes, true)) {
                    continue;
                }

                $indexer->reindexAll();
                if (!empty($sharedIndex) && $this->makeSharedIndexValid->execute($sharedIndex)) {
                    $completedSharedIndexes[] = $sharedIndex;
                }
            } catch (\Throwable $e) {
                $this->_addFailure('indexer', $indexerId . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Clean and/or flush every cache type, as `bin/magento cache:clean` / `cache:flush` do — incl. their events,
     * which e.g. Magento_CacheInvalidate observes to purge Varnish
     *
     * @param RunOptionsInterface $options
     * @return void
     */
    private function _refreshCache(RunOptionsInterface $options): void
    {
        $types = $this->cacheManager->getAvailableTypes();
        // event first, as the cache commands do; each event and operation is its own step, so a failing observer
        // (e.g. an unreachable Varnish) or clean doesn't skip the requested operation or the flush
        if ($options->isCleanCache()) {
            $this->_cacheStep('cleaning', fn () => $this->eventManager->dispatch('adminhtml_cache_flush_system'));
            $this->_cacheStep('cleaning', fn () => $this->cacheManager->clean($types));
        }
        if ($options->isFlushCache()) {
            $this->_cacheStep('flushing', fn () => $this->eventManager->dispatch('adminhtml_cache_flush_all'));
            $this->_cacheStep('flushing', fn () => $this->cacheManager->flush($types));
        }
    }

    /**
     * @param string $label
     * @param callable $step
     * @return void
     */
    private function _cacheStep(string $label, callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $e) {
            $this->_addFailure('cache', $label . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * @param AbstractRegenerateRewrites $regenerator
     * @param int[] $processedStoreIds
     * @return RunResultInterface
     */
    private function _createResult(
        AbstractRegenerateRewrites $regenerator,
        array $processedStoreIds
    ): RunResultInterface {
        $counts = $regenerator->getFailureCounts();
        foreach ($this->failureCounts as $type => $count) {
            $counts[$type] = ($counts[$type] ?? 0) + $count;
        }
        $failures = array_slice(
            array_merge($regenerator->getFailures(), $this->failures),
            0,
            self::FAILURE_DETAILS_LIMIT
        );

        return new RunResult($counts, $failures, $processedStoreIds);
    }

    /**
     * @param string $type
     * @param string $message
     * @return void
     */
    private function _addFailure(string $type, string $message): void
    {
        $this->failureCounts[$type] = ($this->failureCounts[$type] ?? 0) + 1;
        $this->failures[] = ['entity_type' => $type, 'entity_id' => null, 'store_id' => null, 'message' => $message];
    }

    /**
     * @param RunOptionsInterface $options
     * @return array<int, string> store_id => code, in store_id order
     */
    private function _getStoresToProcess(RunOptionsInterface $options): array
    {
        $allStores = $this->_getAllStores();

        return count($options->getStoreIds()) === 0
            ? $allStores
            : array_intersect_key($allStores, array_flip($options->getStoreIds()));
    }

    /**
     * @return array<int, string> store_id => code of every store, incl. store 0 ("admin")
     */
    private function _getAllStores(): array
    {
        if ($this->allStores === null) {
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from($this->resource->getTableName('store'), ['store_id', 'code'])
                ->order('store_id ASC');

            $this->allStores = [];
            foreach ($connection->fetchAll($select) as $row) {
                $this->allStores[(int)$row['store_id']] = $row['code'];
            }
        }

        return $this->allStores;
    }

    /**
     * Save the requested URL suffixes via the same write path Magento's own `config:set` uses, so the Suffix
     * backend model's validation and its swap of the suffix on existing url_rewrite rows both run (see #87)
     *
     * @param RunOptionsInterface $options
     * @return void
     * @throws InputException when a suffix could not be saved (a sibling suffix already saved stays saved)
     */
    private function _saveSuffixes(RunOptionsInterface $options): void
    {
        $errors = [];
        foreach ($this->_getSuffixesToSave($options) as $configPath => [$value, $label]) {
            try {
                // in the adminhtml config scope, as `config:set` does: system.xml (its backend models) is loaded
                // only there, and without them the suffix is saved unvalidated and existing rewrites keep the old one
                $this->adminhtmlAreaProcessor->process(fn () => $this->_saveSuffix($options, $configPath, $value));
            } catch (\Exception $e) {
                $errors[] = $this->_suffixError($label, $e->getMessage());
            }
        }

        $this->_throwIfErrors($errors);
    }

    /**
     * @param RunOptionsInterface $options
     * @param string $configPath
     * @param string $value
     * @return void
     */
    private function _saveSuffix(RunOptionsInterface $options, string $configPath, string $value): void
    {
        foreach ($this->_getSuffixScopes($options) as [$scope, $scopeCode]) {
            if ($this->settingChecker->isReadOnly($configPath, $scope, $scopeCode)) {
                throw new \RuntimeException($this->_lockedMessage($scope, $scopeCode));
            }

            $config = $this->configFactory->create(['data' => [
                'scope' => $scope,
                'scope_code' => $scopeCode,
            ]]);
            $config->setDataByPath($configPath, $value);
            $config->save();
        }
    }

    /**
     * @param RunOptionsInterface $options
     * @return array<string, array{0: string, 1: Phrase}> config path => [value, label]
     */
    private function _getSuffixesToSave(RunOptionsInterface $options): array
    {
        $suffixes = [];
        if ($options->getProductUrlSuffix() !== null) {
            $suffixes['catalog/seo/product_url_suffix'] = [$options->getProductUrlSuffix(), __('product URL suffix')];
        }
        if ($options->getCategoryUrlSuffix() !== null) {
            $suffixes['catalog/seo/category_url_suffix'] = [
                $options->getCategoryUrlSuffix(),
                __('category URL suffix'),
            ];
        }

        return $suffixes;
    }

    /**
     * Default Config + every real store view in an all-stores run, otherwise just the selected store views
     *
     * @param RunOptionsInterface $options
     * @return array<int, array{0: string, 1: string}> [scope, scope code]
     */
    private function _getSuffixScopes(RunOptionsInterface $options): array
    {
        $scopes = count($options->getStoreIds()) === 0 ? [['default', '']] : [];
        foreach ($this->_getStoresToProcess($options) as $storeId => $storeCode) {
            // store_id 0 is the "admin" pseudo-store, not a real store-view scope
            if ($storeId > 0) {
                $scopes[] = ['stores', $storeCode];
            }
        }

        return $scopes;
    }

    /**
     * @param string $scope
     * @param string $scopeCode
     * @return string
     */
    private function _lockedMessage(string $scope, string $scopeCode): string
    {
        return (string)__(
            'value is locked via app/etc/config.php (scope: %scopeDescriptor)',
            ['scopeDescriptor' => $scopeCode !== '' ? "{$scope}/{$scopeCode}" : $scope]
        );
    }

    /**
     * @param Phrase $label
     * @param string $message
     * @return string
     */
    private function _suffixError(Phrase $label, string $message): string
    {
        return (string)__('ERROR: could not save %label: %msg', ['label' => $label, 'msg' => $message]);
    }

    /**
     * @param string[] $errors
     * @return void
     * @throws InputException
     */
    private function _throwIfErrors(array $errors): void
    {
        if (count($errors) === 0) {
            return;
        }

        $exception = new InputException();
        foreach ($errors as $error) {
            $exception->addError(new Phrase('%1', [$error]));
        }

        throw $exception;
    }
}
