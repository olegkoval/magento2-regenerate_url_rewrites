<?php
/**
 * AdminFeedTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model\Notification;

use Magento\Framework\App\CacheInterface;
use OlegKoval\RegenerateUrlRewrites\Model\Notification\AdminFeed;
use OlegKoval\RegenerateUrlRewrites\Model\Notification\FeedSource;
use PHPUnit\Framework\TestCase;

class AdminFeedTest extends TestCase
{
    /**
     * @return void
     */
    public function testUsesTheExtensionsFeedAndItsOwnDailyLastCheck(): void
    {
        $feedSource = $this->createMock(FeedSource::class);
        $feedSource->method('getFeedUrl')->willReturn('https://feed.test/n.xml');
        $feedSource->method('fetch')->with(FeedSource::CHANNEL_ADMIN)->willReturn(null);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->with('olegkoval_regenerateurlrewrites_notifications_lastcheck')->willReturn('1700000000');
        $cache->expects(self::once())->method('save')
            ->with(self::isType('string'), 'olegkoval_regenerateurlrewrites_notifications_lastcheck');

        // bypass the constructor: its generated factories (InboxFactory, CurlFactory) don't exist in CI
        $feed = (new \ReflectionClass(AdminFeed::class))->newInstanceWithoutConstructor();
        (function () use ($feedSource, $cache): void {
            $this->feedSource = $feedSource;
            $this->_cacheManager = $cache;
        })->call($feed);

        self::assertSame('https://feed.test/n.xml', $feed->getFeedUrl());
        self::assertSame(86400, $feed->getFrequency());
        self::assertSame(1700000000, $feed->getLastUpdate());
        self::assertFalse($feed->getFeedData());
        $feed->setLastUpdate();
    }
}
