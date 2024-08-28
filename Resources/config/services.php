<?php

use Doctrine\ORM\EntityManagerInterface;
use JMS\JobQueueBundle\Entity\Listener\ManyToAnyListener;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use JMS\JobQueueBundle\Retry\ExponentialRetryScheduler;
use JMS\JobQueueBundle\Twig\JobQueueExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return function (ContainerConfigurator $configurator) {

    $parameters = $configurator->parameters();
    $parameters->set('jms_job_queue.entity.many_to_any_listener.class', ManyToAnyListener::class);
    $parameters->set('jms_job_queue.twig.extension.class', JobQueueExtension::class);
    $parameters->set('jms_job_queue.retry_scheduler.class', ExponentialRetryScheduler::class);
    $parameters->set('jms_job_queue.job_manager.class', JobManager::class);

    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->private();

    $services->set('jms_job_queue.retry_scheduler', '%jms_job_queue.retry_scheduler.class%');

    $services->set('jms_job_queue.entity.many_to_any_listener', '%jms_job_queue.entity.many_to_any_listener.class%')
        ->args([new Reference(EntityManagerInterface::class)])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postGenerateSchema'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postLoad'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postPersist'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'preRemove']);

    $services->set('jms_job_queue.twig.extension', '%jms_job_queue.twig.extension.class%')
        ->tag('twig.extension');

    $services->set('jms_job_queue.job_manager', '%jms_job_queue.job_manager.class%')
        ->public()
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference('event_dispatcher'),
            new Reference('jms_job_queue.retry_scheduler'),
        ]);
};
