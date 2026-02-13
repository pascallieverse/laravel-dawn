<?php

namespace Dawn\Contracts;

interface TagRepository
{
    /**
     * Get all monitored tags.
     */
    public function monitoring(): array;

    /**
     * Start monitoring a tag.
     */
    public function monitor(string $tag): void;

    /**
     * Stop monitoring a tag.
     */
    public function stopMonitoring(string $tag): void;

    /**
     * Get jobs for a monitored tag.
     */
    public function taggedJobs(string $tag, int $offset = 0, int $limit = 50): array;

    /**
     * Count jobs for a tag.
     */
    public function countTaggedJobs(string $tag): int;
}
