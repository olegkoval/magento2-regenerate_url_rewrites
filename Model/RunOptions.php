<?php
/**
 * RunOptions.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use OlegKoval\RegenerateUrlRewrites\Api\Data\RunOptionsInterface;

/**
 * Created by RunOptionsBuilder::create()
 */
class RunOptions implements RunOptionsInterface
{
    /**
     * @var array
     */
    private array $values;

    /**
     * @param array $values every key of RunOptionsBuilder::DEFAULTS
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->values['entityType'];
    }

    /**
     * @return int[]
     */
    public function getStoreIds(): array
    {
        return $this->values['storeIds'];
    }

    /**
     * @return int[]
     */
    public function getProductIds(): array
    {
        return $this->values['productIds'];
    }

    /**
     * @return int[]
     */
    public function getCategoryIds(): array
    {
        return $this->values['categoryIds'];
    }

    /**
     * @return bool
     */
    public function isSaveOldUrls(): bool
    {
        return $this->values['saveOldUrls'];
    }

    /**
     * @return bool
     */
    public function isRegenUrlKey(): bool
    {
        return $this->values['regenUrlKey'];
    }

    /**
     * @return bool
     */
    public function isSkipExisting(): bool
    {
        return $this->values['skipExisting'];
    }

    /**
     * @return bool
     */
    public function isSkipProducts(): bool
    {
        return $this->values['skipProducts'];
    }

    /**
     * @return bool
     */
    public function isIncludeNotVisible(): bool
    {
        return $this->values['includeNotVisible'];
    }

    /**
     * @return bool
     */
    public function isAddSkuToUrl(): bool
    {
        return $this->values['addSkuToUrl'];
    }

    /**
     * @return bool
     */
    public function isDeleteOrphanedRewrites(): bool
    {
        return $this->values['deleteOrphanedRewrites'];
    }

    /**
     * @return string|null
     */
    public function getProductUrlSuffix(): ?string
    {
        return $this->values['productUrlSuffix'];
    }

    /**
     * @return string|null
     */
    public function getCategoryUrlSuffix(): ?string
    {
        return $this->values['categoryUrlSuffix'];
    }

    /**
     * @return bool
     */
    public function isReindex(): bool
    {
        return $this->values['reindex'];
    }

    /**
     * @return bool
     */
    public function isCleanCache(): bool
    {
        return $this->values['cleanCache'];
    }

    /**
     * @return bool
     */
    public function isFlushCache(): bool
    {
        return $this->values['flushCache'];
    }
}
