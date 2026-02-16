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
        $this->ensureFailedJobsIndexed();

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

        // Skip already-retried jobs
        $job = $this->find($id);
        if ($job && ($job['status'] ?? '') === 'retried') {
            return;
        }

        $queue = $failed['queue'] ?? 'default';
        $payload = $failed['payload'] ?? [];

        if (! empty($payload)) {
            // Create a NEW job with a fresh UUID for the retry
            $newUuid = (string) \Illuminate\Support\Str::uuid();
            $newId = 'dawn-' . $newUuid;
            $now = now()->timestamp;

            $payload['uuid'] = $newUuid;
            $payload['id'] = $newId;
            $payload['attempts'] = 0;
            $payload['pushedAt'] = (float) $now;

            $encoded = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);

            if ($encoded !== false) {
                $conn = $this->connection();

                // Push the new job to the queue. It will appear in the
                // "pending" tab immediately (reads directly from queue lists).
                // When Rust picks it up, it adds it to pending_jobs/recent_jobs
                // ZSETs with status "reserved".
                $conn->rpush('queues:' . $queue, [$encoded]);

                // Pre-register in recent_jobs so it's visible in the
                // dashboard's recent jobs list. Do NOT add to pending_jobs —
                // that ZSET is managed by Rust for reserved (processing) jobs.
                $newJobData = json_encode([
                    'id' => $newId,
                    'uuid' => $newUuid,
                    'name' => $payload['displayName'] ?? $failed['name'] ?? 'Unknown',
                    'class' => $payload['displayName'] ?? $failed['class'] ?? 'Unknown',
                    'status' => 'pending',
                    'queue' => $queue,
                    'tags' => $payload['tags'] ?? [],
                    'pushed_at' => (float) $now,
                    'attempts' => 0,
                    'retried_from' => $id,
                ]);
                $conn->setex($this->prefix . 'job:' . $newId, 86400, $newJobData);
                $conn->zadd($this->prefix . 'recent_jobs', $now, $newId);
            }

            // Mark the OLD failed job as "retried" (keep it visible in the list)
            $retriedJob = json_encode([
                'id' => $id,
                'uuid' => $failed['uuid'] ?? '',
                'name' => $failed['name'] ?? $failed['class'] ?? 'Unknown',
                'class' => $failed['class'] ?? $failed['name'] ?? 'Unknown',
                'status' => 'retried',
                'queue' => $queue,
                'tags' => $failed['tags'] ?? [],
                'failed_at' => $failed['failed_at'] ?? null,
                'exception' => $failed['exception'] ?? null,
                'retried_at' => $now,
                'retried_by' => $newId,
            ]);
            $this->connection()->setex($this->prefix . 'job:' . $id, 604800, $retriedJob);

            // Update the failed detail to include retry info (keep for history)
            $failed['retried_at'] = $now;
            $failed['retried_by'] = $newId;
            $this->connection()->setex(
                $this->prefix . 'failed:' . $id,
                604800,
                json_encode($failed, JSON_INVALID_UTF8_SUBSTITUTE)
            );
        }
    }

    public function retryAll(): void
    {
        $ids = $this->connection()->zrange($this->prefix . 'failed_jobs', 0, -1);

        foreach ($ids as $id) {
            // retry() already skips retried jobs internally
            $this->retry($id);
        }
    }

    public function countFailedPending(): int
    {
        $ids = $this->connection()->zrange($this->prefix . 'failed_jobs', 0, -1);
        $count = 0;

        foreach ($ids as $id) {
            $job = $this->find($id);
            if ($job && ($job['status'] ?? '') !== 'retried') {
                $count++;
            }
        }

        return $count;
    }

    public function countRecent(): int
    {
        return (int) $this->connection()->zcard($this->prefix . 'recent_jobs');
    }

    public function countPending(): int
    {
        return (int) $this->connection()->zcard($this->prefix . 'pending_jobs');
    }

    public function countCompleted(): int
    {
        return (int) $this->connection()->zcard($this->prefix . 'completed_jobs');
    }

    public function countFailed(): int
    {
        $this->ensureFailedJobsIndexed();

        return (int) $this->connection()->zcard($this->prefix . 'failed_jobs');
    }

    /**
     * Repair the failed_jobs ZSET by scanning recent_jobs for failed-status
     * jobs that are missing from failed_jobs (e.g. removed by old cleanup code).
     * Runs at most once per request.
     */
    protected bool $failedIndexRepaired = false;

    protected function ensureFailedJobsIndexed(): void
    {
        if ($this->failedIndexRepaired) {
            return;
        }
        $this->failedIndexRepaired = true;

        $conn = $this->connection();
        $failedKey = $this->prefix . 'failed_jobs';
        $recentKey = $this->prefix . 'recent_jobs';

        // Get all recent job IDs with their scores
        $idsWithScores = $conn->zrevrange($recentKey, 0, 499, 'WITHSCORES');

        if (empty($idsWithScores)) {
            return;
        }

        foreach ($idsWithScores as $id => $score) {
            // Check if already in failed_jobs
            $rank = $conn->zrank($failedKey, $id);
            if ($rank !== null && $rank !== false) {
                continue;
            }

            // Check if this job has failed status
            $job = $this->find($id);
            if (! $job) {
                $job = $this->findFailed($id);
            }

            if ($job && in_array($job['status'] ?? '', ['failed', 'retried'])) {
                $conn->zadd($failedKey, $score, $id);
            }
        }
    }

    /**
     * Get jobs from a sorted set, reverse ordered (newest first).
     * Over-fetches from the ZSET to compensate for entries whose detail
     * keys (dawn:job:{id}) have expired. For failed job sets, falls back
     * to dawn:failed:{id} which has a longer TTL.
     */
    protected function getJobsFromSortedSet(string $key, int $offset, int $limit): array
    {
        $fullKey = $this->prefix . $key;
        $isFailedSet = in_array($key, ['failed_jobs', 'recent_failed_jobs']);
        $jobs = [];
        $cursor = $offset;
        $batchSize = $limit;
        $maxPasses = 3;

        for ($pass = 0; $pass < $maxPasses && count($jobs) < $limit; $pass++) {
            $ids = $this->connection()->zrevrange($fullKey, $cursor, $cursor + $batchSize - 1);

            if (empty($ids)) {
                break;
            }

            foreach ($ids as $id) {
                $job = $this->find($id);

                // For failed job sets, fall back to dawn:failed:{id} (7d TTL)
                // when dawn:job:{id} (24h TTL) has expired
                if (! $job && $isFailedSet) {
                    $job = $this->findFailed($id);
                    if ($job) {
                        $job['status'] = $job['status'] ?? 'failed';
                    }
                }

                if ($job) {
                    $jobs[] = $job;
                    if (count($jobs) >= $limit) {
                        break;
                    }
                }
            }

            $cursor += $batchSize;
        }

        return $jobs;
    }
}
