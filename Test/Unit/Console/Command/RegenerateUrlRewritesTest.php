<?php
/**
 * RegenerateUrlRewritesTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Console\Command;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Phrase;
use OlegKoval\RegenerateUrlRewrites\Api\Data\RunOptionsInterface;
use OlegKoval\RegenerateUrlRewrites\Api\Data\RunResultInterface;
use OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface;
use OlegKoval\RegenerateUrlRewrites\Api\RegenerateServiceInterface;
use OlegKoval\RegenerateUrlRewrites\Console\Command\RegenerateUrlRewrites;
use OlegKoval\RegenerateUrlRewrites\Helper\Regenerate as RegenerateHelper;
use OlegKoval\RegenerateUrlRewrites\Model\RunResult;
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
        [$code, $text] = $this->execute(new RunResult(['product' => 2], [
            ['entity_type' => 'product', 'entity_id' => 9, 'store_id' => 1, 'message' => 'bad'],
            ['entity_type' => 'product', 'entity_id' => 9, 'store_id' => 2, 'message' => 'bad'],
        ], [0, 1, 2]));

        self::assertSame(1, $code);
        self::assertStringContainsString('2 product failure(s)', $text);
        self::assertStringEndsWith('Finished with failures', trim($text));
    }

    /**
     * @return void
     */
    public function testCleanRunExitsWithZero(): void
    {
        [$code, $text] = $this->execute(new RunResult([], [], [0, 1, 2]));

        self::assertSame(0, $code);
        self::assertStringNotContainsString('[FAILURES]', $text);
        self::assertStringEndsWith('Finished', trim($text));
    }

    /**
     * @return void
     */
    public function testRejectedOptionsArePrintedAsConsoleMessagesAndExitWithOne(): void
    {
        $exception = new InputException();
        $exception->addError(new Phrase('%1', ['ERROR: could not save product URL suffix: x']));
        $exception->addError(new Phrase('%1', ['ERROR: could not save category URL suffix: y']));

        [$code, $text] = $this->execute($exception);

        self::assertSame(1, $code);
        self::assertStringContainsString(
            "[CONSOLE MESSAGES]\nERROR: could not save product URL suffix: x\n"
            . "ERROR: could not save category URL suffix: y\n[END OF CONSOLE MESSAGES]",
            $text
        );
        self::assertStringNotContainsString('Finished', $text);
    }

    /**
     * @return void
     */
    public function testAllStoresRunPassesNoStoreIdsAndTheParsedOptions(): void
    {
        $service = $this->execute(new RunResult([], [], []), null, ['productId' => 7, 'saveOldUrls' => true])[2];
        $options = $service->options;

        self::assertSame([], $options->getStoreIds());
        self::assertSame([7], $options->getProductIds());
        self::assertTrue($options->isSaveOldUrls());
        self::assertFalse($options->isReindex());
        self::assertSame('.htm', $options->getProductUrlSuffix());
    }

    /**
     * @return void
     */
    public function testStoreIdOptionPassesThatStoreOnly(): void
    {
        $service = $this->execute(new RunResult([], [], []), '0', ['storesList' => [0 => 'admin']])[2];

        self::assertSame([0], $service->options->getStoreIds());
    }

    /**
     * @param RunResult|InputException $outcome what the service returns or throws
     * @param string|null $storeIdOption
     * @param array $commandOptions overrides of the parsed command options
     * @return array{0: int, 1: string, 2: object}
     */
    private function execute(
        RunResult|InputException $outcome,
        ?string $storeIdOption = null,
        array $commandOptions = []
    ): array {
        $service = new class ($outcome) implements RegenerateServiceInterface {
            /**
             * @var RunOptionsInterface|null
             */
            public ?RunOptionsInterface $options = null;

            /**
             * @param RunResult|InputException $outcome
             */
            public function __construct(private RunResult|InputException $outcome)
            {
            }

            /**
             * @param RunOptionsInterface $options
             * @return string[]
             */
            public function validate(RunOptionsInterface $options): array
            {
                return [];
            }

            /**
             * @param RunOptionsInterface $options
             * @param ProgressReporterInterface|null $reporter
             * @return RunResultInterface
             */
            public function run(
                RunOptionsInterface $options,
                ?ProgressReporterInterface $reporter = null
            ): RunResultInterface {
                $this->options = $options;
                if ($this->outcome instanceof InputException) {
                    throw $this->outcome;
                }

                return $this->outcome;
            }
        };

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

        $helper = $this->createMock(RegenerateHelper::class);
        $helper->method('getSupportMeText')->willReturn(['support']);

        (function () use ($service, $helper, $commandOptions): void {
            $this->helper = $helper;
            $this->regenerateService = $service;
            // the real constructor (bypassed here) sets the defaults; these are a parsed run
            $this->_commandOptions = array_merge([
                'entityType' => 'product',
                'saveOldUrls' => false,
                'runReindex' => false,
                'storesList' => [0 => 'admin', 1 => 'default', 2 => 'second'],
                'showProgress' => false,
                'runCacheClean' => false,
                'runCacheFlush' => false,
                'categoriesFilter' => [],
                'productsFilter' => [],
                'categoryId' => null,
                'productId' => null,
                'regenUrlKey' => false,
                'deleteOrphanedRewrites' => false,
                'skipProducts' => false,
                'skipExisting' => false,
                'includeNotVisible' => false,
                'addSkuToUrl' => false,
                'setProductSuffix' => '.htm',
                'setCategorySuffix' => null,
                'defaultScopeOnly' => false,
            ], $commandOptions);
        })->call($command);

        $definition = new InputDefinition([new InputOption('store-id', null, InputOption::VALUE_OPTIONAL)]);
        $input = new ArrayInput($storeIdOption === null ? [] : ['--store-id' => $storeIdOption], $definition);
        $output = new BufferedOutput();
        $code = (new \ReflectionMethod($command, 'execute'))->invoke($command, $input, $output);

        return [$code, $output->fetch(), $service];
    }
}
