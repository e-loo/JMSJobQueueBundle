<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * JMSJobQueueBundle Configuration.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('jms_job_queue');
        $nodeDefinition = $treeBuilder->getRootNode();

        $nodeDefinition
            ->children()
                ->booleanNode('statistics')->defaultTrue()->end();

        $defaultOptionsNode = $nodeDefinition
            ->children()
                ->arrayNode('queue_options_defaults')
                    ->addDefaultsIfNotSet();
        $this->addQueueOptions($defaultOptionsNode);

        $queueOptionsNode = $nodeDefinition
            ->children()
                ->arrayNode('queue_options')
                    ->useAttributeAsKey('queue')
                    ->prototype('array');
        $this->addQueueOptions($queueOptionsNode);

        return $treeBuilder;
    }

    private function addQueueOptions(ArrayNodeDefinition $arrayNodeDefinition): void
    {
        $arrayNodeDefinition
            ->children()
                ->scalarNode('max_concurrent_jobs')->end()
        ;
    }
}
