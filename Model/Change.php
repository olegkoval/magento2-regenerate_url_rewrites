<?php
/**
 * Change.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use OlegKoval\RegenerateUrlRewrites\Api\Data\ChangeInterface;

class Change implements ChangeInterface
{
    /**
     * @param string $type
     * @param string $entityType
     * @param int|null $entityId
     * @param int $storeId
     * @param string|null $oldValue
     * @param string|null $newValue
     * @param array|null $oldRewrite
     * @param array|null $newRewrite
     * @param bool|null $hasOldRow null: whether $oldRewrite is given
     * @param bool|null $hasNewRow null: whether $newRewrite is given
     */
    public function __construct(
        private string $type,
        private string $entityType,
        private ?int $entityId,
        private int $storeId,
        private ?string $oldValue,
        private ?string $newValue,
        private ?array $oldRewrite = null,
        private ?array $newRewrite = null,
        private ?bool $hasOldRow = null,
        private ?bool $hasNewRow = null
    ) {
    }

    /**
     * @return bool
     */
    public function hasOldRow(): bool
    {
        return $this->hasOldRow ?? $this->oldRewrite !== null;
    }

    /**
     * @return bool
     */
    public function hasNewRow(): bool
    {
        return $this->hasNewRow ?? $this->newRewrite !== null;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * @return string|null
     */
    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    /**
     * @return string|null
     */
    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    /**
     * @return array|null
     */
    public function getOldRewrite(): ?array
    {
        return $this->oldRewrite;
    }

    /**
     * @return array|null
     */
    public function getNewRewrite(): ?array
    {
        return $this->newRewrite;
    }
}
