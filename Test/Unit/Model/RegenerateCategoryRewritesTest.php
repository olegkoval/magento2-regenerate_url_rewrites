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
    public function testProcessedCountsIncludeProductsOfTheCategoryRunAndResetOnBothModels(): void
    {
        $this->invoke('_countProcessed', 1);
        $this->invoke('_countProcessed', 1);
        (fn () => $this->_countProcessed(1))->call($this->productModel);

        self::assertSame(['category' => [1 => 2], 'product' => [1 => 1]], $this->model->getProcessedCounts());

        $this->model->resetProcessedCounts();
        self::assertSame([], $this->model->getProcessedCounts());
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
     * @return void
     */
    public function testCategoryUrlKeyRegenerationPreservesCascadedProductKeys(): void
    {
        foreach ([false, true] as $exactCategories) {
            $this->model->setRegenerateOptions([
                'regenUrlKey' => true,
                'exactCategories' => $exactCategories,
                'saveOldUrls' => true,
            ]);
            (fn () => $this->pendingProductIds = [77 => true])->call($this->model);

            $this->invoke('_regenerateProducts', 1);

            $productOptions = (fn (): array => $this->regenerateOptions)->call($this->productModel);
            self::assertFalse($productOptions['regenUrlKey']);
            self::assertTrue($productOptions['saveOldUrls']);
            self::assertTrue((fn (): bool => $this->regenerateOptions['regenUrlKey'])->call($this->model));
        }
        self::assertSame([[77], [77]], $this->productModel->calls);
    }

    /**
     * @return void
     */
    public function testPathChangesOfEarlierStoresOfTheRunCountUntilTheNextRunStarts(): void
    {
        (fn () => $this->changedUrlPathIds = [3 => true])->call($this->model);

        $this->model->resetFailures();
        $this->model->resetProcessedCounts();
        self::assertSame([3 => true], (fn (): array => $this->changedUrlPathIds)->call($this->model));

        $this->model->startRun();
        self::assertSame([], (fn (): array => $this->changedUrlPathIds)->call($this->model));
    }

    /**
     * @return void
     */
    public function testExactModeRepathsDescendantsInStoreViewsAfterTheDefaultScopeChangedThePath(): void
    {
        $collection = $this->createMock(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
        $collection->method('getLastPageNumber')->willReturn(1);
        $collection->method('getIterator')->willReturnCallback(
            fn () => new \ArrayIterator([new \Magento\Framework\DataObject(['id' => 3])])
        );
        $model = new class ($this->productModel, $collection) extends RegenerateCategoryRewrites {
            /**
             * @var int[] store IDs with a descendant pass
             */
            public array $descendantPassStores = [];

            /**
             * @param RegenerateProductRewrites $productModel
             * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
             */
            public function __construct(RegenerateProductRewrites $productModel, private $collection)
            {
                $this->regenerateProductRewrites = $productModel;
                $this->regenerateOptions = $this->defaultRegenerateOptions;
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
             * @param int[] $categoryIds
             * @param int[] $excludeIds
             * @param int $storeId
             * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
             */
            protected function _getDescendantsCollection(
                array $categoryIds,
                array $excludeIds,
                int $storeId
            ): \Magento\Catalog\Model\ResourceModel\Category\Collection {
                $this->descendantPassStores[] = $storeId;

                return $this->createEmpty();
            }

            /**
             * the store 0 pass changes the default url_path; the store view inherits it, so its pass sees no change
             *
             * @param mixed $category
             * @param int $storeId
             * @return $this
             */
            protected function categoryProcess($category, int $storeId = 0): static
            {
                if ($storeId === 0) {
                    $this->changedUrlPathIds[3] = true;
                }

                return $this;
            }

            /**
             * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
             */
            private function createEmpty(): \Magento\Catalog\Model\ResourceModel\Category\Collection
            {
                return (new \ReflectionClass(\Magento\Catalog\Model\ResourceModel\Category\Collection::class))
                    ->newInstanceWithoutConstructor();
            }

            /**
             * @return $this
             */
            protected function _updateSecondaryTable(): static
            {
                return $this;
            }

            /**
             * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $categories
             * @param int $storeId
             * @return int[]
             */
            protected function _processCategories(
                \Magento\Catalog\Model\ResourceModel\Category\Collection $categories,
                int $storeId
            ): array {
                return $categories === $this->collection ? parent::_processCategories($categories, $storeId) : [];
            }
        };
        $model->setRegenerateOptions(['exactCategories' => true, 'categoriesFilter' => [3]]);
        $model->startRun();

        $model->regenerateCategoriesRangeUrlRewrites([3], 0);
        $model->regenerateCategoriesRangeUrlRewrites([3], 1);

        self::assertSame([0, 1], $model->descendantPassStores);
    }

    /**
     * @return array<string, array{0: bool, 1: int[], 2: array}>
     */
    public static function exactModeCases(): array
    {
        return [
            'exact, a path changed: its descendants, no url_key regeneration' => [true, [2], [[[2], [1, 2], false]]],
            'exact, no path changed: no descendant pass' => [true, [], []],
            'not exact: descendants are already in the pass' => [false, [2], []],
        ];
    }

    /**
     * @dataProvider exactModeCases
     * @param bool $exact
     * @param int[] $changedIds categories whose url_path the pass changes
     * @param array $expectedDescendantPasses [[changed IDs, excluded IDs, regenUrlKey during the pass], ...]
     * @return void
     */
    public function testExactModeProcessesDescendantsOnlyWhereAPathChanged(
        bool $exact,
        array $changedIds,
        array $expectedDescendantPasses
    ): void {
        $collection = $this->createMock(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
        $collection->method('getLastPageNumber')->willReturn(1);
        $collection->method('getIterator')->willReturnCallback(fn () => new \ArrayIterator([
            new \Magento\Framework\DataObject(['id' => 1]),
            new \Magento\Framework\DataObject(['id' => 2]),
        ]));

        $model = new class ($this->productModel, $collection, $changedIds) extends RegenerateCategoryRewrites {
            /**
             * @var array
             */
            public array $descendantPasses = [];

            /**
             * @param RegenerateProductRewrites $productModel
             * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
             * @param int[] $changedIds
             */
            public function __construct(
                RegenerateProductRewrites $productModel,
                private $collection,
                private array $changedIds
            ) {
                $this->regenerateProductRewrites = $productModel;
                $this->regenerateOptions = $this->defaultRegenerateOptions;
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
             * @param int[] $categoryIds
             * @param int[] $excludeIds
             * @param int $storeId
             * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
             */
            protected function _getDescendantsCollection(
                array $categoryIds,
                array $excludeIds,
                int $storeId
            ): \Magento\Catalog\Model\ResourceModel\Category\Collection {
                $this->descendantPasses[] = [$categoryIds, $excludeIds, $this->regenerateOptions['regenUrlKey']];

                return $this->collection;
            }

            /**
             * @param mixed $category
             * @param int $storeId
             * @return $this
             */
            protected function categoryProcess($category, int $storeId = 0): static
            {
                if (in_array((int)$category->getId(), $this->changedIds, true) && $this->descendantPasses === []) {
                    $this->changedUrlPathIds[(int)$category->getId()] = true;
                }

                return $this;
            }

            /**
             * @return $this
             */
            protected function _updateSecondaryTable(): static
            {
                return $this;
            }
        };
        $model->setRegenerateOptions([
            'exactCategories' => $exact,
            'regenUrlKey' => true,
            'categoriesFilter' => [1, 2],
        ]);

        $model->regenerateCategoriesRangeUrlRewrites([1, 2], 1);

        self::assertSame($expectedDescendantPasses, $model->descendantPasses);
        self::assertTrue((fn (): bool => $this->regenerateOptions['regenUrlKey'])->call($model), 'restored after');
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
