<?php
/**
 * Regenerate Url rewrites product abstract class
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <contact@olegkoval.com>
 * @copyright Coyright (c) Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Console\Command;

abstract class RegenerateUrlRewritesProductAbstract extends RegenerateUrlRewritesAbstract
{
    /**
     * @var integer
     */
    protected $_productsCollectionPageSize = 1000;

    /**
     * @see parent::regenerateProductsRangeUrlRewrites()
     */
    public function regenerateProductsRangeUrlRewrites($productsFilter = [], $storeId = 0)
    {
        //get products collection
        $products = $this->_getProductsCollection($storeId, $productsFilter);

        $pageCount = $products->getLastPageNumber();
        $this->_progress = 0;
        $this->_total = (int)$products->getSize();
        $this->_displayProgressBar();
        $currentPage = 1;

        while ($currentPage <= $pageCount) {
            $products->clear();
            $products->setCurPage($currentPage);

            foreach ($products as $product) {
                $this->_productProcess($product, $storeId);
            }

            $currentPage++;
        }
    }

    /**
     * @see parent::regenerateAllProductsUrlRewrites()
     */
    public function regenerateAllProductsUrlRewrites($storeId = 0)
    {
        $this->regenerateProductsRangeUrlRewrites([], $storeId);
    }

    /**
     * @see parent::regenerateSpecificProductUrlRewrites()
     */
    public function regenerateSpecificProductUrlRewrites($productId, $storeId = 0)
    {
        $this->regenerateProductsRangeUrlRewrites([$productId], $storeId);
    }

    /**
     * Regenerate Url Rewrite for specific product
     * @param  object $product
     * @param  integer $storeId
     * @return void
     */
    protected function _productProcess($product, $storeId)
    {
        try {
            if ($this->_commandOptions['saveOldUrls']) {
                $product->setData('save_rewrites_history', true);
            }

            if($this->_commandOptions['regenUrlKeys']) {
                $product->setData('url_key', null);
            }

            $generatedKey = $this->_productUrlPathGenerator->getUrlKey($product);

            $this->_getProductAction()->updateAttributes(
                [$product->getId()],
                ['url_path' => null, 'url_key' => $generatedKey],
                $storeId
            );
            $product->setData('url_path', null)
                    ->setData('url_key', $generatedKey)
                    ->setStoreId($storeId);
            $productUrlRewriteResult = $this->_getProductUrlRewriteGenerator()->generate($product);

            $productUrlRewriteResult = $this->_sanitizeProductUrlRewrites($productUrlRewriteResult);

            try {
                $this->_urlPersist->replace($productUrlRewriteResult);
            } catch (\Exception $y) {
                //to debugg error
                $this->_displayConsoleMsg($y->getMessage() .' Product ID: '. $product->getId());
            }

            $this->_progress++;
            $this->_displayProgressBar();
        } catch (\Exception $e) {
            $this->_displayConsoleMsg($e->getMessage() . ' Product ID: '. $product->getId());
        }
    }

    /**
     * Sanitize product URL rewrites
     * @param  array $productUrlRewrites
     * @return array
     */
    protected function _sanitizeProductUrlRewrites($productUrlRewrites)
    {
        $paths = [];
        foreach ($productUrlRewrites as $key => $urlRewrite) {
            $path = $this->_clearRequestPath($urlRewrite->getRequestPath());
            if (!in_array($path, $paths)) {
                $productUrlRewrites[$key]->setRequestPath($path);
                $paths[] = $path;
            } else {
                unset($productUrlRewrites[$key]);
            }
        }

        return $productUrlRewrites;
    }

    /**
     * Get products collection
     * @param  integer $storeId
     * @param  array   $productsFilter
     * @return collection
     */
    protected function _getProductsCollection($storeId = 0, $productsFilter = [])
    {
        $productsCollection = $this->_productCollectionFactory->create();

        $productsCollection->setStore($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path')
            ->addAttributeToSelect('status')
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            // use limit to avoid a "eating" of a memory 
            ->setPageSize($this->_productsCollectionPageSize);

        if (count($productsFilter) > 0) {
            $productsCollection->addIdFilter($productsFilter);
        }

        return $productsCollection;
    }
}
