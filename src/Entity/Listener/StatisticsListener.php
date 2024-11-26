<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Entity\Listener;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use JMS\JobQueueBundle\Entity\Job;

class StatisticsListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $generateSchemaEventArgs): void
    {
        $schema = $generateSchemaEventArgs->getSchema();

        // When using multiple entity managers ignore events that are triggered by other entity managers.
        if ($generateSchemaEventArgs->getEntityManager()->getMetadataFactory()->isTransient(Job::class)) {
            return;
        }

        $table = $schema->createTable('jms_job_statistics');
        $table->addColumn('job_id', 'bigint', ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('characteristic', 'string', ['length' => 30, 'notnull' => true]);
        $table->addColumn('createdAt', 'datetime', ['notnull' => true]);
        $table->addColumn('charValue', 'float', ['notnull' => true]);
        $table->setPrimaryKey(['job_id', 'characteristic', 'createdAt']);
    }
}
