<?php
/**
 * RunOptionsBuilder.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use OlegKoval\RegenerateUrlRewrites\Api\Data\RunOptionsInterface;

/**
 * Builds RunOptionsInterface; unset options keep the CLI command's defaults (all stores, all entities of the
 * type, reindex and cache refresh on). create() resets the builder.
 *
 * @api
 */
class RunOptionsBuilder
{
    /**
     * @var array
     */
    private const DEFAULTS = [
        'entityType' => RunOptionsInterface::ENTITY_TYPE_PRODUCT,
        'storeIds' => [],
        'productIds' => [],
        'categoryIds' => [],
        'exactCategories' => false,
        'saveOldUrls' => false,
        'regenUrlKey' => false,
        'skipExisting' => false,
        'skipProducts' => false,
        'includeNotVisible' => false,
        'addSkuToUrl' => false,
        'deleteOrphanedRewrites' => false,
        'productUrlSuffix' => null,
        'categoryUrlSuffix' => null,
        'reindex' => true,
        'cleanCache' => true,
        'flushCache' => true,
    ];

    /**
     * @var array
     */
    private array $values = self::DEFAULTS;

    /**
     * @param string $entityType RunOptionsInterface::ENTITY_TYPE_PRODUCT or ENTITY_TYPE_CATEGORY
     * @return $this
     */
    public function setEntityType(string $entityType): static
    {
        $this->values['entityType'] = $entityType;

        return $this;
    }

    /**
     * @param int[] $storeIds empty: all stores
     * @return $this
     */
    public function setStoreIds(array $storeIds): static
    {
        $this->values['storeIds'] = $this->_toIds($storeIds);

        return $this;
    }

    /**
     * @param int[] $productIds empty: all products
     * @return $this
     */
    public function setProductIds(array $productIds): static
    {
        $this->values['productIds'] = $this->_toIds($productIds);

        return $this;
    }

    /**
     * @param int[] $categoryIds empty: all categories
     * @return $this
     */
    public function setCategoryIds(array $categoryIds): static
    {
        $this->values['categoryIds'] = $this->_toIds($categoryIds);

        return $this;
    }

    /**
     * @param bool $value true: process only the given category IDs (see RunOptionsInterface::isExactCategories())
     * @return $this
     */
    public function setExactCategories(bool $value): static
    {
        $this->values['exactCategories'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setSaveOldUrls(bool $value): static
    {
        $this->values['saveOldUrls'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setRegenUrlKey(bool $value): static
    {
        $this->values['regenUrlKey'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setSkipExisting(bool $value): static
    {
        $this->values['skipExisting'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setSkipProducts(bool $value): static
    {
        $this->values['skipProducts'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setIncludeNotVisible(bool $value): static
    {
        $this->values['includeNotVisible'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setAddSkuToUrl(bool $value): static
    {
        $this->values['addSkuToUrl'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setDeleteOrphanedRewrites(bool $value): static
    {
        $this->values['deleteOrphanedRewrites'] = $value;

        return $this;
    }

    /**
     * @param string|null $suffix null: keep the current suffix
     * @return $this
     */
    public function setProductUrlSuffix(?string $suffix): static
    {
        $this->values['productUrlSuffix'] = $suffix;

        return $this;
    }

    /**
     * @param string|null $suffix null: keep the current suffix
     * @return $this
     */
    public function setCategoryUrlSuffix(?string $suffix): static
    {
        $this->values['categoryUrlSuffix'] = $suffix;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setReindex(bool $value): static
    {
        $this->values['reindex'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setCleanCache(bool $value): static
    {
        $this->values['cleanCache'] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setFlushCache(bool $value): static
    {
        $this->values['flushCache'] = $value;

        return $this;
    }

    /**
     * Set options from an array as RunOptionsInterface::toArray() returns it; missing keys keep their current value
     *
     * @param array<string, mixed> $options
     * @return $this
     * @throws \InvalidArgumentException for an unknown key or a value of the wrong type (nothing is set then), so a
     *         typo can't silently widen a run to e.g. all products
     */
    public function fromArray(array $options): static
    {
        $unknown = array_diff(array_keys($options), array_keys(self::DEFAULTS));
        if (count($unknown) > 0) {
            throw new \InvalidArgumentException('Unknown run option(s): ' . implode(', ', $unknown));
        }
        foreach ($options as $key => $value) {
            if (!$this->_isValidValue($key, $value)) {
                throw new \InvalidArgumentException("Invalid value for run option \"{$key}\"");
            }
        }

        foreach ($options as $key => $value) {
            $this->{'set' . ucfirst($key)}($value);
        }

        return $this;
    }

    /**
     * @return RunOptionsInterface
     */
    public function create(): RunOptionsInterface
    {
        $options = new RunOptions($this->values);
        $this->values = self::DEFAULTS;

        return $options;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    private function _isValidValue(string $key, mixed $value): bool
    {
        $default = self::DEFAULTS[$key];
        if (is_array($default)) {
            return is_array($value) && count(array_filter(
                $value,
                fn ($id): bool => is_int($id) || (is_string($id) && ctype_digit($id))
            )) === count($value);
        }
        if (is_bool($default)) {
            return is_bool($value);
        }
        if ($default === null) {
            return $value === null || is_string($value);
        }

        return is_string($value);
    }

    /**
     * @param array $ids
     * @return int[] unique, in the given order
     */
    private function _toIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
