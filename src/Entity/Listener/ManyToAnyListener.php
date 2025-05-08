<?php
/**
 * © Eloo <info@eloo.nl> This source file is subject to the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Entity\Listener;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use JMS\JobQueueBundle\Entity\Job;
use ReflectionProperty;
use RuntimeException;

/**
 * Provides many-to-any association support for jobs.
 *
 * This listener only implements the minimal support for this feature. For
 * example, currently we do not support any modification of a collection after
 * its initial creation.
 *
 * @see http://docs.jboss.org/hibernate/orm/4.1/javadocs/org/hibernate/annotations/ManyToAny.html
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ManyToAnyListener
{
    private readonly ReflectionProperty $reflectionProperty;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private ManagerRegistry $registry,
    ) {
        $this->reflectionProperty = new ReflectionProperty(Job::class, 'relatedEntities');
        $this->reflectionProperty->setAccessible(true);
    }

    public function postLoad(PostLoadEventArgs $postLoadEventArgs): void
    {
        $object = $postLoadEventArgs->getObject();
        if (!$object instanceof Job) {
            return;
        }

        $this->reflectionProperty->setValue($object, new PersistentRelatedEntitiesCollection($this->entityManager, $object));
    }

    public function preRemove(PreRemoveEventArgs $preRemoveEventArgs): void
    {
        $object = $preRemoveEventArgs->getObject();
        if (!$object instanceof Job) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $connection->executeUpdate('DELETE FROM jms_job_related_entities WHERE job_id = :id', ['id' => $object->getId()]);
    }

    public function postPersist(PostPersistEventArgs $postPersistEventArgs): void
    {
        $object = $postPersistEventArgs->getObject();
        if (!$object instanceof Job) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        foreach ($this->reflectionProperty->getValue($object) as $relatedEntity) {
            $relClass = ClassUtils::getClass($relatedEntity);
            $relId = $this->registry->getManagerForClass($relClass)->getMetadataFactory()->getMetadataFor($relClass)->getIdentifierValues($relatedEntity);

            asort($relId);

            if ([] === $relId) {
                throw new RuntimeException('The identifier for the related entity "'.$relClass.'" was empty.');
            }

            $connection->executeUpdate('INSERT INTO jms_job_related_entities (job_id, related_class, related_id) VALUES (:jobId, :relClass, :relId)', ['jobId' => $object->getId(), 'relClass' => $relClass, 'relId' => json_encode($relId)]);
        }
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $generateSchemaEventArgs): void
    {
        $schema = $generateSchemaEventArgs->getSchema();

        // When using multiple entity managers ignore events that are triggered by other entity managers.
        if ($generateSchemaEventArgs->getEntityManager()->getMetadataFactory()->isTransient(Job::class)) {
            return;
        }

        $table = $schema->createTable('jms_job_related_entities');
        $table->addColumn('job_id', 'bigint', ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('related_class', 'string', ['notnull' => true, 'length' => '150']);
        $table->addColumn('related_id', 'string', ['notnull' => true, 'length' => '100']);
        $table->setPrimaryKey(['job_id', 'related_class', 'related_id']);
        $table->addForeignKeyConstraint('jms_jobs', ['job_id'], ['id']);
    }
}
