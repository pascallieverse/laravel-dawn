<?php

namespace Dawn\Console\Commands;

use Dawn\Contracts\CommandQueue;
use Dawn\Contracts\MasterSupervisorRepository;
use Illuminate\Console\Command;

class ContinueCommand extends Command
{
    protected $signature = 'dawn:continue';
    protected $description = 'Resume all Dawn queue processing';

    public function handle(CommandQueue $commands, MasterSupervisorRepository $masters): int
    {
        foreach ($masters->names() as $master) {
            $commands->push($master, 'continue');
        }

        $this->info('Continue signal sent to all Dawn supervisors.');

        return 0;
    }
}
