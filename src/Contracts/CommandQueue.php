<?php

namespace Dawn\Contracts;

interface CommandQueue
{
    /**
     * Push a command onto the command queue.
     */
    public function push(string $target, string $command, array $options = []): void;
}
