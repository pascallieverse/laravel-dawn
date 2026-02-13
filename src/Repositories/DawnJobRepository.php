<?php

namespace Dawn\Repositories;

use Dawn\Contracts\JobRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class DawnJobRepository implements JobRepository
{
    protected RedisFactory $redis;
    protected string $prefix;

    public function __construct(RedisFactory $redis, string $prefix = 'dawn:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    protected function connection()
    {
        return $this->redis->connection('dawn');
    }

    public function nextJobId(): string
    {
        return 'dawn-' . \Illuminate\Support\Str::uuid()->toString();
    }

    public function find(string $id): ?array
    {
        $data = $this->connection()->get($this->prefix . 'job:' . $id);

        return $data ? json_decode($data, true) : null;
    }

    public function getRecent(int $offset = 0, int $limit = 50): array
    {
        return $this->getJobsFromSortedSet('recent_jobs', $offset, $limit);
    }

    public function getPending(int $offset = 0, int $limit = 50): array
    {
        return $this->getJobsFromSortedSet('pending_jobs', $offset, $limit);
    }

    public function getCompleted(int $offset = 0, int $limit = 50): array
    {
        return $this->getJobsFromSortedSet('completed_jobs', $offset, $limit);
    }

    public function getSilenced(int $offset = 0, int $limit = 50): array
    {
        // Silenced jobs are in completed_jobs but with silenced=true
        $jobs = $this->getJobsFromSortedSet('completed_jobs', $offset, $limit * 2);

        return array_values(array_filter($jobs, fn ($job) => ($job['silenced'] ?? false) === true));
    }

    public function getFailed(int $offset = 0, int $limit = 50): array
    {
        return $this->getJobsFromSortedSet('failed_jobs', $offset, $limit);
    }

    public function findFailed(string $id): ?array
    {
        $data = $this->connection()->get($this->prefix . 'failed:' . $id);

        return $data ? json_decode($data, true) : null;
    }

    public function deleteFailed(string $id): void
    {
        $conn = $this->connection();
        $conn->del([$this->prefix . 'failed:' . $id]);
        $conn->zrem($this->prefix . 'failed_jobs', $id);
        $conn->zrem($this->prefix . 'recent_failed_jobs', $id);
    }

    public function retry(string $id): void
    {
        $failed = $this->findFailed($id);

        if (! $failed) {
            return;
        }

        // Re-push the job payload to the queue
        $queue = $failed['queue'] ?? 'default';
        $payload = $failed['payload'] ?? [];

        if (! empty($payload)) {
            $encoded = json_encode(array_merge($payload, ['attempts' => 0]));
            $this->connection()->rpush('queues:' . $queue, [$encoded]);
        }

        $this->deleteFailed($id);
    }

    public function retryAll(): void
    {
        $ids = $this->connection()->zrange($this->prefix . 'failed_jobs', 0, -1);

        foreach ($ids as $id) {
            $this->retry($id);
        }
    }

    public function countRecent(): int
    {
        return (int) $this->connection()->zcard($this->prefix . 'recent_jobs');
    }

    public function countCompleted(): int
    {
        return (int) $this->connection()->zcard($this->prefix . 'completed_jobs');
    }

    public function countFailed(): int
    {
        return (int) $this->connection()->zcard($this->prefix . 'failed_jobs');
    }

    /**
     * Get jobs from a sorted set, reverse ordered (newest first).
     */
    protected function getJobsFromSortedSet(string $key, int $offset, int $limit): array
    {
        $ids = $this->connection()->zrevrange(
            $this->prefix . $key,
            $offset,
            $offset + $limit - 1,
        );

        $jobs = [];
        foreach ($ids as $id) {
            $job = $this->find($id);
            if ($job) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }
}
