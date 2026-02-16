<?php

namespace Dawn\Livewire;

use Dawn\Contracts\JobRepository;
use Dawn\Contracts\MasterSupervisorRepository;
use Dawn\Contracts\MetricsRepository;
use Dawn\Contracts\SupervisorRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use FormatsValues;

    /**
     * Cancel a processing job from the dashboard.
     */
    public function cancelProcessingJob(string $id): void
    {
        $commands = app(\Dawn\Contracts\CommandQueue::class);
        $masters = app(MasterSupervisorRepository::class);

        foreach ($masters->names() as $master) {
            $commands->push($master, 'cancel-job', ['id' => $id]);
        }
    }

    public function render()
    {
        $masters = app(MasterSupervisorRepository::class);
        $supervisors = app(SupervisorRepository::class);
        $jobs = app(JobRepository::class);
        $metrics = app(MetricsRepository::class);
        $redis = app(RedisFactory::class);

        $allSupervisors = $supervisors->all();
        $totalProcesses = collect($allSupervisors)->sum(fn ($s) => $s['processes'] ?? 0);

        $status = $this->getStatus($masters);
        $jobsPerMinute = $this->getJobsPerMinute($metrics);

        $workload = [];
        $waitingInQueue = 0;
        foreach ($allSupervisors as $supervisor) {
            $pools = $supervisor['pools'] ?? [];
            foreach ($pools as $queue => $pool) {
                $queueSize = (int) $redis->connection('dawn')->llen('queues:' . $queue);
                $delayedSize = (int) $redis->connection('dawn')->zcard('queues:' . $queue . ':delayed');
                $waitingInQueue += $queueSize + $delayedSize;
                $workload[] = [
                    'queue' => $queue,
                    'length' => $queueSize + $delayedSize,
                    'processes' => $pool['workers'] ?? 0,
                    'wait' => 0,
                ];
            }
        }

        // Processing jobs: jobs in pending_jobs with status 'reserved' (currently being executed by a worker)
        $pendingJobs = $jobs->getPending(0, 100);
        $processingJobsList = collect($pendingJobs)
            ->filter(fn ($job) => ($job['status'] ?? '') === 'reserved')
            ->map(fn ($job) => [
                'id' => $job['id'] ?? '',
                'name' => class_basename($job['name'] ?? 'Unknown'),
                'queue' => $job['queue'] ?? 'default',
                'status' => 'processing',
                'started_at' => isset($job['reserved_at'])
                    ? $this->formatDate($job['reserved_at'])
                    : '—',
                'elapsed' => isset($job['reserved_at'])
                    ? $this->formatRuntime((now()->timestamp - $job['reserved_at']) * 1000)
                    : '—',
                'supervisor' => $job['supervisor'] ?? '',
            ])
            ->values();

        // Recent jobs: only completed and failed (not processing/reserved)
        $recentJobsList = collect($jobs->getRecent(0, 20))
            ->filter(fn ($job) => in_array($job['status'] ?? '', ['completed', 'failed']))
            ->take(10)
            ->map(fn ($job) => [
                'id' => $job['id'] ?? '',
                'name' => class_basename($job['name'] ?? 'Unknown'),
                'queue' => $job['queue'] ?? 'default',
                'status' => $job['status'] ?? 'unknown',
                'date' => $this->formatDate($job['completed_at'] ?? $job['failed_at'] ?? null),
                'runtime' => isset($job['runtime'])
                    ? $this->formatRuntime($job['runtime'] * 1000)
                    : '—',
                'completed_at' => $job['completed_at'] ?? $job['failed_at'] ?? null,
            ])
            ->values();

        return view('dawn::livewire.dashboard', [
            'status' => $status,
            'processes' => $totalProcesses,
            'jobsPerMinute' => $jobsPerMinute,
            'completedJobsCount' => $jobs->countCompleted(),
            'failedJobsCount' => $jobs->countFailed(),
            'pendingJobsCount' => $waitingInQueue,
            'processingJobsCount' => $processingJobsList->count(),
            'workload' => $workload,
            'processingJobs' => $processingJobsList,
            'recentJobs' => $recentJobsList,
        ]);
    }

    protected function getStatus(MasterSupervisorRepository $masters): string
    {
        $all = $masters->all();

        if (empty($all)) {
            return 'inactive';
        }

        foreach ($all as $master) {
            if (($master['status'] ?? '') === 'paused') {
                return 'paused';
            }
        }

        return 'running';
    }

    protected function getJobsPerMinute(MetricsRepository $metrics): float
    {
        $queues = $metrics->measuredQueues();
        $total = 0;

        foreach ($queues as $queue) {
            $data = $metrics->getQueueMetrics($queue);
            $total += $data['count'] ?? 0;
        }

        return round($total / max(1, 60), 2);
    }
}
