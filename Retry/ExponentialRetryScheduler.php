<?php

namespace JMS\JobQueueBundle\Retry;

use JMS\JobQueueBundle\Entity\Job;

class ExponentialRetryScheduler implements RetryScheduler
{
    public function __construct(private $base = 5)
    {
    }

    public function scheduleNextRetry(Job $originalJob)
    {
        return new \DateTime('+'.($this->base ** count($originalJob->getRetryJobs())).' seconds');
    }
}