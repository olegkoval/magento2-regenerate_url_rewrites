<?php
/**
 * Regenerate Url rewrites
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
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateUrlRewrites extends Command
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $_categoryCollectionFactory;

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
     * Constructor of RegenerateUrlRewrites
     *
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Catalog\Helper\Category $categoryHelper
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Framework\App\State $appState
    ) {
        $this->_resource = $resource;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
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
                    'storeId',
                    InputArgument::OPTIONAL,
                    '5'
                )
            ]);
    }

    /**
     * Regenerate Url Rewrites
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);
        $allStores = $this->getAllStoreIds();
        $storesList = [];
        $output->writeln('Regenerating of Url rewrites:');

        // get store Id (if was set)
        $storeId = $input->getArgument('storeId');

        // if store ID is not specified the re-generate for all stores
        if (is_null($storeId)) {
            $storesList = $allStores;
        }
        // we will re-generate URL only in this specific store (if it exists)
        elseif (strlen($storeId) && ctype_digit($storeId)) {
            if (isset($allStores[$storeId])) {
                $storesList = array(
                    $storeId => $allStores[$storeId]
                );
            } else {
                $this->displayError($output, 'ERROR: store with this ID not exists.');
                return;
            }
        }
        // disaply error if user set some incorrect value
        else {
            $this->displayError($output, 'ERROR: store ID should have a integer value.', true);
            return;
        }

        // remove all current url rewrites
        if (count($storesList) > 0) {
            $this->removeAllUrlRewrites($storesList);
        }
        
        // set area code if needed
        try {
            $areaCode = $this->_appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // if area code is not set then magento generate exception "LocalizedException"
            $this->_appState->setAreaCode('adminhtml');
        }

        foreach ($storesList as $storeId => $storeCode) {
            $output->writeln('');
            $output->write("[Store ID: {$storeId}, Store View code: {$storeCode}]:");
            $step = 0;

            // get categories collection
            $categories = $this->_categoryCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->setStore($storeId)
                ->addFieldToFilter('level', array('gt' => '1'))
                ->setOrder('level', 'DESC');

            foreach ($categories as $category) {
                try {
                    // we use save() action to start all before/after 'save' events (includes a regenerating of url rewrites)
                    // and we set orig "url_key" as empty to pass checks if data was updated
                    $category->setStoreId($storeId);
                    $category->setOrigData('url_key', '');
                    $category->save();

                    $step++;
                    $output->write('.');
                    if ($step > 19) {
                        $output->writeln('');
                        $step = 0;
                    }
                } catch (\Exception $e) {
                    // debugging
                    $output->writeln($e->getMessage());
                }
            }
        }

        $output->writeln('');
        $output->writeln('');
        $output->writeln('Reindexation...');
        shell_exec('php bin/magento indexer:reindex');

        $output->writeln('Cache refreshing...');
        shell_exec('php bin/magento cache:clean');
        shell_exec('php bin/magento cache:flush');
        $output->writeln('Finished');
    }

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
     * Display error message
     * @param  OutputInterface  $output
     * @param  string  $errorMsg
     * @param  boolean $displayHint
     * @return void
     */
    private function displayError(&$output, $errorMsg, $displayHint = false)
    {
        $output->writeln('');
        $output->writeln($errorMsg);

        if ($displayHint) {
            $output->writeln('Correct command is: bin/magento ok:urlrewrites:regenerate 19');
        }

        $output->writeln('Finished');
    }
}
