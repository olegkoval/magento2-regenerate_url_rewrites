<?php
/**
 * Regenerate Url rewrites abstract class
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <contact@olegkoval.com>
 * @copyright 2018 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\UrlRewrite\Model\UrlPersistInterface as UrlPersist;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer;
use Magento\CatalogUrlRewrite\Observer\UrlRewriteHandlerFactory;
use Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool;
use Magento\CatalogUrlRewrite\Model\Map\DataCategoryUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\Map\DataProductUrlRewriteDatabaseMap;
use Magento\Catalog\Model\ResourceModel\Product\ActionFactory as ProductActionFactory;
use Magento\Framework\App\State as AppState;

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
     * @var \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGeneratorFactory
     */
    protected $_categoryUrlRewriteGeneratorFactory;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator
     */
    protected $_categoryUrlRewriteGenerator;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGeneratorFactory
     */
    protected $_productUrlRewriteGeneratorFactory;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator
     */
    protected $_productUrlRewriteGenerator;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer
     */
    protected $_urlRewriteBunchReplacer;

    /**
     * @var \Magento\CatalogUrlRewrite\Observer\UrlRewriteHandlerFactory
     */
    protected $_urlRewriteHandlerFactory;

    /**
     * @var \Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler
     */
    protected $_urlRewriteHandler;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool
     */
    protected $_databaseMapPool;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\ActionFactory
     */
    protected $_productActionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected $_productAction;

    /**
     * @var array
     */
    protected $_dataUrlRewriteClassNames;

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
     * Constructor
     * @param ResourceConnection                       $resource
     * @param CategoryCollectionFactory                $categoryCollectionFactory
     * @param ProductCollectionFactory                 $productCollectionFactory
     * @param UrlPersist\Proxy                         $urlPersist
     * @param CategoryHelper\Proxy                     $categoryHelper
     * @param CategoryUrlRewriteGeneratorFactory\Proxy $categoryUrlRewriteGeneratorFactory
     * @param ProductUrlRewriteGeneratorFactory\Proxy  $productUrlRewriteGeneratorFactory
     * @param UrlRewriteBunchReplacer\Proxy            $urlRewriteBunchReplacer
     * @param UrlRewriteHandlerFactory\Proxy           $urlRewriteHandlerFactory
     * @param DatabaseMapPool\Proxy                    $databaseMapPool
     * @param ProductActionFactory\Proxy               $productActionFactory
     * @param AppState\Proxy                           $appState
     */
    public function __construct(
        ResourceConnection $resource,
        CategoryCollectionFactory $categoryCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        UrlPersist\Proxy $urlPersist,
        CategoryHelper\Proxy $categoryHelper,
        CategoryUrlRewriteGeneratorFactory\Proxy $categoryUrlRewriteGeneratorFactory,
        ProductUrlRewriteGeneratorFactory\Proxy $productUrlRewriteGeneratorFactory,
        UrlRewriteBunchReplacer\Proxy $urlRewriteBunchReplacer,
        UrlRewriteHandlerFactory\Proxy $urlRewriteHandlerFactory,
        DatabaseMapPool\Proxy $databaseMapPool,
        ProductActionFactory\Proxy $productActionFactory,
        AppState\Proxy $appState
    ) {
        $this->_resource = $resource;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_urlPersist = $urlPersist;
        $this->_categoryHelper = $categoryHelper;
        $this->_categoryUrlRewriteGeneratorFactory = $categoryUrlRewriteGeneratorFactory;
        $this->_productUrlRewriteGeneratorFactory = $productUrlRewriteGeneratorFactory;
        $this->_urlRewriteBunchReplacer = $urlRewriteBunchReplacer;
        $this->_urlRewriteHandlerFactory = $urlRewriteHandlerFactory;
        $this->_databaseMapPool = $databaseMapPool;
        $this->_productActionFactory = $productActionFactory;
        $this->_dataUrlRewriteClassNames = [
            DataCategoryUrlRewriteDatabaseMap::class,
            DataProductUrlRewriteDatabaseMap::class
        ];
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
     * Regenerate all URL rewrites
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateAllUrlRewrites($storeId = 0);

    /**
     * Regenerate products range URL rewrites
     * @param  array $productsFilter
     * @param  integer $storeId
     * @return void
     */
    public function regenerateProductsRangeUrlRewrites($productsFilter = [], $storeId = 0)
    {
        $this->_output->writeln('To use this feature, please, purchase a Pro version.');
    }

    /**
     * Remove all current Url rewrites of categories/products from DB
     * Use a sql queries to speed up
     *
     * @param array $storesList
     * @param array $productsFilter
     * @return void
     */
    public function removeAllUrlRewrites($storesList, $productsFilter = []) {
        $whereSuffix = [
            "`entity_type` IN ('category', 'product')"
        ];

        if (count($storesList)) {
            $storeIds = implode(',', array_keys($storesList));
            $whereSuffix[] = "`store_id` IN ({$storeIds})";
        }

        $whereSuffix = implode(' AND ', $whereSuffix);
        $sql = "DELETE FROM {$this->_resource->getTableName('url_rewrite')} WHERE {$whereSuffix};";
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

    /**
     * @return Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator
     */
    protected function getCategoryUrlRewriteGenerator()
    {
        if (is_null($this->_categoryUrlRewriteGenerator)) {
            $this->_categoryUrlRewriteGenerator = $this->_categoryUrlRewriteGeneratorFactory->create();
        }

        return $this->_categoryUrlRewriteGenerator;
    }

    /**
     * @return Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator
     */
    protected function getProductUrlRewriteGenerator()
    {
        if (is_null($this->_productUrlRewriteGenerator)) {
            $this->_productUrlRewriteGenerator = $this->_productUrlRewriteGeneratorFactory->create();
        }

        return $this->_productUrlRewriteGenerator;
    }

    /**
     * @return Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected function getProductAction()
    {
        if (is_null($this->_productAction)) {
            $this->_productAction = $this->_productActionFactory->create();
        }

        return $this->_productAction;
    }

    /**
     * @return Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected function getUrlRewriteHandler()
    {
        if (is_null($this->_urlRewriteHandler)) {
            $this->_urlRewriteHandler = $this->_urlRewriteHandlerFactory->create();
        }

        return $this->_urlRewriteHandler;
    }

    /**
     * Resets used data maps to free up memory and temporary tables
     *
     * @param Category $category
     * @return void
     */
    protected function resetUrlRewritesDataMaps($category)
    {
        foreach ($this->_dataUrlRewriteClassNames as $className) {
            $this->_databaseMapPool->resetMap($className, $category->getEntityId());
        }
    }

    /**
     * Resets a products url_key and url_path
     *
     * @param Category $category
     * @return void
     */
    protected function resetCategoryProductsUrlKeyPath($category, $storeId)
    {
        $productCollection = $this->_productCollectionFactory->create();

        $productCollection->setStoreId($storeId);
        $productCollection->addAttributeToSelect('entity_id');
        $productCollection->addCategoriesFilter(['eq' => [$category->getEntityId()]]);
            

        $productCollection->setPageSize(100);
        $pageCount = $productCollection->getLastPageNumber();
        $currentPage = 1;

        while ($currentPage <= $pageCount) {
            $productCollection->setCurPage($currentPage);
            
            $this->getProductAction()->updateAttributes(
                $productCollection->getAllIds(),
                ['url_path' => null, 'url_key' => null],
                $storeId
            );

            $productCollection->clear();
            $currentPage++;
        }
    }

    /**
     * Display progress dots in console
     * @param  string  $errorMsg
     * @param  boolean $displayHint
     * @return void
     */
    protected function displayProgressDots(&$step)
    {
        $step++;
        $this->_output->write('.');
        // max 70 dots in log line
        if ($step > 69) {
            $this->_output->writeln('');
            $step = 0;
        }
    }
}
