<?php
/**
 * DryRunState.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

/**
 * Whether a dry run is in progress in this process (shared instance)
 */
class DryRunState
{
    /**
     * @var bool
     */
    private bool $active = false;

    /**
     * @param bool $active
     * @return void
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}
