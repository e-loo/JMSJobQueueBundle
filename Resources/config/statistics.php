<?php

use JMS\JobQueueBundle\Entity\Listener\StatisticsListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator) {
    $parameters = $configurator->parameters();
    $parameters->set('jms_job_queue.entity.statistics_listener.class', StatisticsListener::class);

    $services = $configurator->services();

    $services->set('jms_job_queue.entity.statistics_listener', '%jms_job_queue.entity.statistics_listener.class%')
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postGenerateSchema']);
};
