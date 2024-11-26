<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use JMS\JobQueueBundle\Exception\InvalidStateTransitionException;
use JMS\JobQueueBundle\Exception\LogicException;
use RuntimeException;
use Stringable;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'jms_jobs')]
#[ORM\Index('cmd_search_index', columns: ['command'])]
#[ORM\Index('sorting_index', columns: ['state', 'priority', 'id'])]
class Job implements Stringable
{
    /** State if job is inserted, but not yet ready to be started. */
    public const STATE_NEW = 'new';

    /**
     * State if job is inserted, and might be started.
     *
     * It is important to note that this does not automatically mean that all
     * jobs of this state can actually be started, but you have to check
     * isStartable() to be absolutely sure.
     *
     * In contrast to NEW, jobs of this state at least might be started,
     * while jobs of state NEW never are allowed to be started.
     */
    public const STATE_PENDING = 'pending';

    /** State if job was never started, and will never be started. */
    public const STATE_CANCELED = 'canceled';

    /** State if job was started and has not exited, yet. */
    public const STATE_RUNNING = 'running';

    /** State if job exists with a successful exit code. */
    public const STATE_FINISHED = 'finished';

    /** State if job exits with a non-successful exit code. */
    public const STATE_FAILED = 'failed';

    /** State if job exceeds its configured maximum runtime. */
    public const STATE_TERMINATED = 'terminated';

    /**
     * State if an error occurs in the runner command.
     *
     * The runner command is the command that actually launches the individual
     * jobs. If instead an error occurs in the job command, this will result
     * in a state of FAILED.
     */
    public const STATE_INCOMPLETE = 'incomplete';

    /**
     * State if an error occurs in the runner command.
     *
     * The runner command is the command that actually launches the individual
     * jobs. If instead an error occurs in the job command, this will result
     * in a state of FAILED.
     */
    public const DEFAULT_QUEUE = 'default';
    public const MAX_QUEUE_LENGTH = 50;

    public const PRIORITY_LOW = -5;
    public const PRIORITY_DEFAULT = 0;
    public const PRIORITY_HIGH = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private $id;

    #[ORM\Column(type: Types::STRING, length: 15)]
    private string $state;

    #[ORM\Column(type: Types::STRING, length: Job::MAX_QUEUE_LENGTH)]
    private string|null $queue = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int|float $priority = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'createdAt')]
    private DateTime $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'startedAt', nullable: true)]
    private DateTime|null $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'checkedAt', nullable: true)]
    private DateTime|null $checkedAt = null;

    #[ORM\Column(type: Types::STRING, name: 'workerName', length: 50, nullable: true)]
    private string|null $workerName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'executeAfter', nullable: true)]
    private DateTime $executeAfter;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'closedAt', nullable: true)]
    private DateTime|null $closedAt = null;

    #[ORM\ManyToMany(targetEntity: self::class, fetch: 'EAGER')]
    #[ORM\JoinTable(name: 'jms_job_dependencies', joinColumns: [new ORM\JoinColumn(name: 'source_job_id', referencedColumnName: 'id')], inverseJoinColumns: [new ORM\JoinColumn(name: 'dest_job_id', referencedColumnName: 'id')])]
    private Collection $dependencies;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private string|null $output = null;

    #[ORM\Column(type: Types::TEXT, name: 'errorOutput', nullable: true)]
    private string|null $errorOutput = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'exitCode', nullable: true, options: ['unsigned' => true])]
    private int|null $exitCode = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'maxRuntime', options: ['unsigned' => true])]
    private int $maxRuntime = 0;

    #[ORM\Column(type: Types::SMALLINT, name: 'maxRetries', options: ['unsigned' => true])]
    private int $maxRetries = 0;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'retryJobs')]
    #[ORM\JoinColumn(name: 'originalJob_id', referencedColumnName: 'id')]
    private self|null $originalJob = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'originalJob', cascade: ['persist', 'remove', 'detach', 'refresh'])]
    private Collection $retryJobs;

    #[ORM\Column(type: 'jms_job_safe_object', name: 'stackTrace', nullable: true)]
    private FlattenException|null $flattenException = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private int|null $runtime = null;

    #[ORM\Column(type: Types::INTEGER, name: 'memoryUsage', nullable: true, options: ['unsigned' => true])]
    private int|null $memoryUsage = null;

    #[ORM\Column(type: Types::INTEGER, name: 'memoryUsageReal', nullable: true, options: ['unsigned' => true])]
    private int|null $memoryUsageReal = null;

    /**
     * This may store any entities which are related to this job, and are
     * managed by Doctrine.
     *
     * It is effectively a many-to-any association.
     */
    private Collection $relatedEntities;

    public static function create($command, array $args = [], $confirmed = true, $queue = self::DEFAULT_QUEUE, $priority = self::PRIORITY_DEFAULT): self
    {
        return new self($command, $args, $confirmed, $queue, $priority);
    }

    public static function isNonSuccessfulFinalState($state): bool
    {
        return in_array($state, [self::STATE_CANCELED, self::STATE_FAILED, self::STATE_INCOMPLETE, self::STATE_TERMINATED], true);
    }

    public static function getStates(): array
    {
        return [self::STATE_NEW, self::STATE_PENDING, self::STATE_CANCELED, self::STATE_RUNNING, self::STATE_FINISHED, self::STATE_FAILED, self::STATE_TERMINATED, self::STATE_INCOMPLETE];
    }

    public function __construct(#[ORM\Column(type: Types::STRING)]
        private $command, #[ORM\Column(type: 'json_array')]
        private readonly array $args = [], $confirmed = true, $queue = self::DEFAULT_QUEUE, $priority = self::PRIORITY_DEFAULT)
    {
        if ('' === trim((string) $queue)) {
            throw new InvalidArgumentException('$queue must not be empty.');
        }
        if (strlen((string) $queue) > self::MAX_QUEUE_LENGTH) {
            throw new InvalidArgumentException(sprintf('The maximum queue length is %d, but got "%s" (%d chars).', self::MAX_QUEUE_LENGTH, $queue, strlen((string) $queue)));
        }
        $this->state = $confirmed ? self::STATE_PENDING : self::STATE_NEW;
        $this->queue = $queue;
        $this->priority = $priority * -1;
        $this->createdAt = new DateTime();
        $this->executeAfter = new DateTime();
        $this->executeAfter = $this->executeAfter->modify('-1 second');
        $this->dependencies = new ArrayCollection();
        $this->retryJobs = new ArrayCollection();
        $this->relatedEntities = new ArrayCollection();
    }

    public function __clone()
    {
        $this->state = self::STATE_PENDING;
        $this->createdAt = new DateTime();
        $this->startedAt = null;
        $this->checkedAt = null;
        $this->closedAt = null;
        $this->workerName = null;
        $this->output = null;
        $this->errorOutput = null;
        $this->exitCode = null;
        $this->flattenException = null;
        $this->runtime = null;
        $this->memoryUsage = null;
        $this->memoryUsageReal = null;
        $this->relatedEntities = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setWorkerName(string|null $workerName): void
    {
        $this->workerName = $workerName;
    }

    public function getWorkerName(): string|null
    {
        return $this->workerName;
    }

    public function getPriority(): int|float
    {
        return $this->priority * -1;
    }

    public function isInFinalState(): bool
    {
        return !$this->isNew() && !$this->isPending() && !$this->isRunning();
    }

    public function isStartable(): bool
    {
        foreach ($this->dependencies as $dependency) {
            if (self::STATE_FINISHED !== $dependency->getState()) {
                return false;
            }
        }

        return true;
    }

    public function setState($newState): void
    {
        if ($newState === $this->state) {
            return;
        }

        switch ($this->state) {
            case self::STATE_NEW:
                if (!in_array($newState, [self::STATE_PENDING, self::STATE_CANCELED], true)) {
                    throw new InvalidStateTransitionException($this, $newState, [self::STATE_PENDING, self::STATE_CANCELED]);
                }

                if (self::STATE_CANCELED === $newState) {
                    $this->closedAt = new DateTime();
                }

                break;

            case self::STATE_PENDING:
                if (!in_array($newState, [self::STATE_RUNNING, self::STATE_CANCELED], true)) {
                    throw new InvalidStateTransitionException($this, $newState, [self::STATE_RUNNING, self::STATE_CANCELED]);
                }

                if (self::STATE_RUNNING === $newState) {
                    $this->startedAt = new DateTime();
                    $this->checkedAt = new DateTime();
                } elseif (self::STATE_CANCELED === $newState) {
                    $this->closedAt = new DateTime();
                }

                break;

            case self::STATE_RUNNING:
                if (!in_array($newState, [self::STATE_FINISHED, self::STATE_FAILED, self::STATE_TERMINATED, self::STATE_INCOMPLETE])) {
                    throw new InvalidStateTransitionException($this, $newState, [self::STATE_FINISHED, self::STATE_FAILED, self::STATE_TERMINATED, self::STATE_INCOMPLETE]);
                }

                $this->closedAt = new DateTime();

                break;

            case self::STATE_FINISHED:
            case self::STATE_FAILED:
            case self::STATE_TERMINATED:
            case self::STATE_INCOMPLETE:
                throw new InvalidStateTransitionException($this, $newState);
            default:
                throw new LogicException('The previous cases were exhaustive. Unknown state: '.$this->state);
        }

        $this->state = $newState;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getClosedAt(): DateTime|null
    {
        return $this->closedAt;
    }

    public function getExecuteAfter(): DateTime
    {
        return $this->executeAfter;
    }

    public function setExecuteAfter(DateTime $executeAfter): void
    {
        $this->executeAfter = $executeAfter;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getRelatedEntities(): Collection
    {
        return $this->relatedEntities;
    }

    public function isClosedNonSuccessful(): bool
    {
        return self::isNonSuccessfulFinalState($this->state);
    }

    public function findRelatedEntity($class): object|null
    {
        foreach ($this->relatedEntities as $relatedEntity) {
            if ($relatedEntity instanceof $class) {
                return $relatedEntity;
            }
        }

        return null;
    }

    public function addRelatedEntity($entity): void
    {
        if (!is_object($entity)) {
            throw new RuntimeException('$entity must be an object.');
        }

        if ($this->relatedEntities->contains($entity)) {
            return;
        }

        $this->relatedEntities->add($entity);
    }

    public function getDependencies(): Collection
    {
        return $this->dependencies;
    }

    public function hasDependency(Job $job)
    {
        return $this->dependencies->contains($job);
    }

    public function addDependency(Job $job): void
    {
        if ($this->dependencies->contains($job)) {
            return;
        }

        if ($this->mightHaveStarted()) {
            throw new \LogicException('You cannot add dependencies to a job which might have been started already.');
        }

        $this->dependencies->add($job);
    }

    public function getRuntime(): int|null
    {
        return $this->runtime;
    }

    public function setRuntime($time): void
    {
        $this->runtime = (int) $time;
    }

    public function getMemoryUsage(): int|null
    {
        return $this->memoryUsage;
    }

    public function getMemoryUsageReal(): int|null
    {
        return $this->memoryUsageReal;
    }

    public function addOutput(string $output): void
    {
        $this->output .= $output;
    }

    public function addErrorOutput(string $output): void
    {
        $this->errorOutput .= $output;
    }

    public function setOutput(string|null $output): void
    {
        $this->output = $output;
    }

    public function setErrorOutput(string|null $output): void
    {
        $this->errorOutput = $output;
    }

    public function getOutput(): string|null
    {
        return $this->output;
    }

    public function getErrorOutput(): string|null
    {
        return $this->errorOutput;
    }

    public function setExitCode(int|null $code): void
    {
        $this->exitCode = $code;
    }

    public function getExitCode(): int|null
    {
        return $this->exitCode;
    }

    public function setMaxRuntime($time): void
    {
        $this->maxRuntime = (int) $time;
    }

    public function getMaxRuntime(): int
    {
        return $this->maxRuntime;
    }

    public function getStartedAt(): DateTime|null
    {
        return $this->startedAt;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries($tries): void
    {
        $this->maxRetries = (int) $tries;
    }

    public function isRetryAllowed()
    {
        // If no retries are allowed, we can bail out directly, and we
        // do not need to initialize the retryJobs relation.
        if (0 === $this->maxRetries) {
            return false;
        }

        return count($this->retryJobs) < $this->maxRetries;
    }

    public function getOriginalJob(): self
    {
        if (!$this->originalJob instanceof Job) {
            return $this;
        }

        return $this->originalJob;
    }

    public function setOriginalJob(Job $job): void
    {
        if (self::STATE_PENDING !== $this->state) {
            throw new \LogicException($this.' must be in state "PENDING".');
        }

        if ($this->originalJob instanceof Job) {
            throw new \LogicException($this.' already has an original job set.');
        }

        $this->originalJob = $job;
    }

    public function addRetryJob(Job $job): void
    {
        if (self::STATE_RUNNING !== $this->state) {
            throw new \LogicException('Retry jobs can only be added to running jobs.');
        }

        $job->setOriginalJob($this);
        $this->retryJobs->add($job);
    }

    public function getRetryJobs(): Collection
    {
        return $this->retryJobs;
    }

    public function isRetryJob(): bool
    {
        return $this->originalJob instanceof Job;
    }

    public function isRetried(): bool
    {
        foreach ($this->retryJobs as $retryJob) {
            /* @var Job $job */

            if (!$retryJob->isInFinalState()) {
                return true;
            }
        }

        return false;
    }

    public function checked(): void
    {
        $this->checkedAt = new DateTime();
    }

    public function getCheckedAt(): DateTime|null
    {
        return $this->checkedAt;
    }

    public function setStackTrace(FlattenException $flattenException): void
    {
        $this->flattenException = $flattenException;
    }

    public function getStackTrace(): FlattenException|null
    {
        return $this->flattenException;
    }

    public function getQueue(): string|null
    {
        return $this->queue;
    }

    public function isNew(): bool
    {
        return self::STATE_NEW === $this->state;
    }

    public function isPending(): bool
    {
        return self::STATE_PENDING === $this->state;
    }

    public function isCanceled(): bool
    {
        return self::STATE_CANCELED === $this->state;
    }

    public function isRunning(): bool
    {
        return self::STATE_RUNNING === $this->state;
    }

    public function isTerminated(): bool
    {
        return self::STATE_TERMINATED === $this->state;
    }

    public function isFailed(): bool
    {
        return self::STATE_FAILED === $this->state;
    }

    public function isFinished(): bool
    {
        return self::STATE_FINISHED === $this->state;
    }

    public function isIncomplete(): bool
    {
        return self::STATE_INCOMPLETE === $this->state;
    }

    public function __toString(): string
    {
        return sprintf('Job(id = %s, command = "%s")', $this->id, $this->command);
    }

    private function mightHaveStarted()
    {
        if (null === $this->id) {
            return false;
        }

        if (self::STATE_NEW === $this->state) {
            return false;
        }

        return !(self::STATE_PENDING === $this->state && !$this->isStartable());
    }
}
