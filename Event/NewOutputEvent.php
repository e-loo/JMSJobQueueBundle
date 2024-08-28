<?php

/*
 * Copyright 2012 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\JobQueueBundle\Event;

use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Event\JobEvent;

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