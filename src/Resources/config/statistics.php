<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

use JMS\JobQueueBundle\Entity\Listener\StatisticsListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('jms_job_queue.entity.statistics_listener.class', StatisticsListener::class);

    $services = $containerConfigurator->services();

    $services->set('jms_job_queue.entity.statistics_listener', '%jms_job_queue.entity.statistics_listener.class%')
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postGenerateSchema']);
};
