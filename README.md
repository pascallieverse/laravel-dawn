# Dawn

A Rust-powered queue manager and dashboard for Laravel. Dawn replaces PHP as the queue worker — a compiled Rust binary pops jobs from Redis directly, delegates PHP execution via warm worker pools or isolated processes, and writes all metrics to Redis. A Livewire dashboard gives you real-time visibility into your queues.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Redis
- `ext-pcntl`

## Quick Start

```bash
composer require pascallieverse/laravel-dawn
php artisan dawn:install
./vendor/bin/dawn
```

That's it. The install command handles everything:

- Publishes `config/dawn.php` and a service provider
- Sets `QUEUE_CONNECTION=dawn` in your `.env`
- Sets a unique `DAWN_PREFIX` based on your app name (safe for multi-project servers)
- Registers the queue connection automatically (no need to edit `config/queue.php`)

The Rust binary reads your Laravel config directly at startup (`php artisan dawn:export-config`) — no intermediate config files to maintain.

## Dashboard

Visit `/dawn` in your browser. In non-local environments, access is controlled by the `viewDawn` gate in `app/Providers/DawnServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewDawn', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}
```

### Pages

- **Dashboard** — Live stats and workload overview
- **Monitoring** — Tag-based job monitoring
- **Metrics** — Job and queue throughput/runtime
- **Performance** — Historical charts
- **Jobs** — Pending, processing, completed, failed, and silenced
- **Batches** — Job batch inspection

## Configuration

After install, the config lives at `config/dawn.php`. The defaults work out of the box — you only need to change things when tuning for production. Changes take effect the next time you restart the Dawn binary.

### Queues

By default Dawn processes the `default` queue with up to 10 workers. To process additional queues:

```php
'defaults' => [
    'supervisor-1' => [
        'queue' => ['default', 'emails', 'notifications'],
        'maxProcesses' => 10,
    ],
],
```

Or split into separate supervisors:

```php
'defaults' => [
    'default' => [
        'queue' => ['default'],
        'maxProcesses' => 5,
    ],
    'emails' => [
        'queue' => ['emails'],
        'maxProcesses' => 3,
    ],
],
```

All other options have sensible defaults and are documented in the config file.

### Environment Overrides

Override supervisor settings per environment:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'maxProcesses' => 20,
        ],
    ],
    'local' => [
        'supervisor-1' => [
            'maxProcesses' => 3,
        ],
    ],
],
```

### Isolated Jobs

Jobs that should run in their own PHP process instead of the warm pool:

```php
'isolated' => [
    App\Jobs\HeavyJob::class,
],
```

Or implement the `Dawn\Contracts\Isolated` interface on the job class.

### Silenced Jobs

Jobs excluded from monitoring and metrics:

```php
'silenced' => [
    App\Jobs\FrequentPingJob::class,
],
```

## Deploying to Production

### Generate a Service Config

Dawn can generate a ready-to-use config for your process manager:

```bash
# Interactive — prompts you to choose
php artisan dawn:service

# Or specify directly
php artisan dawn:service supervisor
php artisan dawn:service systemd
```

Options:

```bash
php artisan dawn:service supervisor --user=deploy --log=/var/log/dawn.log
```

#### Supervisor

```bash
php artisan dawn:service supervisor
# Outputs: dawn-{app-name}-supervisor.conf
sudo cp dawn-*-supervisor.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start dawn-my-app
```

#### systemd

```bash
php artisan dawn:service systemd
# Outputs: dawn-{app-name}.service
sudo cp dawn-*.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable dawn-my-app
sudo systemctl start dawn-my-app
```

The generated configs include the correct paths to your PHP binary and project directory — no manual editing needed. On Forge servers, the user is auto-detected (including isolated sites).

### Static Config File

By default Dawn reads config from Laravel at startup. If you prefer a static config file (e.g. for faster startup or to pin a known-good config):

```bash
php artisan dawn:export-config > dawn.json
./vendor/bin/dawn --config dawn.json
```

### Multiple Projects on One Server

Dawn handles this automatically. Each project gets:

- **Unique Redis prefix** — `dawn:install` sets `DAWN_PREFIX=dawn:{app_name}:` in `.env`, so each project's keys are isolated even on a shared Redis server.
- **Unique service name** — `dawn:service` names the service `dawn-{app-name}` (e.g. `dawn-my-shop`, `dawn-admin-panel`), so multiple services can coexist.

If you need to change the prefix after install, update `DAWN_PREFIX` in `.env` and restart Dawn.

### CLI Options

| Option | Default | Description |
|---|---|---|
| `--config` | | Path to a static JSON config (if omitted, reads from Laravel) |
| `--working-dir` | `.` | Laravel project root |
| `--php` | `php` | PHP binary path |
| `--environment` | | Override app environment |
| `--log-level` | `info` | `debug`, `info`, `warn`, `error` |
| `--log-file` | | Log to file instead of stderr |

## Artisan Commands

| Command | Description |
|---|---|
| `dawn:install` | Install Dawn (config, assets, .env setup) |
| `dawn:service` | Generate Supervisor or systemd service config |
| `dawn:export-config` | Export config as JSON (for static config or debugging) |
| `dawn:status` | Show supervisor status |
| `dawn:pause` | Pause queue processing |
| `dawn:continue` | Resume queue processing |
| `dawn:terminate` | Gracefully stop the supervisor |
| `dawn:snapshot` | Take a metrics snapshot |
| `dawn:clear-metrics` | Clear stored metrics |
| `dawn:purge` | Purge all Dawn data from Redis |

## License

MIT
