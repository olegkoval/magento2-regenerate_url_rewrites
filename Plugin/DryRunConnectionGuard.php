<?php
/**
 * DryRunConnectionGuard.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Plugin;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use OlegKoval\RegenerateUrlRewrites\Model\DryRunState;

/**
 * Stops a dry run when its database connection is lost
 *
 * Magento closes and reopens a lost connection, then retries the statement. The new session has no transaction
 * and commits every statement, so a dry run would go on saving for real.
 */
class DryRunConnectionGuard
{
    /**
     * @var DryRunState
     */
    private DryRunState $dryRunState;

    /**
     * @param DryRunState $dryRunState
     */
    public function __construct(DryRunState $dryRunState)
    {
        $this->dryRunState = $dryRunState;
    }

    /**
     * @param Mysql $subject
     * @return void
     * @throws \RuntimeException during a dry run
     */
    public function beforeCloseConnection(Mysql $subject): void
    {
        if ($this->dryRunState->isActive()) {
            throw new \RuntimeException(
                'The database connection was lost during a dry run: stopped, so that nothing gets saved.'
            );
        }
    }
}
