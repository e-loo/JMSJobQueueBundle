<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

use Doctrine\ORM\EntityManagerInterface;
use JMS\JobQueueBundle\Entity\Listener\ManyToAnyListener;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use JMS\JobQueueBundle\Retry\ExponentialRetryScheduler;
use JMS\JobQueueBundle\Twig\JobQueueExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('jms_job_queue.entity.many_to_any_listener.class', ManyToAnyListener::class);
    $parameters->set('jms_job_queue.twig.extension.class', JobQueueExtension::class);
    $parameters->set('jms_job_queue.retry_scheduler.class', ExponentialRetryScheduler::class);
    $parameters->set('jms_job_queue.job_manager.class', JobManager::class);

    $configurator = $containerConfigurator->services()
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private();

    $configurator->set('jms_job_queue.retry_scheduler', '%jms_job_queue.retry_scheduler.class%');

    $configurator->set('jms_job_queue.entity.many_to_any_listener', '%jms_job_queue.entity.many_to_any_listener.class%')
        ->args([new Reference(EntityManagerInterface::class)])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postGenerateSchema'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postLoad'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postPersist'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'preRemove']);

    $configurator->set('jms_job_queue.twig.extension', '%jms_job_queue.twig.extension.class%');

    $configurator->set('jms_job_queue.job_manager', '%jms_job_queue.job_manager.class%')
        ->public()
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference('event_dispatcher'),
            new Reference('jms_job_queue.retry_scheduler'),
        ]);
};
