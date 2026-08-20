<?php
/**
 * Regenerate Url Rewrites abstract class
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2017-2067 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Console\Command;

use Magento\Framework\Phrase;
use Symfony\Component\Console\Command\Command;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Config\Model\Config\Reader\Source\Deployed\SettingChecker;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Magento\Store\Model\StoreManagerInterface;
use OlegKoval\RegenerateUrlRewrites\Helper\Regenerate as RegenerateHelper;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateProductRewrites;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateCategoryRewrites;

abstract class RegenerateUrlRewritesAbstract extends Command
{
    const INPUT_KEY_STORE_ID = 'store-id';
    const INPUT_KEY_REGENERATE_ENTITY_TYPE = 'entity-type';
    const INPUT_KEY_SAVE_REWRITES_HISTORY = 'save-old-urls';
    const INPUT_KEY_REGEN_URL_KEY = 'regen-url-key';
    const INPUT_KEY_DELETE_ORPHANED_REWRITES = 'delete-orphaned-rewrites';
    const INPUT_KEY_SKIP_PRODUCTS = 'skip-products';
    const INPUT_KEY_SKIP_EXISTING = 'skip-existing';
    const INPUT_KEY_INCLUDE_NOT_VISIBLE = 'include-not-visible';
    const INPUT_KEY_ADD_SKU_TO_URL = 'add-sku-to-url';
    const INPUT_KEY_SET_PRODUCT_SUFFIX = 'set-product-suffix';
    const INPUT_KEY_SET_CATEGORY_SUFFIX = 'set-category-suffix';
    const INPUT_KEY_NO_REINDEX = 'no-reindex';
    const INPUT_KEY_NO_PROGRESS = 'no-progress';
    const INPUT_KEY_NO_CACHE_FLUSH = 'no-cache-flush';
    const INPUT_KEY_NO_CACHE_CLEAN = 'no-cache-clean';
    const INPUT_KEY_CATEGORIES_RANGE = 'categories-range';
    const INPUT_KEY_PRODUCTS_RANGE = 'products-range';
    const INPUT_KEY_CATEGORY_ID = 'category-id';
    const INPUT_KEY_PRODUCT_ID = 'product-id';
    const INPUT_KEY_REGENERATE_ENTITY_TYPE_PRODUCT = 'product';
    const INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY = 'category';

    /**
     * @var ResourceConnection
     */
    protected $_resource;

    /**
     * @var AppState $appState
     */
    protected $_appState;

    /**
     * @var StoreManagerInterface
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
     * @var ConfigFactory
     */
    protected $_configFactory;

    /**
     * @var SettingChecker
     */
    protected $_settingChecker;

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
     * RegenerateUrlRewritesAbstract constructor
     *
     * @param ResourceConnection $resource
     * @param AppState\Proxy $appState
     * @param StoreManagerInterface $storeManager
     * @param RegenerateHelper $helper
     * @param RegenerateCategoryRewrites $regenerateCategoryRewrites
     * @param RegenerateProductRewrites $regenerateProductRewrites
     * @param ConfigFactory $configFactory
     * @param SettingChecker $settingChecker
     */
    public function __construct(
        ResourceConnection         $resource,
        AppState\Proxy             $appState,
        StoreManagerInterface      $storeManager,
        RegenerateHelper           $helper,
        RegenerateCategoryRewrites $regenerateCategoryRewrites,
        RegenerateProductRewrites  $regenerateProductRewrites,
        ConfigFactory              $configFactory,
        SettingChecker             $settingChecker
    )
    {
        parent::__construct();

        $this->_resource = $resource;
        $this->_appState = $appState;
        $this->_storeManager = $storeManager;
        $this->helper = $helper;
        $this->regenerateCategoryRewrites = $regenerateCategoryRewrites;
        $this->regenerateProductRewrites = $regenerateProductRewrites;
        $this->_configFactory = $configFactory;
        $this->_settingChecker = $settingChecker;

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
        $this->_commandOptions['regenUrlKey'] = false;
        $this->_commandOptions['deleteOrphanedRewrites'] = false;
        $this->_commandOptions['skipProducts'] = false;
        $this->_commandOptions['skipExisting'] = false;
        $this->_commandOptions['includeNotVisible'] = false;
        $this->_commandOptions['addSkuToUrl'] = false;
        $this->_commandOptions['setProductSuffix'] = null;
        $this->_commandOptions['setCategorySuffix'] = null;
    }

    /**
     * Display a support/donate information
     *
     * @return void
     */
    protected function _showSupportMe(): void
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
     * Get a list of all stores id/code
     *
     * @return array
     */
    protected function _getAllStoreIds(): array
    {
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
     *
     * @param string $idsRange
     * @param string $type
     * @return array
     */
    protected function _generateIdsRangeArray(string $idsRange, string $type = 'product'): array
    {
        $result = $tmpIds = [];

        list($start, $end) = array_map('intval', explode('-', $idsRange, 2));

        if ($end < $start) $end = $start;

        for ($id = $start; $id <= $end; $id++) {
            $tmpIds[] = $id;
        }

        // get existed ID's from this range in entity DB table
        $tableName = $this->_resource->getTableName('catalog_' . $type . '_entity');
        $select = $this->_resource->getConnection()->select()
            ->from($tableName, ['entity_id'])
            ->where('entity_id IN (?)', $tmpIds)
            ->order('entity_id ASC');

        $queryResult = $this->_resource->getConnection()->fetchAll($select);

        foreach ($queryResult as $row) {
            $result[] = (int)$row['entity_id'];
        }

        // if not entity_id in this range - show error
        if (count($result) == 0) {
            $this->_addError(__("ERROR: %type ID's in this range not exists", ['type' => ucfirst($type)]));
        }

        return $result;
    }

    /**
     * @param Phrase|string $error
     * @return void
     */
    protected function _addError(Phrase|string $error): void
    {
        $this->_errors[] = $error;
    }

    /**
     * Collect console messages
     *
     * @param Phrase|string $msg
     * @return void
     */
    protected function _addConsoleMsg(Phrase|string $msg): void
    {
        if ($msg instanceof Phrase) {
            $msg = $msg->render();
        }

        $this->_consoleMsg[] = (string)$msg;
    }

    /**
     * Display all console messages
     *
     * @return void
     */
    protected function _displayConsoleMsg(): void
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
     * Run re-indexation
     * @return void
     */
    protected function _runReindexation(): void
    {
        if ($this->_commandOptions['runReindex']) {
            $this->_output->write('Reindexation...');
            shell_exec(escapeshellarg(PHP_BINARY) . ' bin/magento indexer:reindex');
            $this->_output->writeln(' Done');
        }
    }

    /**
     * Clear cache
     *
     * @return void
     */
    protected function _runClearCache(): void
    {
        if ($this->_commandOptions['runCacheClean'] || $this->_commandOptions['runCacheFlush']) {
            $this->_output->write('Cache refreshing...');
            if ($this->_commandOptions['runCacheClean']) {
                shell_exec(escapeshellarg(PHP_BINARY) . ' bin/magento cache:clean');
            }
            if ($this->_commandOptions['runCacheFlush']) {
                shell_exec(escapeshellarg(PHP_BINARY) . ' bin/magento cache:flush');
            }
            $this->_output->writeln(' Done');
            $this->_output->writeln('If you use some external cache mechanisms (e.g.: Redis, Varnish, etc.) - please, refresh this external cache.');
        }
    }

    /**
     * Save --set-product-suffix/--set-category-suffix values (if set) via the same write path
     * Magento's own `config:set` CLI command uses, so the Suffix backend model's validation and its
     * automatic swap of the suffix on existing url_rewrite rows both run (see #87).
     *
     * Any failure is collected into $_errors rather than thrown, so the caller can abort before running
     * any regeneration - but a failure on one suffix does not roll back an already-saved sibling suffix.
     *
     * @return void
     */
    protected function _setSeoUrlSuffixes(): void
    {
        if ($this->_commandOptions['setProductSuffix'] !== null) {
            $this->_trySetConfigValueForRun(
                'catalog/seo/product_url_suffix',
                $this->_commandOptions['setProductSuffix'],
                __('product URL suffix')
            );
        }

        if ($this->_commandOptions['setCategorySuffix'] !== null) {
            $this->_trySetConfigValueForRun(
                'catalog/seo/category_url_suffix',
                $this->_commandOptions['setCategorySuffix'],
                __('category URL suffix')
            );
        }
    }

    /**
     * @param string $configPath
     * @param string $value
     * @param Phrase $label
     * @return void
     */
    private function _trySetConfigValueForRun(string $configPath, string $value, Phrase $label): void
    {
        try {
            $this->_setConfigValueForRun($configPath, $value);
        } catch (\Exception $e) {
            $this->_addError(__('ERROR: could not save %label: %msg', ['label' => $label, 'msg' => $e->getMessage()]));
        }
    }

    /**
     * Write a config value to Default Config + every real store view (no --store-id given), or to just
     * the single requested store (--store-id given) - matching $_commandOptions['storesList'].
     *
     * @param string $configPath
     * @param string $value
     * @return void
     */
    private function _setConfigValueForRun(string $configPath, string $value): void
    {
        $isAllStoresRun = is_null($this->_input->getOption(self::INPUT_KEY_STORE_ID));

        if ($isAllStoresRun) {
            $this->_saveConfigValueForScope($configPath, $value, 'default', '');
        }

        foreach ($this->_commandOptions['storesList'] as $storeId => $storeCode) {
            // store_id 0 is the "admin" pseudo-store, not a real store-view scope
            if ((int)$storeId > 0) {
                $this->_saveConfigValueForScope($configPath, $value, 'stores', $storeCode);
            }
        }
    }

    /**
     * @param string $configPath
     * @param string $value
     * @param string $scope
     * @param string $scopeCode
     * @return void
     */
    private function _saveConfigValueForScope(string $configPath, string $value, string $scope, string $scopeCode): void
    {
        if ($this->_isConfigLocked($configPath, $scope, $scopeCode)) {
            $scopeDescriptor = $scopeCode !== '' ? "{$scope}/{$scopeCode}" : $scope;

            throw new \RuntimeException(
                (string)__(
                    'value is locked via app/etc/config.php (scope: %scopeDescriptor)',
                    ['scopeDescriptor' => $scopeDescriptor]
                )
            );
        }

        $config = $this->_configFactory->create(['data' => [
            'scope' => $scope,
            'scope_code' => $scopeCode,
        ]]);
        $config->setDataByPath($configPath, $value);
        $config->save();
    }

    /**
     * Check whether a config path is locked via app/etc/config.php (config-as-code) for the given
     * scope, using Magento's own SettingChecker - the exact same check Magento\Config\Model\Config::
     * save() performs internally (per field, falling back to the Default Config scope) before silently
     * skipping locked/read-only fields instead of raising an error. Without this pre-check, a locked
     * field would make the command look like it succeeded while writing nothing.
     *
     * @param string $configPath
     * @param string $scope
     * @param string $scopeCode
     * @return bool
     */
    private function _isConfigLocked(string $configPath, string $scope, string $scopeCode): bool
    {
        return $this->_settingChecker->isReadOnly($configPath, $scope, $scopeCode);
    }
}
