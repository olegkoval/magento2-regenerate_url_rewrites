<?php
/**
 * Regenerate Url rewrites abstract class
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <contact@olegkoval.com>
 * @copyright 2017-2067 Oleg Koval
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
use Magento\Store\Model\StoreManagerInterface;
use OlegKoval\RegenerateUrlRewrites\Helper\Regenerate as RegenerateHelper;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateProductRewrites;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateCategoryRewrites;

abstract class RegenerateUrlRewritesAbstract extends Command
{
    const INPUT_KEY_STOREID                              = 'store-id';
    const INPUT_KEY_REGENERATE_ENTITY_TYPE               = 'entity-type';
    const INPUT_KEY_SAVE_REWRITES_HISTORY                = 'save-old-urls';
    const INPUT_KEY_NO_REGEN_URL_KEY                     = 'no-regen-url-key';
    const INPUT_KEY_NO_REINDEX                           = 'no-reindex';
    const INPUT_KEY_NO_PROGRESS                          = 'no-progress';
    const INPUT_KEY_NO_CACHE_FLUSH                       = 'no-cache-flush';
    const INPUT_KEY_NO_CACHE_CLEAN                       = 'no-cache-clean';
    const INPUT_KEY_CATEGORIES_RANGE                     = 'categories-range';
    const INPUT_KEY_PRODUCTS_RANGE                       = 'products-range';
    const INPUT_KEY_CATEGORY_ID                          = 'category-id';
    const INPUT_KEY_PRODUCT_ID                           = 'product-id';
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var RegenerateHelper
     */
    protected $helper;

    /**
     * @var RegenerateProductRewrites
     */
    protected $regenerateProductRewrites;

    /**
     * @var RegenerateCategoryRewrites
     */
    protected $regenerateCategoryRewrites;

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
     * RegenerateUrlRewritesAbstract constructor.
     * @param ResourceConnection $resource
     * @param AppState\Proxy $appState
     * @param StoreManagerInterface $storeManager
     * @param RegenerateHelper $helper
     * @param RegenerateCategoryRewrites $regenerateCategoryRewrites
     * @param RegenerateProductRewrites $regenerateProductRewrites
     */
    public function __construct(
        ResourceConnection $resource,
        AppState\Proxy $appState,
        StoreManagerInterface $storeManager,
        RegenerateHelper $helper,
        RegenerateCategoryRewrites $regenerateCategoryRewrites,
        RegenerateProductRewrites $regenerateProductRewrites
    ) {
        parent::__construct();

        $this->_resource = $resource;
        $this->_appState = $appState;
        $this->_storeManager = $storeManager;
        $this->helper = $helper;
        $this->regenerateCategoryRewrites = $regenerateCategoryRewrites;
        $this->regenerateProductRewrites = $regenerateProductRewrites;

        // set default config values
        $this->_commandOptions['entityType'] = 'product';
        $this->_commandOptions['saveOldUrls'] = false;
        $this->_commandOptions['runReindex'] = true;
        $this->_commandOptions['storesList'] = [];
        $this->_commandOptions['showProgress'] = true;
        $this->_commandOptions['runCacheClean'] = true;
        $this->_commandOptions['runCacheFlush'] = true;
        $this->_commandOptions['categoriesFilter'] = [];
        $this->_commandOptions['productsFilter'] = [];
        $this->_commandOptions['categoryId'] = null;
        $this->_commandOptions['productId'] = null;
        $this->_commandOptions['noRegenUrlKey'] = false;
    }

    /**
     * Display a support/donate information
     * @return void
     */
    protected function _showSupportMe()
    {
        $text = $this->helper->getSupportMeText();

        $this->_output->writeln('');
        $this->_output->writeln('----------------------------------------------------');
        foreach ($text as $line) {
            $this->_output->writeln($line);
        }
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
}
