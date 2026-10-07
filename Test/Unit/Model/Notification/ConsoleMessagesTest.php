<?php
/**
 * ConsoleMessagesTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model\Notification;

use Magento\Framework\FlagManager;
use OlegKoval\RegenerateUrlRewrites\Model\Notification\ConsoleMessages;
use OlegKoval\RegenerateUrlRewrites\Model\Notification\FeedSource;
use PHPUnit\Framework\TestCase;

class ConsoleMessagesTest extends TestCase
{
    /**
     * @return void
     */
    public function testAStaleCheckFetchesStoresAndReturnsTheNewestThree(): void
    {
        $items = '';
        foreach (['2026-10-01', '2026-10-04', '2026-10-02', '2026-10-03'] as $i => $date) {
            $items .= "<item><title> m$i </title><description>d$i</description><link>https://x/$i</link>"
                . "<pubDate>$date</pubDate></item>";
        }
        $feedSource = $this->feedSource(true);
        $feedSource->expects(self::once())->method('fetch')->with(FeedSource::CHANNEL_CONSOLE)
            ->willReturn(new \SimpleXMLElement("<rss><channel>$items</channel></rss>"));

        $saved = null;
        $flagManager = $this->createMock(FlagManager::class);
        $flagManager->method('getFlagData')->willReturn(['checked_at' => time() - 90000, 'messages' => []]);
        $flagManager->method('saveFlag')->willReturnCallback(function ($code, $value) use (&$saved): bool {
            $saved = $value;
            return true;
        });

        $messages = (new ConsoleMessages($feedSource, $flagManager))->getMessages();

        self::assertSame(['m1', 'm3', 'm2'], array_column($messages, 'title'));
        self::assertSame(['title' => 'm1', 'description' => 'd1', 'link' => 'https://x/1'], $messages[0]);
        self::assertSame($messages, $saved['messages']);
        self::assertEqualsWithDelta(time(), $saved['checked_at'], 5);
    }

    /**
     * @return void
     */
    public function testARecentCheckIsReusedWithoutAFetch(): void
    {
        $stored = [['title' => 'kept', 'description' => '', 'link' => '']];
        $feedSource = $this->feedSource(true);
        $feedSource->expects(self::never())->method('fetch');
        $flagManager = $this->createMock(FlagManager::class);
        $flagManager->method('getFlagData')->willReturn(['checked_at' => time() - 60, 'messages' => $stored]);

        self::assertSame($stored, (new ConsoleMessages($feedSource, $flagManager))->getMessages());
    }

    /**
     * @return void
     */
    public function testAFailedFetchIsStoredSoItIsNotRetriedOnEveryRun(): void
    {
        $feedSource = $this->feedSource(true);
        $feedSource->method('fetch')->willReturn(null);
        $flagManager = $this->createMock(FlagManager::class);
        $flagManager->method('getFlagData')->willReturn(null);
        $flagManager->expects(self::once())->method('saveFlag')
            ->with(self::anything(), self::callback(fn(array $value): bool => $value['messages'] === []));

        self::assertSame([], (new ConsoleMessages($feedSource, $flagManager))->getMessages());
    }

    /**
     * @return void
     */
    public function testDisabledOrBrokenGivesNothing(): void
    {
        $disabled = $this->feedSource(false);
        $disabled->expects(self::never())->method('fetch');
        self::assertSame([], (new ConsoleMessages($disabled, $this->createMock(FlagManager::class)))->getMessages());

        $flagManager = $this->createMock(FlagManager::class);
        $flagManager->method('getFlagData')->willThrowException(new \Exception('table missing'));
        self::assertSame([], (new ConsoleMessages($this->feedSource(true), $flagManager))->getMessages());
    }

    /**
     * @param bool $enabled
     * @return FeedSource&\PHPUnit\Framework\MockObject\MockObject
     */
    private function feedSource(bool $enabled): FeedSource
    {
        $feedSource = $this->createMock(FeedSource::class);
        $feedSource->method('isEnabled')->willReturn($enabled);

        return $feedSource;
    }
}
