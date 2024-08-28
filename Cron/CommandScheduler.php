<?php

namespace JMS\JobQueueBundle\Cron;

use JMS\JobQueueBundle\Console\CronCommand;
use JMS\JobQueueBundle\Entity\Job;

class CommandScheduler implements JobScheduler
{
    public function __construct(private readonly string $name, private readonly CronCommand $command)
    {
    }

    public function getCommands(): array
    {
        return [$this->name];
    }

    public function shouldSchedule(string $_, \DateTime $lastRunAt): bool
    {
        return $this->command->shouldBeScheduled($lastRunAt);
    }

    public function createJob(string $_, \DateTime $lastRunAt): Job
    {
        return $this->command->createCronJob($lastRunAt);
    }
}