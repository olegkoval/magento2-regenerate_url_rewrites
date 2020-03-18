<?php
/**
 * Regenerate Url rewrites
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

class RegenerateUrlRewrites extends RegenerateUrlRewritesAbstract
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
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ok:urlrewrites:regenerate')
            ->setDescription('Regenerate Url rewrites of products and categories')
            ->setDefinition([
                new InputOption(
                    self::INPUT_KEY_STOREID,
                    null,
                    InputArgument::OPTIONAL,
                    'Specific store id'
                ),
                new InputOption(
                    self::INPUT_KEY_REGENERATE_ENTITY_TYPE,
                    null,
                    InputArgument::OPTIONAL,
                    'Entity type which URLs regenerate: product or category. Default is "product".'
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
                    self::INPUT_KEY_CATEGORIES_RANGE,
                    null,
                    InputArgument::OPTIONAL,
                    'Categories ID range, e.g.: 15-40 (Pro version only)'
                ),
                new InputOption(
                    self::INPUT_KEY_PRODUCTS_RANGE,
                    null,
                    InputArgument::OPTIONAL,
                    'Products ID range, e.g.: 101-152 (Pro version only)'
                ),
                new InputOption(
                    self::INPUT_KEY_CATEGORY_ID,
                    null,
                    InputArgument::OPTIONAL,
                    'Specific category ID, e.g.: 123 (Pro version only)'
                ),
                new InputOption(
                    self::INPUT_KEY_PRODUCT_ID,
                    null,
                    InputArgument::OPTIONAL,
                    'Specific product ID, e.g.: 107 (Pro version only)'
                ),
                new InputOption(
                    self::INPUT_KEY_NO_REGEN_URL_KEY,
                    null,
                    InputOption::VALUE_NONE,
                    'Prevent url_key regeneration'
                ),       
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
        $this->_input = $input;
        $this->_output = $output;

        $this->_output->writeln('Regenerating of URL rewrites:');
        $this->_showSupportMe();
        $this->getCommandOptions();

        if (count($this->_errors) > 0) {
            foreach ($this->_errors as $error) {
                $this->_addConsoleMsg($error);
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

        foreach ($this->_commandOptions['storesList'] as $storeId => $storeCode) {
            $this->_output->writeln('');
            $this->_output->writeln("[Type: {$this->_commandOptions['entityType']}, Store ID: {$storeId}, Store View code: {$storeCode}]:");
            $this->_storeManager->setCurrentStore($storeId);

            if ($this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_PRODUCT) {
                $this->regenerateProductRewrites->regenerateOptions = $this->_commandOptions;
                $this->regenerateProductRewrites->regenerate($storeId);
            } elseif ($this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY) {
                $this->regenerateCategoryRewrites->regenerateOptions = $this->_commandOptions;
                $this->regenerateCategoryRewrites->regenerate($storeId);
            }
        }

        $this->_output->writeln('');
        $this->_output->writeln('');

        $this->_displayConsoleMsg();

        $this->_runReindexation();
        $this->_runClearCache();

        $this->_showSupportMe();
        $this->_output->writeln('Finished');
    }

    /**
     * Get command options
     * @return void
     */
    public function getCommandOptions()
    {
        $options = $this->_input->getOptions();
        $allStores = $this->_getAllStoreIds();
        $distinctOptionsUsed = 0;

        if (
            isset($options[self::INPUT_KEY_REGENERATE_ENTITY_TYPE])
            && in_array(
                $options[self::INPUT_KEY_REGENERATE_ENTITY_TYPE],
                array(self::INPUT_KEY_REGENERATE_ENTITY_TYPE_PRODUCT, self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY)
            )
        ) {
            $this->_commandOptions['entityType'] = $options[self::INPUT_KEY_REGENERATE_ENTITY_TYPE];
        }

        if (isset($options[self::INPUT_KEY_SAVE_REWRITES_HISTORY]) && $options[self::INPUT_KEY_SAVE_REWRITES_HISTORY] === true) {
            $this->_commandOptions['saveOldUrls'] = true;
        }

        if (isset($options[self::INPUT_KEY_NO_REGEN_URL_KEY]) && $options[self::INPUT_KEY_NO_REGEN_URL_KEY] === true) {
            $this->_commandOptions['noRegenUrlKey'] = true;
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

        if (isset($options[self::INPUT_KEY_PRODUCTS_RANGE])) {
            $this->_commandOptions['productsFilter'] = $this->_generateIdsRangeArray(
                $options[self::INPUT_KEY_PRODUCTS_RANGE],
                'product'
            );
            $distinctOptionsUsed++;
        }

        if (isset($options[self::INPUT_KEY_PRODUCT_ID])) {
            $this->_commandOptions['productId'] = (int)$options[self::INPUT_KEY_PRODUCT_ID];

            if ($this->_commandOptions['productId'] == 0) {
                $this->_errors[] = __('ERROR: product ID should be greater than 0.');
            } else {
                $distinctOptionsUsed++;
            }
        }

        if (isset($options[self::INPUT_KEY_CATEGORIES_RANGE])) {
            $this->_commandOptions['categoriesFilter'] = $this->_generateIdsRangeArray(
                $options[self::INPUT_KEY_CATEGORIES_RANGE],
                'category'
            );
            $distinctOptionsUsed++;

            // if this option was used then for 100% user want to regenerate entity type "category"
            $this->_commandOptions['entityType'] = self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY;
        }

        if (isset($options[self::INPUT_KEY_CATEGORY_ID])) {
            $this->_commandOptions['categoryId'] = (int)$options[self::INPUT_KEY_CATEGORY_ID];

            if ($this->_commandOptions['categoryId'] == 0) {
                $this->_errors[] = __('ERROR: category ID should be greater than 0.');
            } else {
                $distinctOptionsUsed++;
            }

            // if this option was used then for 100% user want to regenerate entity type "category"
            $this->_commandOptions['entityType'] = self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY;
        }

        if (
            $this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_PRODUCT
            && (
                count($this->_commandOptions['categoriesFilter']) > 0
                || (int) $this->_commandOptions['categoryId'] > 0
            )
        ) {
            $this->_errors[] = $this->_getLogicalConflictError(
                self::INPUT_KEY_REGENERATE_ENTITY_TYPE_PRODUCT,
                self::INPUT_KEY_CATEGORIES_RANGE,
                self::INPUT_KEY_CATEGORY_ID
            );
        }

        if (
            $this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY
            && (
                count($this->_commandOptions['productsFilter']) > 0
                || (int) $this->_commandOptions['productId'] > 0
            )
        ) {
            $this->_errors[] = $this->_getLogicalConflictError(
                self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY,
                self::INPUT_KEY_PRODUCTS_RANGE,
                self::INPUT_KEY_PRODUCT_ID
            );
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
        $storeId = $this->_input->getOption(self::INPUT_KEY_STOREID);

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

    /**
     * Generate logical conflict error
     * @param  string $option1
     * @param  string $option2
     * @param  string $option3
     * @return string
     */
    private function _getLogicalConflictError($option1, $option2, $option3)
    {
        return __(
                "ERROR: you can not use this options together (logical conflict):\n'--%o1' with '--%o2'/'--%o3'",
                [
                    'o1' => $option1,
                    'o2' => $option2,
                    'o3' => $option3
                ]
            );
    }
}
