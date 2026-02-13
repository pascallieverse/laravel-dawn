<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class ClearMetricsCommand extends Command
{
    protected $signature = 'dawn:clear-metrics';
    protected $description = 'Clear all Dawn metrics data';

    public function handle(RedisFactory $redis): int
    {
        $prefix = config('dawn.prefix', 'dawn:');
        $connection = $redis->connection();

        // Delete all metrics keys
        $patterns = [
            $prefix . 'metrics:*',
            $prefix . 'snapshot:*',
            $prefix . 'throughput:*',
        ];

        foreach ($patterns as $pattern) {
            $keys = $connection->keys($pattern);
            if (! empty($keys)) {
                $connection->del($keys);
            }
        }

        $this->info('Dawn metrics cleared.');

        return 0;
    }
}
