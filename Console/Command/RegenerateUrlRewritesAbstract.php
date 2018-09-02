<?php
/**
 * Regenerate Url rewrites abstract class
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <contact@olegkoval.com>
 * @copyright 2017 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

abstract class RegenerateUrlRewritesAbstract extends Command
{
    const INPUT_KEY_STOREID = 'storeId';
    const INPUT_KEY_SAVE_REWRITES_HISTORY = 'save-old-urls';
    const INPUT_KEY_NO_REINDEX = 'no-reindex';
    const INPUT_KEY_PRODUCTS_RANGE = 'products-range';

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $_categoryCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator
     */
    protected $_productUrlRewriteGenerator;

    /**
     * @var \Magento\UrlRewrite\Model\UrlPersistInterface
     */
    protected $_urlPersist;

    /**
     * @var \Magento\Catalog\Helper\Category
     */
    protected $_categoryHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\App\State $appState
     */
    protected $_appState;

    /**
     * @var boolean
     */
    protected $_saveOldUrls = false;

    /**
     * @var boolean
     */
    protected $_runReindex = true;

    /**
     * Constructor of RegenerateUrlRewrites
     *
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Catalog\Helper\Category $categoryHelper
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Framework\App\State $appState
    ) {
        $this->_resource = $resource;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->_urlPersist = $urlPersist;
        $this->_categoryHelper = $categoryHelper;
        $this->_appState = $appState;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ok:urlrewrites:regenerate')
            ->setDescription('Regenerate Url rewrites of products/categories')
            ->setDefinition([
                new InputArgument(
                    self::INPUT_KEY_STOREID,
                    InputArgument::OPTIONAL,
                    'Specific store id'
                ),
                new InputOption(
                    self::INPUT_KEY_STOREID,
                    null,
                    InputArgument::OPTIONAL,
                    'Specific store id'
                ),
                new InputOption(
                    self::INPUT_KEY_SAVE_REWRITES_HISTORY,
                    null,
                    InputOption::VALUE_NONE,
                    'Save current URL Rewrites'
                ),
                new InputOption(
                    self::INPUT_KEY_NO_REINDEX,
                    null,
                    InputOption::VALUE_NONE,
                    'Do not run reindex when URL rewrites are generated.'
                ),
                new InputOption(
                    self::INPUT_KEY_PRODUCTS_RANGE,
                    null,
                    InputArgument::OPTIONAL,
                    'Products range, e.g.: 101-152'
                )
            ]);
    }

    /**
     * Regenerate URL rewrites
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateUrlRewrites($storeId = 0);

    /**
     * Regenerate products range URL rewrites
     * @param  array $productsFilter
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateProductsRangeUrlRewrites($productsFilter = [], $storeId = 0);

    /**
     * Remove all current Url rewrites of categories/products from DB
     * Use a sql queries to speed up
     *
     * @param array $storesList
     * @return void
     */
    public function removeAllUrlRewrites($storesList) {
        $storeIds = implode(',', array_keys($storesList));
        $sql = "DELETE FROM {$this->_resource->getTableName('url_rewrite')} WHERE `entity_type` IN ('category', 'product') AND `store_id` IN ({$storeIds});";
        $this->_resource->getConnection()->query($sql);

        $sql = "DELETE FROM {$this->_resource->getTableName('catalog_url_rewrite_product_category')} WHERE `url_rewrite_id` NOT IN (
            SELECT `url_rewrite_id` FROM {$this->_resource->getTableName('url_rewrite')}
        );";
        $this->_resource->getConnection()->query($sql);
    }

    /**
     * Get list of all stores id/code
     *
     * @return array
     */
    public function getAllStoreIds() {
        $result = [];

        $sql = $this->_resource->getConnection()->select()
                    ->from($this->_resource->getTableName('store'), array('store_id', 'code'))
                    ->order('store_id', 'ASC');

        $queryResult = $this->_resource->getConnection()->fetchAll($sql);

        foreach ($queryResult as $row) {
            $result[(int)$row['store_id']] = $row['code'];
        }

        return $result;
    }

    /**
     * Generate range of products ID's
     * @param  string $productsRange
     * @return array
     */
    public function generateProductsIdsRange($productsRange)
    {
        $result = [];

        list($start, $end) = explode('-', $productsRange, 2);

        if ($start > 0 && $end > 0 && $end >= $start) {
            for ($productId = $start; $productId <= $end; $productId++) {
                $result[] = $productId;
            }
        }

        return $result;
    }
}
