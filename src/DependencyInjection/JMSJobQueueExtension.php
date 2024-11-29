<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\DependencyInjection;

use JMS\JobQueueBundle\Console\CronCommand;
use JMS\JobQueueBundle\Cron\JobScheduler;
use JMS\JobQueueBundle\Entity\Type\SafeObjectType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class JMSJobQueueExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $phpFileLoader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $phpFileLoader->load('services.php');
        $phpFileLoader->load('console.php');

        $container->setParameter('jms_job_queue.statistics', $config['statistics']);
        if ($config['statistics']) {
            $phpFileLoader->load('statistics.php');
        }

        $container->registerForAutoconfiguration(JobScheduler::class)
            ->addTag('jms_job_queue.scheduler');
        $container->registerForAutoconfiguration(CronCommand::class)
            ->addTag('jms_job_queue.cron_command');

        $container->setParameter('jms_job_queue.queue_options_defaults', $config['queue_options_defaults']);
        $container->setParameter('jms_job_queue.queue_options', $config['queue_options']);
    }

    public function prepend(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->prependExtensionConfig('doctrine', ['dbal' => ['types' => ['jms_job_safe_object' => ['class' => SafeObjectType::class]]]]);
    }
}
