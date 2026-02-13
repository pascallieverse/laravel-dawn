<?php

namespace Dawn\Console\Commands;

use Dawn\Contracts\CommandQueue;
use Dawn\Contracts\MasterSupervisorRepository;
use Illuminate\Console\Command;

class TerminateCommand extends Command
{
    protected $signature = 'dawn:terminate {--wait : Wait for all workers to finish current jobs}';
    protected $description = 'Terminate all Dawn processes';

    public function handle(CommandQueue $commands, MasterSupervisorRepository $masters): int
    {
        foreach ($masters->names() as $master) {
            $commands->push($master, 'terminate', [
                'wait' => $this->option('wait'),
            ]);
        }

        $this->info('Terminate signal sent to all Dawn supervisors.');

        return 0;
    }
}
