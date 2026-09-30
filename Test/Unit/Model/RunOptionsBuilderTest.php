<?php
/**
 * RunOptionsBuilderTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model;

use OlegKoval\RegenerateUrlRewrites\Model\RunOptionsBuilder;
use PHPUnit\Framework\TestCase;

class RunOptionsBuilderTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefaultsMatchAPlainCommandRun(): void
    {
        $options = (new RunOptionsBuilder())->create();

        self::assertSame('product', $options->getEntityType());
        self::assertSame([], $options->getStoreIds());
        self::assertSame([], $options->getProductIds());
        self::assertSame([], $options->getCategoryIds());
        self::assertFalse($options->isExactCategories());
        self::assertFalse($options->isSaveOldUrls());
        self::assertFalse($options->isRegenUrlKey());
        self::assertFalse($options->isSkipExisting());
        self::assertFalse($options->isSkipProducts());
        self::assertFalse($options->isIncludeNotVisible());
        self::assertFalse($options->isAddSkuToUrl());
        self::assertFalse($options->isDeleteOrphanedRewrites());
        self::assertNull($options->getProductUrlSuffix());
        self::assertNull($options->getCategoryUrlSuffix());
        self::assertTrue($options->isReindex());
        self::assertTrue($options->isCleanCache());
        self::assertTrue($options->isFlushCache());
    }

    /**
     * @return void
     */
    public function testEverySetterReachesItsGetter(): void
    {
        $options = (new RunOptionsBuilder())
            ->setEntityType('category')
            ->setStoreIds([2])
            ->setProductIds([3])
            ->setCategoryIds([4])
            ->setExactCategories(true)
            ->setSaveOldUrls(true)
            ->setRegenUrlKey(true)
            ->setSkipExisting(true)
            ->setSkipProducts(true)
            ->setIncludeNotVisible(true)
            ->setAddSkuToUrl(true)
            ->setDeleteOrphanedRewrites(true)
            ->setProductUrlSuffix('.htm')
            ->setCategoryUrlSuffix('')
            ->setReindex(false)
            ->setCleanCache(false)
            ->setFlushCache(false)
            ->create();

        self::assertSame('category', $options->getEntityType());
        self::assertSame([2], $options->getStoreIds());
        self::assertSame([3], $options->getProductIds());
        self::assertSame([4], $options->getCategoryIds());
        self::assertTrue($options->isExactCategories());
        self::assertTrue($options->isSaveOldUrls());
        self::assertTrue($options->isRegenUrlKey());
        self::assertTrue($options->isSkipExisting());
        self::assertTrue($options->isSkipProducts());
        self::assertTrue($options->isIncludeNotVisible());
        self::assertTrue($options->isAddSkuToUrl());
        self::assertTrue($options->isDeleteOrphanedRewrites());
        self::assertSame('.htm', $options->getProductUrlSuffix());
        self::assertSame('', $options->getCategoryUrlSuffix());
        self::assertFalse($options->isReindex());
        self::assertFalse($options->isCleanCache());
        self::assertFalse($options->isFlushCache());
    }

    /**
     * @return void
     */
    public function testIdsBecomeUniqueIntegersInTheirOrder(): void
    {
        $options = (new RunOptionsBuilder())->setProductIds(['7', 3, 7, '3'])->create();

        self::assertSame([7, 3], $options->getProductIds());
    }

    /**
     * @return void
     */
    public function testEveryOptionRoundTripsThroughAnArray(): void
    {
        $values = [
            'entityType' => 'category',
            'storeIds' => [2, 1],
            'productIds' => [],
            'categoryIds' => [4, 9],
            'exactCategories' => true,
            'saveOldUrls' => true,
            'regenUrlKey' => true,
            'skipExisting' => true,
            'skipProducts' => true,
            'includeNotVisible' => true,
            'addSkuToUrl' => true,
            'deleteOrphanedRewrites' => true,
            'productUrlSuffix' => '.htm',
            'categoryUrlSuffix' => '',
            'reindex' => false,
            'cleanCache' => false,
            'flushCache' => false,
        ];

        $options = (new RunOptionsBuilder())->fromArray($values)->create();

        self::assertEquals($values, $options->toArray());
        self::assertSame(
            array_keys((new RunOptionsBuilder())->create()->toArray()),
            array_keys($values),
            'every option is in the array'
        );
        self::assertEquals($options, (new RunOptionsBuilder())->fromArray($options->toArray())->create());
    }

    /**
     * @return void
     */
    public function testMissingKeysKeepDefaultsAndIdStringsBecomeIntegers(): void
    {
        $options = (new RunOptionsBuilder())->fromArray(['productIds' => ['5', 6]])->create();

        self::assertSame([5, 6], $options->getProductIds());
        self::assertTrue($options->isReindex());
    }

    /**
     * @return array<string, array{0: array}>
     */
    public static function invalidArrays(): array
    {
        return [
            'typo in a key' => [['productId' => [5]]],
            'bool as string' => [['saveOldUrls' => 'yes']],
            'ids not a list of IDs' => [['productIds' => ['5a']]],
            'ids not an array' => [['storeIds' => 1]],
            'suffix not a string' => [['productUrlSuffix' => 1]],
            'entity type not a string' => [['entityType' => null]],
        ];
    }

    /**
     * @dataProvider invalidArrays
     * @param array $values
     * @return void
     */
    public function testAnInvalidArrayIsRejectedAndSetsNothing(array $values): void
    {
        $builder = (new RunOptionsBuilder())->setProductIds([7]);

        try {
            $builder->fromArray($values + ['skipExisting' => true]);
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
            $options = $builder->create();
            self::assertSame([7], $options->getProductIds());
            self::assertFalse($options->isSkipExisting());
        }
    }

    /**
     * @return void
     */
    public function testCreateResetsTheBuilder(): void
    {
        $builder = new RunOptionsBuilder();
        $first = $builder->setStoreIds([1])->setSaveOldUrls(true)->create();
        $second = $builder->create();

        self::assertSame([1], $first->getStoreIds());
        self::assertSame([], $second->getStoreIds());
        self::assertFalse($second->isSaveOldUrls());
    }
}
