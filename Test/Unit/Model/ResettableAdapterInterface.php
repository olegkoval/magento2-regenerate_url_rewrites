<?php
/**
 * Resettable database adapter for service tests.
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

interface ResettableAdapterInterface extends AdapterInterface, ResetAfterRequestInterface
{
}
