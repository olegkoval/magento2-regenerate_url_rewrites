<?php
/**
 * Regenerate Url Rewrites
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2017-2067 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateUrlRewrites extends RegenerateUrlRewritesAbstract
{
    /**
     * Max failures listed individually in the end-of-run summary (the rest are counted)
     */
    private const FAILURE_SUMMARY_LIMIT = 20;

    /**
     * @var null|InputInterface
     */
    protected ?InputInterface $_input = null;

    /**
     * @var null|OutputInterface
     */
    protected ?OutputInterface $_output = null;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('ok:urlrewrites:regenerate')
            ->setDescription('Regenerate Url Rewrites of products and categories')
            ->setDefinition([
                new InputOption(
                    self::INPUT_KEY_STORE_ID,
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
                ),
                new InputOption(
                    self::INPUT_KEY_REGEN_URL_KEY,
                    null,
                    InputOption::VALUE_NONE,
                    'Regenerate url_key values (by default url_key is not regenerated).'
                ),
                new InputOption(
                    self::INPUT_KEY_DELETE_ORPHANED_REWRITES,
                    null,
                    InputOption::VALUE_NONE,
                    'Delete url_rewrite rows (for the given --entity-type) whose product/category no longer exists.'
                ),
                new InputOption(
                    self::INPUT_KEY_SKIP_PRODUCTS,
                    null,
                    InputOption::VALUE_NONE,
                    'Skip regenerating associated product URLs when regenerating categories.'
                ),
                new InputOption(
                    self::INPUT_KEY_SKIP_EXISTING,
                    null,
                    InputOption::VALUE_NONE,
                    'Skip an entity entirely if it already has any URL Rewrite for the current store.'
                ),
                new InputOption(
                    self::INPUT_KEY_INCLUDE_NOT_VISIBLE,
                    null,
                    InputOption::VALUE_NONE,
                    'Also regenerate URLs for products with visibility "Not Visible Individually" (e.g. configurable child products).'
                ),
                new InputOption(
                    self::INPUT_KEY_ADD_SKU_TO_URL,
                    null,
                    InputOption::VALUE_NONE,
                    'Append the product\'s SKU as a URL segment in generated product Url Rewrites, e.g. screws.html -> screws-2244000004.html.'
                ),
                new InputOption(
                    self::INPUT_KEY_SET_PRODUCT_SUFFIX,
                    null,
                    InputArgument::OPTIONAL,
                    'Set the product URL suffix (e.g. ".html") before regenerating, applied to Default Config and every store (or only --store-id, if given).'
                ),
                new InputOption(
                    self::INPUT_KEY_SET_CATEGORY_SUFFIX,
                    null,
                    InputArgument::OPTIONAL,
                    'Set the category URL suffix (e.g. ".html") before regenerating, applied to Default Config and every store (or only --store-id, if given).'
                ),
            ]);
    }

    /**
     * Regenerate Url Rewrites
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
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
            $this->_displayConsoleMsg();
            return  Command::FAILURE;
        }

        // set area code if needed
        try {
            $areaCode = $this->_appState->getAreaCode();
        } catch (LocalizedException $e) {
            // if area code is not set then magento generate exception "LocalizedException"
            try {
                $this->_appState->setAreaCode(Area::AREA_ADMINHTML);
            } catch (LocalizedException $e) {}
        }

        $this->_setSeoUrlSuffixes();

        if (count($this->_errors) > 0) {
            foreach ($this->_errors as $error) {
                $this->_addConsoleMsg($error);
            }
            $this->_displayConsoleMsg();
            return Command::FAILURE;
        }

        $regenerator = $this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY
            ? $this->regenerateCategoryRewrites
            : $this->regenerateProductRewrites;
        $regenerator->resetFailures();

        foreach ($this->_commandOptions['storesList'] as $storeId => $storeCode) {
            $this->_output->writeln('');
            $this->_output->writeln("[Type: {$this->_commandOptions['entityType']}, Store ID: {$storeId}, Store View code: {$storeCode}]:");
            $this->_storeManager->setCurrentStore($storeId);

            if ($this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_PRODUCT) {
                $this->regenerateProductRewrites->setRegenerateOptions($this->_commandOptions);
                $this->regenerateProductRewrites->regenerate($storeId);
            } elseif ($this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY) {
                $this->regenerateCategoryRewrites->setRegenerateOptions($this->_commandOptions);
                $this->regenerateCategoryRewrites->regenerate($storeId);
            }
        }

        if ($this->_commandOptions['deleteOrphanedRewrites']) {
            $this->_output->write('Deleting orphaned url_rewrite rows...');
            if ($this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_PRODUCT) {
                $this->regenerateProductRewrites->deleteOrphanedRewrites();
            } elseif ($this->_commandOptions['entityType'] == self::INPUT_KEY_REGENERATE_ENTITY_TYPE_CATEGORY) {
                $this->regenerateCategoryRewrites->deleteOrphanedRewrites();
            }
            $this->_output->writeln(' Done');
        }

        $this->_output->writeln('');
        $this->_output->writeln('');

        $this->_displayConsoleMsg();

        $this->_runReindexation();
        $this->_runClearCache();

        $this->_showSupportMe();

        $failureCounts = $regenerator->getFailureCounts();
        if (count($failureCounts) > 0) {
            $this->_displayFailures($failureCounts, $regenerator->getFailures());
            $this->_output->writeln('Finished with failures');

            return Command::FAILURE;
        }

        $this->_output->writeln('Finished');

        return Command::SUCCESS;
    }

    /**
     * Print failure counts per entity type and up to FAILURE_SUMMARY_LIMIT of the retained failures
     *
     * @param array<string, int> $failureCounts exact totals per entity type
     * @param array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}> $failures
     *        retained details (may be fewer than the totals)
     * @return void
     */
    private function _displayFailures(array $failureCounts, array $failures): void
    {
        $countParts = [];
        foreach ($failureCounts as $entityType => $count) {
            $countParts[] = "{$count} {$entityType} failure(s)";
        }

        $this->_output->writeln('[FAILURES] ' . implode(', ', $countParts) . ':');
        foreach (array_slice($failures, 0, self::FAILURE_SUMMARY_LIMIT) as $failure) {
            $subject = $failure['entity_type']
                . ($failure['entity_id'] !== null ? " {$failure['entity_id']}" : '')
                . ($failure['store_id'] !== null ? " (store {$failure['store_id']})" : '');
            $this->_output->writeln("  {$subject}: {$failure['message']}");
        }

        $hidden = array_sum($failureCounts) - min(count($failures), self::FAILURE_SUMMARY_LIMIT);
        if ($hidden > 0) {
            $this->_output->writeln("  ...and {$hidden} more");
        }
        $this->_output->writeln('');
    }

    /**
     * Get command options
     * @return void
     */
    public function getCommandOptions(): void
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

        if (isset($options[self::INPUT_KEY_REGEN_URL_KEY]) && $options[self::INPUT_KEY_REGEN_URL_KEY] === true) {
            $this->_commandOptions['regenUrlKey'] = true;
        }

        if (isset($options[self::INPUT_KEY_DELETE_ORPHANED_REWRITES]) && $options[self::INPUT_KEY_DELETE_ORPHANED_REWRITES] === true) {
            $this->_commandOptions['deleteOrphanedRewrites'] = true;
        }

        if (isset($options[self::INPUT_KEY_SKIP_PRODUCTS]) && $options[self::INPUT_KEY_SKIP_PRODUCTS] === true) {
            $this->_commandOptions['skipProducts'] = true;
        }

        if (isset($options[self::INPUT_KEY_SKIP_EXISTING]) && $options[self::INPUT_KEY_SKIP_EXISTING] === true) {
            $this->_commandOptions['skipExisting'] = true;
        }

        if (isset($options[self::INPUT_KEY_INCLUDE_NOT_VISIBLE]) && $options[self::INPUT_KEY_INCLUDE_NOT_VISIBLE] === true) {
            $this->_commandOptions['includeNotVisible'] = true;
        }

        if (isset($options[self::INPUT_KEY_ADD_SKU_TO_URL]) && $options[self::INPUT_KEY_ADD_SKU_TO_URL] === true) {
            $this->_commandOptions['addSkuToUrl'] = true;
        }

        if (isset($options[self::INPUT_KEY_SET_PRODUCT_SUFFIX]) && $options[self::INPUT_KEY_SET_PRODUCT_SUFFIX] !== null) {
            $this->_commandOptions['setProductSuffix'] = (string)$options[self::INPUT_KEY_SET_PRODUCT_SUFFIX];
        }

        if (isset($options[self::INPUT_KEY_SET_CATEGORY_SUFFIX]) && $options[self::INPUT_KEY_SET_CATEGORY_SUFFIX] !== null) {
            $this->_commandOptions['setCategorySuffix'] = (string)$options[self::INPUT_KEY_SET_CATEGORY_SUFFIX];
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

        // get store ID (if was set)
        $storeId = $this->_input->getOption(self::INPUT_KEY_STORE_ID);

        // if store ID is not specified the re-generate for all stores
        if (is_null($storeId)) {
            $this->_commandOptions['storesList'] = $allStores;
        }
        // we will re-generate URL only in this specific store (if it exists)
        elseif (strlen((string)$storeId) && is_numeric($storeId)) {
            if (isset($allStores[$storeId])) {
                $this->_commandOptions['storesList'] = array(
                    (int)$storeId => $allStores[$storeId]
                );
            } else {
                $this->_errors[] = __('ERROR: store with this ID not exists.')->render();
            }
        }
        // display error if user set some incorrect value
        else {
            $this->_errors[] = __('ERROR: store ID should have a integer value.')->render();
        }
    }

    /**
     * Generate logical conflict error
     *
     * @param string $option1
     * @param string $option2
     * @param string $option3
     * @return string
     */
    private function _getLogicalConflictError(string $option1, string $option2, string $option3): string
    {
        return __(
                "ERROR: you can not use this options together (logical conflict):\n'--%o1' with '--%o2'/'--%o3'",
                [
                    'o1' => $option1,
                    'o2' => $option2,
                    'o3' => $option3
                ]
            )->render();
    }
}
