<?php

namespace Dawn\Console\Commands;

use Dawn\Jobs\DawnJob;
use Illuminate\Console\Command;
use Illuminate\Log\LogManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

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
     *
     * Uses Laravel's CallQueuedHandler pipeline (via Job::fire()) instead
     * of calling handle() directly. This ensures full compatibility with
     * InteractsWithQueue, job middleware, event broadcasting, chains, and batches.
     */
    protected function processJob(string $rawPayload): array
    {
        $startMemory = memory_get_usage();
        $startTime = hrtime(true);

        try {
            $payload = json_decode($rawPayload, true);

            if (! $payload || ! isset($payload['data']['command'])) {
                return [
                    'status' => 'failed',
                    'exception' => 'Invalid job payload: missing data.command',
                    'trace' => '',
                    'memory' => memory_get_usage() - $startMemory,
                    'runtime_ms' => (int) ((hrtime(true) - $startTime) / 1_000_000),
                ];
            }

            // Install a temporary log handler to capture logs during job execution
            $capturedLogs = [];
            $handler = $this->installLogCapture($capturedLogs);

            // Create a proper Job instance and fire it through Laravel's pipeline.
            // This calls CallQueuedHandler::call() which sets $this->job on the
            // command, runs middleware, fires events, and handles chains/batches.
            $job = new DawnJob(
                app(),
                $rawPayload,
                'dawn',
                $payload['queue'] ?? 'default',
            );

            $job->fire();

            $this->removeLogCapture($handler);

            // Check if the job was released back to the queue
            if ($job->isReleased()) {
                return [
                    'status' => 'released',
                    'delay' => $job->releaseDelay,
                    'memory' => memory_get_usage() - $startMemory,
                    'runtime_ms' => (int) ((hrtime(true) - $startTime) / 1_000_000),
                    'logs' => array_slice($capturedLogs, 0, 50),
                ];
            }

            return [
                'status' => 'complete',
                'memory' => memory_get_usage() - $startMemory,
                'runtime_ms' => (int) ((hrtime(true) - $startTime) / 1_000_000),
                'logs' => array_slice($capturedLogs, 0, 50),
            ];
        } catch (\Throwable $e) {
            $runtimeMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            // Log the failure via Laravel's logger (captured by the handler)
            $jobName = $payload['displayName'] ?? $payload['data']['commandName'] ?? 'Unknown';
            \Illuminate\Support\Facades\Log::error("[Dawn] Job failed: {$jobName}", [
                'job' => $jobName,
                'exception' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($handler)) {
                $this->removeLogCapture($handler);
            }

            // Truncate exception/trace to prevent oversized JSON responses
            // that could cause encoding issues or slow down Redis storage
            $exceptionMsg = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 2000) . ' in ' . $e->getFile() . ':' . $e->getLine();
            $trace = mb_substr($e->getTraceAsString(), 0, 8000);

            return [
                'status' => 'failed',
                'exception' => $exceptionMsg,
                'trace' => $trace,
                'memory' => memory_get_usage() - $startMemory,
                'runtime_ms' => $runtimeMs,
                'logs' => array_slice($capturedLogs ?? [], 0, 50),
            ];
        }
    }

    /**
     * Install a temporary Monolog handler that captures log entries in memory.
     *
     * @param  array<int, array{timestamp: string, text: string}>  $capturedLogs
     */
    protected function installLogCapture(array &$capturedLogs): AbstractProcessingHandler
    {
        $handler = new class($capturedLogs) extends AbstractProcessingHandler
        {
            /** @var array<int, array{timestamp: string, text: string}> */
            private array $logs;

            public function __construct(array &$logs)
            {
                parent::__construct();
                $this->logs = &$logs;
            }

            protected function write(LogRecord $record): void
            {
                $this->logs[] = [
                    'timestamp' => $record->datetime->format('Y-m-d H:i:s'),
                    'text' => sprintf(
                        '[%s] %s.%s: %s %s',
                        $record->datetime->format('Y-m-d H:i:s'),
                        $record->channel,
                        $record->level->name,
                        $record->message,
                        $record->context ? json_encode($record->context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : '',
                    ),
                ];
            }
        };

        $logger = app('log');

        if ($logger instanceof LogManager) {
            $monolog = $logger->driver()->getLogger();
            $monolog->pushHandler($handler);
        }

        return $handler;
    }

    /**
     * Remove the temporary log capture handler.
     */
    protected function removeLogCapture(AbstractProcessingHandler $handler): void
    {
        $logger = app('log');

        if ($logger instanceof LogManager) {
            $monolog = $logger->driver()->getLogger();
            $handlers = $monolog->getHandlers();

            $monolog->setHandlers(
                array_values(array_filter(
                    iterator_to_array($handlers),
                    fn ($h) => $h !== $handler
                ))
            );
        }
    }
}
