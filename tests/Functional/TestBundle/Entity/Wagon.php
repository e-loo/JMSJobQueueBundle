<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Tests\Functional\TestBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Train;

#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'wagons')]
class Wagon
{
    #[ORM\Id] // @ORM\GeneratedValue(strategy = "AUTO") @ORM\Column(type = "integer")
    public $id;

    #[ORM\ManyToOne(targetEntity: Train::class)]
    public $train;

    #[ORM\Column(type: Types::STRING)]
    public string|null $state = 'new';
}
