<?php
/**
 * NullProgressReporter.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface;

/**
 * Discards all progress (the default when RegenerateServiceInterface::run() gets no reporter)
 *
 * @api
 */
class NullProgressReporter implements ProgressReporterInterface
{
    /**
     * @param string $entityType
     * @param int $storeId
     * @param int $total
     * @return void
     */
    public function start(string $entityType, int $storeId, int $total): void
    {
    }

    /**
     * @param int $steps
     * @return void
     */
    public function advance(int $steps = 1): void
    {
    }

    /**
     * @return void
     */
    public function finish(): void
    {
    }

    /**
     * @param string $text
     * @param bool $newLine
     * @return void
     */
    public function message(string $text, bool $newLine = true): void
    {
    }
}
