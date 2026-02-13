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
     */
    public function getQueue($queue): string
    {
        return 'queues:' . ($queue ?: $this->default);
    }
}
