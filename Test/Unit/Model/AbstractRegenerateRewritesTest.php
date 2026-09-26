<?php
/**
 * AbstractRegenerateRewritesTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use OlegKoval\RegenerateUrlRewrites\Model\RegenerateProductRewrites;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractRegenerateRewritesTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private $connection;

    /**
     * @var RegenerateProductRewrites
     */
    private $model;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('quoteInto')->willReturnCallback(
            fn (string $text, $value): string => str_replace('?', "'" . $value . "'", $text)
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->model = new class ($resource) extends RegenerateProductRewrites {
            /**
             * "store|path" owned by other entities (stands in for the DB lookup)
             * @var array<string, true>
             */
            public array $takenPaths = [];

            /**
             * @param ResourceConnection $resource
             */
            public function __construct(ResourceConnection $resource)
            {
                $this->resourceConnection = $resource;
                $this->regenerateOptions = $this->defaultRegenerateOptions;
            }

            /**
             * @param array $rewrite
             * @return string|false
             */
            protected function _urlRewriteExists(array $rewrite): string|false
            {
                return isset($this->takenPaths[$rewrite['store_id'] . '|' . $rewrite['request_path']]) ? '1' : false;
            }
        };
    }

    /**
     * @return void
     */
    public function testSetRegenerateOptionsMergesOverDefaults(): void
    {
        $this->model->setRegenerateOptions(['productsFilter' => [12, 45]]);

        self::assertSame([12, 45], $this->option('productsFilter'));
        self::assertFalse($this->option('skipExisting'));
        self::assertArrayNotHasKey('checkUseCategoryInProductUrl', $this->options());
    }

    /**
     * @return void
     */
    public function testSetRegenerateOptionsDoesNotLeakKeysBetweenCalls(): void
    {
        $this->model->setRegenerateOptions(['saveOldUrls' => true]);
        $this->model->setRegenerateOptions(['regenUrlKey' => true]);

        self::assertFalse($this->option('saveOldUrls'));
        self::assertTrue($this->option('regenUrlKey'));
    }

    /**
     * @return void
     */
    public function testFailureDetailsAreCappedButCountsStayExact(): void
    {
        for ($i = 1; $i <= 1005; $i++) {
            $this->invoke('_addFailure', 'product', $i, 1, 'error ' . $i);
        }

        self::assertCount(1000, $this->model->getFailures());
        self::assertSame(['product' => 1005], $this->model->getFailureCounts());
        self::assertSame(
            ['entity_type' => 'product', 'entity_id' => 1, 'store_id' => 1, 'message' => 'error 1'],
            $this->model->getFailures()[0]
        );
    }

    /**
     * @return void
     */
    public function testResetFailuresClearsDetailsAndCounts(): void
    {
        $this->invoke('_addFailure', 'product', 1, 1, 'x');
        $this->model->resetFailures();

        self::assertSame([], $this->model->getFailures());
        self::assertSame([], $this->model->getFailureCounts());
    }

    /**
     * @return void
     */
    public function testSaveUrlRewritesWithOnlyEmptyPathsTouchesNothing(): void
    {
        $this->connection->expects(self::never())->method('beginTransaction');
        $this->connection->expects(self::never())->method('delete');
        $this->connection->expects(self::never())->method('insertOnDuplicate');

        $this->model->saveUrlRewrites(
            [$this->rewrite(1, '   ')],
            [['entity_type' => 'product', 'entity_id' => 5, 'store_id' => 1]]
        );

        self::assertSame([], $this->model->getFailures());
    }

    /**
     * @return void
     */
    public function testSaveUrlRewritesRollbackRecordsOneFailurePerAffectedEntity(): void
    {
        $this->connection->method('insertOnDuplicate')->willThrowException(new \Exception('boom'));
        $this->connection->expects(self::once())->method('rollBack');
        $this->connection->expects(self::never())->method('commit');

        $this->model->saveUrlRewrites([
            $this->rewrite(1, 'a.html', 'category', 10),
            $this->rewrite(1, 'b.html', 'category', 10),
            $this->rewrite(1, 'c.html', 'category', 11),
        ]);

        $failures = $this->model->getFailures();
        self::assertSame([10, 11], array_column($failures, 'entity_id'));
        self::assertStringContainsString('boom', $failures[0]['message']);
    }

    /**
     * @return void
     */
    public function testSaveUrlRewritesWithoutEntityDataReplacesRowsOfTheGeneratedStores(): void
    {
        $this->connection->expects(self::once())->method('delete')->with(
            'url_rewrite',
            self::callback(fn (string $where): bool => str_contains($where, "store_id = '1'")
                && str_contains($where, "store_id = '2'")
                && !str_contains($where, "store_id = '0'"))
        );
        $this->connection->expects(self::once())->method('commit');

        $this->model->saveUrlRewrites([$this->rewrite(1, 'item.html'), $this->rewrite(2, 'item.html')]);
    }

    /**
     * @return void
     */
    public function testPrepareKeepsCustomRowsVerbatim(): void
    {
        $result = $this->prepare([
            $this->rewrite(1, 'item.html'),
            $this->rewrite(1, '.well-known//x', 'product', 1, 'catalog/product/view/id/1', 0, false),
        ]);

        self::assertNotNull($this->find($result, 1, '.well-known//x'));
        self::assertNotNull($this->find($result, 1, 'item.html'));
    }

    /**
     * @return void
     */
    public function testPrepareAddsDedupIndexWhenPathBelongsToAnotherEntity(): void
    {
        $this->model->takenPaths = ['1|item.html' => true];

        self::assertNotNull($this->find($this->prepare([$this->rewrite(1, 'item.html')]), 1, 'item-1.html'));
    }

    /**
     * @return void
     */
    public function testPrepareMovesGeneratedPathOffAPreservedPathOfTheSameEntity(): void
    {
        $result = $this->prepare([
            $this->rewrite(1, 'item.html'),
            $this->rewrite(1, 'item.html', 'product', 1, 'cms-page', 302, false),
        ]);

        self::assertTrue((bool)$this->find($result, 1, 'item-1.html')['is_autogenerated']);
        self::assertFalse((bool)$this->find($result, 1, 'item.html')['is_autogenerated']);
        self::assertCount(2, $result);
    }

    /**
     * @return void
     */
    public function testPrepareRetargetsPreservedRedirectsWithinTheirOwnStore(): void
    {
        $this->model->takenPaths = ['2|item.html' => true];

        $result = $this->prepare([
            $this->rewrite(1, 'item.html'),
            $this->rewrite(2, 'item.html'),
            $this->rewrite(1, 'old-1', 'product', 1, 'item.html', 301, false),
            $this->rewrite(2, 'old-2', 'product', 1, 'item.html', 301, false),
        ]);

        self::assertSame('item.html', $this->find($result, 1, 'old-1')['target_path']);
        self::assertSame('item-1.html', $this->find($result, 2, 'old-2')['target_path']);
    }

    /**
     * @return void
     */
    public function testPrepareLeavesNonRedirectCustomTargetsAlone(): void
    {
        $this->model->takenPaths = ['1|item.html' => true];

        $result = $this->prepare([
            $this->rewrite(1, 'item.html'),
            $this->rewrite(1, 'custom', 'product', 1, 'item.html', 0, false),
        ]);

        self::assertSame('item.html', $this->find($result, 1, 'custom')['target_path']);
    }

    /**
     * @param int $storeId
     * @param string $requestPath
     * @param string $entityType
     * @param int $entityId
     * @param string $targetPath
     * @param int $redirectType
     * @param bool $autogenerated
     * @return UrlRewrite
     */
    private function rewrite(
        int $storeId,
        string $requestPath,
        string $entityType = 'product',
        int $entityId = 1,
        string $targetPath = 'catalog/product/view/id/1',
        int $redirectType = 0,
        bool $autogenerated = true
    ): UrlRewrite {
        return (new UrlRewrite([], new Json()))
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setStoreId($storeId)
            ->setRequestPath($requestPath)
            ->setTargetPath($targetPath)
            ->setRedirectType($redirectType)
            ->setIsAutogenerated((int)$autogenerated);
    }

    /**
     * @param UrlRewrite[] $rewrites
     * @return array
     */
    private function prepare(array $rewrites): array
    {
        return $this->invoke('_prepareUrlRewrites', $rewrites);
    }

    /**
     * @param array $rows
     * @param int $storeId
     * @param string $requestPath
     * @return array|null
     */
    private function find(array $rows, int $storeId, string $requestPath): ?array
    {
        foreach ($rows as $row) {
            if ((int)$row['store_id'] === $storeId && $row['request_path'] === $requestPath) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function option(string $key): mixed
    {
        return $this->options()[$key];
    }

    /**
     * @return array
     */
    private function options(): array
    {
        return (fn (): array => $this->regenerateOptions)->call($this->model);
    }

    /**
     * @param string $method
     * @param mixed ...$args
     * @return mixed
     */
    private function invoke(string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod($this->model, $method))->invoke($this->model, ...$args);
    }
}
