<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

declare(strict_types=1);

namespace JMS\JobQueueBundle\Console;

trait ScheduleEveryOtherMinute
{
    use ScheduleInSecondInterval;

    protected function getScheduleInterval(): int
    {
        return 120;
    }
}
