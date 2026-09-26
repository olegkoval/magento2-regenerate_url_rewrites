<?php
/**
 * RegenerateCategoryRewritesTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model;

use OlegKoval\RegenerateUrlRewrites\Model\RegenerateCategoryRewrites;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateProductRewrites;
use PHPUnit\Framework\TestCase;

class RegenerateCategoryRewritesTest extends TestCase
{
    /**
     * @var RegenerateProductRewrites
     */
    private $productModel;

    /**
     * @var RegenerateCategoryRewrites
     */
    private $model;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->productModel = new class () extends RegenerateProductRewrites {
            /**
             * product ID lists passed per call
             * @var int[][]
             */
            public array $calls = [];

            /**
             * @var bool
             */
            public bool $throwOnNextCall = false;

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
                $this->calls[] = $productsFilter;
                if ($this->throwOnNextCall) {
                    $this->throwOnNextCall = false;
                    throw new \RuntimeException('db gone');
                }

                return $this;
            }

            /**
             * @param string $entityType
             * @param int|null $entityId
             * @param int|null $storeId
             * @param string $message
             * @return void
             */
            public function addFailure(string $entityType, ?int $entityId, ?int $storeId, string $message): void
            {
                $this->_addFailure($entityType, $entityId, $storeId, $message);
            }
        };

        $this->model = (new \ReflectionClass(RegenerateCategoryRewrites::class))->newInstanceWithoutConstructor();
        $productModel = $this->productModel;
        (function () use ($productModel): void {
            $this->regenerateProductRewrites = $productModel;
            $this->regenerateOptions = $this->defaultRegenerateOptions;
        })->call($this->model);
    }

    /**
     * @return void
     */
    public function testFailuresIncludeProductsRegeneratedThroughTheCategoryRun(): void
    {
        $this->invoke('_addFailure', 'category', 4, 1, 'own');
        $this->productModel->addFailure('product', 77, 1, 'cascade');
        $this->productModel->addFailure('product', 78, 1, 'cascade');

        self::assertSame([4, 77, 78], array_column($this->model->getFailures(), 'entity_id'));
        self::assertSame(['category' => 1, 'product' => 2], $this->model->getFailureCounts());
    }

    /**
     * @return void
     */
    public function testResetFailuresClearsBothModels(): void
    {
        $this->invoke('_addFailure', 'category', 4, 1, 'own');
        $this->productModel->addFailure('product', 77, 1, 'cascade');

        $this->model->resetFailures();

        self::assertSame([], $this->model->getFailures());
        self::assertSame([], $this->productModel->getFailures());
    }

    /**
     * @return void
     */
    public function testAFailingProductBatchIsRecordedAndTheNextBatchStillRuns(): void
    {
        $this->productModel->throwOnNextCall = true;

        $this->invoke('_regenerateProductsBatch', [1, 2], 1);
        $this->invoke('_regenerateProductsBatch', [3], 1);

        self::assertSame([[1, 2], [3]], $this->productModel->calls);
        $failures = $this->model->getFailures();
        self::assertCount(1, $failures);
        self::assertSame('product', $failures[0]['entity_type']);
        self::assertNull($failures[0]['entity_id']);
        self::assertSame(1, $failures[0]['store_id']);
        self::assertStringContainsString('db gone', $failures[0]['message']);
    }

    /**
     * @return void
     */
    public function testQueuedProductsAreRegeneratedOnceInBoundedBatchesWithOneTableSync(): void
    {
        $collection = $this->createMock(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
        $collection->method('getLastPageNumber')->willReturn(1);
        $collection->method('getSize')->willReturn(3);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new \Magento\Framework\DataObject(['id' => 1]),
            new \Magento\Framework\DataObject(['id' => 2]),
            new \Magento\Framework\DataObject(['id' => 3]),
        ]));

        $productModel = $this->productModel;
        $model = new class ($productModel, $collection) extends RegenerateCategoryRewrites {
            /**
             * @var int
             */
            public int $tableSyncs = 0;

            /**
             * @var \Magento\Catalog\Model\ResourceModel\Category\Collection
             */
            private $collection;

            /**
             * @param RegenerateProductRewrites $productModel
             * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
             */
            public function __construct(RegenerateProductRewrites $productModel, $collection)
            {
                $this->regenerateProductRewrites = $productModel;
                $this->regenerateOptions = $this->defaultRegenerateOptions;
                $this->collection = $collection;
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
             * 3 overlapping "subtrees" (as ancestor categories would be): 1..1500, 1001..2500, 1..10
             *
             * @param mixed $category
             * @param int $storeId
             * @return $this
             */
            protected function categoryProcess($category, int $storeId = 0): static
            {
                $ranges = [1 => [1, 1500], 2 => [1001, 2500], 3 => [1, 10]];
                foreach (range(...$ranges[$category->getId()]) as $productId) {
                    $this->pendingProductIds[$productId] = true;
                }

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

        $model->regenerateCategoriesRangeUrlRewrites([], 1);

        self::assertSame([1000, 1000, 500], array_map('count', $this->productModel->calls));
        self::assertSame(range(1, 2500), array_merge(...$this->productModel->calls));
        self::assertSame(1, $model->tableSyncs);
        $productOptions = (fn (): array => $this->regenerateOptions)->call($this->productModel);
        self::assertTrue($productOptions['skipSecondaryTableUpdate']);
        self::assertFalse($productOptions['showProgress']);
    }

    /**
     * @param string $method
     * @param mixed ...$args
     * @return mixed
     */
    private function invoke(string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod($this->model, $method))->invoke($this->model, ...$args);
    }
}
