<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Event;

use JMS\JobQueueBundle\Entity\Job;

class StateChangeEvent extends JobEvent
{
    public function __construct(Job $job, private $newState)
    {
        parent::__construct($job);
    }

    public function getNewState()
    {
        return $this->newState;
    }

    public function setNewState($state): void
    {
        $this->newState = $state;
    }

    public function getOldState()
    {
        return $this->getJob()->getState();
    }
}
