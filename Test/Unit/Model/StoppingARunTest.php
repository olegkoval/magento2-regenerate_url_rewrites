<?php
/**
 * StoppingARunTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model;

use Magento\Framework\DataObject;
use OlegKoval\RegenerateUrlRewrites\Api\ChangeListenerInterface;
use OlegKoval\RegenerateUrlRewrites\Api\Data\ChangeInterface;
use OlegKoval\RegenerateUrlRewrites\Model\NullProgressReporter;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateCategoryRewrites;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateProductRewrites;
use PHPUnit\Framework\TestCase;

/**
 * An exception from the progress reporter stops a model's pass after the current entity
 */
class StoppingARunTest extends TestCase
{
    /**
     * @return void
     */
    public function testAProductPassStopsAfterTheCurrentProductAndStillSyncsTheLinkTable(): void
    {
        $collection = $this->collection(\Magento\Catalog\Model\ResourceModel\Product\Collection::class, 3);
        $model = new class ($collection) extends RegenerateProductRewrites {
            /**
             * @var int[]
             */
            public array $processed = [];

            /**
             * @var int
             */
            public int $tableSyncs = 0;

            /**
             * @param mixed $collection
             */
            public function __construct(private $collection)
            {
                $this->regenerateOptions = array_merge($this->defaultRegenerateOptions, ['showProgress' => true]);
            }

            /**
             * @param array $productsFilter
             * @param int $storeId
             * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
             */
            protected function _getProductsCollection(
                array $productsFilter = [],
                int $storeId = 0
            ): \Magento\Catalog\Model\ResourceModel\Product\Collection {
                return $this->collection;
            }

            /**
             * @param mixed $entity
             * @param int $storeId
             * @return $this
             */
            public function processProduct($entity, int $storeId = 0): static
            {
                $this->processed[] = (int)$entity->getId();

                return $this;
            }

            /**
             * @return $this
             */
            protected function _updateSecondaryTable(): static
            {
                $this->tableSyncs++;

                return $this;
            }
        };
        $stop = new \RuntimeException('stopped by the caller');
        $model->setProgressReporter($this->reporterThrowingOnAdvance($stop));

        try {
            $model->regenerateProductsRangeUrlRewrites([], 1);
            self::fail('the reporter exception is expected to propagate');
        } catch (\RuntimeException $e) {
            self::assertSame($stop, $e);
        }

        self::assertSame([1], $model->processed);
        self::assertSame(['product' => [1 => 1]], $model->getProcessedCounts());
        self::assertSame(1, $model->tableSyncs);
        self::assertSame([], $model->getFailures());
    }

    /**
     * @return void
     */
    public function testACategoryPassStopsDropsQueuedProductsAndStillSyncsTheLinkTable(): void
    {
        $collection = $this->collection(\Magento\Catalog\Model\ResourceModel\Category\Collection::class, 3);
        $productModel = $this->createMock(RegenerateProductRewrites::class);
        $productModel->expects(self::never())->method('regenerateProductsRangeUrlRewrites');
        $model = new class ($collection, $productModel) extends RegenerateCategoryRewrites {
            /**
             * @var int
             */
            public int $tableSyncs = 0;

            /**
             * @param mixed $collection
             * @param RegenerateProductRewrites $productModel
             */
            public function __construct(private $collection, RegenerateProductRewrites $productModel)
            {
                $this->regenerateProductRewrites = $productModel;
                $this->regenerateOptions = array_merge($this->defaultRegenerateOptions, ['showProgress' => true]);
            }

            /**
             * @param array $categoriesFilter
             * @param int $storeId
             * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
             */
            protected function _getCategoriesCollection(
                array $categoriesFilter = [],
                int $storeId = 0
            ): \Magento\Catalog\Model\ResourceModel\Category\Collection {
                return $this->collection;
            }

            /**
             * @param mixed $category
             * @param int $storeId
             * @return $this
             */
            protected function categoryProcess($category, int $storeId = 0): static
            {
                $this->pendingProductIds[(int)$category->getId() * 10] = true;

                return $this;
            }

            /**
             * @return $this
             */
            protected function _updateSecondaryTable(): static
            {
                $this->tableSyncs++;

                return $this;
            }

            /**
             * @return array
             */
            public function getPending(): array
            {
                return $this->pendingProductIds;
            }
        };
        $model->setProgressReporter($this->reporterThrowingOnAdvance(new \RuntimeException('stop')));

        try {
            $model->regenerateCategoriesRangeUrlRewrites([], 1);
            self::fail('the reporter exception is expected to propagate');
        } catch (\RuntimeException $e) {
            self::assertSame('stop', $e->getMessage());
        }

        self::assertSame(1, $model->tableSyncs);
        self::assertSame([], $model->getPending());
    }

    /**
     * @return void
     */
    public function testACategoryRunCanBeStoppedDuringItsProductPhase(): void
    {
        $productModel = new class () extends RegenerateProductRewrites {
            /**
             * @var int[]
             */
            public array $processed = [];

            public function __construct()
            {
                $this->regenerateOptions = $this->defaultRegenerateOptions;
            }

            /**
             * @param array $productsFilter
             * @param int $storeId
             * @return $this
             */
            public function regenerateProductsRangeUrlRewrites(array $productsFilter = [], int $storeId = 0): static
            {
                $this->_progressStart($storeId, count($productsFilter));
                foreach ($productsFilter as $productId) {
                    $this->processed[] = $productId;
                    $this->_progressAdvance();
                }

                return $this;
            }

            /**
             * @return \OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface|null
             */
            public function getReporter(): ?\OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface
            {
                return $this->progressReporter;
            }
        };
        $model = new class ($productModel) extends RegenerateCategoryRewrites {
            /**
             * @param RegenerateProductRewrites $productModel
             */
            public function __construct(RegenerateProductRewrites $productModel)
            {
                $this->regenerateProductRewrites = $productModel;
                $this->regenerateOptions = array_merge($this->defaultRegenerateOptions, ['showProgress' => true]);
                $this->pendingProductIds = [10 => true, 11 => true, 12 => true, 13 => true];
            }

            /**
             * @param int $storeId
             * @return void
             */
            public function regenerateQueuedProducts(int $storeId): void
            {
                $this->_regenerateProducts($storeId);
            }
        };
        $stop = new \RuntimeException('stop');
        $reporter = new class ($stop) extends NullProgressReporter {
            /**
             * @var array
             */
            public array $starts = [];

            /**
             * @var int
             */
            private int $advances = 0;

            /**
             * @param \Throwable $stop
             */
            public function __construct(private \Throwable $stop)
            {
            }

            /**
             * @param string $entityType
             * @param int $storeId
             * @param int $total
             * @return void
             */
            public function start(string $entityType, int $storeId, int $total): void
            {
                $this->starts[] = [$entityType, $storeId, $total];
            }

            /**
             * @param int $steps
             * @return void
             */
            public function advance(int $steps = 1): void
            {
                if (++$this->advances === 2) {
                    throw $this->stop;
                }
            }
        };
        $model->setProgressReporter($reporter);

        try {
            $model->regenerateQueuedProducts(1);
            self::fail('the reporter exception is expected to propagate');
        } catch (\RuntimeException $e) {
            self::assertSame($stop, $e);
        }

        self::assertSame([10, 11], $productModel->processed, 'stops after the current product');
        self::assertSame([['product', 1, 4]], $reporter->starts, 'one pass for the whole product phase');
        self::assertSame([], $model->getFailures(), 'a stop is not a failure');
        self::assertNull($productModel->getReporter());
    }

    /**
     * @return void
     */
    public function testAListenerExceptionInACategoryRunsProductPhaseStopsItInsteadOfFailingTheBatch(): void
    {
        $productModel = new class () extends RegenerateProductRewrites {
            public function __construct()
            {
                $this->regenerateOptions = $this->defaultRegenerateOptions;
            }

            /**
             * @param array $productsFilter
             * @param int $storeId
             * @return $this
             */
            public function regenerateProductsRangeUrlRewrites(array $productsFilter = [], int $storeId = 0): static
            {
                foreach ($productsFilter as $productId) {
                    $this->pendingChanges[] = new \OlegKoval\RegenerateUrlRewrites\Model\Change(
                        'rewrite_added',
                        'product',
                        $productId,
                        $storeId,
                        null,
                        'p' . $productId . '.html'
                    );
                    $this->_flushChanges();
                }

                return $this;
            }
        };
        $model = new class ($productModel) extends RegenerateCategoryRewrites {
            /**
             * @param RegenerateProductRewrites $productModel
             */
            public function __construct(RegenerateProductRewrites $productModel)
            {
                $this->regenerateProductRewrites = $productModel;
                $this->regenerateOptions = $this->defaultRegenerateOptions;
                $this->pendingProductIds = [10 => true, 11 => true];
            }

            /**
             * @param int $storeId
             * @return void
             */
            public function regenerateQueuedProducts(int $storeId): void
            {
                $this->_regenerateProducts($storeId);
            }
        };
        $stop = new \RuntimeException('audit storage full');
        $model->setChangeListener(new class ($stop) implements ChangeListenerInterface {
            /**
             * @param \Throwable $stop
             */
            public function __construct(private \Throwable $stop)
            {
            }

            /**
             * @param ChangeInterface $change
             * @return void
             */
            public function onChange(ChangeInterface $change): void
            {
                throw $this->stop;
            }
        });

        try {
            $model->regenerateQueuedProducts(1);
            self::fail('the listener exception is expected to propagate');
        } catch (\RuntimeException $e) {
            self::assertSame($stop, $e);
        }
        self::assertSame([], $model->getFailures());
    }

    /**
     * @param string $class
     * @param int $size
     * @return object
     */
    private function collection(string $class, int $size): object
    {
        $items = array_map(fn (int $id): DataObject => new DataObject(['id' => $id]), range(1, $size));
        $collection = $this->createMock($class);
        $collection->method('getLastPageNumber')->willReturn(1);
        $collection->method('getSize')->willReturn($size);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));

        return $collection;
    }

    /**
     * @param \Throwable $exception
     * @return NullProgressReporter
     */
    private function reporterThrowingOnAdvance(\Throwable $exception): NullProgressReporter
    {
        return new class ($exception) extends NullProgressReporter {
            /**
             * @param \Throwable $exception
             */
            public function __construct(private \Throwable $exception)
            {
            }

            /**
             * @param int $steps
             * @return void
             */
            public function advance(int $steps = 1): void
            {
                throw $this->exception;
            }
        };
    }
}
