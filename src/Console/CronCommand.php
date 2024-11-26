<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

declare(strict_types=1);

namespace JMS\JobQueueBundle\Console;

use DateTime;
use JMS\JobQueueBundle\Entity\Job;

interface CronCommand
{
    /**
     * Returns the job when this command is scheduled.
     */
    public function createCronJob(DateTime $lastRunAt): Job;

    /**
     * Returns whether this command should be scheduled.
     */
    public function shouldBeScheduled(DateTime $lastRunAt): bool;
}
