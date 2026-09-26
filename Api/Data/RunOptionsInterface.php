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
}
