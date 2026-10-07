<?php
/**
 * AdminFeed.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model\Notification;

use Magento\AdminNotification\Model\Feed;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\App\ConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Escaper;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;

/**
 * Core's admin notification feed, reading the extension's feed instead of Magento's
 *
 * Core checkUpdate() stores new items in the admin inbox (bell); the newest unread critical one is shown in the
 * "Incoming Message" popup.
 */
class AdminFeed extends Feed
{
    private const LAST_CHECK_CACHE_ID = 'olegkoval_regenerateurlrewrites_notifications_lastcheck';

    private const FREQUENCY = 86400;

    /**
     * @var FeedSource
     */
    private FeedSource $feedSource;

    /**
     * @param FeedSource $feedSource
     * @param Context $context
     * @param Registry $registry
     * @param ConfigInterface $backendConfig
     * @param InboxFactory $inboxFactory
     * @param CurlFactory $curlFactory
     * @param DeploymentConfig $deploymentConfig
     * @param ProductMetadataInterface $productMetadata
     * @param UrlInterface $urlBuilder
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param Escaper|null $escaper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        FeedSource $feedSource,
        Context $context,
        Registry $registry,
        ConfigInterface $backendConfig,
        InboxFactory $inboxFactory,
        CurlFactory $curlFactory,
        DeploymentConfig $deploymentConfig,
        ProductMetadataInterface $productMetadata,
        UrlInterface $urlBuilder,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?Escaper $escaper = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $backendConfig,
            $inboxFactory,
            $curlFactory,
            $deploymentConfig,
            $productMetadata,
            $urlBuilder,
            $resource,
            $resourceCollection,
            $data,
            $escaper
        );
        $this->feedSource = $feedSource;
    }

    /**
     * @return string
     */
    public function getFeedUrl(): string
    {
        return $this->feedSource->getFeedUrl();
    }

    /**
     * @return int
     */
    public function getFrequency(): int
    {
        return self::FREQUENCY;
    }

    /**
     * @return int
     */
    public function getLastUpdate(): int
    {
        return (int)$this->_cacheManager->load(self::LAST_CHECK_CACHE_ID);
    }

    /**
     * @return static
     */
    public function setLastUpdate(): static
    {
        $this->_cacheManager->save((string)time(), self::LAST_CHECK_CACHE_ID);
        return $this;
    }

    /**
     * @return \SimpleXMLElement|false
     */
    public function getFeedData(): \SimpleXMLElement|false
    {
        return $this->feedSource->fetch(FeedSource::CHANNEL_ADMIN) ?? false;
    }
}
