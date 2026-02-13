<?php

namespace Dawn\Repositories;

use Dawn\Contracts\MetricsRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class DawnMetricsRepository implements MetricsRepository
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

    public function getJobMetrics(string $class): array
    {
        $data = $this->connection()->hgetall($this->prefix . 'metrics:job:' . $class);

        if (empty($data)) {
            return [];
        }

        $count = (int) ($data['count'] ?? 0);
        $totalRuntime = (int) ($data['total_runtime'] ?? 0);

        return [
            'name' => $class,
            'count' => $count,
            'avg_runtime' => $count > 0 ? round($totalRuntime / $count) : 0,
            'total_runtime' => $totalRuntime,
        ];
    }

    public function getQueueMetrics(string $queue): array
    {
        $data = $this->connection()->hgetall($this->prefix . 'metrics:queue:' . $queue);

        if (empty($data)) {
            return [];
        }

        $count = (int) ($data['count'] ?? 0);
        $totalRuntime = (int) ($data['total_runtime'] ?? 0);

        return [
            'name' => $queue,
            'count' => $count,
            'avg_runtime' => $count > 0 ? round($totalRuntime / $count) : 0,
            'total_runtime' => $totalRuntime,
        ];
    }

    public function measuredJobs(): array
    {
        $keys = $this->connection()->keys($this->prefix . 'metrics:job:*');
        $prefixLen = strlen($this->prefix . 'metrics:job:');

        return array_map(fn ($key) => substr($key, $prefixLen), $keys);
    }

    public function measuredQueues(): array
    {
        $keys = $this->connection()->keys($this->prefix . 'metrics:queue:*');
        $prefixLen = strlen($this->prefix . 'metrics:queue:');

        return array_map(fn ($key) => substr($key, $prefixLen), $keys);
    }

    public function snapshotsForJob(string $class): array
    {
        return $this->getSnapshots('snapshot:job:' . $class);
    }

    public function snapshotsForQueue(string $queue): array
    {
        return $this->getSnapshots('snapshot:queue:' . $queue);
    }

    protected function getSnapshots(string $key): array
    {
        $raw = $this->connection()->zrange($this->prefix . $key, 0, -1);

        return array_map(fn ($item) => json_decode($item, true), $raw);
    }
}
