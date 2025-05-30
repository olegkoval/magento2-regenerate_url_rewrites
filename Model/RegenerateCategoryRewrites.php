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
use Magento\CatalogUrlRewrite\Observer\UrlRewriteHandlerFactory;
use Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler;

class RegenerateCategoryRewrites extends AbstractRegenerateRewrites
{
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
     * @var UrlRewriteHandlerFactory
     */
    protected $urlRewriteHandlerFactory;

    /**
     * @var UrlRewriteHandler
     */
    protected $urlRewriteHandler;

    /**
     * @var RegenerateProductRewrites
     */
    protected $regenerateProductRewrites;

    /**
     * @param RegenerateHelper $helper
     * @param ResourceConnection $resourceConnection
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param DatabaseMapPool\Proxy $databaseMapPool
     * @param CategoryUrlPathGeneratorFactory\Proxy $categoryUrlPathGeneratorFactory
     * @param CategoryUrlRewriteGeneratorFactory\Proxy $categoryUrlRewriteGeneratorFactory
     * @param UrlRewriteHandlerFactory\Proxy $urlRewriteHandlerFactory
     * @param RegenerateProductRewrites $regenerateProductRewrites
     */
    public function __construct(
        RegenerateHelper                         $helper,
        ResourceConnection                       $resourceConnection,
        CategoryCollectionFactory                $categoryCollectionFactory,
        DatabaseMapPool\Proxy                    $databaseMapPool,
        CategoryUrlPathGeneratorFactory\Proxy    $categoryUrlPathGeneratorFactory,
        CategoryUrlRewriteGeneratorFactory\Proxy $categoryUrlRewriteGeneratorFactory,
        UrlRewriteHandlerFactory\Proxy           $urlRewriteHandlerFactory,
        RegenerateProductRewrites                $regenerateProductRewrites
    )
    {
        parent::__construct($helper, $resourceConnection);

        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->databaseMapPool = $databaseMapPool;
        $this->categoryUrlPathGeneratorFactory = $categoryUrlPathGeneratorFactory;
        $this->categoryUrlRewriteGeneratorFactory = $categoryUrlRewriteGeneratorFactory;
        $this->urlRewriteHandlerFactory = $urlRewriteHandlerFactory;
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

            $pageCount = $categories->getLastPageNumber();
            $this->progressBarProgress = 0;
            $this->progressBarTotal = (int)$categories->getSize();
            $currentPage = 1;

            $this->_showProgress();
            while ($currentPage <= $pageCount) {
                $categories->clear();
                $categories->setCurPage($currentPage);

                foreach ($categories as $category) {
                    $this->categoryProcess($category, $storeId);
                    $this->_showProgress();
                }

                $currentPage++;
            }

            $this->_updateSecondaryTable();
        } catch (LocalizedException $e) {
            // skip it
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
        $category->setStoreId($storeId);

        if ($this->regenerateOptions['saveOldUrls']) {
            $category->setData('save_rewrites_history', true);
        }

        if (!$this->regenerateOptions['noRegenUrlKey']) {
            $category->setOrigData('url_key', null);
            $category->setUrlKey($this->_getCategoryUrlPathGenerator()->getUrlKey($category->setUrlKey(null)));
            $category->getResource()->saveAttribute($category, 'url_key');
        }

        try {
            $urlPath = $this->_getCategoryUrlPathGenerator()->getUrlPath($category);
        } catch (LocalizedException $e) {
            $urlPath = null;
        }
        if (!empty($urlPath)) {
            $category->unsUrlPath();
            $category->setUrlPath($urlPath);
            $category->getResource()->saveAttribute($category, 'url_path');
        }

        $category->setChangedProductIds(true);

        try {
            $categoryUrlRewriteResult = $this->_getCategoryUrlRewriteGenerator()->generate($category, true);
        } catch (\Exception $e) {
            $categoryUrlRewriteResult = null;
        }
        if (!empty($categoryUrlRewriteResult)) {
            $this->saveUrlRewrites($categoryUrlRewriteResult);
        }

        // if config option "Use Category Path for Product URLs" is "Yes" then regenerate product urls
        if ($this->helper->useCategoriesPathForProductUrls($storeId)) {
            $productsIds = $this->_getCategoriesProductsIds($category->getAllChildren());
            if (!empty($productsIds)) {
                $this->regenerateProductRewrites->regenerateOptions = $this->regenerateOptions;
                $this->regenerateProductRewrites->regenerateOptions['showProgress'] = false;
                $this->regenerateProductRewrites->regenerateProductsRangeUrlRewrites($productsIds, $storeId);
            }
        }

        //frees memory for maps that are self-initialized in multiple classes that were called by the generators
        $this->_resetUrlRewritesDataMaps($category);

        $this->progressBarProgress++;

        return $this;
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
            // if we need to regenerate Url Rewrites for all categories, then we select only top level
            // and all subcategories (and products) will be regenerated as children
            ->addFieldToFilter('level', (count($categoriesFilter) > 0 ? ['gt' => '1'] : 2))
            ->setOrder('level', 'ASC')
            // use limit to avoid an "eating" of a memory
            ->setPageSize($this->categoriesCollectionPageSize);

        $rootCategoryId = $this->_getStoreRootCategoryId($storeId);
        if ($rootCategoryId > 0) {
            // we use this filter instead of "->setStore()" - because "setStore()" is not working (another Magento issue)
            $categoriesCollection->addAttributeToFilter('path', array('like' => "1/{$rootCategoryId}/%"));
        }

        if (count($categoriesFilter) > 0) {
            $categoriesCollection->addIdFilter($categoriesFilter);
        }

        return $categoriesCollection;
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
            $select = $this->_getResourceConnection()->getConnection()->select()
                ->from($this->_getCategoryProductsTableName(), ['product_id'])
                ->where("category_id IN ({$categoryIds})");
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
     * Get Url Rewrite handler
     *
     * @return UrlRewriteHandler
     */
    protected function _getUrlRewriteHandler(): UrlRewriteHandler
    {
        if (is_null($this->urlRewriteHandler)) {
            $this->urlRewriteHandler = $this->urlRewriteHandlerFactory->create();
        }

        return $this->urlRewriteHandler;
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
