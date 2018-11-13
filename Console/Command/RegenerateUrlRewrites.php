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
     * @var null|Symfony\Component\Console\Input\InputInterface
     */
    protected $_input = null;

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
        $this->_input = $input;
        $this->_output = $output;

        $this->_output->writeln('Regenerating of URL rewrites:');
        $this->_showSupportMe();
        $this->getCommandOptions();

        if (count($this->_errors) > 0) {
            foreach ($this->_errors as $error) {
                $this->_displayConsoleMsg($error);
            }
            return;
        }

        // set area code if needed
        try {
            $areaCode = $this->_appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // if area code is not set then magento generate exception "LocalizedException"
            $this->_appState->setAreaCode('adminhtml');
        }

        // remove current url rewrites
        if (count($this->_commandOptions['storesList']) > 0 && !$this->_commandOptions['saveOldUrls']) {
            $this->_removeAllUrlRewrites($this->_commandOptions['storesList'], $this->_commandOptions['productsFilter']);
        }

        foreach ($this->_commandOptions['storesList'] as $storeId => $storeCode) {
            $this->_output->writeln('');
            $this->_output->writeln("[Store ID: {$storeId}, Store View code: {$storeCode}]:");

            if (count($this->_commandOptions['categoriesFilter']) > 0) {
                $this->regenerateCategoriesRangeUrlRewrites(
                    $this->_commandOptions['categoriesFilter'],
                    $storeId
                );
            } elseif (count($this->_commandOptions['productsFilter']) > 0) {
                $this->regenerateProductsRangeUrlRewrites(
                    $this->_commandOptions['productsFilter'],
                    $storeId
                );
            } elseif (!empty($this->_commandOptions['categoryId'])) {
                $this->regenerateSpecificCategoryUrlRewrites(
                    $this->_commandOptions['categoryId'],
                    $storeId
                );
            } elseif (!empty($this->_commandOptions['productId'])) {
                $this->regenerateSpecificProductUrlRewrites(
                    $this->_commandOptions['productId'],
                    $storeId
                );
            } else {
                $this->regenerateAllUrlRewrites($storeId);
            }
        }

        $this->_output->writeln('');
        $this->_output->writeln('');

        if ($this->_commandOptions['runReindex'] == true) {
            $this->_output->write('Reindexation...');
            shell_exec('php bin/magento indexer:reindex');
            $this->_output->writeln(' Done');
        }

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

        $this->_showSupportMe();
        $this->_output->writeln('Finished');
    }

    /**
     * @see parent::regenerateAllUrlRewrites()
     */
    public function regenerateAllUrlRewrites($storeId = 0)
    {
        $this->_step = 0;

        // get categories collection
        $categories = $this->_getCategoriesCollection($storeId);

        $pageCount = $categories->getLastPageNumber();
        $currentPage = 1;
        while ($currentPage <= $pageCount) {
            $categories->setCurPage($currentPage);

            foreach ($categories as $category) {
                $this->_categoryProcess($category, $storeId);
            }

            $categories->clear();
            $currentPage++;
        }
    }

    /**
     * @see parent::getCommandOptions()
     */
    public function getCommandOptions()
    {
        $options = $this->_input->getOptions();
        $allStores = $this->_getAllStoreIds();

        // default values
        $this->_commandOptions['saveOldUrls'] = false;
        $this->_commandOptions['runReindex'] = true;
        $this->_commandOptions['protectOutOfMemory'] = false;
        $this->_commandOptions['storesList'] = [];
        $this->_commandOptions['showProgress'] = true;
        $this->_commandOptions['runCacheClean'] = true;
        $this->_commandOptions['runCacheFlush'] = true;
        $this->_commandOptions['cleanUrlKey'] = true;
        $this->_commandOptions['categoriesFilter'] = [];
        $this->_commandOptions['productsFilter'] = [];
        $this->_commandOptions['categoryId'] = null;
        $this->_commandOptions['productId'] = null;
        $distinctOptionsUsed = 0;

        if (isset($options[self::INPUT_KEY_SAVE_REWRITES_HISTORY]) && $options[self::INPUT_KEY_SAVE_REWRITES_HISTORY] === true) {
            $this->_commandOptions['saveOldUrls'] = true;
        }

        if (isset($options[self::INPUT_KEY_NO_REINDEX]) && $options[self::INPUT_KEY_NO_REINDEX] === true) {
            $this->_commandOptions['runReindex'] = false;
        }

        if (isset($options[self::INPUT_KEY_NO_PROGRESS]) && $options[self::INPUT_KEY_NO_PROGRESS] === true) {
            $this->_commandOptions['showProgress'] = false;
        }

        if (isset($options[self::INPUT_KEY_NO_CACHE_CLEAN]) && $options[self::INPUT_KEY_NO_CACHE_CLEAN] === true) {
            $this->_commandOptions['runCacheClean'] = false;
        }

        if (isset($options[self::INPUT_KEY_NO_CACHE_FLUSH]) && $options[self::INPUT_KEY_NO_CACHE_FLUSH] === true) {
            $this->_commandOptions['runCacheFlush'] = false;
        }

        if (isset($options[self::INPUT_KEY_NO_CLEAN_URL_KEY]) && $options[self::INPUT_KEY_NO_CLEAN_URL_KEY] === true) {
            $this->_commandOptions['cleanUrlKey'] = false;
        }

        if (isset($options[self::INPUT_KEY_CATEGORIES_RANGE])) {
            $this->_commandOptions['categoriesFilter'] = $this->_generateIdsRangeArray(
                $options[self::INPUT_KEY_CATEGORIES_RANGE],
                'category'
            );
            $distinctOptionsUsed++;
        }

        if (isset($options[self::INPUT_KEY_PRODUCTS_RANGE])) {
            $this->_commandOptions['productsFilter'] = $this->_generateIdsRangeArray(
                $options[self::INPUT_KEY_PRODUCTS_RANGE],
                'product'
            );
            $distinctOptionsUsed++;
        }

        if (isset($options[self::INPUT_KEY_CATEGORY_ID])) {
            $this->_commandOptions['categoryId'] = (int)$options[self::INPUT_KEY_CATEGORY_ID];

            if ($this->_commandOptions['categoryId'] == 0) {
                $this->_errors[] = __('ERROR: category ID should be greater than 0.');
            } else {
                $distinctOptionsUsed++;
            }
        }

        if (isset($options[self::INPUT_KEY_PRODUCT_ID])) {
            $this->_commandOptions['productId'] = (int)$options[self::INPUT_KEY_PRODUCT_ID];

            if ($this->_commandOptions['productId'] == 0) {
                $this->_errors[] = __('ERROR: product ID should be greater than 0.');
            } else {
                $distinctOptionsUsed++;
            }
        }

        if ($distinctOptionsUsed > 1) {
            $this->_errors[] = __(
                "ERROR: you can use only one of the option (not together):\n'--%o1' or '--%o2' or '--%o3' or '--%o4'.",
                [
                    'o1' => self::INPUT_KEY_CATEGORIES_RANGE,
                    'o2' => self::INPUT_KEY_PRODUCTS_RANGE,
                    'o3' => self::INPUT_KEY_CATEGORY_ID,
                    'o4' => self::INPUT_KEY_PRODUCT_ID
                ]
            );
        }

        // get store Id (if was set)
        $storeId = $this->_input->getArgument(self::INPUT_KEY_STOREID);
        if (is_null($storeId)) {
            $storeId = $this->_input->getOption(self::INPUT_KEY_STOREID);
        }

        // if store ID is not specified the re-generate for all stores
        if (is_null($storeId)) {
            $this->_commandOptions['storesList'] = $allStores;
        }
        // we will re-generate URL only in this specific store (if it exists)
        elseif (strlen($storeId) && ctype_digit($storeId)) {
            if (isset($allStores[$storeId])) {
                $this->_commandOptions['storesList'] = array(
                    $storeId => $allStores[$storeId]
                );
            } else {
                $this->_errors[] = __('ERROR: store with this ID not exists.');
            }
        }
        // disaply error if user set some incorrect value
        else {
            $this->_errors[] = __('ERROR: store ID should have a integer value.');
        }
    }
}
