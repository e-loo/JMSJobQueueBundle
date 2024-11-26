<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Cron;

use DateTime;
use JMS\JobQueueBundle\Entity\Job;

interface JobScheduler
{
    /**
     * Returns an array of commands managed by this scheduler.
     *
     * @return string[]
     */
    public function getCommands(): array;

    /**
     * Returns whether to schedule the given command again.
     */
    public function shouldSchedule(string $command, DateTime $lastRunAt): bool;

    /**
     * Creates the given command when it is scheduled.
     */
    public function createJob(string $command, DateTime $lastRunAt): Job;
}
