<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Tests\Functional;

use Doctrine\ORM\EntityManager;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use JMS\JobQueueBundle\Event\StateChangeEvent;
use JMS\JobQueueBundle\Retry\ExponentialRetryScheduler;
use JMS\JobQueueBundle\Tests\Functional\TestBundle\Entity\Train;
use JMS\JobQueueBundle\Tests\Functional\TestBundle\Entity\Wagon;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobManagerTest extends BaseTestCase
{
    /** @var EntityManager */
    private $entityManager;

    private JobManager $jobManager;

    /** @var EventDispatcher */
    private MockObject $mockObject;

    public function testGetOne(): void
    {
        $a = new Job('a', ['foo']);
        $a2 = new Job('a');
        $this->entityManager->persist($a);
        $this->entityManager->persist($a2);
        $this->entityManager->flush();

        $this->assertSame($a, $this->jobManager->getJob('a', ['foo']));
        $this->assertSame($a2, $this->jobManager->getJob('a'));
    }

    /**
     * @expectedException \RuntimeException
     *
     * @expectedExceptionMessage Found no job for command
     */
    public function testGetOneThrowsWhenNotFound(): void
    {
        $this->jobManager->getJob('foo');
    }

    public function getOrCreateIfNotExists(): void
    {
        $a = $this->jobManager->getOrCreateIfNotExists('a');
        $this->assertSame($a, $this->jobManager->getOrCreateIfNotExists('a'));
        $this->assertNotSame($a, $this->jobManager->getOrCreateIfNotExists('a', ['foo']));
    }

    public function testFindPendingJobReturnsAllDependencies(): void
    {
        $a = new Job('a');
        $b = new Job('b');

        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $c = new Job('c');
        $c->addDependency($a);
        $c->addDependency($b);
        $this->entityManager->persist($c);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $cReloaded = $this->jobManager->findPendingJob([$a->getId(), $b->getId()]);
        $this->assertNotNull($cReloaded);
        $this->assertEquals($c->getId(), $cReloaded->getId());
        $this->assertCount(2, $cReloaded->getDependencies());
    }

    public function testFindPendingJob(): void
    {
        $this->assertNull($this->jobManager->findPendingJob());

        $a = new Job('a');
        $a->setState('running');
        $b = new Job('b');
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $this->assertSame($b, $this->jobManager->findPendingJob());
        $this->assertNull($this->jobManager->findPendingJob([$b->getId()]));
    }

    public function testFindPendingJobInRestrictedQueue(): void
    {
        $this->assertNull($this->jobManager->findPendingJob());

        $a = new Job('a');
        $b = new Job('b', [], true, 'other_queue');
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $this->assertSame($a, $this->jobManager->findPendingJob());
        $this->assertSame($b, $this->jobManager->findPendingJob([], [], ['other_queue']));
    }

    public function testFindStartableJob(): void
    {
        $this->assertNull($this->jobManager->findStartableJob('my-name'));

        $a = new Job('a');
        $a->setState('running');
        $b = new Job('b');
        $c = new Job('c');
        $b->addDependency($c);
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->persist($c);
        $this->entityManager->flush();

        $excludedIds = [];

        $this->assertSame($c, $this->jobManager->findStartableJob('my-name', $excludedIds));
        $this->assertEquals([$b->getId()], $excludedIds);
    }

    public function testFindJobByRelatedEntity(): void
    {
        $a = new Job('a');
        $b = new Job('b');
        $b->addRelatedEntity($a);
        $b2 = new Job('b');
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->persist($b2);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertFalse($this->entityManager->contains($b));

        $reloadedB = $this->jobManager->findJobForRelatedEntity('b', $a);
        $this->assertNotNull($reloadedB);
        $this->assertEquals($b->getId(), $reloadedB->getId());
        $this->assertCount(1, $reloadedB->getRelatedEntities());
        $this->assertEquals($a->getId(), $reloadedB->getRelatedEntities()->first()->getId());
    }

    public function testFindStartableJobDetachesNonStartableJobs(): void
    {
        $a = new Job('a');
        $b = new Job('b');
        $a->addDependency($b);
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $this->assertTrue($this->entityManager->contains($a));
        $this->assertTrue($this->entityManager->contains($b));

        $excludedIds = [];
        $startableJob = $this->jobManager->findStartableJob('my-name', $excludedIds);
        $this->assertNotNull($startableJob);
        $this->assertEquals($b->getId(), $startableJob->getId());
        $this->assertEquals([$a->getId()], $excludedIds);
        $this->assertFalse($this->entityManager->contains($a));
        $this->assertTrue($this->entityManager->contains($b));
    }

    public function testCloseJob(): void
    {
        $a = new Job('a');
        $a->setState('running');
        $b = new Job('b');
        $b->addDependency($a);
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $this->mockObject->expects($this->at(0))
            ->method('dispatch')
            ->with('jms_job_queue.job_state_change', new StateChangeEvent($a, 'terminated'));
        $this->mockObject->expects($this->at(1))
            ->method('dispatch')
            ->with('jms_job_queue.job_state_change', new StateChangeEvent($b, 'canceled'));

        $this->assertSame('running', $a->getState());
        $this->assertSame('pending', $b->getState());
        $this->jobManager->closeJob($a, 'terminated');
        $this->assertSame('terminated', $a->getState());
        $this->assertSame('canceled', $b->getState());
    }

    public function testCloseJobDoesNotCreateRetryJobsWhenCanceled(): void
    {
        $a = new Job('a');
        $a->setMaxRetries(4);
        $b = new Job('b');
        $b->setMaxRetries(4);
        $b->addDependency($a);
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $this->mockObject->expects($this->at(0))
            ->method('dispatch')
            ->with('jms_job_queue.job_state_change', new StateChangeEvent($a, 'canceled'));

        $this->mockObject->expects($this->at(1))
            ->method('dispatch')
            ->with('jms_job_queue.job_state_change', new StateChangeEvent($b, 'canceled'));

        $this->jobManager->closeJob($a, 'canceled');
        $this->assertSame('canceled', $a->getState());
        $this->assertCount(0, $a->getRetryJobs());
        $this->assertSame('canceled', $b->getState());
        $this->assertCount(0, $b->getRetryJobs());
    }

    public function testCloseJobDoesNotCreateMoreThanAllowedRetries(): void
    {
        $job = new Job('a');
        $job->setMaxRetries(2);
        $job->setState('running');
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->mockObject->expects($this->at(0))
            ->method('dispatch')
            ->with('jms_job_queue.job_state_change', new StateChangeEvent($job, 'failed'));
        $this->mockObject->expects($this->at(1))
            ->method('dispatch')
            ->with('jms_job_queue.job_state_change', new LogicalNot($this->equalTo(new StateChangeEvent($job, 'failed'))));
        $this->mockObject->expects($this->at(2))
            ->method('dispatch')
            ->with('jms_job_queue.job_state_change', new LogicalNot($this->equalTo(new StateChangeEvent($job, 'failed'))));

        $this->assertCount(0, $job->getRetryJobs());
        $this->jobManager->closeJob($job, 'failed');
        $this->assertSame('running', $job->getState());
        $this->assertCount(1, $job->getRetryJobs());

        $job->getRetryJobs()->first()->setState('running');
        $this->jobManager->closeJob($job->getRetryJobs()->first(), 'failed');
        $this->assertCount(2, $job->getRetryJobs());
        $this->assertSame('failed', $job->getRetryJobs()->first()->getState());
        $this->assertSame('running', $job->getState());

        $job->getRetryJobs()->last()->setState('running');
        $this->jobManager->closeJob($job->getRetryJobs()->last(), 'terminated');
        $this->assertCount(2, $job->getRetryJobs());
        $this->assertSame('terminated', $job->getRetryJobs()->last()->getState());
        $this->assertSame('terminated', $job->getState());

        $this->entityManager->clear();
        $reloadedA = $this->entityManager->find(Job::class, $job->getId());
        $this->assertCount(2, $reloadedA->getRetryJobs());
    }

    public function testModifyingRelatedEntity(): void
    {
        $wagon = new Wagon();
        $train = new Train();
        $wagon->train = $train;

        $defEm = self::$kernel->getContainer()->get('doctrine')->getManager('default');
        $defEm->persist($wagon);
        $defEm->persist($train);
        $defEm->flush();

        $job = new Job('j');
        $job->addRelatedEntity($wagon);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $defEm->clear();
        $this->entityManager->clear();
        $this->assertNotSame($defEm, $this->entityManager);

        $reloadedJ = $this->entityManager->find(Job::class, $job->getId());

        $reloadedWagon = $reloadedJ->findRelatedEntity(Wagon::class);
        $reloadedWagon->state = 'broken';
        $defEm->persist($reloadedWagon);
        $defEm->flush();

        $this->assertTrue($defEm->contains($reloadedWagon->train));
    }

    protected function setUp(): void
    {
        $this->createClient();
        $this->importDatabaseSchema();

        $this->mockObject = $this->createMock(EventDispatcherInterface::class);
        $this->entityManager = self::$kernel->getContainer()->get('doctrine')->getManagerForClass(Job::class);
        $this->jobManager = new JobManager(
            self::$kernel->getContainer()->get('doctrine'),
            $this->mockObject,
            new ExponentialRetryScheduler()
        );
    }
}
