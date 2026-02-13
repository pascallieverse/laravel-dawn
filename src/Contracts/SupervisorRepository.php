<?php

namespace Dawn\Contracts;

interface SupervisorRepository
{
    /**
     * Get all supervisors for a given master.
     */
    public function forMaster(string $master): array;

    /**
     * Get a single supervisor by name.
     */
    public function find(string $name): ?array;

    /**
     * Get all running supervisors.
     */
    public function all(): array;
}
