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
use Magento\Framework\App\State as AppState;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\ActionFactory as ProductActionFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer;
use Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool;
use Magento\CatalogUrlRewrite\Model\Map\DataCategoryUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\Map\DataProductUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Observer\UrlRewriteHandlerFactory;
use Magento\UrlRewrite\Model\UrlPersistInterface as UrlPersist;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

abstract class RegenerateUrlRewritesAbstract extends Command
{
    const INPUT_KEY_STOREID                              = 'store-id';
    const INPUT_KEY_REGENERATE_ENTITY_TYPE               = 'entity-type';
    const INPUT_KEY_SAVE_REWRITES_HISTORY                = 'save-old-urls';
    const INPUT_KEY_NO_REINDEX                           = 'no-reindex';
    const INPUT_KEY_NO_PROGRESS                          = 'no-progress';
    const INPUT_KEY_NO_CACHE_FLUSH                       = 'no-cache-flush';
    const INPUT_KEY_NO_CACHE_CLEAN                       = 'no-cache-clean';
    const INPUT_KEY_CATEGORIES_RANGE                     = 'categories-range';
    const INPUT_KEY_PRODUCTS_RANGE                       = 'products-range';
    const INPUT_KEY_CATEGORY_ID                          = 'category-id';
    const INPUT_KEY_PRODUCT_ID                           = 'product-id';
    const INPUT_KEY_CHECK_USE_CATEGORIES_FOR_PRODUCT_URL = 'check-use-category-in-product-url';

    const CONSOLE_LOG_MAX_DOTS_IN_LINE                   = 70;
    const INPUT_KEY_REGENERATE_ENTITY_TYPE_PRODUCT       = 'product';
    const INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY      = 'category';

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_resource;

    /**
     * @var \Magento\Framework\App\State $appState
     */
    protected $_appState;

    /**
     * @var \Magento\Catalog\Helper\Category
     */
    protected $_categoryHelper;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $_categoryCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\ActionFactory
     */
    protected $_productActionFactory;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator
     */
    protected $_categoryUrlPathGenerator;

    /**
     * @var Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator
     */
    protected $_productUrlPathGenerator;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGeneratorFactory
     */
    protected $_categoryUrlRewriteGeneratorFactory;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGeneratorFactory
     */
    protected $_productUrlRewriteGeneratorFactory;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer
     */
    protected $_urlRewriteBunchReplacer;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool
     */
    protected $_databaseMapPool;

    /**
     * @var \Magento\CatalogUrlRewrite\Observer\UrlRewriteHandlerFactory
     */
    protected $_urlRewriteHandlerFactory;

    /**
     * @var \Magento\UrlRewrite\Model\UrlPersistInterface
     */
    protected $_urlPersist;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator
     */
    protected $_categoryUrlRewriteGenerator;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator
     */
    protected $_productUrlRewriteGenerator;

    /**
     * @var \Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler
     */
    protected $_urlRewriteHandler;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected $_productAction;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var array
     */
    protected $_dataUrlRewriteClassNames;

    /**
     * @var integer
     */
    protected $_progress = 0;

    /**
     * @var integer
     */
    protected $_total = 0;

    /**
     * @var array
     */
    protected $_commandOptions = [];

    /**
     * @var array
     */
    protected $_errors = [];

    /**
     * @var array
     */
    protected $_consoleMsg = [];

    /**
     * Constructor
     * @param ResourceConnection $resource
     * @param AppState\Proxy $appState
     * @param CategoryHelper\Proxy $categoryHelper
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductActionFactory\Proxy $productActionFactory
     * @param CategoryUrlPathGenerator\Proxy $categoryUrlPathGenerator
     * @param CategoryUrlRewriteGeneratorFactory\Proxy $categoryUrlRewriteGeneratorFactory
     * @param ProductUrlPathGenerator\Proxy $productUrlPathGenerator
     * @param ProductUrlRewriteGeneratorFactory\Proxy $productUrlRewriteGeneratorFactory
     * @param UrlRewriteBunchReplacer\Proxy $urlRewriteBunchReplacer
     * @param UrlRewriteHandlerFactory\Proxy $urlRewriteHandlerFactory
     * @param DatabaseMapPool\Proxy $databaseMapPool
     * @param UrlPersist\Proxy $urlPersist
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ResourceConnection $resource,
        AppState\Proxy $appState,
        CategoryHelper\Proxy $categoryHelper,
        CategoryCollectionFactory $categoryCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        ProductActionFactory\Proxy $productActionFactory,
        CategoryUrlPathGenerator\Proxy $categoryUrlPathGenerator,
        CategoryUrlRewriteGeneratorFactory\Proxy $categoryUrlRewriteGeneratorFactory,
        ProductUrlPathGenerator\Proxy $productUrlPathGenerator,
        ProductUrlRewriteGeneratorFactory\Proxy $productUrlRewriteGeneratorFactory,
        UrlRewriteBunchReplacer\Proxy $urlRewriteBunchReplacer,
        UrlRewriteHandlerFactory\Proxy $urlRewriteHandlerFactory,
        DatabaseMapPool\Proxy $databaseMapPool,
        UrlPersist\Proxy $urlPersist,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_resource = $resource;
        $this->_appState = $appState;
        $this->_categoryHelper = $categoryHelper;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_productActionFactory = $productActionFactory;
        $this->_categoryUrlPathGenerator = $categoryUrlPathGenerator;
        $this->_categoryUrlRewriteGeneratorFactory = $categoryUrlRewriteGeneratorFactory;
        $this->_productUrlPathGenerator = $productUrlPathGenerator;
        $this->_productUrlRewriteGeneratorFactory = $productUrlRewriteGeneratorFactory;
        $this->_urlRewriteBunchReplacer = $urlRewriteBunchReplacer;
        $this->_urlRewriteHandlerFactory = $urlRewriteHandlerFactory;
        $this->_databaseMapPool = $databaseMapPool;
        $this->_urlPersist = $urlPersist;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;

        $this->_dataUrlRewriteClassNames = [
            DataCategoryUrlRewriteDatabaseMap::class,
            DataProductUrlRewriteDatabaseMap::class
        ];
        parent::__construct();

        // set default config values
        $this->_commandOptions['entityType'] = 'product';
        $this->_commandOptions['saveOldUrls'] = false;
        $this->_commandOptions['runReindex'] = true;
        $this->_commandOptions['protectOutOfMemory'] = false;
        $this->_commandOptions['storesList'] = [];
        $this->_commandOptions['showProgress'] = true;
        $this->_commandOptions['runCacheClean'] = true;
        $this->_commandOptions['runCacheFlush'] = true;
        $this->_commandOptions['categoriesFilter'] = [];
        $this->_commandOptions['productsFilter'] = [];
        $this->_commandOptions['categoryId'] = null;
        $this->_commandOptions['productId'] = null;
        $this->_commandOptions['checkUseCategoryInProductUrl'] = false;
    }

    /**
     * Regenerate all products URL rewrites
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateAllProductsUrlRewrites($storeId = 0);

    /**
     * Regenerate URL rewrites for a products range 
     * @param  array $productsFilter
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateProductsRangeUrlRewrites($productsFilter = [], $storeId = 0);

    /**
     * Regenerate URL rewrites for a specific product
     * @param  array $productId
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateSpecificProductUrlRewrites($productId, $storeId = 0);

    /**
     * Regenerate all categories (and categories products) URL rewrites
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateAllCategoriesUrlRewrites($storeId = 0);

    /**
     * Regenerate URL rewrites for a categories range 
     * @param  array $categoriesFilter
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateCategoriesRangeUrlRewrites($categoriesFilter = [], $storeId = 0);

    /**
     * Regenerate URL rewrites for a specific category + products from this category
     * @param  array $categoryId
     * @param  integer $storeId
     * @return void
     */
    abstract public function regenerateSpecificCategoryUrlRewrites($categoryId, $storeId = 0);


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
        $this->_output->writeln('----------------------------------------------------');
        $this->_output->writeln('');
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
     * Show a progress bar in the console
     *
     * @param  int $size optional size of the progress bar
     * @return void
     *
     */
    protected function _displayProgressBar($size = 70)
    {
        if (!$this->_commandOptions['showProgress']) {
            return;
        }

        // if we go over our bound, just ignore it
        if ($this->_progress > $this->_total) {
            return;
        }

        $perc = $this->_total ? (double)($this->_progress / $this->_total) : 1;
        $bar = floor($perc * $size);

        $status_bar = "\r[";
        $status_bar .= str_repeat('=', $bar);
        if ($bar < $size) {
            $status_bar .= '>';
            $status_bar .= str_repeat(' ', $size - $bar);
        } else {
            $status_bar .= '=';
        }

        $disp = number_format($perc * 100, 0);

        $status_bar .= "] {$disp}%  {$this->_progress}/{$this->_total}";

        echo $status_bar;
        flush();

        // when done, send a newline
        if ($this->_progress == $this->_total) {
            echo "\r\n";
        }
    }

    /**
     * Collect console messages
     * @param mixed $msg
     * @return void
     */
    protected function _addConsoleMsg($msg)
    {
        if ($msg instanceof \Magento\Framework\Phrase) {
            $msg = $msg->render();
        }

        $this->_consoleMsg[] = (string)$msg;
    }

    /**
     * Display all console messages
     * @return void
     */
    protected function _displayConsoleMsg()
    {
        if (count($this->_consoleMsg) > 0) {
            $this->_output->writeln('[CONSOLE MESSAGES]');
            foreach ($this->_consoleMsg as $msg) {
                $this->_output->writeln($msg);
            }
            $this->_output->writeln('[END OF CONSOLE MESSAGES]');
            $this->_output->writeln('');
            $this->_output->writeln('');
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
                        $this->_addConsoleMsg($y->getMessage() .' '. $type .' ID: '. $data['entity_id'] .'. Request path: '. $data['request_path']);
                    }
                }
            }
        }
    }

    /**
     * Run reindexation
     * @return void
     */
    protected function _runReindexation()
    {
        if ($this->_commandOptions['runReindex'] == true) {
            $this->_output->write('Reindexation...');
            shell_exec('php bin/magento indexer:reindex');
            $this->_output->writeln(' Done');
        }
    }

    /**
     * Clear cache
     * @return void
     */
    protected function _runClearCache()
    {
        if ($this->_commandOptions['runCacheClean'] || $this->_commandOptions['runCacheFlush']) {
            $this->_output->write('Cache refreshing...');
            if ($this->_commandOptions['runCacheClean']) {
                shell_exec('php bin/magento cache:clean');
            }
            if ($this->_commandOptions['runCacheFlush']) {
                shell_exec('php bin/magento cache:flush');
            }
            $this->_output->writeln(' Done');
            $this->_output->writeln('If you use some external cache mechanisms (e.g.: Redis, Varnish, etc.) - please, refresh this external cache.');
        }
    }

    /**
     * Clear request path
     * @param  string $requestPath
     * @return string
     */
    protected function _clearRequestPath($requestPath)
    {
        return str_replace(['//', './'], ['/', '/'], ltrim(ltrim($requestPath, '/'), '.'));
    }

    /**
     * Get "Use Categories Path for Product URLs" config option value
     * @param  mixed $storeId
     * @return boolean
     */
    protected function _getUseCategoriesPathForProductUrlsConfig($storeId = null)
    {
        return (bool) $this->_scopeConfig->getValue(
            'catalog/seo/product_use_categories',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );
    }
}
