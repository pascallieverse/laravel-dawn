<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;

/**
 * Isolated job executor — reads a single job payload from stdin, executes it, and exits.
 * Used for jobs implementing Dawn\Contracts\Isolated.
 */
class DawnRunJobCommand extends Command
{
    protected $signature = 'dawn:run-job';
    protected $description = 'Execute a single job in isolation (managed by Dawn Rust supervisor)';
    protected $hidden = true;

    public function handle(): int
    {
        $rawPayload = file_get_contents('php://stdin');

        if (empty($rawPayload)) {
            fwrite(STDERR, "No payload received on stdin\n");
            return 1;
        }

        $payload = json_decode($rawPayload, true);

        if (! $payload || ! isset($payload['data']['command'])) {
            $result = [
                'status' => 'failed',
                'exception' => 'Invalid job payload: missing data.command',
                'trace' => '',
            ];
            fwrite(STDOUT, json_encode($result) . "\n");
            fflush(STDOUT);
            return 1;
        }

        try {
            $command = unserialize($payload['data']['command']);
            app()->call([$command, 'handle']);

            $result = [
                'status' => 'complete',
            ];
            fwrite(STDOUT, json_encode($result) . "\n");
            fflush(STDOUT);

            return 0;
        } catch (\Throwable $e) {
            $result = [
                'status' => 'failed',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
            fwrite(STDOUT, json_encode($result) . "\n");
            fflush(STDOUT);

            return 1;
        }
    }
}
