<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class LinkGeneratorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder): void
    {
        $generators = [];
        foreach (array_keys($containerBuilder->findTaggedServiceIds('jms_job_queue.link_generator')) as $id) {
            $generators[] = new Reference($id);
        }

        $containerBuilder->getDefinition('jms_job_queue.twig.extension')
                ->addArgument($generators);
    }
}
