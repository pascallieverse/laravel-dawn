<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Log\LogManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

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

        $startTime = hrtime(true);
        $capturedLogs = [];
        $handler = $this->installLogCapture($capturedLogs);

        try {
            $command = unserialize($payload['data']['command']);
            app()->call([$command, 'handle']);

            $this->removeLogCapture($handler);

            $result = [
                'status' => 'complete',
                'runtime_ms' => (int) ((hrtime(true) - $startTime) / 1_000_000),
                'logs' => array_slice($capturedLogs, 0, 50),
            ];
            fwrite(STDOUT, json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            fflush(STDOUT);

            return 0;
        } catch (\Throwable $e) {
            $this->removeLogCapture($handler);

            $result = [
                'status' => 'failed',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'runtime_ms' => (int) ((hrtime(true) - $startTime) / 1_000_000),
                'logs' => array_slice($capturedLogs, 0, 50),
            ];
            fwrite(STDOUT, json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            fflush(STDOUT);

            return 1;
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
