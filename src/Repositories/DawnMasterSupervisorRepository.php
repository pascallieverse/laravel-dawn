<?php

namespace Dawn\Repositories;

use Dawn\Contracts\MasterSupervisorRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class DawnMasterSupervisorRepository implements MasterSupervisorRepository
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

    public function all(): array
    {
        $names = $this->names();
        $masters = [];

        foreach ($names as $name) {
            $data = $this->find($name);
            if ($data) {
                $masters[] = $data;
            }
        }

        return $masters;
    }

    public function find(string $name): ?array
    {
        $data = $this->connection()->get($this->prefix . 'master:' . $name);

        return $data ? json_decode($data, true) : null;
    }

    public function names(): array
    {
        $names = $this->connection()->smembers($this->prefix . 'masters');

        // Filter out stale entries
        return array_values(array_filter($names, function ($name) {
            return $this->connection()->exists($this->prefix . 'master:' . $name);
        }));
    }
}
