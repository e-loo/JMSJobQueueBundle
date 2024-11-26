<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Event;

use JMS\JobQueueBundle\Entity\Job;
use Symfony\Contracts\EventDispatcher\Event;

abstract class JobEvent extends Event
{
    public function __construct(private readonly Job $job)
    {
    }

    public function getJob()
    {
        return $this->job;
    }
}
