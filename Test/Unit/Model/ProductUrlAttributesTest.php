<?php
/**
 * ProductUrlAttributesTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Entity\Attribute\Backend\DefaultBackend;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\StoreManagerInterface;
use OlegKoval\RegenerateUrlRewrites\Helper\Regenerate;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateProductRewrites;
use PHPUnit\Framework\TestCase;

class ProductUrlAttributesTest extends TestCase
{
    /**
     * @return array
     */
    public static function keyCases(): array
    {
        return [
            'ordinary maintenance' => [false, null, false, 'custom', 'default', false],
            'unchanged regenerated key' => [false, 'custom', false, 'custom', 'default', false],
            'changed regenerated key' => [false, 'new', false, 'custom', 'default', true],
            'stale override removed' => [false, 'default', true, 'stale', 'default', true],
            'redundant override removed' => [false, 'default', true, 'default', 'default', false],
            'single store unchanged default' => [true, 'default', true, 'stale', 'default', false],
            'single store changes default' => [true, 'custom', false, 'custom', 'default', true],
            'failed maintenance write' => [false, null, false, 'custom', 'default', false, true],
            'NULL override changes effective key' => [false, 'default', true, null, 'default', true],
        ];
    }

    /**
     * @dataProvider keyCases
     * @param bool $singleStore
     * @param string|null $key
     * @param bool $inherit
     * @param string|null $storeKey
     * @param string $defaultKey
     * @param bool $touch
     * @param bool $failWrite
     * @return void
     */
    public function testOnlyAChangedKeyUsesTheTimestampUpdatingAction(
        bool $singleStore,
        ?string $key,
        bool $inherit,
        ?string $storeKey,
        string $defaultKey,
        bool $touch,
        bool $failWrite = false
    ): void {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects($failWrite ? self::never() : self::once())->method('commit');
        $connection->expects($failWrite ? self::once() : self::never())->method('rollBack');
        $rows = [];
        $connection->method('insertOnDuplicate')->willReturnCallback(
            function ($table, $row, $fields) use (&$rows, $failWrite): int {
                if ($failWrite) {
                    throw new \RuntimeException('write failed');
                }
                self::assertSame('prefixed_product_varchar', $table);
                self::assertSame(['value'], $fields);
                self::assertSame(42, $row['row_id']);
                self::assertArrayNotHasKey('updated_at', $row);
                $rows[] = $row;
                return 1;
            }
        );
        $deletes = [];
        $connection->method('delete')->willReturnCallback(
            function ($table, $where) use (&$deletes): int {
                $deletes[] = $where;
                return 1;
            }
        );
        $resource = $this->createMock(ProductResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getLinkField')->willReturn('row_id');
        $resource->method('getAttribute')->willReturnCallback(function ($code) {
            $backend = $this->createMock(DefaultBackend::class);
            $backend->method('getTable')->willReturn('prefixed_product_varchar');
            $attribute = $this->createMock(Attribute::class);
            $attribute->method('getId')->willReturn($code === 'url_path' ? 11 : 12);
            $attribute->method('getBackend')->willReturn($backend);
            $attribute->method('isScopeStore')->willReturn(true);
            return $attribute;
        });
        $product = $this->createMock(Product::class);
        $product->method('getResource')->willReturn($resource);
        $product->method('getId')->willReturn(7);
        $product->method('getData')->with('row_id')->willReturn(42);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('hasSingleStore')->willReturn($singleStore);
        $helper = $this->createMock(Regenerate::class);
        $helper->method('getStoreManager')->willReturn($storeManager);
        $action = $this->createMock(Action::class);
        if ($touch) {
            $action->expects(self::once())->method('updateAttributes')->with([7], ['url_key' => $key], 2);
        } else {
            $action->expects(self::never())->method('updateAttributes');
        }
        $model = new class ($helper, $action, $storeKey, $defaultKey) extends RegenerateProductRewrites {
            /**
             * @param Regenerate $helper
             * @param Action $action
             * @param string|null $storeKey
             * @param string $defaultKey
             */
            public function __construct($helper, $action, private ?string $storeKey, private string $defaultKey)
            {
                $this->helper = $helper;
                $this->productAction = $action;
            }

            /**
             * @param \Magento\Framework\Model\AbstractModel $entity
             * @param array $storeIds
             * @return array
             */
            protected function _readUrlAttributes($entity, array $storeIds): array
            {
                return ['url_key' => [0 => [true, $this->defaultKey], 2 => [true, $this->storeKey]]];
            }
        };
        $attributes = ['url_path' => null];
        if ($key !== null) {
            $attributes['url_key'] = $key;
        }
        if ($failWrite) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('write failed');
        }
        (new \ReflectionMethod($model, '_saveProductUrlAttributes'))->invoke($model, $product, $attributes, 2, $inherit);

        self::assertSame(['attribute_id' => 11, 'row_id' => 42, 'store_id' => $singleStore ? 0 : 2, 'value' => null], $rows[0]);
        if ($singleStore) {
            self::assertContains(['attribute_id = ?' => 11, 'row_id = ?' => 42, 'store_id <> ?' => 0], $deletes);
        } elseif ($inherit) {
            self::assertContains(['attribute_id = ?' => 12, 'row_id = ?' => 42, 'store_id = ?' => 2], $deletes);
        }
        self::assertCount($key !== null && !$touch && (!$inherit || $singleStore) ? 2 : 1, $rows);
    }
}
