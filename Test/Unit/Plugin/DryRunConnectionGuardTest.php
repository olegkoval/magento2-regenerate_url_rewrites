<?php
/**
 * DryRunConnectionGuardTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Plugin;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use OlegKoval\RegenerateUrlRewrites\Model\DryRunState;
use OlegKoval\RegenerateUrlRewrites\Plugin\DryRunConnectionGuard;
use PHPUnit\Framework\TestCase;

class DryRunConnectionGuardTest extends TestCase
{
    /**
     * @return void
     */
    public function testAConnectionMayOnlyBeClosedOutsideADryRun(): void
    {
        $state = new DryRunState();
        $guard = new DryRunConnectionGuard($state);
        $adapter = $this->createMock(Mysql::class);

        $guard->beforeCloseConnection($adapter);

        $state->setActive(true);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('lost during a dry run');
        $guard->beforeCloseConnection($adapter);
    }
}
