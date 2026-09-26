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
