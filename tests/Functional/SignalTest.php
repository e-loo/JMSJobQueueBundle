<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SignalTest extends TestCase
{
    public function testControlledExit(): void
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('PCNTL extension is not loaded.');
        }

        $process = new Process('exec '.PHP_BINARY.' '.escapeshellarg(__DIR__.'/console').' jms-job-queue:run --worker-name=test --verbose --max-runtime=999999');
        $process->start();

        usleep(5E5);

        $this->assertTrue($process->isRunning(), 'Process exited prematurely: '.$process->getOutput().$process->getErrorOutput());
        $this->assertTrueWithin(
            3,
            fn (): bool => str_contains($process->getOutput(), 'Signal Handlers have been installed'),
            function () use ($process): void {
                $this->fail('Signal handlers were not installed: '.$process->getOutput().$process->getErrorOutput());
            }
        );

        $process->signal(SIGTERM);

        $this->assertTrueWithin(
            3,
            fn (): bool => str_contains($process->getOutput(), 'Received SIGTERM'),
            function () use ($process): void {
                $this->fail('Signal was not received by process within 3 seconds: '.$process->getOutput().$process->getErrorOutput());
            }
        );

        $this->assertTrueWithin(
            3,
            fn (): bool => !$process->isRunning(),
            function () use ($process): void {
                $this->fail('Process did not terminate within 3 seconds: '.$process->getOutput().$process->getErrorOutput());
            }
        );

        $this->assertContains('All jobs finished, exiting.', $process->getOutput());
    }

    private function assertTrueWithin(int $seconds, callable $block, callable $failureHandler): void
    {
        $start = microtime(true);
        while (true) {
            if ($block()) {
                break;
            }

            if (microtime(true) - $start >= $seconds) {
                $failureHandler();
                $this->fail('Failure handler did not raise an exception.');
            }

            usleep(2E5);
        }
    }
}
