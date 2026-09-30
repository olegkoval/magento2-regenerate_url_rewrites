<?php
/**
 * RunResultInterface.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Api\Data;

/**
 * Outcome of one regeneration run
 *
 * Implemented only by this module, so it may gain methods in minor releases; don't implement it yourself.
 *
 * @api
 */
interface RunResultInterface
{
    /**
     * Exact failure count per type ("product", "category", "indexer", "cache")
     *
     * @return array<string, int>
     */
    public function getFailureCounts(): array;

    /**
     * The first 1000 failures (getFailureCounts() has the totals)
     *
     * @return array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}>
     */
    public function getFailures(): array;

    /**
     * @return bool
     */
    public function hasFailures(): bool;

    /**
     * @return int[]
     */
    public function getProcessedStoreIds(): array;

    /**
     * Entities the run went through, incl. failed and --skip-existing ones; a category run's products are under
     * "product". Store 0 of an all-stores run counts its default-scope pass.
     *
     * @return array<string, array<int, int>> entity type => store ID => count
     */
    public function getProcessedCounts(): array;
}
