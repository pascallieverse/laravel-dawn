<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;

/**
 * Export Dawn configuration as JSON for the Rust binary to consume.
 */
class ExportConfigCommand extends Command
{
    protected $signature = 'dawn:export-config {--environment= : The environment to export}';
    protected $description = 'Export Dawn configuration as JSON for the Rust supervisor';

    public function handle(): int
    {
        $environment = $this->option('environment') ?: app()->environment();
        $config = config('dawn');

        // Merge defaults with environment-specific overrides
        $supervisors = $this->resolveSupervisors($config, $environment);

        $export = [
            'app_name' => config('app.name', 'Laravel'),
            'prefix' => rtrim($config['prefix'] ?? 'dawn:', ':') . ':',
            'redis_url' => $this->resolveRedisUrl($config),
            'php_binary' => PHP_BINARY,
            'artisan_path' => base_path('artisan'),
            'environment' => $environment,
            'supervisors' => $supervisors,
            'metrics' => [
                'snapshot_interval' => $config['metrics']['trim_snapshots']['job'] ?? 5,
                'trim_interval' => 1,
            ],
            'trim' => [
                'recent' => $config['trim']['recent'] ?? 60,
                'completed' => $config['trim']['completed'] ?? 60,
                'failed' => $config['trim']['recent_failed'] ?? 10080,
                'monitored' => $config['trim']['monitored'] ?? 10080,
            ],
            'waits' => $config['waits'] ?? [],
            'memory_limit' => $config['memory_limit'] ?? 512,
            'isolated_jobs' => $config['isolated'] ?? [],
        ];

        $this->line(json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    /**
     * Resolve supervisor configurations by merging defaults with environment overrides.
     */
    protected function resolveSupervisors(array $config, string $environment): array
    {
        $defaults = $config['defaults'] ?? [];
        $environments = $config['environments'] ?? [];
        $envOverrides = $environments[$environment] ?? [];

        $supervisors = [];

        foreach ($defaults as $name => $defaultConfig) {
            $merged = array_merge($defaultConfig, $envOverrides[$name] ?? []);

            $supervisors[$name] = [
                'connection' => $merged['connection'] ?? 'dawn',
                'queues' => (array) ($merged['queue'] ?? ['default']),
                'balance' => $merged['balance'] ?? 'auto',
                'processes' => $merged['minProcesses'] ?? $merged['maxProcesses'] ?? 1,
                'min_processes' => $merged['minProcesses'] ?? 1,
                'max_processes' => $merged['maxProcesses'] ?? 10,
                'timeout' => $merged['timeout'] ?? 60,
                'max_tries' => $merged['tries'] ?? 3,
                'memory' => $merged['memory'] ?? 128,
                'sleep' => $merged['sleep'] ?? 1.0,
                'balance_cooldown' => $merged['balanceCooldown'] ?? 3,
                'balance_max_shift' => $merged['balanceMaxShift'] ?? 1,
                'tries' => $merged['tries'] ?? 0,
                'nice' => $merged['nice'] ?? 0,
            ];
        }

        return $supervisors;
    }

    /**
     * Resolve the Redis URL from Laravel's configuration.
     */
    protected function resolveRedisUrl(array $config): string
    {
        $connection = $config['use'] ?? 'default';
        $redisConfig = config("database.redis.{$connection}", []);

        $scheme = ($redisConfig['scheme'] ?? 'tcp') === 'tls' ? 'rediss' : 'redis';
        $host = $redisConfig['host'] ?? '127.0.0.1';
        $port = $redisConfig['port'] ?? 6379;
        $password = $redisConfig['password'] ?? null;
        $database = $redisConfig['database'] ?? 0;

        if ($redisConfig['url'] ?? null) {
            return $redisConfig['url'];
        }

        $auth = $password ? ":{$password}@" : '';

        return "{$scheme}://{$auth}{$host}:{$port}/{$database}";
    }
}
