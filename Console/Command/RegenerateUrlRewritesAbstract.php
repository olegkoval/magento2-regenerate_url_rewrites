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
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;

abstract class RegenerateUrlRewritesAbstract extends Command
{
    const INPUT_KEY_STOREID               = 'storeId';
    const INPUT_KEY_SAVE_REWRITES_HISTORY = 'save-old-urls';
    const INPUT_KEY_NO_REINDEX            = 'no-reindex';
    const INPUT_KEY_NO_PROGRESS           = 'no-progress';
    const INPUT_KEY_NO_CACHE_FLUSH        = 'no-cache-flush';
    const INPUT_KEY_NO_CACHE_CLEAN        = 'no-cache-clean';
    const INPUT_KEY_NO_CLEAN_URL_KEY      = 'no-clean-url-key';
    const INPUT_KEY_CATEGORIES_RANGE      = 'categories-range';
    const INPUT_KEY_PRODUCTS_RANGE        = 'products-range';
    const INPUT_KEY_CATEGORY_ID           = 'category-id';
    const INPUT_KEY_PRODUCT_ID            = 'product-id';
    const CONSOLE_LOG_MAX_DOTS_IN_LINE    = 70;

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
     * @var Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator
     */
    protected $_categoryUrlPathGenerator;

    /**
     * @var integer
     */
    protected $_step = 0;

    /**
     * @var integer
     */
    protected $_collectionPageSize = 100;

    /**
     * @var array
     */
    protected $_commandOptions = [];

    /**
     * @var array
     */
    protected $_errors = [];

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
        AppState\Proxy $appState,
        CategoryUrlPathGenerator\Proxy $categoryUrlPathGenerator
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
        $this->_categoryUrlPathGenerator = $categoryUrlPathGenerator;
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
                    self::INPUT_KEY_NO_PROGRESS,
                    null,
                    InputOption::VALUE_NONE,
                    'Do not show progress indicator.'
                ),
                new InputOption(
                    self::INPUT_KEY_NO_CACHE_FLUSH,
                    null,
                    InputOption::VALUE_NONE,
                    'Do not run cache:flush when URL rewrites are generated.'
                ),
                new InputOption(
                    self::INPUT_KEY_NO_CACHE_CLEAN,
                    null,
                    InputOption::VALUE_NONE,
                    'Do not run cache:clean when URL rewrites are generated.'
                ),
                new InputOption(
                    self::INPUT_KEY_NO_CLEAN_URL_KEY,
                    null,
                    InputOption::VALUE_NONE,
                    'Do not clean current products url_key values.'
                ),
                new InputOption(
                    self::INPUT_KEY_CATEGORIES_RANGE,
                    null,
                    InputArgument::OPTIONAL,
                    'Categories ID range, e.g.: 15-40'
                ),
                new InputOption(
                    self::INPUT_KEY_PRODUCTS_RANGE,
                    null,
                    InputArgument::OPTIONAL,
                    'Products ID range, e.g.: 101-152'
                ),
                new InputOption(
                    self::INPUT_KEY_CATEGORY_ID,
                    null,
                    InputArgument::OPTIONAL,
                    'Specific category ID, e.g.: 123'
                ),
                new InputOption(
                    self::INPUT_KEY_PRODUCT_ID,
                    null,
                    InputArgument::OPTIONAL,
                    'Specific product ID, e.g.: 107'
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
     * Get command options
     * @return void
     */
    abstract public function getCommandOptions();

    /**
     * Regenerate URL rewrites for a categories range 
     * @param  array $categoriesFilter
     * @param  integer $storeId
     * @return void
     */
    public function regenerateCategoriesRangeUrlRewrites($categoriesFilter = [], $storeId = 0)
    {
        $this->_output->writeln('To use this feature, please, purchase a Pro version.');
    }

    /**
     * Regenerate URL rewrites for a products range 
     * @param  array $productsFilter
     * @param  integer $storeId
     * @return void
     */
    public function regenerateProductsRangeUrlRewrites($productsFilter = [], $storeId = 0)
    {
        $this->_output->writeln('To use this feature, please, purchase a Pro version.');
    }

    /**
     * Regenerate URL rewrites for a specific category + products from this category
     * @param  array $categoryId
     * @param  integer $storeId
     * @return void
     */
    public function regenerateSpecificCategoryUrlRewrites($categoryId, $storeId = 0)
    {
        $this->_output->writeln('To use this feature, please, purchase a Pro version.');
    }

    /**
     * Regenerate URL rewrites for a specific product
     * @param  array $productId
     * @param  integer $storeId
     * @return void
     */
    public function regenerateSpecificProductUrlRewrites($productId, $storeId = 0)
    {
        $this->_output->writeln('To use this feature, please, purchase a Pro version.');
    }

    /**
     * Display a support/donate information
     * @return void
     */
    protected function _showSupportMe()
    {
        $this->_output->writeln('');
        $this->_output->writeln('----------------------------------------------------');
        $this->_output->writeln('Please, support me on:');
        $this->_output->writeln('https://www.patreon.com/olegkoval');
        $this->_output->writeln('https://api.fondy.eu/s/ghYyR');
        $this->_output->writeln('----------------------------------------------------');
        $this->_output->writeln('');
    }

    /**
     * Remove all current Url rewrites of categories/products from DB
     * Use a sql queries to speed up
     *
     * @param array $storesList
     * @param array $productsFilter
     * @return void
     */
    protected function _removeAllUrlRewrites($storesList, $productsFilter = []) {
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
    protected function _getAllStoreIds() {
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
     * Generate range of ID's
     * @param  string $idsRange
     * @param  string $type
     * @return array
     */
    protected function _generateIdsRangeArray($idsRange, $type = 'product')
    {
        $result = $tmpIds = [];

        list($start, $end) = array_map('intval', explode('-', $idsRange, 2));

        if ($end < $start) $end = $start;

        for ($id = $start; $id <= $end; $id++) {
            $tmpIds[] = $id;
        }

        // get existed Id's from this range in entity DB table
        $tableName = $this->_resource->getTableName('catalog_'. $type .'_entity');
        $ids = implode(', ', $tmpIds);
        $sql = "SELECT entity_id FROM {$tableName} WHERE entity_id IN ({$ids}) ORDER BY entity_id";

        $queryResult = $this->_resource->getConnection()->fetchAll($sql);

        foreach ($queryResult as $row) {
            $result[] = (int)$row['entity_id'];
        }

        // if not entity_id in this range - show error
        if (count($result) == 0) {
            $this->_errors[] = __("ERROR: %type ID's in this range not exists", ['type' => ucfirst($type)]);
        }

        return $result;
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

    /**
     * @return Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator
     */
    protected function _getCategoryUrlRewriteGenerator()
    {
        if (is_null($this->_categoryUrlRewriteGenerator)) {
            $this->_categoryUrlRewriteGenerator = $this->_categoryUrlRewriteGeneratorFactory->create();
        }

        return $this->_categoryUrlRewriteGenerator;
    }

    /**
     * @return Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator
     */
    protected function _getProductUrlRewriteGenerator()
    {
        if (is_null($this->_productUrlRewriteGenerator)) {
            $this->_productUrlRewriteGenerator = $this->_productUrlRewriteGeneratorFactory->create();
        }

        return $this->_productUrlRewriteGenerator;
    }

    /**
     * @return Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected function _getProductAction()
    {
        if (is_null($this->_productAction)) {
            $this->_productAction = $this->_productActionFactory->create();
        }

        return $this->_productAction;
    }

    /**
     * @return Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected function _getUrlRewriteHandler()
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
        // get categories collection
        $categoriesCollection = $this->_categoryCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('level', array('gt' => '1'))
            ->setOrder('level', 'DESC')
            // use limit to avoid a "eating" of a memory
            ->setPageSize($this->_collectionPageSize);

        $rootCategoryId = $this->_getStoreRootCategoryId($storeId);
        if ($rootCategoryId > 0) {
            // we use this filter instead of "->setStore()" - because "setStore()" is not working now (another Magento issue)
            $categoriesCollection->addAttributeToFilter('path', array('like' => "1/{$rootCategoryId}/%"));
        }

        if (count($categoriesFilter) > 0) {
            $categoriesCollection->addAttributeToFilter('entity_id', array('in' => $categoriesFilter));
        }

        return $categoriesCollection;
    }

    /**
     * Resets a products url_key and url_path
     *
     * @param Category $category
     * @return void
     */
    protected function _resetCategoryProductsUrlKeyPath($category, $storeId)
    {
        if (!$this->_commandOptions['cleanUrlKey']) {
            return;
        }
        $productCollection = $this->_productCollectionFactory->create();
        $productCollection->setStoreId($storeId);
        $productCollection->addAttributeToSelect('entity_id');
        $productCollection->addAttributeToSelect('name');
        $productCollection->addCategoriesFilter(['eq' => [$category->getEntityId()]]);
        $productCollection->setPageSize($this->_collectionPageSize);

        $pageCount = $productCollection->getLastPageNumber();
        $currentPage = 1;

        while ($currentPage <= $pageCount) {
            $productCollection->setCurPage($currentPage);
            
            foreach ($productCollection as $product) {
                $this->_getProductAction()->updateAttributes(
                    [$product->getId()],
                    ['url_path' => null, 'url_key' => $product->formatUrlKey($product->getName())],
                    $storeId
                );
            }

            $productCollection->clear();
            $currentPage++;
        }
    }

    /**
     * Display progress dots in console
     * @return void
     */
    protected function _displayProgressDots()
    {
        if (!$this->_commandOptions['showProgress']) {
            return;
        }
        $this->_step++;
        $this->_output->write('.');

        if ($this->_step > self::CONSOLE_LOG_MAX_DOTS_IN_LINE) {
            $this->_output->writeln('');
            $this->_step = 0;
        }
    }

    /**
     * Display message in console
     * @param  string $msg
     * @return void
     */
    protected function _displayConsoleMsg($msg)
    {
        if ($msg instanceof \Magento\Framework\Phrase) {
            $msg = $msg->render();
        }
        $this->_output->writeln('');
        $this->_output->writeln($msg);
        $this->_step = 0;
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
            $category->setUrlPath($this->_categoryUrlPathGenerator->getUrlPath($category));
            $category->getResource()->saveAttribute($category, 'url_path');
            $category->setUrlKey($category->formatUrlKey($category->getName()));
            $category->getResource()->saveAttribute($category, 'url_key');

            $this->_resetCategoryProductsUrlKeyPath($category, $storeId);

            $this->_regenerateCategoryUrlRewrites($category, $storeId);

            //frees memory for maps that are self-initialized in multiple classes that were called by the generators
            $this->_resetUrlRewritesDataMaps($category);

            $this->_displayProgressDots();
        } catch (\Exception $e) {
            // debugging
            $this->_displayConsoleMsg('Exception: '. $e->getMessage() .' Category ID: '. $category->getId());
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

            $productUrlRewriteResult = $this->_getUrlRewriteHandler()->generateProductUrlRewrites($category);

            // fix for double slashes issue
            foreach ($productUrlRewriteResult as &$urlRewrite) {
                $urlRewrite->setRequestPath(trim($urlRewrite->getRequestPath(), '/'));
            }

            $this->_doBunchReplaceUrlRewrites($productUrlRewriteResult, 'Product');
        } catch (\Exception $e) {
            // debugging
            $this->_displayConsoleMsg('Exception: '. $e->getMessage() .' Category ID: '. $category->getId());
        }
    }

    /**
     * Do a bunch replace of url rewrites
     * @param  array  $urlRewrites
     * @param  string $type
     * @return void
     */
    protected function _doBunchReplaceUrlRewrites($urlRewrites = array(), $type = 'Category')
    {
        try {
            $this->_urlRewriteBunchReplacer->doBunchReplace($urlRewrites);
        } catch (\Exception $e) {
            if (
                $e instanceof \Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException
                || strpos($e->getMessage(), 'Duplicate entry') !== false
            ) {
                foreach ($urlRewrites as $singleUrlRewrite) {
                    try {
                        $this->_urlRewriteBunchReplacer->doBunchReplace(array($singleUrlRewrite));
                    } catch (\Exception $y) {
                        // debugging
                        $data = $singleUrlRewrite->toArray();
                        $this->_displayConsoleMsg($y->getMessage() .' '. $type .' ID: '. $data['entity_id'] .'. Request path: '. $data['request_path']);
                    }
                }
            }
        }
    }
}
