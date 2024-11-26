<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Entity\Repository;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\Proxy;
use Exception;
use InvalidArgumentException;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Event\StateChangeEvent;
use JMS\JobQueueBundle\Retry\ExponentialRetryScheduler;
use JMS\JobQueueBundle\Retry\RetryScheduler;
use LogicException;
use PDO;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly EventDispatcherInterface $eventDispatcher, private RetryScheduler $retryScheduler)
    {
    }

    public function findJob($command, array $args = []): mixed
    {
        return $this->entityManager->createQuery('SELECT j FROM '.Job::class.' j WHERE j.command = :command AND j.args = :args')
            ->setParameter('command', $command)
            ->setParameter('args', $args, Types::JSON)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    public function getJob($command, array $args = [])
    {
        if (null !== $job = $this->findJob($command, $args)) {
            return $job;
        }

        throw new RuntimeException(sprintf('Found no job for command "%s" with args "%s".', $command, json_encode($args)));
    }

    public function getOrCreateIfNotExists($command, array $args = [])
    {
        if (null !== $job = $this->findJob($command, $args)) {
            return $job;
        }

        $job = new Job($command, $args, false);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $firstJob = $this->entityManager->createQuery('SELECT j FROM '.Job::class.' j WHERE j.command = :command AND j.args = :args ORDER BY j.id ASC')
             ->setParameter('command', $command)
             ->setParameter('args', $args, 'json_array')
             ->setMaxResults(1)
             ->getSingleResult();

        if ($firstJob === $job) {
            $job->setState(Job::STATE_PENDING);
            $this->entityManager->persist($job);
            $this->entityManager->flush();

            return $job;
        }

        $this->entityManager->remove($job);
        $this->entityManager->flush();

        return $firstJob;
    }

    public function findStartableJob($workerName, array &$excludedIds = [], $excludedQueues = [], $restrictedQueues = [])
    {
        while (null !== $job = $this->findPendingJob($excludedIds, $excludedQueues, $restrictedQueues)) {
            if ($job->isStartable() && $this->acquireLock($workerName, $job)) {
                return $job;
            }

            $excludedIds[] = $job->getId();

            // We do not want to have non-startable jobs floating around in
            // cache as they might be changed by another process. So, better
            // re-fetch them when they are not excluded anymore.
            $this->entityManager->detach($job);
        }

        return null;
    }

    private function acquireLock(string|null $workerName, Job $job): bool
    {
        $affectedRows = $this->entityManager->getConnection()->executeUpdate(
            'UPDATE jms_jobs SET workerName = :worker WHERE id = :id AND workerName IS NULL',
            ['worker' => $workerName, 'id' => $job->getId()]
        );

        if ($affectedRows > 0) {
            $job->setWorkerName($workerName);

            return true;
        }

        return false;
    }

    public function findAllForRelatedEntity($relatedEntity): mixed
    {
        [$relClass, $relId] = $this->getRelatedEntityIdentifier($relatedEntity);

        $resultSetMappingBuilder = new ResultSetMappingBuilder($this->entityManager);
        $resultSetMappingBuilder->addRootEntityFromClassMetadata(Job::class, 'j');

        return $this->entityManager->createNativeQuery('SELECT j.* FROM jms_jobs j INNER JOIN jms_job_related_entities r ON r.job_id = j.id WHERE r.related_class = :relClass AND r.related_id = :relId', $resultSetMappingBuilder)
                    ->setParameter('relClass', $relClass)
                    ->setParameter('relId', $relId)
                    ->getResult();
    }

    public function findOpenJobForRelatedEntity($command, $relatedEntity): mixed
    {
        return $this->findJobForRelatedEntity($command, $relatedEntity, [Job::STATE_RUNNING, Job::STATE_PENDING, Job::STATE_NEW]);
    }

    public function findJobForRelatedEntity($command, $relatedEntity, array $states = []): mixed
    {
        [$relClass, $relId] = $this->getRelatedEntityIdentifier($relatedEntity);

        $resultSetMappingBuilder = new ResultSetMappingBuilder($this->entityManager);
        $resultSetMappingBuilder->addRootEntityFromClassMetadata(Job::class, 'j');

        $sql = 'SELECT j.* FROM jms_jobs j INNER JOIN jms_job_related_entities r ON r.job_id = j.id WHERE r.related_class = :relClass AND r.related_id = :relId AND j.command = :command';
        $params = new ArrayCollection();
        $params->add(new Parameter('command', $command));
        $params->add(new Parameter('relClass', $relClass));
        $params->add(new Parameter('relId', $relId));

        if ([] !== $states) {
            $sql .= ' AND j.state IN (:states)';
            $params->add(new Parameter('states', $states, ArrayParameterType::STRING));
        }

        return $this->entityManager->createNativeQuery($sql, $resultSetMappingBuilder)
                   ->setParameters($params)
                   ->getOneOrNullResult();
    }

    private function getRelatedEntityIdentifier($entity): array
    {
        if (!is_object($entity)) {
            throw new RuntimeException('$entity must be an object.');
        }

        if ($entity instanceof Proxy) {
            $entity->__load();
        }

        $relClass = ClassUtils::getClass($entity);
        $relId = $this->entityManager
            ->getMetadataFactory()
            ->getMetadataFor($relClass)
            ->getIdentifierValues($entity);

        asort($relId);

        if ([] === $relId) {
            throw new InvalidArgumentException(sprintf('The identifier for entity of class "%s" was empty.', $relClass));
        }

        return [$relClass, json_encode($relId)];
    }

    public function findPendingJob(array $excludedIds = [], array $excludedQueues = [], array $restrictedQueues = []): mixed
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('j')->from(Job::class, 'j')
            ->orderBy('j.priority', 'ASC')
            ->addOrderBy('j.id', 'ASC');

        $conditions = [];

        $conditions[] = $queryBuilder->expr()->isNull('j.workerName');

        $conditions[] = $queryBuilder->expr()->lt('j.executeAfter', ':now');
        $queryBuilder->setParameter(':now', new DateTime(), 'datetime');

        $conditions[] = $queryBuilder->expr()->eq('j.state', ':state');
        $queryBuilder->setParameter('state', Job::STATE_PENDING);

        if ([] !== $excludedIds) {
            $conditions[] = $queryBuilder->expr()->notIn('j.id', ':excludedIds');
            $queryBuilder->setParameter('excludedIds', $excludedIds, ArrayParameterType::INTEGER);
        }

        if ([] !== $excludedQueues) {
            $conditions[] = $queryBuilder->expr()->notIn('j.queue', ':excludedQueues');
            $queryBuilder->setParameter('excludedQueues', $excludedQueues, ArrayParameterType::STRING);
        }

        if ([] !== $restrictedQueues) {
            $conditions[] = $queryBuilder->expr()->in('j.queue', ':restrictedQueues');
            $queryBuilder->setParameter('restrictedQueues', $restrictedQueues, ArrayParameterType::STRING);
        }

        $queryBuilder->where(call_user_func_array([$queryBuilder->expr(), 'andX'], $conditions));

        return $queryBuilder->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

    public function closeJob(Job $job, $finalState): void
    {
        $this->entityManager->getConnection()->beginTransaction();

        try {
            $visited = [];
            $this->closeJobInternal($job, $finalState, $visited);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            // Clean-up entity manager to allow for garbage collection to kick in.
            foreach ($visited as $job) {
                // If the job is an original job which is now being retried, let's
                // not remove it just yet.
                if (!$job->isClosedNonSuccessful()) {
                    continue;
                }
                if ($job->isRetryJob()) {
                    continue;
                }
                $this->entityManager->detach($job);
            }
        } catch (Exception $ex) {
            $this->entityManager->getConnection()->rollback();

            throw $ex;
        }
    }

    private function closeJobInternal(Job $job, $finalState, array &$visited = []): void
    {
        if (in_array($job, $visited, true)) {
            return;
        }
        $visited[] = $job;

        if ($job->isInFinalState()) {
            return;
        }

        if ($this->eventDispatcher instanceof EventDispatcherInterface && ($job->isRetryJob() || 0 === count($job->getRetryJobs()))) {
            $event = $this->eventDispatcher->dispatch(new StateChangeEvent($job, $finalState), 'jms_job_queue.job_state_change');
            $finalState = $event->getNewState();
        }

        switch ($finalState) {
            case Job::STATE_CANCELED:
                $job->setState(Job::STATE_CANCELED);
                $this->entityManager->persist($job);

                if ($job->isRetryJob()) {
                    $this->closeJobInternal($job->getOriginalJob(), Job::STATE_CANCELED, $visited);

                    return;
                }

                foreach ($this->findIncomingDependencies($job) as $dep) {
                    $this->closeJobInternal($dep, Job::STATE_CANCELED, $visited);
                }

                return;

            case Job::STATE_FAILED:
            case Job::STATE_TERMINATED:
            case Job::STATE_INCOMPLETE:
                if ($job->isRetryJob()) {
                    $job->setState($finalState);
                    $this->entityManager->persist($job);

                    $this->closeJobInternal($job->getOriginalJob(), $finalState);

                    return;
                }

                // The original job has failed, and we are allowed to retry it.
                if ($job->isRetryAllowed()) {
                    $retryJob = new Job($job->getCommand(), $job->getArgs(), true, $job->getQueue(), $job->getPriority());
                    $retryJob->setMaxRuntime($job->getMaxRuntime());

                    if (!$this->retryScheduler instanceof RetryScheduler) {
                        $this->retryScheduler = new ExponentialRetryScheduler(5);
                    }

                    $retryJob->setExecuteAfter($this->retryScheduler->scheduleNextRetry($job));

                    $job->addRetryJob($retryJob);
                    $this->entityManager->persist($retryJob);
                    $this->entityManager->persist($job);

                    return;
                }

                $job->setState($finalState);
                $this->entityManager->persist($job);

                // The original job has failed, and no retries are allowed.
                foreach ($this->findIncomingDependencies($job) as $dep) {
                    // This is a safe-guard to avoid blowing up if there is a database inconsistency.
                    if (!$dep->isPending() && !$dep->isNew()) {
                        continue;
                    }

                    $this->closeJobInternal($dep, Job::STATE_CANCELED, $visited);
                }

                return;

            case Job::STATE_FINISHED:
                if ($job->isRetryJob()) {
                    $job->getOriginalJob()->setState($finalState);
                    $this->entityManager->persist($job->getOriginalJob());
                }
                $job->setState($finalState);
                $this->entityManager->persist($job);

                return;

            default:
                throw new LogicException(sprintf('Non allowed state "%s" in closeJobInternal().', $finalState));
        }
    }

    /**
     * @return Job[]
     */
    public function findIncomingDependencies(Job $job)
    {
        $jobIds = $this->getJobIdsOfIncomingDependencies($job);
        if ([] === $jobIds) {
            return [];
        }

        return $this->entityManager->createQuery('SELECT j, d FROM '.Job::class.' j LEFT JOIN j.dependencies d WHERE j.id IN (:ids)')
                    ->setParameter('ids', $jobIds)
                    ->getResult();
    }

    /**
     * @return Job[]
     */
    public function getIncomingDependencies(Job $job)
    {
        $jobIds = $this->getJobIdsOfIncomingDependencies($job);
        if ([] === $jobIds) {
            return [];
        }

        return $this->entityManager->createQuery('SELECT j FROM '.Job::class.' j WHERE j.id IN (:ids)')
                    ->setParameter('ids', $jobIds)
                    ->getResult();
    }

    private function getJobIdsOfIncomingDependencies(Job $job): array
    {
        return $this->entityManager->getConnection()
            ->executeQuery('SELECT source_job_id FROM jms_job_dependencies WHERE dest_job_id = :id', ['id' => $job->getId()])
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findLastJobsWithError(int|null $nbJobs = 10): mixed
    {
        return $this->entityManager->createQuery('SELECT j FROM '.Job::class.' j WHERE j.state IN (:errorStates) AND j.originalJob IS NULL ORDER BY j.closedAt DESC')
                    ->setParameter('errorStates', [Job::STATE_TERMINATED, Job::STATE_FAILED])
                    ->setMaxResults($nbJobs)
                    ->getResult();
    }

    /**
     * @return mixed[]
     */
    public function getAvailableQueueList(): array
    {
        $queues = $this->entityManager->createQuery('SELECT DISTINCT j.queue FROM '.Job::class.' j WHERE j.state IN (:availableStates)  GROUP BY j.queue')
            ->setParameter('availableStates', [Job::STATE_RUNNING, Job::STATE_NEW, Job::STATE_PENDING])
            ->getResult();

        $newQueueArray = [];

        foreach ($queues as $queue) {
            $newQueue = $queue['queue'];
            $newQueueArray[] = $newQueue;
        }

        return $newQueueArray;
    }

    public function getAvailableJobsForQueueCount($jobQueue): int
    {
        $result = $this->entityManager->createQuery('SELECT j.queue FROM '.Job::class.' j WHERE j.state IN (:availableStates) AND j.queue = :queue')
            ->setParameter('availableStates', [Job::STATE_RUNNING, Job::STATE_NEW, Job::STATE_PENDING])
            ->setParameter('queue', $jobQueue)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        return count($result);
    }
}
