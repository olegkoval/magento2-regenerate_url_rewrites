<?php
/**
 * RegenerateUrlRewritesTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Console\Command;

use Magento\Framework\App\State as AppState;
use Magento\Store\Model\StoreManagerInterface;
use OlegKoval\RegenerateUrlRewrites\Console\Command\RegenerateUrlRewrites;
use OlegKoval\RegenerateUrlRewrites\Helper\Regenerate as RegenerateHelper;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateProductRewrites;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

class RegenerateUrlRewritesTest extends TestCase
{
    /**
     * @return void
     */
    public function testFailureSummaryShowsExactCountsAndCapsTheListedLines(): void
    {
        $failures = [
            [
                'entity_type' => 'category',
                'entity_id' => null,
                'store_id' => 1,
                'message' => 'loading categories failed: x',
            ],
            ['entity_type' => 'product', 'entity_id' => null, 'store_id' => null, 'message' => 'orphans failed: y'],
        ];
        for ($i = 1; $i <= 23; $i++) {
            $failures[] = ['entity_type' => 'product', 'entity_id' => $i, 'store_id' => 2, 'message' => 'err' . $i];
        }

        $output = new BufferedOutput();
        $command = (new \ReflectionClass(RegenerateUrlRewrites::class))->newInstanceWithoutConstructor();
        (function () use ($output, $failures): void {
            $this->_output = $output;
            $this->_displayFailures(['category' => 1, 'product' => 1024], $failures);
        })->call($command);
        $text = $output->fetch();

        self::assertStringContainsString('[FAILURES] 1 category failure(s), 1024 product failure(s):', $text);
        self::assertStringContainsString('  category (store 1): loading categories failed: x', $text);
        self::assertStringContainsString('  product: orphans failed: y', $text);
        self::assertStringContainsString('  product 1 (store 2): err1', $text);
        self::assertStringNotContainsString('err19', $text);
        self::assertStringContainsString('  ...and 1005 more', $text);
    }

    /**
     * @return void
     */
    public function testFailedRunExitsWithOneAfterTheSummary(): void
    {
        [$code, $text] = $this->execute(true, null);

        self::assertSame(1, $code);
        self::assertStringContainsString('2 product failure(s)', $text);
        self::assertStringEndsWith('Finished with failures', trim($text));
    }

    /**
     * @return void
     */
    public function testCleanRunExitsWithZero(): void
    {
        [$code, $text] = $this->execute(false, null);

        self::assertSame(0, $code);
        self::assertStringNotContainsString('[FAILURES]', $text);
        self::assertStringEndsWith('Finished', trim($text));
    }

    /**
     * @return void
     */
    public function testAllStoresRunLimitsStoreZeroToTheDefaultScope(): void
    {
        $model = $this->execute(false, null)[2];

        self::assertSame([0 => true, 1 => false, 2 => false], $model->defaultScopeOnlyByStore);
    }

    /**
     * @return void
     */
    public function testExplicitStoreZeroRunStillGeneratesGlobally(): void
    {
        $model = $this->execute(false, '0', [0 => 'admin'])[2];

        self::assertSame([0 => false], $model->defaultScopeOnlyByStore);
    }

    /**
     * @param bool $withFailures
     * @param string|null $storeIdOption
     * @param array<int, string> $stores
     * @return array{0: int, 1: string, 2: RegenerateProductRewrites}
     */
    private function execute(
        bool $withFailures,
        ?string $storeIdOption,
        array $stores = [0 => 'admin', 1 => 'default', 2 => 'second']
    ): array {
        $model = new class () extends RegenerateProductRewrites {
            /**
             * @var bool
             */
            public bool $withFailures = false;

            /**
             * @var array<int, bool>
             */
            public array $defaultScopeOnlyByStore = [];

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
                $this->defaultScopeOnlyByStore[$storeId] = $this->regenerateOptions['defaultScopeOnly'];
                if ($this->withFailures && $storeId > 0) {
                    $this->_addFailure('product', 9, $storeId, 'bad');
                }

                return $this;
            }
        };
        $model->withFailures = $withFailures;

        $command = new class () extends RegenerateUrlRewrites {
            public function __construct()
            {
            }

            /**
             * @return void
             */
            public function getCommandOptions(): void
            {
            }
        };

        $appState = $this->createMock(AppState::class);
        $appState->method('getAreaCode')->willReturn('adminhtml');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $helper = $this->createMock(RegenerateHelper::class);
        $helper->method('getSupportMeText')->willReturn(['support']);

        (function () use ($model, $appState, $storeManager, $helper, $stores): void {
            $this->_appState = $appState;
            $this->_storeManager = $storeManager;
            $this->helper = $helper;
            $this->regenerateProductRewrites = $model;
            // the real constructor (bypassed here) sets these defaults
            $this->_commandOptions = [
                'entityType' => 'product',
                'storesList' => $stores,
                'runReindex' => false,
                'runCacheClean' => false,
                'runCacheFlush' => false,
                'deleteOrphanedRewrites' => false,
                'setProductSuffix' => null,
                'setCategorySuffix' => null,
                'defaultScopeOnly' => false,
            ];
        })->call($command);

        $definition = new InputDefinition([new InputOption('store-id', null, InputOption::VALUE_OPTIONAL)]);
        $input = new ArrayInput($storeIdOption === null ? [] : ['--store-id' => $storeIdOption], $definition);
        $output = new BufferedOutput();
        $code = (new \ReflectionMethod($command, 'execute'))->invoke($command, $input, $output);

        return [$code, $output->fetch(), $model];
    }
}
