<?php
/**
 * RegenerateServiceInterface.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Api;

use Magento\Framework\Exception\InputException;
use OlegKoval\RegenerateUrlRewrites\Api\Data\RunOptionsInterface;
use OlegKoval\RegenerateUrlRewrites\Api\Data\RunResultInterface;

/**
 * Regenerates product/category URL rewrites, the same run as `bin/magento ok:urlrewrites:regenerate`
 *
 * @api
 */
interface RegenerateServiceInterface
{
    /**
     * Every problem that would stop run() from starting
     *
     * @param RunOptionsInterface $options
     * @return string[] error messages; empty when the options are valid
     */
    public function validate(RunOptionsInterface $options): array;

    /**
     * Run a regeneration: URL suffixes (if set), every selected store, orphan cleanup, reindex and cache
     *
     * Failures of single entities or post-run steps don't stop the run; they are collected in the result.
     * Emulates the adminhtml area when no area is set.
     *
     * @param RunOptionsInterface $options
     * @param ProgressReporterInterface|null $reporter
     * @return RunResultInterface
     * @throws InputException when validate() reports problems or a URL suffix can't be saved (nothing is
     *         regenerated then)
     */
    public function run(RunOptionsInterface $options, ?ProgressReporterInterface $reporter = null): RunResultInterface;
}
