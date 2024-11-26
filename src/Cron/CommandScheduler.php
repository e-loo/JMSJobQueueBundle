<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Cron;

use DateTime;
use JMS\JobQueueBundle\Console\CronCommand;
use JMS\JobQueueBundle\Entity\Job;

class CommandScheduler implements JobScheduler
{
    public function __construct(private readonly string $name, private readonly CronCommand $cronCommand)
    {
    }

    public function getCommands(): array
    {
        return [$this->name];
    }

    public function shouldSchedule(string $command, DateTime $lastRunAt): bool
    {
        return $this->cronCommand->shouldBeScheduled($lastRunAt);
    }

    public function createJob(string $command, DateTime $lastRunAt): Job
    {
        return $this->cronCommand->createCronJob($lastRunAt);
    }
}
