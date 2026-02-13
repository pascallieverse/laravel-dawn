<?php

namespace Dawn\Contracts;

interface MetricsRepository
{
    /**
     * Get the job metrics for a given job class.
     */
    public function getJobMetrics(string $class): array;

    /**
     * Get the queue metrics for a given queue.
     */
    public function getQueueMetrics(string $queue): array;

    /**
     * Get all job classes that have metrics.
     */
    public function measuredJobs(): array;

    /**
     * Get all queues that have metrics.
     */
    public function measuredQueues(): array;

    /**
     * Get job metric snapshots.
     */
    public function snapshotsForJob(string $class): array;

    /**
     * Get queue metric snapshots.
     */
    public function snapshotsForQueue(string $queue): array;
}
