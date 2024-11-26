<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Retry;

use DateTime;
use JMS\JobQueueBundle\Entity\Job;

class ExponentialRetryScheduler implements RetryScheduler
{
    public function __construct(private $base = 5)
    {
    }

    public function scheduleNextRetry(Job $originalJob): DateTime
    {
        return new DateTime('+'.($this->base ** count($originalJob->getRetryJobs())).' seconds');
    }
}
