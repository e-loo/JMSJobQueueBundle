<?php

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
    public function overviewAction(JobManager $jobRepository, EntityManagerInterface $em, Request $request)
    {
        $jobFilter = JobFilter::fromRequest($request);

        $qb = $em->createQueryBuilder();
        $qb->select('j')->from(Job::class, 'j')
            ->where($qb->expr()->isNull('j.originalJob'))
            ->orderBy('j.id', 'desc');

        $lastJobsWithError = $jobFilter->isDefaultPage() ? $jobRepository->findLastJobsWithError(5) : [];
        foreach ($lastJobsWithError as $i => $job) {
            $qb->andWhere($qb->expr()->neq('j.id', '?'.$i));
            $qb->setParameter($i, $job->getId());
        }

        if ( ! empty($jobFilter->command)) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('j.command', ':commandQuery'),
                $qb->expr()->like('j.args', ':commandQuery')
            ))
                ->setParameter('commandQuery', '%'.$jobFilter->command.'%');
        }

        if ( ! empty($jobFilter->state)) {
            $qb->andWhere($qb->expr()->eq('j.state', ':jobState'))
                ->setParameter('jobState', $jobFilter->state);
        }

        $perPage = 50;

        $query = $qb->getQuery();
        $query->setMaxResults($perPage + 1);
        $query->setFirstResult(($jobFilter->page - 1) * $perPage);

        $jobs = $query->getResult();

        return $this->render('@JMSJobQueue/Job/overview.html.twig', ['jobsWithError' => $lastJobsWithError, 'jobs' => array_slice($jobs, 0, $perPage), 'jobFilter' => $jobFilter, 'hasMore' => count($jobs) > $perPage, 'jobStates' => Job::getStates()]);
    }

    #[Route(path: '/{id}', name: 'jms_jobs_details')]
    public function detailsAction(EntityManagerInterface $em, JobManager $jobRepository, Job $job)
    {
        $relatedEntities = [];
        foreach ($job->getRelatedEntities() as $entity) {
            $class = ClassUtils::getClass($entity);
            $relatedEntities[] = ['class' => $class, 'id' => json_encode($em->getClassMetadata($class)->getIdentifierValues($entity)), 'raw' => $entity];
        }

        $statisticData = $statisticOptions = [];
        if ($this->getParameter('jms_job_queue.statistics')) {
            $dataPerCharacteristic = [];
            foreach ($em->getConnection()->query("SELECT * FROM jms_job_statistics WHERE job_id = ".$job->getId()) as $row) {
                $dataPerCharacteristic[$row['characteristic']][] = [
                    // hack because postgresql lower-cases all column names.
                    array_key_exists('createdAt', $row) ? $row['createdAt'] : $row['createdat'],
                    array_key_exists('charValue', $row) ? $row['charValue'] : $row['charvalue'],
                ];
            }

            if ($dataPerCharacteristic !== []) {
                $statisticData = [array_merge(['Time'], $chars = array_keys($dataPerCharacteristic))];
                $startTime = strtotime((string) $dataPerCharacteristic[$chars[0]][0][0]);
                $endTime = strtotime((string) $dataPerCharacteristic[$chars[0]][count($dataPerCharacteristic[$chars[0]])-1][0]);
                $scaleFactor = $endTime - $startTime > 300 ? 1/60 : 1;

                // This assumes that we have the same number of rows for each characteristic.
                for ($i=0,$c=count(reset($dataPerCharacteristic)); $i<$c; $i++) {
                    $row = [(strtotime((string) $dataPerCharacteristic[$chars[0]][$i][0]) - $startTime) * $scaleFactor];
                    foreach ($chars as $name) {
                        $value = (float) $dataPerCharacteristic[$name][$i][1];

                        if ($name === 'memory') {
                            $value /= 1024 * 1024;
                        }

                        $row[] = $value;
                    }

                    $statisticData[] = $row;
                }
            }
        }

        return $this->render('@JMSJobQueue/Job/details.html.twig', ['job' => $job, 'relatedEntities' => $relatedEntities, 'incomingDependencies' => $jobRepository->getIncomingDependencies($job), 'statisticData' => $statisticData, 'statisticOptions' => $statisticOptions]);
    }

    #[Route(path: '/{id}/retry', name: 'jms_jobs_retry_job')]
    public function retryJobAction(EntityManagerInterface $em, Job $job)
    {
        $state = $job->getState();

        if (
            Job::STATE_FAILED !== $state &&
            Job::STATE_TERMINATED !== $state &&
            Job::STATE_INCOMPLETE !== $state
        ) {
            throw new HttpException(400, 'Given job can\'t be retried');
        }

        $retryJob = clone $job;

        $em->persist($retryJob);
        $em->flush();

        $url = $this->generateUrl('jms_jobs_details', ['id' => $retryJob->getId()]);

        return new RedirectResponse($url, Response::HTTP_CREATED);
    }
}
