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

            // Encode result as JSON, handling non-UTF-8 bytes gracefully
            $json = json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE);

            if ($json === false) {
                // Fallback: encode a minimal failure result if json_encode still fails
                $json = json_encode([
                    'status' => 'failed',
                    'exception' => 'Job failed (result could not be JSON-encoded: ' . json_last_error_msg() . ')',
                    'trace' => '',
                    'memory' => 0,
                ]);
            }

            fwrite(STDOUT, $json . "\n");
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

            // Truncate exception/trace to prevent oversized JSON responses
            // that could cause encoding issues or slow down Redis storage
            $exceptionMsg = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 2000) . ' in ' . $e->getFile() . ':' . $e->getLine();
            $trace = mb_substr($e->getTraceAsString(), 0, 8000);

            return [
                'status' => 'failed',
                'exception' => $exceptionMsg,
                'trace' => $trace,
                'memory' => memory_get_usage() - $startMemory,
            ];
        }
    }
}
