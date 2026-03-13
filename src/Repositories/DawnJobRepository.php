<?php

namespace Dawn\Repositories;

use Dawn\Contracts\JobRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        return 'dawn-'.Str::uuid()->toString();
    }

    public function find(string $id): ?array
    {
        $data = $this->connection()->get($this->prefix.'job:'.$id);

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
        $data = $this->connection()->get($this->prefix.'failed:'.$id);

        return $data ? json_decode($data, true) : null;
    }

    public function deleteFailed(string $id): void
    {
        $conn = $this->connection();
        $conn->del([$this->prefix.'failed:'.$id, $this->prefix.'job:'.$id, $this->prefix.'logs:'.$id]);
        $conn->zrem($this->prefix.'failed_jobs', $id);
        $conn->zrem($this->prefix.'recent_failed_jobs', $id);
        $conn->zrem($this->prefix.'recent_jobs', $id);
    }

    public function retry(string $id): ?string
    {
        $failed = $this->findFailed($id);

        if (! $failed) {
            return null;
        }

        // Skip already-retried jobs
        $job = $this->find($id);
        if ($job && ($job['status'] ?? '') === 'retried') {
            return null;
        }

        $queue = $failed['queue'] ?? 'default';
        $payload = $failed['payload'] ?? [];

        // Validate the payload has the minimum fields Rust needs to parse it.
        // Force-cancelled jobs may have incomplete payloads and cannot be retried.
        $hasRequiredFields = ! empty($payload)
            && isset($payload['displayName'])
            && isset($payload['job'])
            && isset($payload['data']['command']);

        if ($hasRequiredFields) {
            // Create a NEW job with a fresh UUID for the retry
            $newUuid = (string) Str::uuid();
            $newId = 'dawn-'.$newUuid;
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
                $conn->rpush('queues:'.$queue, [$encoded]);

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
                $conn->setex($this->prefix.'job:'.$newId, 86400, $newJobData);
                $conn->zadd($this->prefix.'recent_jobs', $now, $newId);
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
            $this->connection()->setex($this->prefix.'job:'.$id, 604800, $retriedJob);

            // Update the failed detail to include retry info (keep for history)
            $failed['retried_at'] = $now;
            $failed['retried_by'] = $newId;
            $this->connection()->setex(
                $this->prefix.'failed:'.$id,
                604800,
                json_encode($failed, JSON_INVALID_UTF8_SUBSTITUTE)
            );

            return $newId;
        }

        return null;
    }

    public function retryAll(): void
    {
        $ids = $this->connection()->zrange($this->prefix.'failed_jobs', 0, -1);

        foreach ($ids as $id) {
            // retry() already skips retried jobs internally
            $this->retry($id);
        }
    }

    public function countFailedPending(): int
    {
        $conn = $this->connection();
        $ids = $conn->zrange($this->prefix.'failed_jobs', 0, -1);

        if (empty($ids)) {
            return 0;
        }

        // Batch-fetch all job details with MGET
        $jobKeys = array_map(fn ($id) => $this->prefix.'job:'.$id, $ids);
        $results = $conn->mget($jobKeys);
        $count = 0;

        foreach ($results as $raw) {
            if ($raw) {
                $job = json_decode($raw, true);
                if ($job && ($job['status'] ?? '') !== 'retried') {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function countRecent(): int
    {
        return (int) $this->connection()->zcard($this->prefix.'recent_jobs');
    }

    public function countPending(): int
    {
        return (int) $this->connection()->zcard($this->prefix.'pending_jobs');
    }

    public function countCompleted(): int
    {
        return (int) $this->connection()->zcard($this->prefix.'completed_jobs');
    }

    public function countFailed(): int
    {
        $this->ensureFailedJobsIndexed();

        return (int) $this->connection()->zcard($this->prefix.'failed_jobs');
    }

    public function forceCancel(string $id): void
    {
        $conn = $this->connection();
        $now = now()->timestamp;

        // Remove from pending_jobs ZSET
        $conn->zrem($this->prefix.'pending_jobs', $id);

        // Update the job detail key to show as failed/cancelled
        $existing = $this->find($id);
        $jobData = json_encode([
            'id' => $id,
            'uuid' => $existing['uuid'] ?? '',
            'name' => $existing['name'] ?? $existing['class'] ?? 'Unknown',
            'class' => $existing['class'] ?? $existing['name'] ?? 'Unknown',
            'status' => 'failed',
            'queue' => $existing['queue'] ?? 'default',
            'tags' => $existing['tags'] ?? [],
            'failed_at' => $now,
            'exception' => 'Manually cancelled from dashboard (job was stuck)',
        ]);

        $conn->setex($this->prefix.'job:'.$id, 86400, $jobData);

        // Add to failed_jobs ZSET so it shows up in the failed tab
        $conn->zadd($this->prefix.'failed_jobs', $now, $id);

        // Store a failed detail record for the failed jobs show page
        $failedDetail = json_encode([
            'id' => $id,
            'uuid' => $existing['uuid'] ?? '',
            'name' => $existing['name'] ?? $existing['class'] ?? 'Unknown',
            'class' => $existing['class'] ?? $existing['name'] ?? 'Unknown',
            'queue' => $existing['queue'] ?? 'default',
            'tags' => $existing['tags'] ?? [],
            'failed_at' => $now,
            'exception' => 'Manually cancelled from dashboard (job was stuck)',
            'trace' => '',
            'payload' => $existing['payload'] ?? [],
        ]);

        $conn->setex($this->prefix.'failed:'.$id, 604800, $failedDetail);
    }

    /**
     * Repair the failed_jobs ZSET by scanning recent_jobs for failed-status
     * jobs that are missing from failed_jobs (e.g. removed by old cleanup code).
     * Uses a Redis TTL lock so this runs at most once per minute across all requests.
     * Within a single request, the in-memory flag prevents duplicate runs.
     */
    protected bool $failedIndexRepaired = false;

    protected function ensureFailedJobsIndexed(): void
    {
        if ($this->failedIndexRepaired) {
            return;
        }
        $this->failedIndexRepaired = true;

        $conn = $this->connection();
        $lockKey = $this->prefix.'repair_lock';

        // Only run the expensive repair once per minute (across all requests)
        if (! $conn->set($lockKey, '1', 'EX', 60, 'NX')) {
            return;
        }

        $failedKey = $this->prefix.'failed_jobs';
        $recentKey = $this->prefix.'recent_jobs';

        // Get recent job IDs with their scores
        $idsWithScores = $conn->zrevrange($recentKey, 0, 499, 'WITHSCORES');

        if (empty($idsWithScores)) {
            return;
        }

        $ids = array_keys($idsWithScores);

        // Batch-fetch all job details with MGET
        $jobKeys = array_map(fn ($id) => $this->prefix.'job:'.$id, $ids);
        $results = $conn->mget($jobKeys);

        // Collect IDs that need fallback to failed: keys
        $missingIds = [];
        $jobMap = [];

        foreach ($ids as $i => $id) {
            $raw = $results[$i] ?? null;
            if ($raw) {
                $job = json_decode($raw, true);
                if ($job) {
                    $jobMap[$id] = $job;

                    continue;
                }
            }
            $missingIds[] = $id;
        }

        // Batch fallback: MGET dawn:failed:{id}
        if (! empty($missingIds)) {
            $failedKeys = array_map(fn ($id) => $this->prefix.'failed:'.$id, $missingIds);
            $failedResults = $conn->mget($failedKeys);
            foreach ($missingIds as $i => $id) {
                $raw = $failedResults[$i] ?? null;
                if ($raw) {
                    $job = json_decode($raw, true);
                    if ($job) {
                        $jobMap[$id] = $job;
                    }
                }
            }
        }

        // Add any failed/retried jobs missing from failed_jobs ZSET
        foreach ($jobMap as $id => $job) {
            if (in_array($job['status'] ?? '', ['failed', 'retried'])) {
                $score = $idsWithScores[$id] ?? 0;
                // Check if already in failed_jobs before adding
                $rank = $conn->zrank($failedKey, $id);
                if ($rank === null || $rank === false) {
                    $conn->zadd($failedKey, $score, $id);
                }
            }
        }
    }

    public function storeJobLogs(string $id, array $logs): void
    {
        if (empty($logs)) {
            return;
        }

        // Truncate to max 50 entries to avoid oversized Redis values
        $logs = array_slice($logs, 0, 50);

        $encoded = json_encode($logs, JSON_INVALID_UTF8_SUBSTITUTE);

        if ($encoded === false) {
            return;
        }

        // Use 7-day TTL (same as failed jobs) so logs outlive the job record
        $this->connection()->setex(
            $this->prefix.'logs:'.$id,
            604800,
            $encoded
        );
    }

    public function getJobLogs(string $id): array
    {
        $data = $this->connection()->get($this->prefix.'logs:'.$id);

        if (! $data) {
            return [];
        }

        return json_decode($data, true) ?: [];
    }

    public function recoverOrphanedJobs(int $graceSeconds = 300): array
    {
        $conn = $this->connection();
        $pendingKey = $this->prefix.'pending_jobs';
        $now = now()->timestamp;
        $stats = ['recovered' => 0, 'retried' => 0, 'failed' => 0];

        // Get all pending job IDs with their scores (reserved_at timestamps)
        $idsWithScores = $conn->zrange($pendingKey, 0, -1, 'WITHSCORES');

        if (empty($idsWithScores)) {
            return $stats;
        }

        // Determine the timeout per queue from config
        $supervisors = config('dawn.defaults', []);
        $queueTimeouts = [];
        foreach ($supervisors as $supervisor) {
            $timeout = $supervisor['timeout'] ?? 600;
            $queues = (array) ($supervisor['queue'] ?? ['default']);
            foreach ($queues as $queue) {
                $queueTimeouts[$queue] = $timeout;
            }
        }
        $defaultTimeout = 600;

        foreach ($idsWithScores as $id => $reservedAt) {
            $reservedAt = (int) $reservedAt;

            // Look up the job detail to find its queue
            $jobData = $this->find($id);
            $queue = $jobData['queue'] ?? 'default';
            $timeout = $queueTimeouts[$queue] ?? $defaultTimeout;

            // Job is orphaned if it's been reserved longer than timeout + grace
            $orphanThreshold = $reservedAt + $timeout + $graceSeconds;

            if ($now < $orphanThreshold) {
                continue;
            }

            $stats['recovered']++;

            // Check if the job has retry attempts remaining
            $attempts = $jobData['attempts'] ?? 0;
            $maxTries = $jobData['maxTries'] ?? ($supervisors[$queue]['tries'] ?? 3);
            $canRetry = $attempts < $maxTries;

            // Remove from pending_jobs
            $conn->zrem($pendingKey, $id);

            if ($canRetry) {
                // Try to re-queue the job for another attempt
                $failedDetail = $this->findFailed($id);
                $payload = $failedDetail['payload'] ?? $jobData['payload'] ?? null;

                if ($payload && isset($payload['data']['command'])) {
                    $queueName = $queue;
                    $payload['attempts'] = $attempts + 1;
                    $encoded = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);

                    if ($encoded !== false) {
                        $conn->rpush('queues:'.$queueName, [$encoded]);

                        // Update job status to show it was recovered
                        $updatedJob = json_encode(array_merge($jobData ?? [], [
                            'status' => 'pending',
                            'recovered_at' => $now,
                            'recovery_reason' => 'Worker killed before reporting result (timeout)',
                        ]));
                        $conn->setex($this->prefix.'job:'.$id, 86400, $updatedJob);

                        $stats['retried']++;

                        Log::warning('[Dawn] Recovered orphaned job - retrying', [
                            'id' => $id,
                            'name' => $jobData['name'] ?? 'Unknown',
                            'queue' => $queue,
                            'reserved_at' => $reservedAt,
                            'orphaned_for' => $now - $reservedAt,
                            'attempt' => $attempts + 1,
                        ]);

                        continue;
                    }
                }
            }

            // Can't retry — mark as failed
            $this->markOrphanedAsFailed($id, $jobData, $now);
            $stats['failed']++;

            Log::error('[Dawn] Recovered orphaned job - marked as failed (max retries exceeded)', [
                'id' => $id,
                'name' => $jobData['name'] ?? 'Unknown',
                'queue' => $queue,
                'reserved_at' => $reservedAt,
                'orphaned_for' => $now - $reservedAt,
            ]);
        }

        return $stats;
    }

    /**
     * Mark an orphaned job as failed when it cannot be retried.
     */
    protected function markOrphanedAsFailed(string $id, ?array $jobData, int $now): void
    {
        $conn = $this->connection();

        $jobDetail = json_encode([
            'id' => $id,
            'uuid' => $jobData['uuid'] ?? '',
            'name' => $jobData['name'] ?? $jobData['class'] ?? 'Unknown',
            'class' => $jobData['class'] ?? $jobData['name'] ?? 'Unknown',
            'status' => 'failed',
            'queue' => $jobData['queue'] ?? 'default',
            'tags' => $jobData['tags'] ?? [],
            'failed_at' => $now,
            'exception' => 'Job orphaned: worker process was killed before reporting result (likely timeout). Recovered by dawn:recover-orphans.',
        ]);

        $conn->setex($this->prefix.'job:'.$id, 86400, $jobDetail);
        $conn->zadd($this->prefix.'failed_jobs', $now, $id);

        $failedDetail = json_encode([
            'id' => $id,
            'uuid' => $jobData['uuid'] ?? '',
            'name' => $jobData['name'] ?? $jobData['class'] ?? 'Unknown',
            'class' => $jobData['class'] ?? $jobData['name'] ?? 'Unknown',
            'queue' => $jobData['queue'] ?? 'default',
            'tags' => $jobData['tags'] ?? [],
            'failed_at' => $now,
            'exception' => 'Job orphaned: worker process was killed before reporting result (likely timeout). Recovered by dawn:recover-orphans.',
            'trace' => '',
            'payload' => $jobData['payload'] ?? [],
        ]);

        $conn->setex($this->prefix.'failed:'.$id, 604800, $failedDetail);
    }

    /**
     * Get jobs from a sorted set, reverse ordered (newest first).
     * Over-fetches from the ZSET to compensate for entries whose detail
     * keys (dawn:job:{id}) have expired. For failed job sets, falls back
     * to dawn:failed:{id} which has a longer TTL.
     *
     * Uses MGET to batch-fetch all job details in a single round-trip
     * instead of individual GET calls per job.
     */
    protected function getJobsFromSortedSet(string $key, int $offset, int $limit): array
    {
        $fullKey = $this->prefix.$key;
        $isFailedSet = in_array($key, ['failed_jobs', 'recent_failed_jobs']);
        $jobs = [];
        $cursor = $offset;
        $batchSize = $limit;
        $maxPasses = 3;
        $conn = $this->connection();

        for ($pass = 0; $pass < $maxPasses && count($jobs) < $limit; $pass++) {
            $ids = $conn->zrevrange($fullKey, $cursor, $cursor + $batchSize - 1);

            if (empty($ids)) {
                break;
            }

            // Batch-fetch all job details with MGET
            $jobKeys = array_map(fn ($id) => $this->prefix.'job:'.$id, $ids);
            $results = $conn->mget($jobKeys);

            // For failed sets, collect IDs that had no job: key for fallback
            $missingIds = [];
            $idToIndex = [];

            foreach ($ids as $i => $id) {
                $raw = $results[$i] ?? null;
                if ($raw) {
                    $job = json_decode($raw, true);
                    if ($job) {
                        $jobs[] = $job;
                        if (count($jobs) >= $limit) {
                            break;
                        }

                        continue;
                    }
                }

                if ($isFailedSet) {
                    $missingIds[] = $id;
                    $idToIndex[$id] = count($jobs); // placeholder index
                }
            }

            // Batch fallback for failed sets: MGET dawn:failed:{id}
            if (! empty($missingIds) && count($jobs) < $limit) {
                $failedKeys = array_map(fn ($id) => $this->prefix.'failed:'.$id, $missingIds);
                $failedResults = $conn->mget($failedKeys);

                foreach ($missingIds as $i => $id) {
                    $raw = $failedResults[$i] ?? null;
                    if ($raw) {
                        $job = json_decode($raw, true);
                        if ($job) {
                            $job['status'] = $job['status'] ?? 'failed';
                            $jobs[] = $job;
                            if (count($jobs) >= $limit) {
                                break;
                            }
                        }
                    }
                }
            }

            $cursor += $batchSize;
        }

        return $jobs;
    }
}
