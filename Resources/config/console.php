<?php

use Doctrine\ORM\EntityManagerInterface;
use JMS\JobQueueBundle\Command\CleanUpCommand;
use JMS\JobQueueBundle\Command\MarkJobIncompleteCommand;
use JMS\JobQueueBundle\Command\RunCommand;
use JMS\JobQueueBundle\Command\ScheduleCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->private();

    $services->set('jms_job_queue.command.clean_up', CleanUpCommand::class)
        ->tag('console.command')
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference('jms_job_queue.job_manager'),
        ]);

    $services->set('jms_job_queue.command.mark_job_incomplete', MarkJobIncompleteCommand::class)
        ->tag('console.command')
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference('jms_job_queue.job_manager'),
        ]);

    $services->set('jms_job_queue.command.run', RunCommand::class)
        ->tag('console.command')
        ->args([
            '$entityManager' => new Reference(EntityManagerInterface::class),
            '$jobManager' => new Reference('jms_job_queue.job_manager'),
            '$queueOptionsDefault' => '%jms_job_queue.queue_options_defaults%',
            '$queueOptions' => '%jms_job_queue.queue_options%',
        ]);

    $services->set('jms_job_queue.command.schedule', ScheduleCommand::class)
        ->tag('console.command')
        ->args([
            '$schedulers' => new TaggedIteratorArgument('jms_job_queue.scheduler'),
            '$cronCommands' => new TaggedIteratorArgument('jms_job_queue.cron_command'),
        ]);
};
