<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'jms_cron_jobs')]
class CronJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private $id;

    #[ORM\Column(name: 'lastRunAt', type: Types::DATETIME_MUTABLE)]
    private DateTime $lastRunAt;

    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 200, unique: true)]
        private $command
    ) {
        $this->lastRunAt = new DateTime();
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getLastRunAt(): DateTime
    {
        return $this->lastRunAt;
    }

    public function getId()
    {
        return $this->id;
    }
}
