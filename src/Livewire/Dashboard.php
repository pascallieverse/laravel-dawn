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
     * Sends a command to Rust AND force-removes from Redis directly
     * so stuck/orphaned jobs are cleaned up even if Rust isn't running.
     */
    public function cancelProcessingJob(string $id): void
    {
        $commands = app(\Dawn\Contracts\CommandQueue::class);
        $masters = app(MasterSupervisorRepository::class);

        foreach ($masters->names() as $master) {
            $commands->push($master, 'cancel-job', ['id' => $id]);
        }

        // Force-remove from Redis in case Rust isn't running
        app(JobRepository::class)->forceCancel($id);
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

        // Pending jobs: jobs waiting in queue lists + delayed sets
        $pendingJobsList = collect();
        $dawnConn = $redis->connection('dawn');
        foreach ($allSupervisors as $supervisor) {
            foreach (($supervisor['queues'] ?? []) as $queue) {
                $raw = $dawnConn->lrange('queues:' . $queue, 0, 9);
                foreach ($raw as $payload) {
                    $data = json_decode($payload, true);
                    if (! $data) continue;
                    $pendingJobsList->push([
                        'id' => $data['id'] ?? $data['uuid'] ?? '-',
                        'name' => class_basename($data['displayName'] ?? $data['job'] ?? 'Unknown'),
                        'queue' => $queue,
                        'status' => 'pending',
                        'pushed_at' => isset($data['pushedAt']) ? (float) $data['pushedAt'] : null,
                    ]);
                }
                $delayed = $dawnConn->zrangebyscore('queues:' . $queue . ':delayed', '-inf', '+inf', ['withscores' => true, 'limit' => [0, 10]]);
                if (is_array($delayed)) {
                    foreach ($delayed as $payload => $score) {
                        $data = json_decode($payload, true);
                        if (! $data) continue;
                        $remaining = (float) $score - now()->timestamp;
                        $pendingJobsList->push([
                            'id' => $data['id'] ?? $data['uuid'] ?? '-',
                            'name' => class_basename($data['displayName'] ?? $data['job'] ?? 'Unknown'),
                            'queue' => $queue,
                            'status' => 'delayed',
                            'delayed_until' => (float) $score,
                            'pushed_at' => isset($data['pushedAt']) ? (float) $data['pushedAt'] : null,
                        ]);
                    }
                }
            }
        }
        $pendingJobsList = $pendingJobsList->take(10)->values();

        // Processing jobs: jobs in pending_jobs with status 'reserved' (currently being executed by a worker)
        $pendingJobs = $jobs->getPending(0, 100);
        $processingJobsList = collect($pendingJobs)
            ->filter(fn ($job) => ($job['status'] ?? '') === 'reserved')
            ->map(fn ($job) => [
                'id' => $job['id'] ?? '',
                'name' => class_basename($job['name'] ?? 'Unknown'),
                'queue' => $job['queue'] ?? 'default',
                'status' => 'processing',
                'reserved_at' => isset($job['reserved_at']) ? (float) $job['reserved_at'] : null,
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
            'pendingJobs' => $pendingJobsList,
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
