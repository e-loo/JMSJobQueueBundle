<?php

namespace JMS\JobQueueBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jms-job-queue:mark-incomplete')]
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
            ->setDescription('Internal command (do not use). It marks jobs as incomplete.')
            ->addArgument('job-id', InputArgument::REQUIRED, 'The ID of the Job.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var Job|null $job */
        $job = $this->entityManager->createQuery("SELECT j FROM ".Job::class." j WHERE j.id = :id")
            ->setParameter('id', $input->getArgument('job-id'))
            ->getOneOrNullResult();

        if ($job === null) {
            $io->error('Job was not found.>');

            return Command::FAILURE;
        }

        $this->jobManager->closeJob($job, Job::STATE_INCOMPLETE);

        return Command::SUCCESS;
    }
}
