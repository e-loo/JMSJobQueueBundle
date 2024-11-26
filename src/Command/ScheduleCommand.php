<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use JMS\JobQueueBundle\Console\CronCommand;
use JMS\JobQueueBundle\Cron\CommandScheduler;
use JMS\JobQueueBundle\Cron\JobScheduler;
use JMS\JobQueueBundle\Entity\CronJob;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'jms-job-queue:schedule', description: 'Schedules jobs at defined intervals')]
class ScheduleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly iterable $schedulers,
        private readonly iterable $cronCommands
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-runtime', null, InputOption::VALUE_REQUIRED, 'The maximum runtime of this command.', 3600)
            ->addOption('min-job-interval', null, InputOption::VALUE_REQUIRED, 'The minimum time between schedules jobs in seconds.', 5)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maxRuntime = $input->getOption('max-runtime');
        if ($maxRuntime > 300) {
            $maxRuntime += random_int(0, (int) ($input->getOption('max-runtime') * 0.05));
        }
        if ($maxRuntime <= 0) {
            throw new RuntimeException('Max. runtime must be greater than zero.');
        }

        $minJobInterval = (int) $input->getOption('min-job-interval');
        if ($minJobInterval <= 0) {
            throw new RuntimeException('Min. job interval must be greater than zero.');
        }

        $jobSchedulers = $this->populateJobSchedulers();
        if ([] === $jobSchedulers) {
            $output->writeln('No job schedulers found, exiting...');

            return Command::SUCCESS;
        }

        $jobsLastRunAt = $this->populateJobsLastRunAt($jobSchedulers);

        $startedAt = time();
        while (true) {
            $lastRunAt = microtime(true);
            $now = time();
            if ($now - $startedAt > $maxRuntime) {
                $output->writeln('Max. runtime reached, exiting...');

                break;
            }

            $this->scheduleJobs($output, $jobSchedulers, $jobsLastRunAt);

            $timeToWait = microtime(true) - $lastRunAt + $minJobInterval;
            if ($timeToWait > 0) {
                usleep($timeToWait * 1E6);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param JobScheduler[] $jobSchedulers
     * @param DateTime[]     $jobsLastRunAt
     */
    private function scheduleJobs(OutputInterface $output, array $jobSchedulers, array &$jobsLastRunAt): void
    {
        foreach ($jobSchedulers as $name => $scheduler) {
            $lastRunAt = $jobsLastRunAt[$name];

            if (!$scheduler->shouldSchedule($name, $lastRunAt)) {
                continue;
            }

            [$success, $newLastRunAt] = $this->acquireLock($name, $lastRunAt);
            $jobsLastRunAt[$name] = $newLastRunAt;

            if ($success) {
                $output->writeln('Scheduling command '.$name);
                $job = $scheduler->createJob($name, $lastRunAt);
                $this->entityManager->persist($job);
                $this->entityManager->flush($job);
            }
        }
    }

    private function acquireLock(int|string $commandName, DateTime $lastRunAt): array
    {
        $connection = $this->entityManager->getConnection();

        $now = new DateTime();
        $affectedRows = $connection->executeUpdate(
            'UPDATE jms_cron_jobs SET lastRunAt = :now WHERE command = :command AND lastRunAt = :lastRunAt',
            ['now' => $now, 'command' => $commandName, 'lastRunAt' => $lastRunAt],
            ['now' => 'datetime', 'lastRunAt' => 'datetime']
        );

        if ($affectedRows > 0) {
            return [true, $now];
        }

        /** @var CronJob $cronJob */
        $cronJob = $this->entityManager->createQuery('SELECT j FROM '.CronJob::class.' j WHERE j.command = :command')
            ->setParameter('command', $commandName)
            ->setHint(Query::HINT_REFRESH, true)
            ->getSingleResult();

        return [false, $cronJob->getLastRunAt()];
    }

    /**
     * @return mixed[]
     */
    private function populateJobSchedulers(): array
    {
        $schedulers = [];
        foreach ($this->schedulers as $scheduler) {
            /** @var JobScheduler $scheduler */
            foreach ($scheduler->getCommands() as $name) {
                $schedulers[$name] = $scheduler;
            }
        }

        foreach ($this->cronCommands as $cronCommand) {
            /* @var CronCommand $command */
            if (!$cronCommand instanceof Command) {
                throw new RuntimeException('CronCommand should only be used on Symfony commands.');
            }

            $schedulers[$cronCommand->getName()] = new CommandScheduler($cronCommand->getName(), $cronCommand);
        }

        return $schedulers;
    }

    /**
     * @return mixed[]
     */
    private function populateJobsLastRunAt(array $jobSchedulers): array
    {
        $jobsLastRunAt = [];

        foreach ($this->entityManager->getRepository(CronJob::class)->findAll() as $cronJob) {
            /* @var CronJob $job */
            $jobsLastRunAt[$cronJob->getCommand()] = $cronJob->getLastRunAt();
        }

        foreach (array_keys($jobSchedulers) as $name) {
            if (!isset($jobsLastRunAt[$name])) {
                $cronJob = new CronJob($name);
                $this->entityManager->persist($cronJob);
                $jobsLastRunAt[$name] = $cronJob->getLastRunAt();
            }
        }

        $this->entityManager->flush();

        return $jobsLastRunAt;
    }
}
