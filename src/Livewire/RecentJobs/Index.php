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

    public string $activeTab = 'pending';

    public int $page = 1;

    public int $perPage = 50;

    public array $selected = [];

    public function mount(?string $type = null): void
    {
        $validTabs = ['pending', 'processing', 'completed', 'failed', 'silenced'];
        $this->activeTab = in_array($type, $validTabs) ? $type : 'pending';
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
     * Cancel a single pending job by removing it from the queue list.
     */
    public function cancelPendingJob(string $id, string $queue): void
    {
        $redis = app(RedisFactory::class)->connection('dawn');
        $raw = $redis->lrange('queues:' . $queue, 0, -1);

        foreach ($raw as $payload) {
            $data = json_decode($payload, true);
            $jobId = $data['id'] ?? $data['uuid'] ?? null;
            if ($jobId === $id) {
                $redis->lrem('queues:' . $queue, 1, $payload);
                break;
            }
        }

        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    /**
     * Cancel a single processing job by sending a cancel command to Rust.
     */
    public function cancelProcessingJob(string $id): void
    {
        $commands = app(CommandQueue::class);
        $masters = app(MasterSupervisorRepository::class);

        foreach ($masters->names() as $master) {
            $commands->push($master, 'cancel-job', ['id' => $id]);
        }

        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    /**
     * Cancel all selected jobs.
     */
    public function cancelSelected(): void
    {
        if ($this->activeTab === 'pending') {
            $allPending = $this->getPendingFromQueues(0, 1000);
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
            'pending' => $this->getPendingFromQueues($offset, $this->perPage),
            'processing' => $jobRepo->getPending($offset, $this->perPage),
            'completed' => $jobRepo->getCompleted($offset, $this->perPage),
            'failed' => $jobRepo->getFailed($offset, $this->perPage),
            'silenced' => $jobRepo->getSilenced($offset, $this->perPage),
            default => [],
        };

        $total = match ($this->activeTab) {
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
     * Read jobs waiting in queue lists (not yet picked up by Rust).
     */
    protected function getPendingFromQueues(int $offset = 0, int $limit = 50): array
    {
        $redis = app(RedisFactory::class)->connection('dawn');
        $queueNames = $this->getQueueNames();

        $allJobs = [];
        foreach ($queueNames as $queue) {
            // Ready jobs (in the queue list)
            $raw = $redis->lrange('queues:' . $queue, 0, -1);
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
            }

            // Delayed jobs (in the delayed sorted set)
            $delayed = $redis->zrangebyscore('queues:' . $queue . ':delayed', '-inf', '+inf', ['withscores' => true]);
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
                }
            }
        }

        return array_slice($allJobs, $offset, $limit);
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
     * Get all queue names from supervisor config, falling back to ['default'].
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

        if (empty($queues)) {
            $queues['default'] = true;
        }

        return array_keys($queues);
    }
}
