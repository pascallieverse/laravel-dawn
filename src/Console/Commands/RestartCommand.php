<?php

namespace Dawn\Console\Commands;

use Dawn\Contracts\CommandQueue;
use Dawn\Contracts\MasterSupervisorRepository;
use Illuminate\Console\Command;

class RestartCommand extends Command
{
    protected $signature = 'dawn:restart';

    protected $description = 'Restart all Dawn worker processes (graceful — finishes current jobs first)';

    public function handle(CommandQueue $commands, MasterSupervisorRepository $masters): int
    {
        foreach ($masters->names() as $master) {
            $commands->push($master, 'restart-workers');
        }

        $this->info('Restart signal sent to all Dawn supervisors. Workers will restart after finishing current jobs.');

        return 0;
    }
}
