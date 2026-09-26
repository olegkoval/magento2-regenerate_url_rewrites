<?php
/**
 * RunResult.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use OlegKoval\RegenerateUrlRewrites\Api\Data\RunResultInterface;

class RunResult implements RunResultInterface
{
    /**
     * @var array<string, int>
     */
    private array $failureCounts;

    /**
     * @var array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}>
     */
    private array $failures;

    /**
     * @var int[]
     */
    private array $processedStoreIds;

    /**
     * @param array<string, int> $failureCounts
     * @param array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}> $failures
     * @param int[] $processedStoreIds
     */
    public function __construct(array $failureCounts, array $failures, array $processedStoreIds)
    {
        $this->failureCounts = $failureCounts;
        $this->failures = $failures;
        $this->processedStoreIds = $processedStoreIds;
    }

    /**
     * @return array<string, int>
     */
    public function getFailureCounts(): array
    {
        return $this->failureCounts;
    }

    /**
     * @return array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * @return bool
     */
    public function hasFailures(): bool
    {
        return count($this->failureCounts) > 0;
    }

    /**
     * @return int[]
     */
    public function getProcessedStoreIds(): array
    {
        return $this->processedStoreIds;
    }
}
