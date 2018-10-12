<?php
/**
 * Regenerate Url rewrites
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <contact@olegkoval.com>
 * @copyright 2018 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Console\Command;

if (class_exists('\OlegKoval\RegenerateUrlRewrites\Console\Command\RegenerateUrlRewritesPro')) {
    abstract class RegenerateUrlRewritesLayer extends RegenerateUrlRewritesPro {}
} else {
    abstract class RegenerateUrlRewritesLayer extends RegenerateUrlRewritesAbstract {}
}

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateUrlRewrites extends RegenerateUrlRewritesLayer
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
        $this->_showSupportMe();

        $options = $input->getOptions();
        if (isset($options[self::INPUT_KEY_SAVE_REWRITES_HISTORY]) && $options[self::INPUT_KEY_SAVE_REWRITES_HISTORY] === true) {
            $this->_saveOldUrls = true;
        }

        if (isset($options[self::INPUT_KEY_NO_REINDEX]) && $options[self::INPUT_KEY_NO_REINDEX] === true) {
            $this->_runReindex = false;
        }

        if (isset($options[self::INPUT_KEY_NO_CACHE_CLEAN]) && $options[self::INPUT_KEY_NO_CACHE_CLEAN] === true) {
            $this->_runCacheClean = false;
        }

        if (isset($options[self::INPUT_KEY_NO_CACHE_FLUSH]) && $options[self::INPUT_KEY_NO_CACHE_FLUSH] === true) {
            $this->_runCacheFlush = false;
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
            $this->removeAllUrlRewrites($storesList, $productsFilter);
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
                $this->regenerateAllUrlRewrites($storeId);
            }
        }

        $this->_output->writeln('');
        $this->_output->writeln('');

        if ($this->_runReindex) {
            $this->_output->writeln('Reindexation...');
            shell_exec('php bin/magento indexer:reindex');
        }
        if ($this->_runCacheClean || $this->_runCacheFlush) {
            $this->_output->writeln('Cache refreshing...');
            if ($this->_runCacheClean) {
                shell_exec('php bin/magento cache:clean');
            }
            if ($this->_runCacheFlush) {
                shell_exec('php bin/magento cache:flush');
            }
            $this->_output->writeln('If you use some external cache mechanisms (e.g.: Redis, Varnish, etc.) - please, refresh this external cache.');
        }
        $this->_output->writeln('Finished');
    }

    /**
     * @see parent::regenerateAllUrlRewrites()
     */
    public function regenerateAllUrlRewrites($storeId = 0)
    {
        $this->_step = 0;

        // get categories collection
        $categories = $this->_categoryCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->setStore($storeId)
            ->addAttributeToFilter('is_active','1')
            ->addFieldToFilter('level', array('gt' => '1'))
            ->setOrder('level', 'DESC');

        foreach ($categories as $category) {
            try {
                if ($this->_saveOldUrls) {
                    $category->setData('save_rewrites_history', true);
                }
                $category->setData('url_path', null)->setData('url_key', null)->setStoreId($storeId)->save();

                $this->resetCategoryProductsUrlKeyPath($category, $storeId);
            } catch (\Exception $e) {
                // debugging
                $this->_displayExceptionMsg('Exception #1: '. $e->getMessage() .' Category ID: '. $category->getId());
            }

            $this->_regenerateCategoryUrlRewrites($category);

            try {
                //frees memory for maps that are self-initialized in multiple classes that were called by the generators
                $this->resetUrlRewritesDataMaps($category);

                $this->_displayProgressDots();
            } catch (\Exception $e) {
                // debugging
                $this->_displayExceptionMsg('Exception #3: '. $e->getMessage() .' Category ID: '. $category->getId());
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
}
