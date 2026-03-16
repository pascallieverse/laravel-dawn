<?php

namespace Dawn\Console\Commands;

use Dawn\DawnServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class PurgeCommand extends Command
{
    protected $signature = 'dawn:purge {queue? : The queue to purge}';
    protected $description = 'Purge all jobs from a queue';

    public function handle(RedisFactory $redis): int
    {
        $queue = $this->argument('queue') ?? 'default';
        $prefix = DawnServiceProvider::resolveQueuePrefix();
        $connection = $redis->connection();

        $deleted = 0;
        $keys = [
            "queues:{$prefix}{$queue}",
            "queues:{$prefix}{$queue}:delayed",
            "queues:{$prefix}{$queue}:reserved",
        ];

        foreach ($keys as $key) {
            $size = $connection->llen($key) ?: $connection->zcard($key) ?: 0;
            $deleted += $size;
            $connection->del([$key]);
        }

        $this->info("Purged {$deleted} jobs from the [{$queue}] queue.");

        return 0;
    }
}
