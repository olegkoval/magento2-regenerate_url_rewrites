<?php
/**
 * RegenerateProductRewrites.php
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2017-2067 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use OlegKoval\RegenerateUrlRewrites\Helper\Regenerate as RegenerateHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\ActionFactory as ProductActionFactory;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGeneratorFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class RegenerateProductRewrites extends AbstractRegenerateRewrites
{
    /**
     * @var string
     */
    protected $entityType = 'product';

    /**
     * @var int
     */
    protected $productsCollectionPageSize = 1000;

    /**
     * @var ProductActionFactory
     */
    protected $productActionFactory;

    /**
     * @var Action
     */
    protected $productAction;

    /**
     * @var ProductUrlRewriteGeneratorFactory
     */
    protected $productUrlRewriteGeneratorFactory;

    /**
     * @var ProductUrlRewriteGenerator
     */
    protected $productUrlRewriteGenerator;

    /**
     * @var ProductUrlPathGeneratorFactory
     */
    protected $productUrlPathGeneratorFactory;

    /**
     * @var ProductUrlPathGenerator
     */
    protected $productUrlPathGenerator;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * RegenerateProductRewrites constructor.
     *
     * @param RegenerateHelper $helper
     * @param ResourceConnection $resourceConnection
     * @param ProductActionFactory $productActionFactory
     * @param ProductUrlRewriteGeneratorFactory\Proxy $productUrlRewriteGeneratorFactory
     * @param ProductUrlPathGeneratorFactory\Proxy $productUrlPathGeneratorFactory
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        RegenerateHelper                        $helper,
        ResourceConnection                      $resourceConnection,
        ProductActionFactory                    $productActionFactory,
        ProductUrlRewriteGeneratorFactory\Proxy $productUrlRewriteGeneratorFactory,
        ProductUrlPathGeneratorFactory\Proxy    $productUrlPathGeneratorFactory,
        ProductCollectionFactory                $productCollectionFactory
    )
    {
        parent::__construct($helper, $resourceConnection);

        $this->productActionFactory = $productActionFactory;
        $this->productUrlRewriteGeneratorFactory = $productUrlRewriteGeneratorFactory;
        $this->productUrlPathGeneratorFactory = $productUrlPathGeneratorFactory;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Regenerate Products Url Rewrites in specific store
     *
     * @return $this
     */
    public function regenerate(int $storeId = 0): static
    {
        if (count($this->regenerateOptions['productsFilter']) > 0) {
            $this->regenerateProductsRangeUrlRewrites(
                $this->regenerateOptions['productsFilter'],
                $storeId
            );
        } elseif (!empty($this->regenerateOptions['productId'])) {
            $this->regenerateSpecificProductUrlRewrites(
                $this->regenerateOptions['productId'],
                $storeId
            );
        } else {
            $this->regenerateAllProductsUrlRewrites($storeId);
        }

        return $this;
    }

    /**
     * @param int $storeId
     * @return $this
     */
    public function regenerateAllProductsUrlRewrites(int $storeId = 0): static
    {
        $this->regenerateProductsRangeUrlRewrites([], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites for a specific product
     *
     * @param int $productId
     * @param int $storeId
     * @return $this
     */
    public function regenerateSpecificProductUrlRewrites(int $productId, int $storeId = 0): static
    {
        $this->regenerateProductsRangeUrlRewrites([$productId], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites for a product range
     *
     * @param array $productsFilter
     * @param int $storeId
     * @return $this
     */
    public function regenerateProductsRangeUrlRewrites(array $productsFilter = [], int $storeId = 0): static
    {
        $products = $this->_getProductsCollection($productsFilter, $storeId);
        $pageCount = $products->getLastPageNumber();
        $currentPage = 1;

        $this->_progressStart($storeId, (int)$products->getSize());
        while ($currentPage <= $pageCount) {
            $products->clear();
            $products->setCurPage($currentPage);

            foreach ($products as $product) {
                $this->processProduct($product, $storeId);
                $this->_progressAdvance();
            }

            $currentPage++;
        }
        $this->_progressFinish();

        // internal option: a caller running several batches syncs the product/category table once itself
        // (a full-table scan each time otherwise)
        if (!$this->regenerateOptions['skipSecondaryTableUpdate']) {
            $this->_updateSecondaryTable();
        }

        return $this;
    }

    /**
     * @param $entity
     * @param int $storeId
     * @return $this
     */
    public function processProduct($entity, int $storeId = 0): static
    {
        // skip entities that already have a URL Rewrite for this store, instead of always
        // deleting + regenerating (see #50)
        if ($this->regenerateOptions['skipExisting'] && $this->_urlRewriteExistsForEntity($entity->getId(), $storeId)) {
            return $this;
        }

        $entity->setStoreId($storeId)->setData('url_path', null);

        if ($this->regenerateOptions['saveOldUrls']) {
            $entity->setData('save_rewrites_history', true);
        }

        // reset url_path to null, we need this to set a flag to use an Url Rewrites:
        // see logic in a core Product Url model: \Magento\Catalog\Model\Product\Url::getUrl()
        // if "request_path" is not null or equal to "false" then Magento do not search and do not use Url Rewrites
        $updateAttributes = ['url_path' => null];
        if ($this->regenerateOptions['regenUrlKey']) {
            $originalUrlKey = $entity->getUrlKey();
            $generatedKey = $this->_getProductUrlPathGenerator()->getUrlKey($entity->setUrlKey(null));

            // don't write a blank url_key when Magento's own transliteration can't handle the title
            // (see #89), and don't write a redundant per-store override if it's identical to the
            // default-scope value (see #92)
            if (trim($generatedKey) === '') {
                // restore the entity's original url_key so the rewrite generation below still
                // produces the existing (working) path instead of a blank/broken one
                $entity->setUrlKey($originalUrlKey);
            } else {
                $entity->setUrlKey($generatedKey);

                if ($storeId == 0 || $generatedKey !== $this->_getDefaultScopeUrlKey($entity->getId())) {
                    $updateAttributes['url_key'] = $generatedKey;
                }
            }
        }

        try {
            $this->_getProductAction()->updateAttributes(
                [$entity->getId()],
                $updateAttributes,
                $storeId
            );

            // store 0 in an all-stores run only updates the default-scope attributes above: generating here
            // (global scope) would regenerate every store view, which the run then does again per store view
            if ($this->regenerateOptions['defaultScopeOnly']) {

                return $this;
            }

            // append the product's SKU as an extra URL segment, e.g. screws.html -> screws-2244000004.html
            // (see #140). Set on the in-memory url_key only (after updateAttributes(), so never saved) and
            // before generation, so Magento's generator itself builds the history 301s (--save-old-urls) and
            // custom-redirect targets for the URL that is actually saved
            if ($this->regenerateOptions['addSkuToUrl']) {
                $urlKey = $this->_getProductUrlPathGenerator()->getUrlKey($entity);
                $skuSegment = $this->helper->sanitizeSkuForUrl((string)$entity->getSku());
                if ($urlKey !== null && $skuSegment !== '') {
                    $entity->setUrlKey($urlKey . '-' . $skuSegment);
                }
            }

            $urlRewrites = $this->_getProductUrlRewriteGenerator()->generate($entity);
            $urlRewrites = $this->helper->sanitizeProductUrlRewrites($urlRewrites);

            // replace the requested store's rows only: with product_rewrite_context=website Magento also
            // generates sibling store views of the group, but re-emits custom/history rows only for the
            // requested store, so a wider delete would lose the siblings' custom rewrites. A global-scope
            // (store 0) run has no store-0 rows: it replaces the rows of the store views it generated
            if (!empty($urlRewrites)) {
                $this->saveUrlRewrites(
                    $urlRewrites,
                    $storeId == 0
                        ? []
                        : [['entity_type' => $this->entityType, 'entity_id' => $entity->getId(), 'store_id' => $storeId]]
                );
            }
        } catch (\Exception $e) {
            $this->_addFailure($this->entityType, (int)$entity->getId(), $storeId, $e->getMessage());
        }

        return $this;
    }

    /**
     * @return Action
     */
    protected function _getProductAction(): Action
    {
        if (is_null($this->productAction)) {
            $this->productAction = $this->productActionFactory->create();
        }

        return $this->productAction;
    }

    /**
     * @return ProductUrlRewriteGenerator
     */
    protected function _getProductUrlRewriteGenerator(): ProductUrlRewriteGenerator
    {
        if (is_null($this->productUrlRewriteGenerator)) {
            $this->productUrlRewriteGenerator = $this->productUrlRewriteGeneratorFactory->create();
        }

        return $this->productUrlRewriteGenerator;
    }

    /**
     * @return ProductUrlPathGenerator
     */
    protected function _getProductUrlPathGenerator(): ProductUrlPathGenerator
    {
        if (is_null($this->productUrlPathGenerator)) {
            $this->productUrlPathGenerator = $this->productUrlPathGeneratorFactory->create();
        }

        return $this->productUrlPathGenerator;
    }

    /**
     * Get default-scope (store 0) url_key value for a product, to avoid writing a redundant
     * per-store override when it would be identical (see #92)
     *
     * @param int $entityId
     * @return string|null
     */
    protected function _getDefaultScopeUrlKey(int $entityId): ?string
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('url_key')
            ->addIdFilter([$entityId])
            ->setStore(0);

        $product = $collection->getFirstItem();

        return $product->getId() ? $product->getUrlKey() : null;
    }

    /**
     * Get products collection
     *
     * @param array $productsFilter
     * @param int $storeId
     * @return Collection
     */
    protected function _getProductsCollection(array $productsFilter = [], int $storeId = 0): Collection
    {
        $productsCollection = $this->productCollectionFactory->create();

        $productsCollection->setStore($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path')
            // use limit to avoid an "eating" of a memory
            ->setPageSize($this->productsCollectionPageSize);

        // exclude "Not Visible Individually" products by default (see #130); --include-not-visible
        // opts back in, e.g. for configurable child products (see #131)
        if (!$this->regenerateOptions['includeNotVisible']) {
            $productsCollection->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]);
        }

        if (count($productsFilter) > 0) {
            $productsCollection->addIdFilter($productsFilter);
        }

        return $productsCollection;
    }
}
