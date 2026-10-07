<?php
/**
 * ConsoleMessages.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Model\Notification;

use Magento\Framework\FlagManager;

/**
 * The feed's console items, fetched at most once a day
 *
 * Kept in the flag table, not the cache: the command cleans/flushes the cache at the end of every run, which would
 * otherwise mean a feed request per run.
 */
class ConsoleMessages
{
    private const FLAG_CODE = 'olegkoval_regenerateurlrewrites_console_messages';

    private const FREQUENCY = 86400;

    private const LIMIT = 3;

    /**
     * @var FeedSource
     */
    private FeedSource $feedSource;

    /**
     * @var FlagManager
     */
    private FlagManager $flagManager;

    /**
     * @param FeedSource $feedSource
     * @param FlagManager $flagManager
     */
    public function __construct(FeedSource $feedSource, FlagManager $flagManager)
    {
        $this->feedSource = $feedSource;
        $this->flagManager = $flagManager;
    }

    /**
     * Newest first; never throws, so a feed problem can't fail a run
     *
     * @return array<int, array{title: string, description: string, link: string}>
     */
    public function getMessages(): array
    {
        try {
            if (!$this->feedSource->isEnabled()) {
                return [];
            }

            $stored = $this->flagManager->getFlagData(self::FLAG_CODE);
            if (is_array($stored) && (int)($stored['checked_at'] ?? 0) + self::FREQUENCY > time()) {
                return $stored['messages'] ?? [];
            }

            $messages = $this->fetchMessages();
            // a failed fetch is stored too, so an unreachable feed is retried tomorrow, not on every run
            $this->flagManager->saveFlag(self::FLAG_CODE, ['checked_at' => time(), 'messages' => $messages]);

            return $messages;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return array<int, array{title: string, description: string, link: string}>
     */
    private function fetchMessages(): array
    {
        $xml = $this->feedSource->fetch(FeedSource::CHANNEL_CONSOLE);
        if (!$xml || !$xml->channel || !$xml->channel->item) {
            return [];
        }

        $messages = [];
        foreach ($xml->channel->item as $item) {
            $messages[] = [
                'published' => strtotime((string)$item->pubDate) ?: 0,
                'title' => trim((string)$item->title),
                'description' => trim((string)$item->description),
                'link' => trim((string)$item->link),
            ];
        }
        usort($messages, static fn(array $a, array $b): int => $b['published'] <=> $a['published']);

        return array_map(
            static fn(array $message): array => array_diff_key($message, ['published' => true]),
            array_slice($messages, 0, self::LIMIT)
        );
    }
}
