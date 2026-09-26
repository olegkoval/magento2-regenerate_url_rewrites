<?php
/**
 * ProgressReporterInterface.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Api;

/**
 * Receives the progress of a regeneration run (the CLI draws a progress bar; other callers may log it)
 *
 * @api
 */
interface ProgressReporterInterface
{
    /**
     * A pass over $total entities of one store starts
     *
     * @param string $entityType "product" or "category"
     * @param int $storeId
     * @param int $total
     * @return void
     */
    public function start(string $entityType, int $storeId, int $total): void;

    /**
     * @param int $steps entities processed since the last call
     * @return void
     */
    public function advance(int $steps = 1): void;

    /**
     * The pass started by start() has ended
     *
     * @return void
     */
    public function finish(): void;

    /**
     * A status line, e.g. the store being processed or a post-run step
     *
     * @param string $text
     * @param bool $newLine false: the next message continues this line (e.g. "Reindexation..." + " Done")
     * @return void
     */
    public function message(string $text, bool $newLine = true): void;
}
