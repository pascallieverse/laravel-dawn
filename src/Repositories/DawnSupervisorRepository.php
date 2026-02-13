<?php

namespace Dawn\Repositories;

use Dawn\Contracts\SupervisorRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class DawnSupervisorRepository implements SupervisorRepository
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

    public function forMaster(string $master): array
    {
        $names = $this->connection()->smembers($this->prefix . 'supervisors:' . $master);
        $supervisors = [];

        foreach ($names as $name) {
            $data = $this->find($name);
            if ($data) {
                $supervisors[] = $data;
            }
        }

        return $supervisors;
    }

    public function find(string $name): ?array
    {
        $data = $this->connection()->get($this->prefix . 'supervisor:' . $name);

        return $data ? json_decode($data, true) : null;
    }

    public function all(): array
    {
        $masters = $this->connection()->smembers($this->prefix . 'masters');
        $supervisors = [];

        foreach ($masters as $master) {
            $supervisors = array_merge($supervisors, $this->forMaster($master));
        }

        return $supervisors;
    }
}
