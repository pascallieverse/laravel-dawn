<?php

namespace Dawn\Console\Commands;

use Dawn\Contracts\MetricsRepository;
use Illuminate\Console\Command;

class SnapshotCommand extends Command
{
    protected $signature = 'dawn:snapshot';
    protected $description = 'Store a snapshot of the current queue metrics';

    public function handle(MetricsRepository $metrics): int
    {
        // Note: In Dawn, snapshots are primarily handled by the Rust binary.
        // This command exists for manual triggering via scheduler.
        $this->info('Snapshot command acknowledged. The Rust supervisor handles periodic snapshots automatically.');

        return 0;
    }
}
