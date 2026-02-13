<?php

namespace Dawn;

use Illuminate\Support\Str;
use ReflectionClass;

class JobPayload
{
    /**
     * The raw payload array.
     */
    public array $decoded;

    /**
     * Create a new job payload instance.
     */
    public function __construct(array $decoded)
    {
        $this->decoded = $decoded;
    }

    /**
     * Prepare a job payload for Dawn.
     * Adds Dawn-specific metadata: id, tags, type, pushedAt, isolated, silenced.
     */
    public static function prepare(array $payload, ?string $queue = null): array
    {
        $instance = new static($payload);

        return $instance
            ->setDawnId()
            ->setTags()
            ->setType()
            ->setPushedAt()
            ->setIsolatedFlag()
            ->setSilencedFlag()
            ->decoded;
    }

    /**
     * Set the Dawn job ID.
     */
    protected function setDawnId(): static
    {
        $this->decoded['id'] = $this->decoded['id'] ?? 'dawn-' . Str::uuid()->toString();

        return $this;
    }

    /**
     * Detect and set tags for the job.
     * Tags are used for monitoring specific job/model combinations.
     */
    protected function setTags(): static
    {
        $this->decoded['tags'] = $this->decoded['tags'] ?? $this->extractTags();

        return $this;
    }

    /**
     * Set the job type.
     */
    protected function setType(): static
    {
        $this->decoded['type'] = $this->decoded['type'] ?? 'job';

        return $this;
    }

    /**
     * Set the pushed at timestamp.
     */
    protected function setPushedAt(): static
    {
        $this->decoded['pushedAt'] = $this->decoded['pushedAt'] ?? microtime(true);

        return $this;
    }

    /**
     * Set the isolated flag based on the Isolated interface or config.
     */
    protected function setIsolatedFlag(): static
    {
        $commandName = $this->decoded['data']['commandName'] ?? null;

        if ($commandName && class_exists($commandName)) {
            $this->decoded['isolated'] = is_subclass_of($commandName, \Dawn\Contracts\Isolated::class)
                || in_array($commandName, config('dawn.isolated', []));
        } else {
            $this->decoded['isolated'] = $this->decoded['isolated'] ?? false;
        }

        return $this;
    }

    /**
     * Set the silenced flag.
     */
    protected function setSilencedFlag(): static
    {
        $commandName = $this->decoded['data']['commandName'] ?? null;
        $silenced = config('dawn.silenced', []);

        $this->decoded['silenced'] = $commandName && in_array($commandName, $silenced);

        return $this;
    }

    /**
     * Extract tags from the job payload.
     * Detects Eloquent model bindings in the serialized command.
     */
    protected function extractTags(): array
    {
        $tags = [];
        $commandName = $this->decoded['data']['commandName'] ?? null;

        if (! $commandName) {
            return $tags;
        }

        // Add the job class itself as a tag
        // We don't add the class as a tag by default (Horizon behavior)

        // Try to extract model tags from the serialized command
        try {
            if (isset($this->decoded['data']['command'])) {
                $command = unserialize($this->decoded['data']['command']);

                if (method_exists($command, 'tags')) {
                    return $command->tags();
                }

                // Auto-detect Eloquent models
                $tags = $this->extractModelTags($command);
            }
        } catch (\Throwable) {
            // Cannot unserialize here safely — return empty tags
        }

        return $tags;
    }

    /**
     * Extract Eloquent model tags from a job command.
     */
    protected function extractModelTags(object $command): array
    {
        $tags = [];

        $reflection = new ReflectionClass($command);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            $value = $property->getValue($command);

            if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                $tags[] = get_class($value) . ':' . $value->getKey();
            } elseif ($value instanceof \Illuminate\Database\Eloquent\Collection) {
                foreach ($value as $model) {
                    $tags[] = get_class($model) . ':' . $model->getKey();
                }
            }
        }

        return array_unique($tags);
    }
}
