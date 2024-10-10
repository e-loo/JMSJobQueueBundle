<?php

namespace JMS\JobQueueBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jms-job-queue:clean-up')]
class CleanUpCommand extends Command
{
    private ?StyleInterface $io = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobManager $jobManager)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Cleans up jobs which exceed the maximum retention time.')
            ->addOption('max-retention', null, InputOption::VALUE_REQUIRED, 'The maximum retention time (value must be parsable by DateTime).', '7 days')
            ->addOption('max-retention-succeeded', null, InputOption::VALUE_REQUIRED, 'The maximum retention time for succeeded jobs (value must be parsable by DateTime).', '1 hour')
            ->addOption('per-call', null, InputOption::VALUE_REQUIRED, 'The maximum number of jobs to clean-up per call.', 1000)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        try {
            $this->cleanUpExpiredJobs($input);
            $this->collectStaleJobs();
        } catch(\Throwable $ex) {
            $this->io->error("Failed to cleanup JMS jobs. An error occurred in {$ex->getFile()} on line {$ex->getLine()}: {$ex->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function collectStaleJobs(): void
    {
        foreach ($this->findStaleJobs() as $job) {
            if ($job->isRetried()) {
                continue;
            }

            $this->jobManager->closeJob($job, Job::STATE_INCOMPLETE);
        }
    }

    /**
     * @return iterable<Job>
     */
    private function findStaleJobs(): iterable
    {
        $excludedIds = [-1];

        do {
            $this->entityManager->clear();

            /** @var Job $job */
            $job = $this->entityManager->createQuery("SELECT j FROM " . Job::class . " j
                                      WHERE j.state = :running AND j.workerName IS NOT NULL AND j.checkedAt < :maxAge
                                                AND j.id NOT IN (:excludedIds)")
                ->setParameter('running', Job::STATE_RUNNING)
                ->setParameter('maxAge', new \DateTime('-5 minutes'), 'datetime')
                ->setParameter('excludedIds', $excludedIds)
                ->setMaxResults(1)
                ->getOneOrNullResult();

            if ($job !== null) {
                $excludedIds[] = $job->getId();

                yield $job;
            }
        } while ($job !== null);
    }

    private function cleanUpExpiredJobs(InputInterface $input): void
    {
        $con = $this->entityManager->getConnection();
        $incomingDepsSql = $con->getDatabasePlatform()->modifyLimitQuery("SELECT 1 FROM jms_job_dependencies WHERE dest_job_id = :id", 1);

        $count = 0;
        foreach ($this->findExpiredJobs($input) as $job) {
            $count++;
            $result = $con->executeQuery($incomingDepsSql, ['id' => $job->getId()]);
            if ($result->rowCount() !== 0) {
                $this->entityManager->beginTransaction();
                try {
                    $this->resolveDependencies($job);
                    $this->entityManager->remove($job);

                    $this->entityManager->commit();
                } catch(\Throwable $exception) {
                    $this->entityManager->rollback();
                    throw $exception;
                }

                $this->entityManager->transactional(function() use ($job): void {
                    $this->resolveDependencies($job);
                    $this->entityManager->remove($job);
                });

                continue;
            }

            $this->entityManager->remove($job);

            if ($count >= $input->getOption('per-call')) {
                break;
            }
        }

        $this->entityManager->flush();
    }

    private function resolveDependencies(Job $job): void
    {
        // If this job has failed, or has otherwise not succeeded, we need to set the
        // incoming dependencies to failed if that has not been done already.
        if ( ! $job->isFinished()) {
            foreach ($this->jobManager->findIncomingDependencies($job) as $incomingDep) {
                if ($incomingDep->isInFinalState()) {
                    continue;
                }

                $finalState = Job::STATE_CANCELED;
                if ($job->isRunning()) {
                    $finalState = Job::STATE_FAILED;
                }

                $this->jobManager->closeJob($incomingDep, $finalState);
            }
        }

        $this->entityManager->getConnection()->executeUpdate("DELETE FROM jms_job_dependencies WHERE dest_job_id = :id", ['id' => $job->getId()]);
    }

    /**
     * @param InputInterface $input
     * @return iterable<Job>
     */
    private function findExpiredJobs(InputInterface $input): iterable
    {
        $succeededJobs = fn(array $excludedIds): mixed => $this->entityManager->createQuery("SELECT j FROM " . Job::class . " j WHERE j.closedAt < :maxRetentionTime AND j.originalJob IS NULL AND j.state = :succeeded AND j.id NOT IN (:excludedIds)")
            ->setParameter('maxRetentionTime', new \DateTime('-'.$input->getOption('max-retention-succeeded')))
            ->setParameter('excludedIds', $excludedIds)
            ->setParameter('succeeded', Job::STATE_FINISHED)
            ->setMaxResults(100)
            ->getResult();
        yield from $this->whileResults( $succeededJobs );

        $finishedJobs = fn(array $excludedIds): mixed => $this->entityManager->createQuery("SELECT j FROM " . Job::class . " j WHERE j.closedAt < :maxRetentionTime AND j.originalJob IS NULL AND j.id NOT IN (:excludedIds)")
            ->setParameter('maxRetentionTime', new \DateTime('-'.$input->getOption('max-retention')))
            ->setParameter('excludedIds', $excludedIds)
            ->setMaxResults(100)
            ->getResult();
        yield from $this->whileResults( $finishedJobs );

        $canceledJobs = fn(array $excludedIds): mixed => $this->entityManager->createQuery("SELECT j FROM " . Job::class . " j WHERE j.state = :canceled AND j.createdAt < :maxRetentionTime AND j.originalJob IS NULL AND j.id NOT IN (:excludedIds)")
            ->setParameter('maxRetentionTime', new \DateTime('-'.$input->getOption('max-retention')))
            ->setParameter('canceled', Job::STATE_CANCELED)
            ->setParameter('excludedIds', $excludedIds)
            ->setMaxResults(100)
            ->getResult();
        yield from $this->whileResults( $canceledJobs );
    }

    private function whileResults(callable $resultProducer)
    {
        $excludedIds = [-1];

        do {
            /** @var Job[] $jobs */
            $jobs = $resultProducer($excludedIds);
            foreach ($jobs as $job) {
                $excludedIds[] = $job->getId();
                yield $job;
            }
        } while ( ! empty($jobs));
    }
}
