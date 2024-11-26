<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Event;

use JMS\JobQueueBundle\Entity\Job;

class NewOutputEvent extends JobEvent
{
    public const TYPE_STDOUT = 1;
    public const TYPE_STDERR = 2;

    public function __construct(Job $job, private $newOutput, private $type = self::TYPE_STDOUT)
    {
        parent::__construct($job);
    }

    public function getNewOutput()
    {
        return $this->newOutput;
    }

    public function setNewOutput($output): void
    {
        $this->newOutput = $output;
    }

    public function getType()
    {
        return $this->type;
    }
}
