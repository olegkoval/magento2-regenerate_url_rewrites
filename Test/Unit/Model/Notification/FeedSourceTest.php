<?php
/**
 * FeedSourceTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Model\Notification;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\HTTP\ClientInterface;
use OlegKoval\RegenerateUrlRewrites\Model\Notification\FeedSource;
use PHPUnit\Framework\TestCase;

class FeedSourceTest extends TestCase
{
    private const FEED = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0"><channel><title>t</title>
<item><title>free admin</title><category>free</category></item>
<item><title>paid admin</title><category>paid</category></item>
<item><title>everyone both</title><category>all</category><channels>admin, console</channels></item>
<item><title>free console</title><category>free</category><channels>console</channels></item>
<item><title>no category</title></item>
</channel></rss>
XML;

    /**
     * @return void
     */
    public function testKeepsOnlyTheEditionsAndChannelsItemsAndSendsNoReferer(): void
    {
        $headers = [];
        $client = $this->createMock(ClientInterface::class);
        $client->method('addHeader')->willReturnCallback(function ($name, $value) use (&$headers): void {
            $headers[$name] = $value;
        });
        $client->method('get')->with('https://feed.test/n.xml');
        $client->method('getStatus')->willReturn(200);
        $client->method('getBody')->willReturn(self::FEED);

        $free = $this->source($client, ['edition' => 'free', 'everyone' => 'all']);

        self::assertSame(
            ['free admin', 'everyone both', 'no category'],
            $this->titles($free->fetch(FeedSource::CHANNEL_ADMIN))
        );
        self::assertSame(['User-Agent' => 'magento2-regenerate-url-rewrites'], $headers);
        self::assertSame(
            ['everyone both', 'free console'],
            $this->titles($free->fetch(FeedSource::CHANNEL_CONSOLE))
        );

        $paid = $this->source($client, ['edition' => 'paid', 'everyone' => 'all']);
        self::assertSame(
            ['paid admin', 'everyone both', 'no category'],
            $this->titles($paid->fetch(FeedSource::CHANNEL_ADMIN))
        );

        $paidOnly = $this->source($client, ['edition' => 'paid']);
        self::assertSame(['paid admin'], $this->titles($paidOnly->fetch(FeedSource::CHANNEL_ADMIN)));
    }

    /**
     * @return void
     */
    public function testAnUnreachableOrBrokenFeedGivesNull(): void
    {
        $notFound = $this->createMock(ClientInterface::class);
        $notFound->method('getStatus')->willReturn(404);
        $notFound->method('getBody')->willReturn(self::FEED);

        $broken = $this->createMock(ClientInterface::class);
        $broken->method('getStatus')->willReturn(200);
        $broken->method('getBody')->willReturn('<rss><channel>');

        $offline = $this->createMock(ClientInterface::class);
        $offline->method('get')->willThrowException(new \Exception('Could not resolve host'));

        foreach ([$notFound, $broken, $offline] as $client) {
            self::assertNull($this->source($client, ['free'])->fetch(FeedSource::CHANNEL_ADMIN));
        }
    }

    /**
     * @return void
     */
    public function testEnabledNeedsTheConfigFlagAndAUrl(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->with(FeedSource::XML_PATH_ENABLED)->willReturnOnConsecutiveCalls(true, false);
        $factory = $this->createMock(ClientFactory::class);

        self::assertTrue((new FeedSource($factory, $config, 'https://feed.test/n.xml'))->isEnabled());
        self::assertFalse((new FeedSource($factory, $config, 'https://feed.test/n.xml'))->isEnabled());
        self::assertFalse((new FeedSource($factory, $this->createMock(ScopeConfigInterface::class)))->isEnabled());
    }

    /**
     * @param ClientInterface $client
     * @param string[] $audiences
     * @return FeedSource
     */
    private function source(ClientInterface $client, array $audiences): FeedSource
    {
        $factory = $this->createMock(ClientFactory::class);
        $factory->method('create')->willReturn($client);

        return new FeedSource(
            $factory,
            $this->createMock(ScopeConfigInterface::class),
            'https://feed.test/n.xml',
            $audiences
        );
    }

    /**
     * @param \SimpleXMLElement|null $xml
     * @return string[]
     */
    private function titles(?\SimpleXMLElement $xml): array
    {
        $titles = [];
        foreach ($xml->channel->item as $item) {
            $titles[] = (string)$item->title;
        }

        return $titles;
    }
}
