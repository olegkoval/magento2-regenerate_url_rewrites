<?php
/**
 * ConsoleProgressReporterTest.php
 *
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2026
 */

namespace OlegKoval\RegenerateUrlRewrites\Test\Unit\Console;

use OlegKoval\RegenerateUrlRewrites\Console\ConsoleProgressReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class ConsoleProgressReporterTest extends TestCase
{
    /**
     * @return void
     */
    public function testBarIsDrawnInTheCommandsFormatAndEndsItsLineAtTheTotal(): void
    {
        $output = new BufferedOutput();
        $reporter = new ConsoleProgressReporter($output);

        $reporter->start('product', 1, 2);
        $reporter->advance();
        $reporter->advance();
        $reporter->finish();

        self::assertSame(
            "\r[>" . str_repeat(' ', 70) . "] 0%  0/2"
            . "\r[" . str_repeat('=', 35) . '>' . str_repeat(' ', 35) . "] 50%  1/2"
            . "\r[" . str_repeat('=', 71) . "] 100%  2/2\r\n",
            $output->fetch()
        );
    }

    /**
     * @return void
     */
    public function testAnEmptyPassDrawsAFullBar(): void
    {
        $output = new BufferedOutput();
        $reporter = new ConsoleProgressReporter($output);

        $reporter->start('category', 0, 0);
        $reporter->advance();
        $reporter->finish();

        self::assertSame("\r[" . str_repeat('=', 71) . "] 100%  0/0\r\n", $output->fetch());
    }

    /**
     * @return void
     */
    public function testAPassEndingShortOfTheTotalStillEndsItsLine(): void
    {
        $output = new BufferedOutput();
        $reporter = new ConsoleProgressReporter($output);

        $reporter->start('product', 1, 4);
        $reporter->advance();
        $reporter->finish();
        $reporter->message('next');

        self::assertStringEndsWith("1/4\r\nnext\n", $output->fetch());
    }

    /**
     * @return void
     */
    public function testWithoutABarOnlyMessagesArePrinted(): void
    {
        $output = new BufferedOutput();
        $reporter = new ConsoleProgressReporter($output, false);

        $reporter->start('product', 1, 2);
        $reporter->advance(2);
        $reporter->finish();
        $reporter->message('Reindexation...', false);
        $reporter->message(' Done');

        self::assertSame("Reindexation... Done\n", $output->fetch());
    }
}
