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

        $status = $this->getStatus($masters, $supervisors);

        // When supervisor keys are missing from Redis but dawn is running,
        // infer process count from config so the dashboard isn't blank
        if ($totalProcesses === 0 && $status === 'running') {
            $totalProcesses = $this->getProcessCountFromConfig();
        }
        $jobsPerMinute = $this->getJobsPerMinute($metrics);

        // Collect unique queues and per-queue process counts from all supervisors
        $dawnConn = $redis->connection('dawn');
        $queueProcesses = [];
        foreach ($allSupervisors as $supervisor) {
            // Use pools if available for process counts
            $pools = $supervisor['pools'] ?? [];
            foreach ($pools as $queue => $pool) {
                $queueProcesses[$queue] = ($queueProcesses[$queue] ?? 0) + ($pool['workers'] ?? 0);
            }
            // Also add queues from config that may not have pools yet
            foreach (($supervisor['queues'] ?? []) as $queue) {
                if (! isset($queueProcesses[$queue])) {
                    $queueProcesses[$queue] = 0;
                }
            }
        }

        // When supervisors haven't registered yet (or keys expired),
        // fall back to queue names from dawn config so workload/pending
        // are still visible even without live supervisor keys in Redis
        if (empty($queueProcesses)) {
            $configQueues = $this->getQueueNamesFromConfig();
            foreach ($configQueues as $queue) {
                $queueProcesses[$queue] = 0;
            }
        }

        // Get per-queue throughput for wait time estimation
        $throughput = $metrics->getRecentThroughput(5);
        $queueThroughput = [];
        foreach ($throughput as $queue => $entries) {
            $totalCount = 0;
            foreach ($entries as $entry) {
                $totalCount += $entry['count'] ?? 0;
            }
            $minuteCount = count($entries);
            $queueThroughput[$queue] = $minuteCount > 0 ? $totalCount / $minuteCount : 0;
        }

        // Calculate pending count and workload from unique queues
        $workload = [];
        $waitingInQueue = 0;
        foreach ($queueProcesses as $queue => $processes) {
            $queueSize = (int) $dawnConn->llen('queues:' . $queue);
            $delayedSize = (int) $dawnConn->zcard('queues:' . $queue . ':delayed');
            $length = $queueSize + $delayedSize;
            $waitingInQueue += $length;

            // Estimate wait: queue length / jobs per minute for this queue
            $jpm = $queueThroughput[$queue] ?? 0;
            $waitSeconds = $jpm > 0 ? (int) round(($length / $jpm) * 60) : 0;

            $workload[] = [
                'queue' => $queue,
                'length' => $length,
                'processes' => $processes,
                'wait' => $waitSeconds,
            ];
        }

        // Pending jobs: jobs waiting in queue lists + delayed sets (use unique queues)
        $pendingJobsList = collect();
        foreach (array_keys($queueProcesses) as $queue) {
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

    protected function getStatus(MasterSupervisorRepository $masters, SupervisorRepository $supervisors): string
    {
        $all = $masters->all();

        if (! empty($all)) {
            foreach ($all as $master) {
                if (($master['status'] ?? '') === 'paused') {
                    return 'paused';
                }
            }
            return 'running';
        }

        // Master key may have expired between heartbeats (30s TTL, 5s refresh).
        // Check for live supervisor keys as a secondary signal — Rust writes
        // both master and supervisor heartbeats, but supervisors are registered
        // under the master name in a SET which may lag behind key expiry.
        // Fall back to checking if ANY supervisor keys exist.
        if (! empty($supervisors->all())) {
            return 'running';
        }

        // Last resort: check for recent processing activity.
        // If jobs are in pending_jobs (being processed by Rust), dawn is running
        // even if master/supervisor registration keys have temporarily expired.
        $conn = app(RedisFactory::class)->connection('dawn');
        $prefix = \Dawn\DawnServiceProvider::resolvePrefix();

        $processingCount = (int) $conn->zcard($prefix . 'pending_jobs');
        if ($processingCount > 0) {
            return 'running';
        }

        return 'inactive';
    }

    protected function getJobsPerMinute(MetricsRepository $metrics): float
    {
        $throughput = $metrics->getRecentThroughput(5);
        $total = 0;
        $minutes = 0;

        foreach ($throughput as $queue => $entries) {
            foreach ($entries as $entry) {
                $total += $entry['count'] ?? 0;
            }
            $minutes = max($minutes, count($entries));
        }

        if ($minutes === 0) {
            return 0;
        }

        return round($total / $minutes, 2);
    }

    /**
     * Get queue names from dawn config as a fallback when supervisor
     * keys are not available in Redis (e.g. between heartbeats).
     */
    protected function getQueueNamesFromConfig(): array
    {
        $queues = [];
        $env = app()->environment();

        // Check environment-specific overrides first, then defaults
        $supervisors = config("dawn.environments.{$env}", []);
        if (empty($supervisors)) {
            $supervisors = config('dawn.defaults', []);
        }

        foreach ($supervisors as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? ['default']) as $queue) {
                $queues[$queue] = true;
            }
        }

        // Also check defaults (in case env overrides don't specify queues)
        foreach (config('dawn.defaults', []) as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? ['default']) as $queue) {
                $queues[$queue] = true;
            }
        }

        return empty($queues) ? ['default'] : array_keys($queues);
    }

    /**
     * Get the total configured process count from dawn config.
     * Used as fallback when supervisor Redis keys are unavailable.
     */
    protected function getProcessCountFromConfig(): int
    {
        $env = app()->environment();
        $defaults = config('dawn.defaults', []);
        $envOverrides = config("dawn.environments.{$env}", []);

        $total = 0;
        foreach ($defaults as $name => $sup) {
            $merged = array_merge($sup, $envOverrides[$name] ?? []);
            $total += $merged['minProcesses'] ?? $merged['maxProcesses'] ?? 1;
        }

        return $total ?: 1;
    }
}
