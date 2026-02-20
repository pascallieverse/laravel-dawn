<?php

namespace Dawn\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

/**
 * Wraps a raw Redis job payload as a proper Laravel Job instance.
 *
 * This ensures jobs are processed through CallQueuedHandler, which:
 * - Sets $this->job on the command (InteractsWithQueue support)
 * - Runs job middleware
 * - Fires JobProcessing/JobProcessed events
 * - Handles job chains and batches
 * - Supports $this->release(), $this->delete(), $this->attempts()
 */
class DawnJob extends Job implements JobContract
{
    /**
     * The raw JSON payload from Redis.
     */
    protected string $rawPayload;

    /**
     * The delay (in seconds) when the job is released back to the queue.
     */
    public int $releaseDelay = 0;

    public function __construct(
        Container $container,
        string $rawPayload,
        string $connectionName = 'dawn',
        string $queue = 'default',
    ) {
        $this->container = $container;
        $this->rawPayload = $rawPayload;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    /**
     * Get the job identifier.
     */
    public function getJobId(): ?string
    {
        return $this->payload()['uuid'] ?? $this->payload()['id'] ?? null;
    }

    /**
     * Get the raw body of the job.
     */
    public function getRawBody(): string
    {
        return $this->rawPayload;
    }

    /**
     * Get the number of times the job has been attempted.
     */
    public function attempts(): int
    {
        return ($this->payload()['attempts'] ?? 0) + 1;
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * Instead of pushing back to Redis directly (Rust handles the queue),
     * we store the delay so the worker can report it back to Rust.
     */
    public function release($delay = 0): void
    {
        parent::release($delay);
        $this->releaseDelay = (int) $delay;
    }
}
