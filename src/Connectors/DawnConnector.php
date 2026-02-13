<?php

namespace Dawn\Connectors;

use Dawn\DawnQueue;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Contracts\Redis\Factory as Redis;

class DawnConnector extends RedisConnector
{
    /**
     * Establish a queue connection.
     */
    public function connect(array $config): DawnQueue
    {
        // Always use the 'dawn' Redis connection (prefix-free) so that
        // PHP writes to the same keys the Rust binary reads from.
        return new DawnQueue(
            $this->redis,
            $config['queue'] ?? 'default',
            'dawn',
            $config['retry_after'] ?? 60,
            $config['block_for'] ?? null,
        );
    }
}
