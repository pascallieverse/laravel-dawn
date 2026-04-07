<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PruneBatchesCommand extends Command
{
    protected $signature = 'dawn:prune-batches
        {--hours=24 : Prune batches stuck for longer than this many hours}
        {--dry-run : Show what would be pruned without making changes}';

    protected $description = 'Prune stuck job batches that have pending jobs but no actual jobs in the queue';

    public function handle(): int
    {
        if (! Schema::hasTable('job_batches')) {
            $this->info('Skipping: job_batches table does not exist (job batching not enabled).');

            return 0;
        }

        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours)->timestamp;

        $stuckBatches = DB::table('job_batches')
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->where('pending_jobs', '>', 0)
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($stuckBatches->isEmpty()) {
            $this->info('No stuck batches found.');

            return 0;
        }

        $this->info("Found {$stuckBatches->count()} stuck batch(es):");

        foreach ($stuckBatches as $batch) {
            $age = now()->diffForHumans(
                \Carbon\Carbon::createFromTimestamp($batch->created_at),
                true,
            );

            $this->line("  [{$batch->id}] {$batch->name} — {$batch->pending_jobs}/{$batch->total_jobs} pending, created {$age} ago");

            if (! $dryRun) {
                $hasCompleted = ($batch->total_jobs - $batch->pending_jobs - $batch->failed_jobs) > 0;

                DB::table('job_batches')
                    ->where('id', $batch->id)
                    ->update([
                        'pending_jobs' => 0,
                        'finished_at' => now()->timestamp,
                    ]);

                Log::info('Dawn: Pruned stuck batch', [
                    'batch_id' => $batch->id,
                    'name' => $batch->name,
                    'pending_jobs' => $batch->pending_jobs,
                    'total_jobs' => $batch->total_jobs,
                    'had_completed_jobs' => $hasCompleted,
                ]);
            }
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes made. Remove --dry-run to prune.');
        } else {
            $this->info("Pruned {$stuckBatches->count()} stuck batch(es).");
        }

        return 0;
    }
}
