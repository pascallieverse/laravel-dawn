<?php

namespace Dawn\Console\Commands;

use Dawn\Contracts\MasterSupervisorRepository;
use Dawn\Contracts\SupervisorRepository;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'dawn:status';
    protected $description = 'Show the status of Dawn supervisors';

    public function handle(
        MasterSupervisorRepository $masters,
        SupervisorRepository $supervisors,
    ): int {
        $masterList = $masters->all();

        if (empty($masterList)) {
            $this->error('No Dawn master supervisors are running.');
            return 1;
        }

        foreach ($masterList as $master) {
            $data = is_string($master) ? json_decode($master, true) : $master;
            $name = $data['name'] ?? 'unknown';
            $status = $data['status'] ?? 'unknown';

            $this->info("Master: {$name} [{$status}]");

            $sups = $supervisors->forMaster($name);
            foreach ($sups as $sup) {
                $supData = is_string($sup) ? json_decode($sup, true) : $sup;
                $supName = $supData['name'] ?? 'unknown';
                $supStatus = $supData['status'] ?? 'unknown';
                $processes = $supData['processes'] ?? 0;
                $queues = implode(', ', $supData['queues'] ?? []);

                $this->line("  Supervisor: {$supName} [{$supStatus}] - {$processes} workers - queues: {$queues}");
            }
        }

        return 0;
    }
}
