<?php
/**
 * RunOptionsInterface.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Api\Data;

/**
 * Immutable options of one regeneration run; build them with \OlegKoval\RegenerateUrlRewrites\Model\RunOptionsBuilder
 *
 * Implemented only by this module, so it may gain methods in minor releases; don't implement it yourself.
 *
 * @api
 */
interface RunOptionsInterface
{
    public const ENTITY_TYPE_PRODUCT = 'product';
    public const ENTITY_TYPE_CATEGORY = 'category';

    /**
     * @return string ENTITY_TYPE_PRODUCT or ENTITY_TYPE_CATEGORY
     */
    public function getEntityType(): string;

    /**
     * @return int[] empty: all stores (store 0 updates only default-scope values, then each store view)
     */
    public function getStoreIds(): array;

    /**
     * @return int[] empty: all products
     */
    public function getProductIds(): array;

    /**
     * @return int[] empty: all categories
     */
    public function getCategoryIds(): array;

    /**
     * @return bool category run: process only the given category IDs; a descendant only when an ancestor's
     *         url_path changed in the run (then without url_key regeneration)
     */
    public function isExactCategories(): bool;

    /**
     * @return bool keep old URLs as 301 redirects
     */
    public function isSaveOldUrls(): bool;

    /**
     * @return bool regenerate url_key values from the names
     */
    public function isRegenUrlKey(): bool;

    /**
     * @return bool skip entities that already have a URL rewrite in the store
     */
    public function isSkipExisting(): bool;

    /**
     * @return bool category run: don't regenerate the categories' products
     */
    public function isSkipProducts(): bool;

    /**
     * @return bool include products that are "Not Visible Individually"
     */
    public function isIncludeNotVisible(): bool;

    /**
     * @return bool append the SKU to generated product URLs
     */
    public function isAddSkuToUrl(): bool;

    /**
     * @return bool delete rewrites of products/categories that no longer exist
     */
    public function isDeleteOrphanedRewrites(): bool;

    /**
     * @return string|null product URL suffix to save before regenerating; null: keep the current one
     */
    public function getProductUrlSuffix(): ?string;

    /**
     * @return string|null category URL suffix to save before regenerating; null: keep the current one
     */
    public function getCategoryUrlSuffix(): ?string;

    /**
     * @return bool reindex all indexers afterwards
     */
    public function isReindex(): bool;

    /**
     * @return bool clean all cache types afterwards
     */
    public function isCleanCache(): bool;

    /**
     * @return bool flush all cache storages afterwards
     */
    public function isFlushCache(): bool;

    /**
     * @return bool run everything in one DB transaction and roll it back: nothing is saved, the change listener
     *         still receives every change; reindex and cache refresh are skipped
     */
    public function isDryRun(): bool;

    /**
     * Every option, keyed by its RunOptionsBuilder name (e.g. to log a run or replay it via
     * RunOptionsBuilder::fromArray())
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
