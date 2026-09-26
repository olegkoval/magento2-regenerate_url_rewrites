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

        $pageCount = $categories->getLastPageNumber();
        $this->progressBarProgress = 0;
        $this->progressBarTotal = (int)$categories->getSize();
        $currentPage = 1;
        $this->pendingProductIds = [];

        $this->_showProgress();
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
                $this->progressBarProgress++;
                $this->_showProgress();
            }

            $currentPage++;
        }

        // products of all processed categories, each regenerated once, after every category's url_path is
        // final (regenerating per category repeated each product once per ancestor category) — in bounded
        // batches so no single collection/SQL IN list spans the whole catalog; one table sync below covers all
        if (!empty($this->pendingProductIds)) {
            $options = $this->regenerateOptions;
            $options['showProgress'] = false;
            $options['skipSecondaryTableUpdate'] = true;
            $this->regenerateProductRewrites->setRegenerateOptions($options);

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
            $this->pendingProductIds = [];
        }

        $this->_updateSecondaryTable();

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
                }
            }
        }

        // clear the inherited url_path before generating, otherwise CategoryUrlPathGenerator's
        // shouldReturnCurrentUrlPath() short-circuits and hands back the default-scope value
        // instead of recomputing it for this store (see #184)
        $category->unsUrlPath();

        try {
            $urlPath = $this->_getCategoryUrlPathGenerator()->getUrlPath($category);
        } catch (LocalizedException $e) {
            $urlPath = null;
        }
        if (!empty($urlPath)) {
            $category->setUrlPath($urlPath);
            $category->getResource()->saveAttribute($category, 'url_path');
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
            foreach ($this->_getCategoriesProductsIds($category->getAllChildren()) as $productId) {
                $this->pendingProductIds[(int)$productId] = true;
            }
        }

        //frees memory for maps that are self-initialized in multiple classes that were called by the generators
        $this->_resetUrlRewritesDataMaps($category);

        return $this;
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

        if (count($categoriesFilter) > 0) {
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
