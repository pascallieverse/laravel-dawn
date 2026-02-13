<?php

namespace Dawn\Console\Commands;

use Dawn\Contracts\CommandQueue;
use Dawn\Contracts\MasterSupervisorRepository;
use Illuminate\Console\Command;

class PauseCommand extends Command
{
    protected $signature = 'dawn:pause';
    protected $description = 'Pause all Dawn queue processing';

    public function handle(CommandQueue $commands, MasterSupervisorRepository $masters): int
    {
        foreach ($masters->names() as $master) {
            $commands->push($master, 'pause');
        }

        $this->info('Pause signal sent to all Dawn supervisors.');

        return 0;
    }
}
