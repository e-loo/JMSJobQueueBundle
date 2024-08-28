<?php

namespace JMS\JobQueueBundle\Tests\Functional\TestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'wagons')]
class Wagon
{
    #[ORM\Id] // @ORM\GeneratedValue(strategy = "AUTO") @ORM\Column(type = "integer")
    public $id;

    #[ORM\ManyToOne(targetEntity: \Train::class)]
    public $train;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING)]
    public ?string $state = 'new';
}