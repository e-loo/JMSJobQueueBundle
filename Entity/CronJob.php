<?php

namespace JMS\JobQueueBundle\Entity;

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

    #[ORM\Column(name: 'lastRunAt', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    private \DateTime $lastRunAt;

    public function __construct(
        #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 200, unique: true)]
        private $command
    ) {
        $this->lastRunAt = new \DateTime();
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getLastRunAt()
    {
        return $this->lastRunAt;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}
