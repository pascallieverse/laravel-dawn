<?php

namespace Dawn\Console\Commands;

use Dawn\Contracts\JobRepository;
use Illuminate\Console\Command;

class RecoverOrphansCommand extends Command
{
    protected $signature = 'dawn:recover-orphans
        {--grace=300 : Grace period in seconds beyond the timeout before considering a job orphaned}';

    protected $description = 'Recover orphaned jobs stuck in pending state (worker killed before reporting result)';

    public function handle(JobRepository $repository): int
    {
        $grace = (int) $this->option('grace');

        $this->components->info("Scanning for orphaned jobs (grace period: {$grace}s)...");

        $stats = $repository->recoverOrphanedJobs($grace);

        if ($stats['recovered'] === 0) {
            $this->components->info('No orphaned jobs found.');

            return 0;
        }

        $this->components->info("Recovered {$stats['recovered']} orphaned job(s): {$stats['retried']} retried, {$stats['failed']} marked as failed.");

        return 0;
    }
}
