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
 * Implemented only by this module, so it may gain methods or optional parameters in minor releases; call it, don't
 * implement it (a plugin on it is fine).
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
     * Runs in the adminhtml area and config scope when no area is set.
     *
     * Stopping: an exception thrown by the reporter's advance() or message(), or by the change listener, stops the run
     * after the current entity — its rewrites stay saved, the rest of that store and the remaining stores are skipped,
     * and so are the post-run steps (orphans, reindex, cache). It isn't counted as a failure; the caller's current
     * store is restored, and the exception is rethrown unchanged.
     *
     * @param RunOptionsInterface $options
     * @param ProgressReporterInterface|null $reporter
     * @param ChangeListenerInterface|null $changeListener receives the run's url_rewrite and url_key/url_path changes
     *        (not suffix config — see there; extra queries only when given); an exception from it stops the run like
     *        one from the reporter
     * @return RunResultInterface
     * @throws InputException when validate() reports problems or a URL suffix can't be saved (nothing is
     *         regenerated then)
     * @throws \Throwable whatever the reporter or change listener throws to stop the run
     */
    public function run(
        RunOptionsInterface $options,
        ?ProgressReporterInterface $reporter = null,
        ?ChangeListenerInterface $changeListener = null
    ): RunResultInterface;
}
