<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Tests\Functional;

use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

class CronTest extends BaseTestCase
{
    private Application $application;

    public function testSchedulesCommands(): void
    {
        $output = $this->doRun(['--min-job-interval' => 1, '--max-runtime' => 12]);
        $this->assertSame(2, substr_count((string) $output, 'Scheduling command scheduled-every-few-seconds'), $output);
    }

    protected function setUp(): void
    {
        $this->createClient(['config' => 'persistent_db.yml']);

        if (is_file($databaseFile = self::$kernel->getCacheDir().'/database.sqlite')) {
            unlink($databaseFile);
        }

        $this->importDatabaseSchema();

        $this->application = new Application(self::$kernel);
        $this->application->setAutoExit(false);
        $this->application->setCatchExceptions(false);

        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManagerForClass(Job::class);
    }

    private function doRun(array $args = [])
    {
        array_unshift($args, 'jms-job-queue:schedule');
        $memoryOutput = new MemoryOutput();
        $this->application->run(new ArrayInput($args), $memoryOutput);

        return $memoryOutput->getOutput();
    }
}
