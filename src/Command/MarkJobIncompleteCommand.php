<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jms-job-queue:mark-incomplete', description: 'Internal command (do not use). It marks jobs as incomplete.')]
class MarkJobIncompleteCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobManager $jobManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('job-id', InputArgument::REQUIRED, 'The ID of the Job.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        /** @var Job|null $job */
        $job = $this->entityManager->createQuery('SELECT j FROM '.Job::class.' j WHERE j.id = :id')
            ->setParameter('id', $input->getArgument('job-id'))
            ->getOneOrNullResult();

        if (null === $job) {
            $symfonyStyle->error('Job was not found.>');

            return Command::FAILURE;
        }

        $this->jobManager->closeJob($job, Job::STATE_INCOMPLETE);

        return Command::SUCCESS;
    }
}
