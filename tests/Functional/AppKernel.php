<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Tests\Functional;

// Set-up composer auto-loading if Client is insulated.
call_user_func(function (): void {
    if (!is_file($autoloadFile = __DIR__.'/../../vendor/autoload.php')) {
        throw new LogicException('The autoload file "vendor/autoload.php" was not found. Did you run "composer install --dev"?');
    }

    require_once $autoloadFile;
});

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Doctrine\Common\Annotations\AnnotationRegistry;
use JMS\JobQueueBundle\JMSJobQueueBundle;
use JMS\JobQueueBundle\Tests\Functional\TestBundle\TestBundle;
use LogicException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    private $config;

    public function __construct($config)
    {
        parent::__construct('test', false);

        $filesystem = new Filesystem();
        if (!$filesystem->isAbsolutePath($config)) {
            $config = __DIR__.'/config/'.$config;
        }

        if (!is_file($config)) {
            throw new RuntimeException(sprintf('The config file "%s" does not exist.', $config));
        }

        $this->config = $config;
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new DoctrineBundle(), new DoctrineFixturesBundle(), new TwigBundle(), new TestBundle(), new JMSJobQueueBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load($this->config);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/JMSJobQueueBundle/'.substr(sha1((string) $this->config), 0, 6).'/cache';
    }

    public function getContainerClass(): string
    {
        return parent::getContainerClass().'_'.substr(sha1((string) $this->config), 0, 6);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/JMSJobQueueBundle/'.substr(sha1((string) $this->config), 0, 6).'/logs';
    }

    public function serialize()
    {
        return $this->config;
    }

    public function unserialize($config): void
    {
        $this->__construct($config);
    }
}
