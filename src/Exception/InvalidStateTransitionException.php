<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Exception;

use InvalidArgumentException;
use JMS\JobQueueBundle\Entity\Job;

class InvalidStateTransitionException extends InvalidArgumentException
{
    private readonly Job $job;
    private $newState;
    private readonly array $allowedStates;

    public function __construct(Job $job, $newState, array $allowedStates = [])
    {
        $msg = sprintf('The Job(id = %d) cannot change from "%s" to "%s". Allowed transitions: ', $job->getId(), $job->getState(), $newState);
        $msg .= [] !== $allowedStates ? '"'.implode('", "', $allowedStates).'"' : '#none#';
        parent::__construct($msg);

        $this->job = $job;
        $this->newState = $newState;
        $this->allowedStates = $allowedStates;
    }

    public function getJob(): Job
    {
        return $this->job;
    }

    public function getNewState()
    {
        return $this->newState;
    }

    public function getAllowedStates(): array
    {
        return $this->allowedStates;
    }
}
