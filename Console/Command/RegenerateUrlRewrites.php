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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateUrlRewrites extends RegenerateUrlRewritesAbstract
{
    /**
     * @var null|Symfony\Component\Console\Output\OutputInterface
     */
    protected $_output = null;

    /**
     * Regenerate Url Rewrites
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);
        $this->_output = $output;
        $allStores = $this->getAllStoreIds();
        $storesList = $productsFilter = [];

        $this->_output->writeln('Regenerating of URL rewrites:');

        $options = $input->getOptions();
        if (isset($options[self::INPUT_KEY_SAVE_REWRITES_HISTORY]) && $options[self::INPUT_KEY_SAVE_REWRITES_HISTORY] === true) {
            $this->_saveOldUrls = true;
        }

        if (isset($options[self::INPUT_KEY_NO_REINDEX]) && $options[self::INPUT_KEY_NO_REINDEX] === true) {
            $this->_runReindex = false;
        }

        if (isset($options[self::INPUT_KEY_PRODUCTS_RANGE])) {
            $productsFilter = $this->generateProductsIdsRange($options[self::INPUT_KEY_PRODUCTS_RANGE]);
        }

        // get store Id (if was set)
        $storeId = $input->getArgument(self::INPUT_KEY_STOREID);
        if (is_null($storeId)) {
            $storeId = $input->getOption(self::INPUT_KEY_STOREID);
        }

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
                $this->displayError('ERROR: store with this ID not exists.');
                return;
            }
        }
        // disaply error if user set some incorrect value
        else {
            $this->displayError('ERROR: store ID should have a integer value.', true);
            return;
        }

        // remove all current url rewrites
        if (count($storesList) > 0 && !$this->_saveOldUrls) {
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
            $this->_output->writeln('');
            $this->_output->writeln("[Store ID: {$storeId}, Store View code: {$storeCode}]:");

            if (count($productsFilter) > 0) {
                $this->regenerateProductsRangeUrlRewrites($productsFilter, $storeId);
            } else {
                $this->regenerateUrlRewrites($storeId);
            }
        }

        $this->_output->writeln('');
        $this->_output->writeln('');

        if ($this->_runReindex == true) {
            $this->_output->writeln('Reindexation...');
            shell_exec('php bin/magento indexer:reindex');
        }

        $this->_output->writeln('Cache refreshing...');
        shell_exec('php bin/magento cache:clean');
        shell_exec('php bin/magento cache:flush');
        $this->_output->writeln('Finished');
    }

    /**
     * @see parent::regenerateUrlRewrites()
     */
    public function regenerateUrlRewrites($storeId = 0)
    {
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
                $category->setOrigData('url_key', null);
                $category->setData('url_key', null);
                if ($this->_saveOldUrls) {
                    $category->setData('save_rewrites_history', true);
                }
                $category->save();

                $this->displayProgressDots($step);
            } catch (\Exception $e) {
                // debugging
                $this->_output->writeln($e->getMessage());
            }
        }
    }

    /**
     * @see parent::regenerateProductsRangeUrlRewrites()
     */
    public function regenerateProductsRangeUrlRewrites($productsFilter = [], $storeId = 0)
    {
        //get products collection
        $products = $this->_productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->setStore($storeId)
            ->addAttributeToFilter('entity_id', array('in' => $productsFilter));

        foreach ($products as $product) {
            try {
                // we use save() action to start all before/after 'save' events (includes a regenerating of url rewrites)
                // and we set orig "url_key" as empty to pass checks if data was updated
                $product->setStoreId($storeId);
                $product->setOrigData('url_key', '');
                if ($this->_saveOldUrls) {
                    $product->setData('save_rewrites_history', true);
                }
                $product->save();
                // $this->_urlPersist->replace($this->_productUrlRewriteGenerator->generate($product));

                $this->displayProgressDots($step);
            } catch (\Exception $e) {
                // debugging
                $this->_output->writeln($e->getMessage());
            }
        }
    }

    /**
     * Display error message
     * @param  string  $errorMsg
     * @param  boolean $displayHint
     * @return void
     */
    private function displayError($errorMsg, $displayHint = false)
    {
        $this->_output->writeln('');
        $this->_output->writeln($errorMsg);

        if ($displayHint) {
            $this->_output->writeln('Correct command is: bin/magento ok:urlrewrites:regenerate 19');
        }

        $this->_output->writeln('Finished');
    }

    /**
     * Display progress dots in console
     * @param  string  $errorMsg
     * @param  boolean $displayHint
     * @return void
     */
    private function displayProgressDots(&$step)
    {
        $step++;
        $this->_output->write('.');
        // max 30 dots in log line
        if ($step > 29) {
            $this->_output->writeln('');
            $step = 0;
        }
    }
}
