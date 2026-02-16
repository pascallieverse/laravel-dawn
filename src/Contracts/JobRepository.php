<?php

namespace Dawn\Contracts;

interface JobRepository
{
    /**
     * Get the next job ID.
     */
    public function nextJobId(): string;

    /**
     * Get a single job by ID.
     */
    public function find(string $id): ?array;

    /**
     * Get recent jobs (paginated).
     */
    public function getRecent(int $offset = 0, int $limit = 50): array;

    /**
     * Get pending jobs (paginated).
     */
    public function getPending(int $offset = 0, int $limit = 50): array;

    /**
     * Get completed jobs (paginated).
     */
    public function getCompleted(int $offset = 0, int $limit = 50): array;

    /**
     * Get silenced jobs (paginated).
     */
    public function getSilenced(int $offset = 0, int $limit = 50): array;

    /**
     * Get failed jobs (paginated).
     */
    public function getFailed(int $offset = 0, int $limit = 50): array;

    /**
     * Get a single failed job by ID.
     */
    public function findFailed(string $id): ?array;

    /**
     * Delete a failed job.
     */
    public function deleteFailed(string $id): void;

    /**
     * Retry a failed job.
     */
    public function retry(string $id): void;

    /**
     * Retry all failed jobs.
     */
    public function retryAll(): void;

    /**
     * Count recent jobs (all statuses).
     */
    public function countRecent(): int;

    /**
     * Count pending/processing jobs (reserved by Rust).
     */
    public function countPending(): int;

    /**
     * Count completed jobs.
     */
    public function countCompleted(): int;

    /**
     * Count failed jobs.
     */
    public function countFailed(): int;
}
