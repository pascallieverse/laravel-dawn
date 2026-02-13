<?php

namespace Dawn\Contracts;

interface MasterSupervisorRepository
{
    /**
     * Get all master supervisors.
     */
    public function all(): array;

    /**
     * Get a single master supervisor by name.
     */
    public function find(string $name): ?array;

    /**
     * Get the names of all master supervisors.
     */
    public function names(): array;
}
