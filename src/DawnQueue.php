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
}
