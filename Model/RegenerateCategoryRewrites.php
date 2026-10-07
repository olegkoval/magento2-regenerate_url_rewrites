<?php
/**
 * RegenerateCategoryRewrites.php
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2017-2067 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\Exception\LocalizedException;
use OlegKoval\RegenerateUrlRewrites\Api\ChangeListenerInterface;
use OlegKoval\RegenerateUrlRewrites\Api\Data\ChangeInterface;
use OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface;
use OlegKoval\RegenerateUrlRewrites\Helper\Regenerate as RegenerateHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool;
use Magento\CatalogUrlRewrite\Model\Map\DataCategoryUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\Map\DataProductUrlRewriteDatabaseMap;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;

class RegenerateCategoryRewrites extends AbstractRegenerateRewrites
{
    /**
     * Products regenerated per batch after a category pass (bounds the collection and its SQL IN list)
     */
    protected const PRODUCT_BATCH_SIZE = 1000;

    /**
     * @var string
     */
    protected $entityType = 'category';

    /**
     * @var int
     */
    protected $categoriesCollectionPageSize = 100;

    /**
     * @var array
     */
    protected $dataUrlRewriteClassNames = [];

    /**
     * @var DatabaseMapPool
     */
    protected $databaseMapPool;

    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var CategoryUrlPathGeneratorFactory
     */
    protected $categoryUrlPathGeneratorFactory;

    /**
     * @var CategoryUrlPathGenerator
     */
    protected $categoryUrlPathGenerator;

    /**
     * @var CategoryUrlRewriteGeneratorFactory
     */
    protected $categoryUrlRewriteGeneratorFactory;

    /**
     * @var CategoryUrlRewriteGenerator
     */
    protected $categoryUrlRewriteGenerator;

    /**
     * @var RegenerateProductRewrites
     */
    protected $regenerateProductRewrites;

    /**
     * Product IDs (as keys) to regenerate once the current category pass has finished
     * @var array<int, true>
     */
    protected array $pendingProductIds = [];

    /**
     * IDs (as keys) of the categories whose url_path the current run changed, in any store so far: store 0 of an
     * all-stores run changes the default url_path, which the store views then inherit — unchanged there, yet their
     * descendants' store-view rewrites still need the new path
     * @var array<int, true>
     */
    protected array $changedUrlPathIds = [];

    /**
     * What the progress reporter or change listener threw during the product phase: a stop, never a batch failure
     * @var \Throwable|null
     */
    protected ?\Throwable $reporterException = null;

    /**
     * @param RegenerateHelper $helper
     * @param ResourceConnection $resourceConnection
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param DatabaseMapPool\Proxy $databaseMapPool
     * @param CategoryUrlPathGeneratorFactory\Proxy $categoryUrlPathGeneratorFactory
     * @param CategoryUrlRewriteGeneratorFactory\Proxy $categoryUrlRewriteGeneratorFactory
     * @param RegenerateProductRewrites $regenerateProductRewrites
     */
    public function __construct(
        RegenerateHelper                         $helper,
        ResourceConnection                       $resourceConnection,
        CategoryCollectionFactory                $categoryCollectionFactory,
        DatabaseMapPool\Proxy                    $databaseMapPool,
        CategoryUrlPathGeneratorFactory\Proxy    $categoryUrlPathGeneratorFactory,
        CategoryUrlRewriteGeneratorFactory\Proxy $categoryUrlRewriteGeneratorFactory,
        RegenerateProductRewrites                $regenerateProductRewrites
    )
    {
        parent::__construct($helper, $resourceConnection);

        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->databaseMapPool = $databaseMapPool;
        $this->categoryUrlPathGeneratorFactory = $categoryUrlPathGeneratorFactory;
        $this->categoryUrlRewriteGeneratorFactory = $categoryUrlRewriteGeneratorFactory;
        $this->regenerateProductRewrites = $regenerateProductRewrites;

        $this->dataUrlRewriteClassNames = [
            DataCategoryUrlRewriteDatabaseMap::class,
            DataProductUrlRewriteDatabaseMap::class
        ];
    }

    /**
     * Regenerate Categories and children (subcategories and related products) Url Rewrites in specific store
     *
     * @param int $storeId
     * @return $this
     */
    public function regenerate(int $storeId = 0): static
    {
        if (count($this->regenerateOptions['categoriesFilter']) > 0) {
            $this->regenerateCategoriesRangeUrlRewrites(
                $this->regenerateOptions['categoriesFilter'],
                $storeId
            );
        } elseif (!empty($this->regenerateOptions['categoryId'])) {
            $this->regenerateSpecificCategoryUrlRewrites(
                $this->regenerateOptions['categoryId'],
                $storeId
            );
        } else {
            $this->regenerateAllCategoriesUrlRewrites($storeId);
        }
        return $this;
    }

    /**
     * Category failures plus those of products regenerated through this category run
     *
     * @return array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}>
     */
    public function getFailures(): array
    {
        return array_merge(parent::getFailures(), $this->regenerateProductRewrites->getFailures());
    }

    /**
     * Category failure counts plus those of products regenerated through this category run
     *
     * @return array<string, int>
     */
    public function getFailureCounts(): array
    {
        $counts = parent::getFailureCounts();
        foreach ($this->regenerateProductRewrites->getFailureCounts() as $entityType => $count) {
            $counts[$entityType] = ($counts[$entityType] ?? 0) + $count;
        }

        return $counts;
    }

    /**
     * @return $this
     */
    public function startRun(): static
    {
        parent::startRun();
        // the cascaded products' model has run state of its own (e.g. a transaction lost in the last run)
        $this->regenerateProductRewrites->startRun();
        $this->changedUrlPathIds = [];

        return $this;
    }

    /**
     * Category counts plus those of products regenerated through this category run
     *
     * @return array<string, array<int, int>>
     */
    public function getProcessedCounts(): array
    {
        return array_merge(parent::getProcessedCounts(), $this->regenerateProductRewrites->getProcessedCounts());
    }

    /**
     * @return $this
     */
    public function resetProcessedCounts(): static
    {
        parent::resetProcessedCounts();
        $this->regenerateProductRewrites->resetProcessedCounts();

        return $this;
    }

    /**
     * @return $this
     */
    public function resetFailures(): static
    {
        parent::resetFailures();
        $this->regenerateProductRewrites->resetFailures();

        return $this;
    }

    /**
     * Regenerate Url Rewrites of all categories
     *
     * @param int $storeId
     * @return $this
     */
    public function regenerateAllCategoriesUrlRewrites(int $storeId = 0): static
    {
        $this->regenerateCategoriesRangeUrlRewrites([], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites of specific category
     *
     * @param int $categoryId
     * @param int $storeId
     * @return $this
     */
    public function regenerateSpecificCategoryUrlRewrites(int $categoryId, int $storeId = 0): static
    {
        $this->regenerateCategoriesRangeUrlRewrites([$categoryId], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites of a category range
     *
     * @param array $categoriesFilter
     * @param int $storeId
     * @return $this
     */
    public function regenerateCategoriesRangeUrlRewrites(array $categoriesFilter = [], int $storeId = 0): static
    {
        try {
            $categories = $this->_getCategoriesCollection($categoriesFilter, $storeId);
        } catch (LocalizedException $e) {
            $this->_addFailure($this->entityType, null, $storeId, 'loading categories failed: ' . $e->getMessage());
            return $this;
        }

        $this->pendingProductIds = [];

        try {
            $processedIds = $this->_processCategories($categories, $storeId);

            // exact mode: a descendant's url_path is built from its ancestors', so the descendants of a category whose
            // path changed are processed too — to update their paths, not their url_keys
            if ($this->regenerateOptions['exactCategories'] && count($this->changedUrlPathIds) > 0) {
                $options = $this->regenerateOptions;
                $this->regenerateOptions['regenUrlKey'] = false;
                try {
                    $this->_processCategories(
                        $this->_getDescendantsCollection(array_keys($this->changedUrlPathIds), $processedIds, $storeId),
                        $storeId
                    );
                } finally {
                    $this->regenerateOptions = $options;
                }
            }

            // products of all processed categories, each regenerated once, after every category's url_path is
            // final (regenerating per category repeated each product once per ancestor category) — in bounded
            // batches so no single collection/SQL IN list spans the whole catalog; one table sync below covers all
            if (!empty($this->pendingProductIds)) {
                $this->_regenerateProducts($storeId);
            }
        } finally {
            // also when a run is stopped (an exception from the progress reporter): products still queued are
            // dropped, and the rows saved so far get their product/category table entries
            $this->pendingProductIds = [];
            $this->_updateSecondaryTable();
        }

        return $this;
    }

    /**
     * Process category Url Rewrites re-generation
     *
     * @param $category
     * @param int $storeId
     * @return $this
     */
    protected function categoryProcess($category, int $storeId = 0): static
    {
        // skip entities that already have a URL Rewrite for this store, instead of always
        // deleting + regenerating (see #50)
        if ($this->regenerateOptions['skipExisting'] && $this->_urlRewriteExistsForEntity($category->getId(), $storeId)) {
            return $this;
        }

        $category->setStoreId($storeId);

        if ($this->regenerateOptions['saveOldUrls']) {
            $category->setData('save_rewrites_history', true);
        }

        $scopes = array_unique([0, $storeId]);
        $before = $this->changeListener !== null ? $this->_readUrlAttributes($category, $scopes) : null;
        try {
            if ($this->regenerateOptions['regenUrlKey']) {
                $originalUrlKey = $category->getUrlKey();
                $category->setOrigData('url_key', null);
                $generatedKey = $this->_getCategoryUrlPathGenerator()->getUrlKey($category->setUrlKey(null));

                // don't write a blank url_key when Magento's own transliteration can't handle the title
                // (see #89), and don't write a redundant per-store override if it's identical to the
                // default-scope value (see #92)
                if (trim($generatedKey) === '') {
                    // restore the category's original url_key so the rewrite generation below still
                    // produces the existing (working) path instead of a blank/broken one
                    $category->setUrlKey($originalUrlKey);
                } else {
                    $category->setUrlKey($generatedKey);

                    if ($storeId == 0 || $generatedKey !== $this->_getDefaultScopeUrlKey($category->getId())) {
                        $category->getResource()->saveAttribute($category, 'url_key');
                    } else {
                        $this->_deleteStoreUrlKey($category, $storeId);
                    }
                }
            }

            // clear the inherited url_path before generating, otherwise CategoryUrlPathGenerator's
            // shouldReturnCurrentUrlPath() short-circuits and hands back the default-scope value
            // instead of recomputing it for this store (see #184)
            $currentUrlPath = $category->getUrlPath();
            $category->unsUrlPath();

            try {
                $urlPath = $this->_getCategoryUrlPathGenerator()->getUrlPath($category);
            } catch (LocalizedException $e) {
                $urlPath = null;
            }
            if (!empty($urlPath)) {
                $category->setUrlPath($urlPath);
                $category->getResource()->saveAttribute($category, 'url_path');
                if ($urlPath !== $currentUrlPath) {
                    $this->changedUrlPathIds[(int)$category->getId()] = true;
                }
            }
        } finally {
            if ($before !== null) {
                $this->_queueAttributeChanges($category, $before, $this->_readUrlAttributes($category, $scopes));
            }
        }

        // store 0 in an all-stores run only updates the default-scope url_key/url_path above: generating
        // here (global scope) would regenerate every store view, which the run then does again per store view
        if ($this->regenerateOptions['defaultScopeOnly']) {
            return $this;
        }

        $category->setChangedProductIds(true);

        try {
            $categoryUrlRewriteResult = $this->_getCategoryUrlRewriteGenerator()->generate($category, true);
        } catch (\Exception $e) {
            $categoryUrlRewriteResult = null;
            $this->_addFailure(
                $this->entityType,
                (int)$category->getId(),
                $storeId,
                'generating URL rewrites failed: ' . $e->getMessage()
            );
        }
        if (!empty($categoryUrlRewriteResult)) {
            $this->saveUrlRewrites($categoryUrlRewriteResult);
        }

        // if config option "Use Category Path for Product URLs" is "Yes" then queue this category's products
        // for regeneration at the end of the pass, unless explicitly skipped (see #86)
        if (!$this->regenerateOptions['skipProducts'] && $this->helper->useCategoriesPathForProductUrls($storeId)) {
            // exact mode: only the category's own products — its descendants are processed only if their path changed
            $categoryIds = $this->regenerateOptions['exactCategories']
                ? (string)$category->getId()
                : $category->getAllChildren();
            foreach ($this->_getCategoriesProductsIds($categoryIds) as $productId) {
                $this->pendingProductIds[(int)$productId] = true;
            }
        }

        //frees memory for maps that are self-initialized in multiple classes that were called by the generators
        $this->_resetUrlRewritesDataMaps($category);

        return $this;
    }

    /**
     * @param Collection $categories
     * @param int $storeId
     * @return int[] IDs of the categories processed
     */
    protected function _processCategories(Collection $categories, int $storeId): array
    {
        $processedIds = [];
        $pageCount = $categories->getLastPageNumber();
        $currentPage = 1;

        $this->_progressStart($storeId, (int)$categories->getSize());
        while ($currentPage <= $pageCount) {
            $categories->clear();
            $categories->setCurPage($currentPage);

            foreach ($categories as $category) {
                try {
                    $this->categoryProcess($category, $storeId);
                } catch (\Exception $e) {
                    // skip this category (e.g. broken/orphaned category tree) and continue with the rest
                    $this->_addFailure($this->entityType, (int)$category->getId(), $storeId, $e->getMessage());
                }
                $this->_flushChanges();
                $processedIds[] = (int)$category->getId();
                $this->_countProcessed($storeId);
                $this->_progressAdvance();
            }

            $currentPage++;
        }
        $this->_progressFinish();

        return $processedIds;
    }

    /**
     * Regenerate the queued products in batches, reported as one "product" pass so the reporter can follow and stop
     * this phase as well
     *
     * @param int $storeId
     * @return void
     */
    protected function _regenerateProducts(int $storeId): void
    {
        $reportProgress = $this->regenerateOptions['showProgress'] && $this->progressReporter !== null;
        $options = $this->regenerateOptions;
        // Cascaded products need new paths and rewrites, but keep their own URL keys.
        $options['regenUrlKey'] = false;
        $options['showProgress'] = $reportProgress;
        $options['progressPassStarted'] = true;
        $options['skipSecondaryTableUpdate'] = true;
        $this->regenerateProductRewrites->setRegenerateOptions($options);

        $this->reporterException = null;
        if ($this->changeListener !== null) {
            $this->regenerateProductRewrites->setChangeListener($this->_getStopTrackingListener());
        }
        if ($reportProgress) {
            $this->progressReporter->start('product', $storeId, count($this->pendingProductIds));
            $this->regenerateProductRewrites->setProgressReporter($this->_getStopTrackingReporter());
        }

        try {
            $batch = [];
            foreach ($this->pendingProductIds as $productId => $unused) {
                $batch[] = $productId;
                if (count($batch) === self::PRODUCT_BATCH_SIZE) {
                    $this->_regenerateProductsBatch($batch, $storeId);
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                $this->_regenerateProductsBatch($batch, $storeId);
            }
        } finally {
            $this->regenerateProductRewrites->setProgressReporter(null);
            $this->regenerateProductRewrites->setChangeListener(null);
        }

        if ($reportProgress) {
            $this->progressReporter->finish();
        }
    }

    /**
     * The change listener, remembering what it throws (see _regenerateProductsBatch())
     *
     * @return ChangeListenerInterface
     */
    private function _getStopTrackingListener(): ChangeListenerInterface
    {
        $listener = $this->changeListener;
        $track = function (\Throwable $e): void {
            $this->reporterException = $e;
        };

        return new class ($listener, $track) implements ChangeListenerInterface {
            /**
             * @param ChangeListenerInterface $listener
             * @param \Closure $track
             */
            public function __construct(private ChangeListenerInterface $listener, private \Closure $track)
            {
            }

            /**
             * @param ChangeInterface $change
             * @return void
             */
            public function onChange(ChangeInterface $change): void
            {
                try {
                    $this->listener->onChange($change);
                } catch (\Throwable $e) {
                    ($this->track)($e);
                    throw $e;
                }
            }
        };
    }

    /**
     * The progress reporter, remembering what it throws (see _regenerateProductsBatch())
     *
     * @return ProgressReporterInterface
     */
    private function _getStopTrackingReporter(): ProgressReporterInterface
    {
        $reporter = $this->progressReporter;
        $track = function (\Throwable $e): void {
            $this->reporterException = $e;
        };

        return new class ($reporter, $track) implements ProgressReporterInterface {
            /**
             * @param ProgressReporterInterface $reporter
             * @param \Closure $track
             */
            public function __construct(private ProgressReporterInterface $reporter, private \Closure $track)
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
                $this->forward(fn () => $this->reporter->start($entityType, $storeId, $total));
            }

            /**
             * @param int $steps
             * @return void
             */
            public function advance(int $steps = 1): void
            {
                $this->forward(fn () => $this->reporter->advance($steps));
            }

            /**
             * @return void
             */
            public function finish(): void
            {
                $this->forward(fn () => $this->reporter->finish());
            }

            /**
             * @param string $text
             * @param bool $newLine
             * @return void
             */
            public function message(string $text, bool $newLine = true): void
            {
                $this->forward(fn () => $this->reporter->message($text, $newLine));
            }

            /**
             * @param callable $call
             * @return void
             */
            private function forward(callable $call): void
            {
                try {
                    $call();
                } catch (\Throwable $e) {
                    ($this->track)($e);
                    throw $e;
                }
            }
        };
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return void
     */
    protected function _regenerateProductsBatch(array $productIds, int $storeId): void
    {
        try {
            $this->regenerateProductRewrites->regenerateProductsRangeUrlRewrites($productIds, $storeId);
        } catch (\Exception $e) {
            if ($e === $this->reporterException) {
                throw $e;
            }
            // a batch that can't even be loaded is reported like any other failure; the run continues
            $this->_addFailure(
                'product',
                null,
                $storeId,
                'regenerating ' . count($productIds) . ' products of the category run failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get categories collection
     *
     * @param array $categoriesFilter
     * @param int $storeId
     * @return Collection
     * @throws LocalizedException
     */
    protected function _getCategoriesCollection(array $categoriesFilter = [], int $storeId = 0): Collection
    {
        $categoriesCollection = $this->categoryCollectionFactory->create();
        $categoriesCollection->addAttributeToSelect('name')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path')
            ->setStoreId($storeId)
            // every category under the root - top level down to the deepest child - is processed
            // individually, so each one's own url_key/url_path gets regenerated (see #153/#166);
            // Magento's own generator only cascades url_rewrite rows to children, not their attributes
            ->addFieldToFilter('level', ['gt' => '1'])
            ->setOrder('level', 'ASC')
            // use limit to avoid an "eating" of a memory
            ->setPageSize($this->categoriesCollectionPageSize);

        $rootCategoryId = $this->_getStoreRootCategoryId($storeId);
        if ($rootCategoryId > 0) {
            // we use this filter instead of "->setStore()" - because "setStore()" is not working (another Magento issue)
            $categoriesCollection->addAttributeToFilter('path', array('like' => "1/{$rootCategoryId}/%"));
        }

        if (count($categoriesFilter) > 0 && $this->regenerateOptions['exactCategories']) {
            $categoriesCollection->addAttributeToFilter('entity_id', ['in' => $categoriesFilter]);
        } elseif (count($categoriesFilter) > 0) {
            // include the targeted categories AND all of their descendants, not just the literal
            // IDs given, so descendant categories' own url_key/url_path also get regenerated
            $orConditions = [
                ['attribute' => 'entity_id', 'in' => $categoriesFilter],
            ];
            foreach ($this->_getCategoriesPaths($categoriesFilter) as $path) {
                $orConditions[] = ['attribute' => 'path', 'like' => $path . '/%'];
            }
            $categoriesCollection->addAttributeToFilter($orConditions);
        }

        return $categoriesCollection;
    }

    /**
     * Every descendant of the given categories, except the excluded ones, shallowest first
     *
     * @param int[] $categoryIds
     * @param int[] $excludeIds
     * @param int $storeId
     * @return Collection
     */
    protected function _getDescendantsCollection(array $categoryIds, array $excludeIds, int $storeId): Collection
    {
        $descendants = $this->_getCategoriesCollection([], $storeId);

        $orConditions = [];
        foreach ($this->_getCategoriesPaths($categoryIds) as $path) {
            $orConditions[] = ['attribute' => 'path', 'like' => $path . '/%'];
        }
        $descendants->addAttributeToFilter($orConditions);
        if (count($excludeIds) > 0) {
            $descendants->addAttributeToFilter('entity_id', ['nin' => $excludeIds]);
        }

        return $descendants;
    }

    /**
     * Get "path" attribute values for given category IDs
     *
     * @param array $categoryIds
     * @return array
     */
    protected function _getCategoriesPaths(array $categoryIds): array
    {
        $select = $this->_getResourceConnection()->getConnection()->select()
            ->from($this->_getResourceConnection()->getTableName('catalog_category_entity'), ['path'])
            ->where('entity_id IN (?)', $categoryIds);

        return $this->_getResourceConnection()->getConnection()->fetchCol($select);
    }

    /**
     * Get default-scope (store 0) url_key value for a category, to avoid writing a redundant
     * per-store override when it would be identical (see #92)
     *
     * @param int $entityId
     * @return string|null
     */
    protected function _getDefaultScopeUrlKey(int $entityId): ?string
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('url_key')
            ->addIdFilter([$entityId])
            ->setStoreId(0);

        $category = $collection->getFirstItem();

        return $category->getId() ? $category->getUrlKey() : null;
    }

    /**
     * Get product Ids which are related to specific categories
     *
     * @param string $categoryIds
     * @return array
     */
    protected function _getCategoriesProductsIds(string $categoryIds = ''): array
    {
        $result = [];

        if (!empty($categoryIds)) {
            $ids = array_filter(array_map('intval', explode(',', $categoryIds)));
            $select = $this->_getResourceConnection()->getConnection()->select()
                ->from($this->_getCategoryProductsTableName(), ['product_id'])
                ->where('category_id IN (?)', $ids);
            $rows = $this->_getResourceConnection()->getConnection()->fetchAll($select);

            foreach ($rows as $row) {
                $result[] = $row['product_id'];
            }
        }

        return $result;
    }

    /**
     * Get category Url Path generator
     *
     * @return CategoryUrlPathGenerator
     */
    protected function _getCategoryUrlPathGenerator(): CategoryUrlPathGenerator
    {
        if (is_null($this->categoryUrlPathGenerator)) {
            $this->categoryUrlPathGenerator = $this->categoryUrlPathGeneratorFactory->create();
        }

        return $this->categoryUrlPathGenerator;
    }

    /**
     * Get category Url Rewrite generator
     *
     * @return CategoryUrlRewriteGenerator
     */
    protected function _getCategoryUrlRewriteGenerator(): CategoryUrlRewriteGenerator
    {
        if (is_null($this->categoryUrlRewriteGenerator)) {
            $this->categoryUrlRewriteGenerator = $this->categoryUrlRewriteGeneratorFactory->create();
        }

        return $this->categoryUrlRewriteGenerator;
    }


    /**
     * Resets used data maps to free up memory and temporary tables
     *
     * @param $category
     * @return void
     */
    protected function _resetUrlRewritesDataMaps($category): void
    {
        foreach ($this->dataUrlRewriteClassNames as $className) {
            $this->databaseMapPool->resetMap($className, $category->getEntityId());
        }
    }
}
