<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;

/**
 * Warm worker loop — boots Laravel once, then loops reading job payloads from stdin
 * and writing results to stdout. The Rust supervisor manages this process.
 */
class DawnLoopCommand extends Command
{
    protected $signature = 'dawn:loop';
    protected $description = 'Start a warm worker loop (managed by Dawn Rust supervisor)';
    protected $hidden = true;

    public function handle(): int
    {
        // Disable output buffering to ensure immediate flushing
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $stdin = fopen('php://stdin', 'r');

        if (! $stdin) {
            fwrite(STDERR, "Failed to open stdin\n");
            return 1;
        }

        // Signal to Rust that we're ready to receive jobs
        fwrite(STDOUT, json_encode(['ready' => true]) . "\n");
        fflush(STDOUT);

        // Read job payloads line by line from stdin
        while (($line = fgets($stdin)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $result = $this->processJob($line);

            // Write result as JSON to stdout
            fwrite(STDOUT, json_encode($result) . "\n");
            fflush(STDOUT);
        }

        fclose($stdin);

        return 0;
    }

    /**
     * Process a single job from the payload JSON.
     */
    protected function processJob(string $rawPayload): array
    {
        $startMemory = memory_get_usage();

        try {
            $payload = json_decode($rawPayload, true);

            if (! $payload || ! isset($payload['data']['command'])) {
                return [
                    'status' => 'failed',
                    'exception' => 'Invalid job payload: missing data.command',
                    'trace' => '',
                    'memory' => memory_get_usage() - $startMemory,
                ];
            }

            // Unserialize the job command
            $command = unserialize($payload['data']['command']);

            // Resolve and execute the handle method
            app()->call([$command, 'handle']);

            return [
                'status' => 'complete',
                'memory' => memory_get_usage() - $startMemory,
            ];
        } catch (\Throwable $e) {
            // Log the failure via Laravel's logger
            $jobName = $payload['displayName'] ?? $payload['data']['commandName'] ?? 'Unknown';
            \Illuminate\Support\Facades\Log::error("[Dawn] Job failed: {$jobName}", [
                'job' => $jobName,
                'exception' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'failed',
                'exception' => get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'memory' => memory_get_usage() - $startMemory,
            ];
        }
    }
}
