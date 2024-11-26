<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

declare(strict_types=1);

namespace JMS\JobQueueBundle\Console;

use DateTime;
use JMS\JobQueueBundle\Entity\Job;
use LogicException;
use Symfony\Component\Console\Command\Command;

trait ScheduleInSecondInterval
{
    public function shouldBeScheduled(DateTime $lastRunAt): bool
    {
        return time() - $lastRunAt->getTimestamp() >= $this->getScheduleInterval();
    }

    public function createCronJob(DateTime $_): Job
    {
        if (!$this instanceof Command) {
            throw new LogicException('This trait must be used in Symfony console commands only.');
        }

        $job = new Job($this->getName());
        $job->setMaxRuntime((int) min(300, $this->getScheduleInterval()));

        return $job;
    }

    abstract protected function getScheduleInterval(): int;
}
