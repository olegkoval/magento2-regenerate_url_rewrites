<?php
/**
 * Regenerate Url rewrites category abstract class
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <contact@olegkoval.com>
 * @copyright Coyright (c) Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Console\Command;

abstract class RegenerateUrlRewritesCategoryAbstract extends RegenerateUrlRewritesProductAbstract
{
    /**
     * @var integer
     */
    protected $_categoriesCollectionPageSize = 100;

    /**
     * @see parent::regenerateCategoriesRangeUrlRewrites()
     */
    public function regenerateCategoriesRangeUrlRewrites($categoriesFilter = [], $storeId = 0)
    {
        // get categories collection
        $categories = $this->_getCategoriesCollection($storeId, $categoriesFilter);

        $pageCount = $categories->getLastPageNumber();
        $this->_progress = 0;
        $this->_total = (int)$categories->getSize();
        $this->_displayProgressBar();
        $currentPage = 1;

        while ($currentPage <= $pageCount) {
            $categories->setCurPage($currentPage);

            foreach ($categories as $category) {
                $this->_categoryProcess($category, $storeId);
            }

            $categories->clear();
            $currentPage++;
        }
    }

    /**
     * @see parent::regenerateAllCategoriesUrlRewrites()
     */
    public function regenerateAllCategoriesUrlRewrites($storeId = 0)
    {
        $this->regenerateCategoriesRangeUrlRewrites([], $storeId);
    }

    /**
     * @see parent::regenerateSpecificCategoryUrlRewrites()
     */
    public function regenerateSpecificCategoryUrlRewrites($categoryId, $storeId = 0)
    {
        $this->regenerateCategoriesRangeUrlRewrites([$categoryId], $storeId);
    }

    /**
     * Process category URL rewrites re-generation
     * @param  \Magento\Catalog\Api\Data\CategoryInterface|\Magento\Framework\Model\AbstractModel $category
     * @param  integer $storeId
     * @return void
     */
    protected function _categoryProcess($category, $storeId = 0)
    {
        try {
            if ($this->_commandOptions['saveOldUrls']) {
                $category->setData('save_rewrites_history', true);
            }
            $category->setStoreId($storeId);
            $category->setUrlKey($category->formatUrlKey($category->getName()));
            $category->getResource()->saveAttribute($category, 'url_key');
            $category->setUrlPath($this->_categoryUrlPathGenerator->getUrlPath($category));
            $category->getResource()->saveAttribute($category, 'url_path');

            $this->_regenerateCategoryUrlRewrites($category, $storeId);

            //frees memory for maps that are self-initialized in multiple classes that were called by the generators
            $this->_resetUrlRewritesDataMaps($category);

            $this->_progress++;
            $this->_displayProgressBar();
        } catch (\Exception $e) {
            // debugging
            $this->_addConsoleMsg('Exception: '. $e->getMessage() .' Category ID: '. $category->getId());
        }
    }

    /**
     * Regenerate category and category products Url Rewrites
     * @param  \Magento\Catalog\Api\Data\CategoryInterface|\Magento\Framework\Model\AbstractModel $category
     * @param  integer $storeId
     * @return void
     */
    protected function _regenerateCategoryUrlRewrites($category, $storeId)
    {
        try {
            $category->setStore($storeId);
            $category->setChangedProductIds(true);
            $categoryUrlRewriteResult = $this->_getCategoryUrlRewriteGenerator()->generate($category, true);
            $this->_doBunchReplaceUrlRewrites($categoryUrlRewriteResult);

            // if config option "Use Categories Path for Product URLs" is "Yes"
            if (
                ($this->_commandOptions['checkUseCategoryInProductUrl'] && $this->_getUseCategoriesPathForProductUrlsConfig($storeId))
                || !$this->_commandOptions['checkUseCategoryInProductUrl']
            ) {
                $productUrlRewriteResult = $this->_getUrlRewriteHandler()->generateProductUrlRewrites($category);

                // fix for double slashes issue and dots
                foreach ($productUrlRewriteResult as &$urlRewrite) {
                    $urlRewrite->setRequestPath($this->_clearRequestPath($urlRewrite->getRequestPath()));
                }

                $this->_doBunchReplaceUrlRewrites($productUrlRewriteResult, 'Product');
            }
        } catch (\Exception $e) {
            // debugging
            $this->_addConsoleMsg('Exception: '. $e->getMessage() .' Category ID: '. $category->getId());
        }
    }

    /**
     * Resets used data maps to free up memory and temporary tables
     *
     * @param Category $category
     * @return void
     */
    protected function _resetUrlRewritesDataMaps($category)
    {
        foreach ($this->_dataUrlRewriteClassNames as $className) {
            $this->_databaseMapPool->resetMap($className, $category->getEntityId());
        }
    }

    /**
     * Get categories collection
     * @param  integer $storeId
     * @param  array   $categoriesFilter
     * @return collection
     */
    protected function _getCategoriesCollection($storeId = 0, $categoriesFilter = [])
    {
        $categoriesCollection = $this->_categoryCollectionFactory->create();

        $categoriesCollection->addAttributeToSelect('name')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path')
            ->addFieldToFilter('level', array('gt' => '1'))
            ->setOrder('level', 'DESC')
            // use limit to avoid a "eating" of a memory
            ->setPageSize($this->_categoriesCollectionPageSize);

        $rootCategoryId = $this->_getStoreRootCategoryId($storeId);
        if ($rootCategoryId > 0) {
            // we use this filter instead of "->setStore()" - because "setStore()" is not working now (another Magento issue)
            $categoriesCollection->addAttributeToFilter('path', array('like' => "1/{$rootCategoryId}/%"));
        }

        if (count($categoriesFilter) > 0) {
            $categoriesCollection->addIdFilter($categoriesFilter);
        }

        return $categoriesCollection;
    }

    /**
     * Get root category Id of specific store
     * @param  string $storeId
     * @return integer
     */
    protected function _getStoreRootCategoryId($storeId)
    {
        $result = 0;

        // use SQL to speed up and to not instantiate additional store object just for root category ID
        $tableName1 = $this->_resource->getTableName('store_group');
        $tableName2 = $this->_resource->getTableName('store');
        $sql = "SELECT t1.root_category_id FROM {$tableName1} t1 INNER JOIN {$tableName2} t2 ON t2.website_id = t1.website_id WHERE t2.store_id = {$storeId};";

        $result = (int) $this->_resource->getConnection()->fetchOne($sql);

        return $result;
    }
}
