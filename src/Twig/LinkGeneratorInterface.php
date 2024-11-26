<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Twig;

interface LinkGeneratorInterface
{
    public function supports($entity);

    public function generate($entity);

    public function getLinkname($entity);
}
