<?php

namespace Dawn\Livewire\Metrics;

use Dawn\Contracts\MetricsRepository;
use Dawn\DawnServiceProvider;
use Dawn\Livewire\Concerns\FormatsValues;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
#[Title('Job Metrics')]
class Jobs extends Component
{
    use FormatsValues;

    public function render()
    {
        $metricsRepo = app(MetricsRepository::class);

        // Get runtime metrics from dawn:metrics:job:* (only completed jobs)
        $measured = $metricsRepo->measuredJobs();
        $runtimeMetrics = [];
        foreach ($measured as $class) {
            $data = $metricsRepo->getJobMetrics($class);
            if (! empty($data)) {
                $runtimeMetrics[$class] = $data;
            }
        }

        // Get per-class status counts from job detail keys
        $statusCounts = $this->getJobStatusCounts();

        // Merge: all known job classes from both sources
        $allClasses = array_unique(array_merge(
            array_keys($runtimeMetrics),
            array_keys($statusCounts),
        ));

        $result = [];
        foreach ($allClasses as $class) {
            $runtime = $runtimeMetrics[$class] ?? [];
            $counts = $statusCounts[$class] ?? [];

            $result[] = [
                'name' => $class,
                'count' => $runtime['count'] ?? 0,
                'avg_runtime' => $runtime['avg_runtime'] ?? 0,
                'total_runtime' => $runtime['total_runtime'] ?? 0,
                'pending' => $counts['pending'] ?? 0,
                'processing' => $counts['reserved'] ?? 0,
                'completed' => $counts['completed'] ?? 0,
                'failed' => $counts['failed'] ?? 0,
            ];
        }

        // Sort by total activity (completed + pending + processing + failed), descending
        usort($result, function ($a, $b) {
            $totalA = $a['count'] + $a['pending'] + $a['processing'] + $a['failed'];
            $totalB = $b['count'] + $b['pending'] + $b['processing'] + $b['failed'];
            return $totalB <=> $totalA;
        });

        return view('dawn::livewire.metrics.jobs', [
            'metrics' => $result,
        ]);
    }

    /**
     * Scan job tracking ZSETs and aggregate status counts per job class.
     * Uses MGET for batch fetching to avoid N+1 Redis queries.
     *
     * @return array<string, array{pending: int, reserved: int, completed: int, failed: int}>
     */
    protected function getJobStatusCounts(): array
    {
        $conn = app(RedisFactory::class)->connection('dawn');
        $prefix = DawnServiceProvider::resolvePrefix();

        $counts = [];

        // Scan each status ZSET and aggregate by class name
        $sets = [
            'pending_jobs' => 500,
            'completed_jobs' => 2000,
            'failed_jobs' => 500,
        ];

        foreach ($sets as $set => $limit) {
            $ids = $conn->zrevrange($prefix . $set, 0, $limit - 1);
            if (empty($ids)) {
                continue;
            }

            // Batch fetch job details
            $keys = array_map(fn ($id) => $prefix . 'job:' . $id, $ids);
            $results = $conn->mget($keys);

            foreach ($results as $raw) {
                if (! $raw) {
                    continue;
                }
                $job = json_decode($raw, true);
                if (! $job) {
                    continue;
                }

                $class = $job['class'] ?? $job['name'] ?? 'Unknown';
                $status = $job['status'] ?? 'unknown';

                // Normalize status
                if ($status === 'retrying') {
                    $status = 'pending';
                } elseif ($status === 'retried') {
                    $status = 'failed';
                }

                if (! isset($counts[$class])) {
                    $counts[$class] = ['pending' => 0, 'reserved' => 0, 'completed' => 0, 'failed' => 0];
                }

                if (isset($counts[$class][$status])) {
                    $counts[$class][$status]++;
                }
            }
        }

        // Also count jobs waiting in queue lists (not yet picked up by Rust)
        $queueNames = $this->getQueueNames();
        foreach ($queueNames as $queue) {
            $queueLen = (int) $conn->llen('queues:' . $queue);
            if ($queueLen === 0) {
                continue;
            }

            // Sample up to 500 entries to count by class
            $sampleSize = min($queueLen, 500);
            $raw = $conn->lrange('queues:' . $queue, 0, $sampleSize - 1);
            foreach ($raw as $payload) {
                $data = json_decode($payload, true);
                if (! $data) {
                    continue;
                }
                $class = $data['displayName'] ?? $data['job'] ?? 'Unknown';
                if (! isset($counts[$class])) {
                    $counts[$class] = ['pending' => 0, 'reserved' => 0, 'completed' => 0, 'failed' => 0];
                }
                $counts[$class]['pending']++;
            }

            // For large queues, we sample 500 entries. If the queue is
            // homogeneous (one job class), scale the count proportionally.
            if ($queueLen > $sampleSize) {
                // Count how many unique classes we found in this sample
                $sampleClasses = [];
                foreach ($raw as $payload) {
                    $data = json_decode($payload, true);
                    if ($data) {
                        $sampleClasses[$data['displayName'] ?? $data['job'] ?? 'Unknown'] = true;
                    }
                }
                // If only one class in the sample, we can safely extrapolate
                if (count($sampleClasses) === 1) {
                    $class = array_key_first($sampleClasses);
                    // We counted $sampleSize, but there are $queueLen total
                    $counts[$class]['pending'] += ($queueLen - $sampleSize);
                }
                // For mixed queues, the sampled counts are a lower bound
            }
        }

        return $counts;
    }

    /**
     * Get queue names from supervisors or config fallback.
     */
    protected function getQueueNames(): array
    {
        $supervisors = app(\Dawn\Contracts\SupervisorRepository::class)->all();
        $queues = [];
        foreach ($supervisors as $supervisor) {
            foreach (($supervisor['queues'] ?? []) as $queue) {
                $queues[$queue] = true;
            }
        }

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

        return empty($queues) ? ['default'] : array_keys($queues);
    }
}
