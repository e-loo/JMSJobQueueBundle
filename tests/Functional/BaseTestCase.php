<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Tests\Functional;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class BaseTestCase extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        $config = $options['config'] ?? 'default.yml';

        return new AppKernel($config);
    }

    final protected function importDatabaseSchema()
    {
        foreach (self::$kernel->getContainer()->get('doctrine')->getManagers() as $manager) {
            $this->importSchemaForEm($manager);
        }
    }

    private function importSchemaForEm(EntityManager $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        if (!empty($metadata)) {
            $schemaTool = new SchemaTool($entityManager);
            $schemaTool->createSchema($metadata);
        }
    }
}
