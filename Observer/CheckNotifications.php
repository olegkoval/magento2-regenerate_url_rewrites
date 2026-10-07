<?php
/**
 * CheckNotifications.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Observer;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use OlegKoval\RegenerateUrlRewrites\Model\Notification\AdminFeedFactory;
use OlegKoval\RegenerateUrlRewrites\Model\Notification\FeedSource;
use Psr\Log\LoggerInterface;

/**
 * Checks the extension's notification feed on admin page loads, like core's own feed observer
 */
class CheckNotifications implements ObserverInterface
{
    /**
     * @var AdminFeedFactory
     */
    private AdminFeedFactory $adminFeedFactory;

    /**
     * @var FeedSource
     */
    private FeedSource $feedSource;

    /**
     * @var Session
     */
    private Session $authSession;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param AdminFeedFactory $adminFeedFactory
     * @param FeedSource $feedSource
     * @param Session $authSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        AdminFeedFactory $adminFeedFactory,
        FeedSource $feedSource,
        Session $authSession,
        LoggerInterface $logger
    ) {
        $this->adminFeedFactory = $adminFeedFactory;
        $this->feedSource = $feedSource;
        $this->authSession = $authSession;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->authSession->isLoggedIn() || !$this->feedSource->isEnabled()) {
            return;
        }

        try {
            $this->adminFeedFactory->create()->checkUpdate();
        } catch (\Exception $e) {
            // a feed problem must never break an admin page
            $this->logger->warning('Regenerate URL Rewrites notification feed: ' . $e->getMessage());
        }
    }
}
