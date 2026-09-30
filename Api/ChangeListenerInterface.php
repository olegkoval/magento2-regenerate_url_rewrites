<?php
/**
 * ChangeListenerInterface.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Api;

use OlegKoval\RegenerateUrlRewrites\Api\Data\ChangeInterface;

/**
 * Receives every change a regeneration run makes to url_rewrite rows and to url_key/url_path attribute values (e.g.
 * to log, audit or undo a run). Not reported: URL suffix config saved by the run (RunOptionsInterface::
 * getProductUrlSuffix()/getCategoryUrlSuffix()) and the suffix swap Magento's own config backend then applies to
 * existing rewrites — a run meant to be undone shouldn't set suffixes.
 *
 * Implement it and pass it to RegenerateServiceInterface::run(); it never gains methods within 1.x (new hooks come as
 * new interfaces). An exception thrown from onChange() stops the run like one from the progress reporter.
 *
 * @api
 */
interface ChangeListenerInterface
{
    /**
     * Called once per change, after the change is saved (a save that is rolled back reports nothing)
     *
     * @param ChangeInterface $change
     * @return void
     */
    public function onChange(ChangeInterface $change): void;
}
