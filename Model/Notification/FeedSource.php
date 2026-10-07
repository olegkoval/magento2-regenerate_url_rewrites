<?php
/**
 * FeedSource.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model\Notification;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientFactory;
use SimpleXMLElement;

/**
 * Fetches the extension's notification feed (RSS 2.0) and keeps the items meant for this edition and channel
 *
 * An item is kept when one of its <category> values is in $audiences (no category: "all") and its <channels>
 * (comma-separated, "admin" when absent) contains the requested channel.
 */
class FeedSource
{
    /**
     * Yes/No field added to core's Stores > Configuration > Advanced > System > Notifications group
     */
    public const XML_PATH_ENABLED = 'system/adminnotification/olegkoval_regenerate_url_rewrites';

    public const CHANNEL_ADMIN = 'admin';

    public const CHANNEL_CONSOLE = 'console';

    public const AUDIENCE_ALL = 'all';

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var string
     */
    private string $feedUrl;

    /**
     * @var string[]
     */
    private array $audiences;

    /**
     * @param ClientFactory $clientFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param string $feedUrl
     * @param string[] $audiences named items, so another module can replace one in its di.xml
     */
    public function __construct(
        ClientFactory $clientFactory,
        ScopeConfigInterface $scopeConfig,
        string $feedUrl = '',
        array $audiences = []
    ) {
        $this->clientFactory = $clientFactory;
        $this->scopeConfig = $scopeConfig;
        $this->feedUrl = $feedUrl;
        $this->audiences = array_values($audiences);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->feedUrl !== '' && $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * @return string
     */
    public function getFeedUrl(): string
    {
        return $this->feedUrl;
    }

    /**
     * The feed with only the matching items, or null when it can't be fetched or parsed
     *
     * @param string $channel
     * @return SimpleXMLElement|null
     */
    public function fetch(string $channel): ?SimpleXMLElement
    {
        try {
            $client = $this->clientFactory->create();
            $client->setTimeout(2);
            // only a generic user agent: core's feed also sends the admin URL as referer, which must not leak
            $client->addHeader('User-Agent', 'magento2-regenerate-url-rewrites');
            $client->get($this->feedUrl);
            if ($client->getStatus() !== 200) {
                return null;
            }
            $body = $client->getBody();
        } catch (\Exception $e) {
            // the Curl client throws on network errors
            return null;
        }

        $xml = $this->parse($body);

        return $xml ? $this->filter($xml, $channel) : null;
    }

    /**
     * @param string $body
     * @return SimpleXMLElement|null
     */
    private function parse(string $body): ?SimpleXMLElement
    {
        // collect libxml errors instead of raising warnings for a broken body
        $useErrors = libxml_use_internal_errors(true);
        try {
            return new SimpleXMLElement($body, LIBXML_NONET);
        } catch (\Exception $e) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
        }
    }

    /**
     * @param SimpleXMLElement $xml
     * @param string $channel
     * @return SimpleXMLElement
     */
    public function filter(SimpleXMLElement $xml, string $channel): SimpleXMLElement
    {
        if (!$xml->channel || !$xml->channel->item) {
            return $xml;
        }

        $drop = [];
        foreach ($xml->channel->item as $item) {
            if (!$this->matches($item, $channel)) {
                $drop[] = $item;
            }
        }
        foreach ($drop as $item) {
            unset($item[0]);
        }

        return $xml;
    }

    /**
     * @param SimpleXMLElement $item
     * @param string $channel
     * @return bool
     */
    private function matches(SimpleXMLElement $item, string $channel): bool
    {
        $channels = array_map('trim', explode(',', (string)$item->channels));
        if ($channels === ['']) {
            $channels = [self::CHANNEL_ADMIN];
        }
        if (!in_array($channel, $channels, true)) {
            return false;
        }

        $categories = [];
        foreach ($item->category as $category) {
            $categories[] = trim((string)$category);
        }

        return (bool)array_intersect($categories ?: [self::AUDIENCE_ALL], $this->audiences);
    }
}
