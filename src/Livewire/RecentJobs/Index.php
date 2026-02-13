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

    public array $selected = [];

    public function mount(?string $type = null): void
    {
        $validTabs = ['pending', 'processing', 'completed', 'failed', 'silenced'];
        $this->activeTab = in_array($type, $validTabs) ? $type : 'pending';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->selected = [];
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
            $jobs = $this->getPendingFromQueues();
            foreach ($jobs as $job) {
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

        $processingJobs = $jobRepo->getPending();
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

        $jobs = match ($this->activeTab) {
            'pending' => $this->getPendingFromQueues(),
            'processing' => $jobRepo->getPending(),
            'completed' => $jobRepo->getCompleted(),
            'failed' => $jobRepo->getFailed(),
            'silenced' => $jobRepo->getSilenced(),
            default => [],
        };

        return view('dawn::livewire.recent-jobs.index', [
            'jobs' => $jobs,
        ]);
    }

    /**
     * Read jobs waiting in queue lists (not yet picked up by Rust).
     * These are raw payloads in queues:{queue} Redis lists.
     */
    protected function getPendingFromQueues(): array
    {
        $redis = app(RedisFactory::class)->connection('dawn');
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

        $jobs = [];
        foreach (array_keys($queues) as $queue) {
            $raw = $redis->lrange('queues:' . $queue, 0, 49);
            foreach ($raw as $payload) {
                $data = json_decode($payload, true);
                if (! $data) {
                    continue;
                }
                $jobs[] = [
                    'id' => $data['id'] ?? $data['uuid'] ?? '-',
                    'name' => $data['displayName'] ?? $data['job'] ?? 'Unknown',
                    'queue' => $queue,
                    'status' => 'pending',
                    'pushed_at' => $data['pushedAt'] ?? null,
                    'runtime' => null,
                ];
            }
        }

        return $jobs;
    }
}
