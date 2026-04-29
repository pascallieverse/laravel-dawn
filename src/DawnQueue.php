<?php

namespace Dawn;

use Illuminate\Queue\RedisQueue;
use Illuminate\Contracts\Queue\Queue as QueueContract;

class DawnQueue extends RedisQueue implements QueueContract
{
    /**
     * Create a payload array from the given job and data.
     * Overrides RedisQueue to inject Dawn metadata.
     */
    protected function createPayloadArray($job, $queue, $data = ''): array
    {
        $payload = parent::createPayloadArray($job, $queue, $data);

        return JobPayload::prepare($payload, $queue);
    }

    /**
     * Get the queue or return the default.
     * Includes the app-specific queue prefix to isolate queues per application
     * when multiple apps share the same Redis instance.
     */
    public function getQueue($queue): string
    {
        $prefix = DawnServiceProvider::resolveQueuePrefix();

        return 'queues:' . $prefix . ($queue ?: $this->default);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * Skips the `:notify` rpush that Laravel's RedisQueue performs. The Dawn
     * Rust worker reads jobs via LPOP on the queue list directly and never
     * consumes `:notify`, so writing to it produces orphan entries that
     * accumulate without bound and have caused multi-GB Redis bloat.
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->getConnection()->rpush($this->getQueue($queue), $payload);

        return json_decode($payload, true)['id'] ?? null;
    }

    /**
     * Migrate the delayed jobs that are ready to the regular queue.
     *
     * Uses a Lua script that mirrors Laravel's migrateExpiredJobs but
     * omits the per-job rpush onto `:notify`, for the same reason as
     * pushRaw above.
     */
    public function migrateExpiredJobs($from, $to)
    {
        return $this->getConnection()->eval(
            self::migrateExpiredJobsScript(), 2, $from, $to,
            $this->currentTime(), $this->migrationBatchSize
        );
    }

    protected static function migrateExpiredJobsScript(): string
    {
        return <<<'LUA'
local val = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1], 'limit', 0, ARGV[2])

if(next(val) ~= nil) then
    redis.call('zremrangebyrank', KEYS[1], 0, #val - 1)

    for i = 1, #val, 100 do
        redis.call('rpush', KEYS[2], unpack(val, i, math.min(i+99, #val)))
    end
end

return val
LUA;
    }
}
