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

    /**
     * @return void
     */
    public function testALineInTheMiddleOfABarClearsItAndRedrawsItBelow(): void
    {
        $output = new BufferedOutput();
        $reporter = new ConsoleProgressReporter($output);

        $reporter->start('product', 1, 2);
        $reporter->advance();
        $output->fetch();
        $reporter->line('  + product 1 (store 1): a.html');

        self::assertSame(
            "\r" . str_repeat(' ', 110) . "\r  + product 1 (store 1): a.html\n"
            . "\r[" . str_repeat('=', 35) . '>' . str_repeat(' ', 35) . "] 50%  1/2",
            $output->fetch()
        );
    }

    /**
     * @return void
     */
    public function testALineWithoutABarOrAfterItsEndIsAPlainLine(): void
    {
        $output = new BufferedOutput();
        (new ConsoleProgressReporter($output, false))->line('x');
        self::assertSame("x\n", $output->fetch());

        $reporter = new ConsoleProgressReporter($output);
        $reporter->start('product', 1, 1);
        $reporter->advance();
        $output->fetch();
        $reporter->line('y');
        self::assertSame("y\n", $output->fetch());
    }

    /**
     * @return void
     */
    public function testALineAfterAnOpenMessageLineStartsOnANewLine(): void
    {
        $output = new BufferedOutput();
        $reporter = new ConsoleProgressReporter($output);

        $reporter->message('Deleting orphaned url_rewrite rows...', false);
        $reporter->line('  - product 9 (store 1): a.html');
        $reporter->message(' Done');
        $reporter->line('x');

        self::assertSame(
            "Deleting orphaned url_rewrite rows...\n  - product 9 (store 1): a.html\n Done\nx\n",
            $output->fetch()
        );
    }
}
