<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Controller;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use JMS\JobQueueBundle\View\JobFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

class JobController extends AbstractController
{
    #[Route(path: '/', name: 'jms_jobs_overview')]
    public function overview(JobManager $jobManager, EntityManagerInterface $entityManager, Request $request): Response
    {
        $jobFilter = JobFilter::fromRequest($request);

        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder->select('j')->from(Job::class, 'j')
            ->where($queryBuilder->expr()->isNull('j.originalJob'))
            ->orderBy('j.id', 'desc');

        $lastJobsWithError = $jobFilter->isDefaultPage() ? $jobManager->findLastJobsWithError(5) : [];
        foreach ($lastJobsWithError as $i => $job) {
            $queryBuilder->andWhere($queryBuilder->expr()->neq('j.id', '?'.$i));
            $queryBuilder->setParameter($i, $job->getId());
        }

        if (!empty($jobFilter->command)) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->like('j.command', ':commandQuery'),
                $queryBuilder->expr()->like('j.args', ':commandQuery')
            ))
                ->setParameter('commandQuery', '%'.$jobFilter->command.'%');
        }

        if (!empty($jobFilter->state)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('j.state', ':jobState'))
                ->setParameter('jobState', $jobFilter->state);
        }

        $perPage = 50;

        $query = $queryBuilder->getQuery();
        $query->setMaxResults($perPage + 1);
        $query->setFirstResult(($jobFilter->page - 1) * $perPage);

        $jobs = $query->getResult();

        return $this->render('@JMSJobQueue/Job/overview.html.twig', ['jobsWithError' => $lastJobsWithError, 'jobs' => array_slice($jobs, 0, $perPage), 'jobFilter' => $jobFilter, 'hasMore' => count($jobs) > $perPage, 'jobStates' => Job::getStates()]);
    }

    #[Route(path: '/{id}', name: 'jms_jobs_details')]
    public function details(EntityManagerInterface $entityManager, JobManager $jobManager, Job $job): Response
    {
        $relatedEntities = [];
        foreach ($job->getRelatedEntities() as $relatedEntity) {
            $class = ClassUtils::getClass($relatedEntity);
            $relatedEntities[] = ['class' => $class, 'id' => json_encode($entityManager->getClassMetadata($class)->getIdentifierValues($relatedEntity)), 'raw' => $relatedEntity];
        }

        $statisticData = $statisticOptions = [];
        if ($this->getParameter('jms_job_queue.statistics')) {
            $dataPerCharacteristic = [];
            foreach ($entityManager->getConnection()->query('SELECT * FROM jms_job_statistics WHERE job_id = '.$job->getId()) as $row) {
                $dataPerCharacteristic[$row['characteristic']][] = [
                    // hack because postgresql lower-cases all column names.
                    array_key_exists('createdAt', $row) ? $row['createdAt'] : $row['createdat'],
                    array_key_exists('charValue', $row) ? $row['charValue'] : $row['charvalue'],
                ];
            }

            if ([] !== $dataPerCharacteristic) {
                $statisticData = [array_merge(['Time'], $chars = array_keys($dataPerCharacteristic))];
                $startTime = strtotime((string) $dataPerCharacteristic[$chars[0]][0][0]);
                $endTime = strtotime((string) $dataPerCharacteristic[$chars[0]][count($dataPerCharacteristic[$chars[0]]) - 1][0]);
                $scaleFactor = $endTime - $startTime > 300 ? 1 / 60 : 1;

                // This assumes that we have the same number of rows for each characteristic.
                for ($i = 0,$c = count(reset($dataPerCharacteristic)); $i < $c; ++$i) {
                    $row = [(strtotime((string) $dataPerCharacteristic[$chars[0]][$i][0]) - $startTime) * $scaleFactor];
                    foreach ($chars as $char) {
                        $value = (float) $dataPerCharacteristic[$char][$i][1];

                        if ('memory' === $char) {
                            $value /= 1024 * 1024;
                        }

                        $row[] = $value;
                    }

                    $statisticData[] = $row;
                }
            }
        }

        return $this->render('@JMSJobQueue/Job/details.html.twig', ['job' => $job, 'relatedEntities' => $relatedEntities, 'incomingDependencies' => $jobManager->getIncomingDependencies($job), 'statisticData' => $statisticData, 'statisticOptions' => $statisticOptions]);
    }

    #[Route(path: '/{id}/retry', name: 'jms_jobs_retry_job')]
    public function retryJob(EntityManagerInterface $entityManager, Job $job)
    {
        $state = $job->getState();

        if (
            Job::STATE_FAILED !== $state
            && Job::STATE_TERMINATED !== $state
            && Job::STATE_INCOMPLETE !== $state
        ) {
            throw new HttpException(400, 'Given job can\'t be retried');
        }

        $retryJob = clone $job;

        $entityManager->persist($retryJob);
        $entityManager->flush();

        $url = $this->generateUrl('jms_jobs_details', ['id' => $retryJob->getId()]);

        return new RedirectResponse($url, Response::HTTP_CREATED);
    }
}
