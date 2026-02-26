<?php

namespace Dawn\Livewire\RecentJobs;

use Dawn\Contracts\CommandQueue;
use Dawn\Contracts\JobRepository;
use Dawn\Contracts\MasterSupervisorRepository;
use Dawn\Contracts\SupervisorRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
#[Title('Jobs')]
class Index extends Component
{
    use FormatsValues;

    public string $activeTab = 'all';

    public int $page = 1;

    public int $perPage = 50;

    public array $selected = [];

    public function mount(?string $type = null): void
    {
        $validTabs = ['all', 'pending', 'processing', 'completed', 'failed', 'silenced'];
        $this->activeTab = in_array($type, $validTabs) ? $type : 'all';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->page = 1;
        $this->selected = [];
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * Retry a single failed job.
     */
    public function retryJob(string $id): void
    {
        app(JobRepository::class)->retry($id);
        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    /**
     * Retry all selected failed jobs.
     */
    public function retrySelected(): void
    {
        $jobRepo = app(JobRepository::class);
        foreach ($this->selected as $id) {
            $jobRepo->retry($id);
        }
        $this->selected = [];
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAllFailed(): void
    {
        app(JobRepository::class)->retryAll();
        $this->selected = [];
    }

    /**
     * Cancel a single pending job by removing it from the queue list.
     * Iterates in chunks to avoid loading the entire queue into memory.
     */
    public function cancelPendingJob(string $id, string $queue): void
    {
        $redis = app(RedisFactory::class)->connection('dawn');
        $queueKey = 'queues:' . $queue;
        $chunkSize = 100;
        $offset = 0;

        while (true) {
            $raw = $redis->lrange($queueKey, $offset, $offset + $chunkSize - 1);

            if (empty($raw)) {
                break;
            }

            foreach ($raw as $payload) {
                $data = json_decode($payload, true);
                $jobId = $data['id'] ?? $data['uuid'] ?? null;
                if ($jobId === $id) {
                    $redis->lrem($queueKey, 1, $payload);
                    $this->selected = array_values(array_diff($this->selected, [$id]));
                    return;
                }
            }

            $offset += $chunkSize;
        }

        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    /**
     * Cancel a single processing job by sending a cancel command to Rust
     * AND force-removing from Redis so stuck jobs are cleaned up immediately.
     */
    public function cancelProcessingJob(string $id): void
    {
        $commands = app(CommandQueue::class);
        $masters = app(MasterSupervisorRepository::class);

        foreach ($masters->names() as $master) {
            $commands->push($master, 'cancel-job', ['id' => $id]);
        }

        // Force-remove from Redis in case Rust isn't running
        app(JobRepository::class)->forceCancel($id);

        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    /**
     * Cancel all selected jobs.
     */
    public function cancelSelected(): void
    {
        if ($this->activeTab === 'pending') {
            // For pending jobs, we need the queue name to remove from.
            // Fetch current page of pending jobs which should contain selections.
            $offset = ($this->page - 1) * $this->perPage;
            $allPending = $this->getPendingFromQueues($offset, $this->perPage);
            foreach ($allPending as $job) {
                if (in_array($job['id'], $this->selected)) {
                    $this->cancelPendingJob($job['id'], $job['queue']);
                }
            }
        } elseif ($this->activeTab === 'processing') {
            foreach ($this->selected as $id) {
                $this->cancelProcessingJob($id);
            }
        }

        $this->selected = [];
    }

    /**
     * Cancel all jobs in the current tab.
     */
    public function cancelAll(): void
    {
        if ($this->activeTab === 'pending') {
            $this->cancelAllPending();
        } elseif ($this->activeTab === 'processing') {
            $this->cancelAllProcessing();
        }

        $this->selected = [];
    }

    /**
     * Cancel all pending jobs by flushing queue lists.
     */
    protected function cancelAllPending(): void
    {
        $redis = app(RedisFactory::class)->connection('dawn');
        $supervisors = app(SupervisorRepository::class)->all();

        $queues = [];
        foreach ($supervisors as $supervisor) {
            foreach (($supervisor['queues'] ?? []) as $queue) {
                $queues[$queue] = true;
            }
        }

        foreach (array_keys($queues) as $queue) {
            $redis->del('queues:' . $queue);
        }
    }

    /**
     * Cancel all processing jobs by sending cancel commands for each.
     */
    protected function cancelAllProcessing(): void
    {
        $jobRepo = app(JobRepository::class);
        $commands = app(CommandQueue::class);
        $masters = app(MasterSupervisorRepository::class);

        $processingJobs = $jobRepo->getPending(0, 500);
        $masterNames = $masters->names();

        foreach ($processingJobs as $job) {
            if (($job['status'] ?? '') === 'reserved') {
                $id = $job['id'] ?? '';
                if ($id) {
                    foreach ($masterNames as $master) {
                        $commands->push($master, 'cancel-job', ['id' => $id]);
                    }
                }
            }
        }
    }

    public function render()
    {
        $jobRepo = app(JobRepository::class);
        $offset = ($this->page - 1) * $this->perPage;

        $jobs = match ($this->activeTab) {
            'all' => $this->getAllJobs($jobRepo, $offset, $this->perPage),
            'pending' => $this->getPendingFromQueues($offset, $this->perPage),
            'processing' => $jobRepo->getPending($offset, $this->perPage),
            'completed' => $jobRepo->getCompleted($offset, $this->perPage),
            'failed' => $jobRepo->getFailed($offset, $this->perPage),
            'silenced' => $jobRepo->getSilenced($offset, $this->perPage),
            default => [],
        };

        $total = match ($this->activeTab) {
            'all' => $this->countAllJobs($jobRepo),
            'pending' => $this->countPendingInQueues(),
            'processing' => $jobRepo->countPending(),
            'completed' => $jobRepo->countCompleted(),
            'failed' => $jobRepo->countFailed(),
            'silenced' => 0,
            default => 0,
        };

        $totalPages = max(1, (int) ceil($total / $this->perPage));

        if ($this->page > $totalPages) {
            $this->page = $totalPages;
        }

        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + count($jobs), $total);

        return view('dawn::livewire.recent-jobs.index', [
            'jobs' => $jobs,
            'total' => $total,
            'totalPages' => $totalPages,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Get all jobs: merge tracked jobs (from dawn:recent_jobs) with
     * pending/delayed jobs still sitting in the queue lists.
     * Fetches only enough from each source to fill the requested page.
     */
    protected function getAllJobs(JobRepository $jobRepo, int $offset, int $limit): array
    {
        // Fetch a window large enough to cover offset+limit from each source
        $fetchSize = $offset + $limit;

        // Jobs already tracked by Rust (reserved, completed, failed, retrying)
        $tracked = $jobRepo->getRecent(0, $fetchSize);
        $trackedIds = [];
        foreach ($tracked as $job) {
            $trackedIds[$job['id'] ?? ''] = true;
        }

        // Pending + delayed jobs from queue lists (not yet seen by Rust)
        $pending = $this->getPendingFromQueues(0, $fetchSize);

        // Merge, avoiding duplicates (pending jobs may already be in recent_jobs)
        foreach ($pending as $job) {
            if (! isset($trackedIds[$job['id'] ?? ''])) {
                $tracked[] = $job;
            }
        }

        // Sort by most recent first (pushed_at / reserved_at / completed_at / failed_at)
        usort($tracked, function ($a, $b) {
            $timeA = $a['failed_at'] ?? $a['completed_at'] ?? $a['reserved_at'] ?? $a['pushed_at'] ?? 0;
            $timeB = $b['failed_at'] ?? $b['completed_at'] ?? $b['reserved_at'] ?? $b['pushed_at'] ?? 0;
            return $timeB <=> $timeA;
        });

        return array_slice($tracked, $offset, $limit);
    }

    /**
     * Count all jobs: tracked (recent_jobs) + untracked pending/delayed.
     */
    protected function countAllJobs(JobRepository $jobRepo): int
    {
        $trackedCount = $jobRepo->countRecent();
        $pendingCount = $this->countPendingInQueues();

        // Avoid double-counting: pending jobs that Rust has already reserved
        // show up in both recent_jobs and the queue list briefly.
        // Since pending jobs in the queue list are NOT in recent_jobs
        // (Rust removes them on pop), we can safely add them.
        return $trackedCount + $pendingCount;
    }

    /**
     * Read jobs waiting in queue lists (not yet picked up by Rust).
     * Uses bounded LRANGE with offset/limit instead of loading entire queues.
     */
    protected function getPendingFromQueues(int $offset = 0, int $limit = 50): array
    {
        $redis = app(RedisFactory::class)->connection('dawn');
        $queueNames = $this->getQueueNames();

        // To paginate across multiple queues, we need to know how many jobs
        // are in each queue so we can skip the right number for offset.
        $queueSizes = [];
        foreach ($queueNames as $queue) {
            $queueSizes[$queue] = [
                'ready' => (int) $redis->llen('queues:' . $queue),
                'delayed' => (int) $redis->zcard('queues:' . $queue . ':delayed'),
            ];
        }

        $allJobs = [];
        $skipped = 0;
        $needed = $limit;

        foreach ($queueNames as $queue) {
            if ($needed <= 0) {
                break;
            }

            $readyCount = $queueSizes[$queue]['ready'];
            $delayedCount = $queueSizes[$queue]['delayed'];
            $queueTotal = $readyCount + $delayedCount;

            // Skip this entire queue if offset hasn't been consumed yet
            if ($skipped + $queueTotal <= $offset) {
                $skipped += $queueTotal;
                continue;
            }

            // Ready jobs (in the queue list)
            if ($readyCount > 0 && $skipped < $offset + $limit) {
                $readyOffset = max(0, $offset - $skipped);
                $readyLimit = min($needed, $readyCount - $readyOffset);

                if ($readyLimit > 0) {
                    $raw = $redis->lrange('queues:' . $queue, $readyOffset, $readyOffset + $readyLimit - 1);
                    foreach ($raw as $payload) {
                        $data = json_decode($payload, true);
                        if (! $data) {
                            continue;
                        }
                        $allJobs[] = [
                            'id' => $data['id'] ?? $data['uuid'] ?? '-',
                            'name' => $data['displayName'] ?? $data['job'] ?? 'Unknown',
                            'queue' => $queue,
                            'status' => 'pending',
                            'pushed_at' => $data['pushedAt'] ?? null,
                            'runtime' => null,
                        ];
                        $needed--;
                        if ($needed <= 0) {
                            break;
                        }
                    }
                }
            }

            $skipped += $readyCount;

            if ($needed <= 0) {
                break;
            }

            // Delayed jobs (in the delayed sorted set)
            if ($delayedCount > 0) {
                $delayedOffset = max(0, $offset - $skipped);
                $delayedLimit = min($needed, $delayedCount - $delayedOffset);

                if ($delayedLimit > 0) {
                    $delayed = $redis->zrangebyscore('queues:' . $queue . ':delayed', '-inf', '+inf', [
                        'withscores' => true,
                        'limit' => [$delayedOffset, $delayedLimit],
                    ]);
                    if (is_array($delayed)) {
                        foreach ($delayed as $payload => $score) {
                            $data = json_decode($payload, true);
                            if (! $data) {
                                continue;
                            }
                            $allJobs[] = [
                                'id' => $data['id'] ?? $data['uuid'] ?? '-',
                                'name' => $data['displayName'] ?? $data['job'] ?? 'Unknown',
                                'queue' => $queue,
                                'status' => 'delayed',
                                'pushed_at' => $data['pushedAt'] ?? null,
                                'delayed_until' => (float) $score,
                                'runtime' => null,
                            ];
                            $needed--;
                            if ($needed <= 0) {
                                break;
                            }
                        }
                    }
                }
            }

            $skipped += $delayedCount;
        }

        return $allJobs;
    }

    /**
     * Count total pending jobs across all queue lists (including delayed).
     */
    protected function countPendingInQueues(): int
    {
        $redis = app(RedisFactory::class)->connection('dawn');
        $queueNames = $this->getQueueNames();

        $total = 0;
        foreach ($queueNames as $queue) {
            $total += (int) $redis->llen('queues:' . $queue);
            $total += (int) $redis->zcard('queues:' . $queue . ':delayed');
        }

        return $total;
    }

    /**
     * Get all queue names from supervisor config, falling back to dawn config.
     */
    protected function getQueueNames(): array
    {
        $supervisors = app(SupervisorRepository::class)->all();

        $queues = [];
        foreach ($supervisors as $supervisor) {
            foreach (($supervisor['queues'] ?? []) as $queue) {
                $queues[$queue] = true;
            }
        }

        // When supervisor keys aren't in Redis (between heartbeats or
        // worker just started), fall back to queue names from dawn config
        if (empty($queues)) {
            $env = app()->environment();
            $configSupervisors = config("dawn.environments.{$env}", []);
            if (empty($configSupervisors)) {
                $configSupervisors = config('dawn.defaults', []);
            }
            foreach ($configSupervisors as $sup) {
                foreach ((array) ($sup['queue'] ?? ['default']) as $queue) {
                    $queues[$queue] = true;
                }
            }
            foreach (config('dawn.defaults', []) as $sup) {
                foreach ((array) ($sup['queue'] ?? ['default']) as $queue) {
                    $queues[$queue] = true;
                }
            }
        }

        if (empty($queues)) {
            $queues['default'] = true;
        }

        return array_keys($queues);
    }
}
