<?php

namespace Dawn\Repositories;

use Dawn\Contracts\TagRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class DawnTagRepository implements TagRepository
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

    public function monitoring(): array
    {
        return $this->connection()->smembers($this->prefix . 'monitoring') ?: [];
    }

    public function monitor(string $tag): void
    {
        $this->connection()->sadd($this->prefix . 'monitoring', [$tag]);
    }

    public function stopMonitoring(string $tag): void
    {
        $this->connection()->srem($this->prefix . 'monitoring', $tag);
        $this->connection()->del([$this->prefix . 'monitor:tag:' . $tag]);
    }

    public function taggedJobs(string $tag, int $offset = 0, int $limit = 50): array
    {
        $ids = $this->connection()->zrevrange(
            $this->prefix . 'monitor:tag:' . $tag,
            $offset,
            $offset + $limit - 1,
        );

        $jobs = [];
        $jobRepo = app(\Dawn\Contracts\JobRepository::class);

        foreach ($ids as $id) {
            $job = $jobRepo->find($id);
            if ($job) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    public function countTaggedJobs(string $tag): int
    {
        return (int) $this->connection()->zcard($this->prefix . 'monitor:tag:' . $tag);
    }
}
