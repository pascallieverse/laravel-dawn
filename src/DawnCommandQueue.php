<?php

namespace Dawn;

use Dawn\Contracts\CommandQueue;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class DawnCommandQueue implements CommandQueue
{
    protected RedisFactory $redis;
    protected string $prefix;

    public function __construct(RedisFactory $redis, string $prefix = 'dawn:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    /**
     * Push a command onto the command queue for the Rust binary to consume.
     */
    public function push(string $target, string $command, array $options = []): void
    {
        $payload = json_encode([
            'command' => $command,
            'options' => (object) $options,
        ]);

        $this->redis->connection('dawn')
            ->rpush($this->prefix . 'commands:' . $target, [$payload]);
    }
}
