<?php
/**
 * ConsoleProgressReporter.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Console;

use OlegKoval\RegenerateUrlRewrites\Api\ProgressReporterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Draws the command's progress bar ("[=====>   ] 42%  420/1000") and prints status lines
 *
 * @api
 */
class ConsoleProgressReporter implements ProgressReporterInterface
{
    /**
     * Bar width in characters
     */
    private const SIZE = 70;

    /**
     * @var OutputInterface
     */
    private OutputInterface $output;

    /**
     * @var bool
     */
    private bool $showBar;

    /**
     * @var int
     */
    private int $progress = 0;

    /**
     * @var int
     */
    private int $total = 0;

    /**
     * the current bar has ended its line
     * @var bool
     */
    private bool $lineEnded = true;

    /**
     * @param OutputInterface $output
     * @param bool $showBar false (--no-progress): status lines only
     */
    public function __construct(OutputInterface $output, bool $showBar = true)
    {
        $this->output = $output;
        $this->showBar = $showBar;
    }

    /**
     * @param string $entityType
     * @param int $storeId
     * @param int $total
     * @return void
     */
    public function start(string $entityType, int $storeId, int $total): void
    {
        if (!$this->showBar) {
            return;
        }

        $this->progress = 0;
        $this->total = $total;
        $this->lineEnded = false;

        if ($total === 0) {
            $this->_write("\r[" . str_repeat('=', self::SIZE + 1) . "] 100%  0/0\r\n");
            $this->lineEnded = true;
            return;
        }

        $this->_draw();
    }

    /**
     * @param int $steps
     * @return void
     */
    public function advance(int $steps = 1): void
    {
        if (!$this->showBar || $this->lineEnded) {
            return;
        }

        $this->progress += $steps;
        // past the total (the collection grew during the run): keep the full bar
        if ($this->progress > $this->total) {
            return;
        }

        $this->_draw();
        if ($this->progress === $this->total) {
            $this->_write("\r\n");
            $this->lineEnded = true;
        }
    }

    /**
     * @return void
     */
    public function finish(): void
    {
        if ($this->showBar && !$this->lineEnded) {
            $this->_write("\r\n");
            $this->lineEnded = true;
        }
    }

    /**
     * @param string $text
     * @param bool $newLine
     * @return void
     */
    public function message(string $text, bool $newLine = true): void
    {
        $this->output->write($text, $newLine);
    }

    /**
     * @return void
     */
    private function _draw(): void
    {
        $perc = (float)($this->progress / $this->total);
        $bar = (int)floor($perc * self::SIZE);

        $statusBar = "\r[" . str_repeat('=', $bar);
        if ($bar < self::SIZE) {
            $statusBar .= '>' . str_repeat(' ', self::SIZE - $bar);
        } else {
            $statusBar .= '=';
        }
        $statusBar .= '] ' . number_format($perc * 100, 0) . "%  {$this->progress}/{$this->total}";

        $this->_write($statusBar);
    }

    /**
     * @param string $text
     * @return void
     */
    private function _write(string $text): void
    {
        $this->output->write($text, false, OutputInterface::OUTPUT_RAW);
    }
}
