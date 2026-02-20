# Dawn (Experimantal)

> [!WARNING]
> **This project is experimantal**
>
> This project is mostly written by AI and still experimantal, everything should work but needs to be tested and improved further)
> 



A Rust-powered queue manager and dashboard for Laravel. Dawn replaces PHP as the queue worker — a compiled Rust binary pops jobs from Redis directly, delegates PHP execution via warm worker pools or isolated processes, and writes all metrics to Redis. A Livewire dashboard gives you real-time visibility into your queues.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Redis
- `ext-pcntl` (Linux/macOS only — not required on Windows)

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
# Interactive — auto-detects your OS and suggests the right option
php artisan dawn:service

# Or specify directly
php artisan dawn:service supervisor   # Linux (Supervisor)
php artisan dawn:service systemd      # Linux (systemd)
php artisan dawn:service launchd      # macOS
php artisan dawn:service windows      # Windows (NSSM)
```

Options:

```bash
php artisan dawn:service supervisor --user=deploy --log=/var/log/dawn.log
```

#### Supervisor (Linux)

```bash
php artisan dawn:service supervisor
# Outputs: dawn-{app-name}-supervisor.conf
sudo cp dawn-*-supervisor.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start dawn-my-app
```

#### systemd (Linux)

```bash
php artisan dawn:service systemd
# Outputs: dawn-{app-name}.service
sudo cp dawn-*.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable dawn-my-app
sudo systemctl start dawn-my-app
```

#### launchd (macOS)

```bash
php artisan dawn:service launchd
# Outputs: com.dawn.{app-name}.plist
cp com.dawn.my-app.plist ~/Library/LaunchAgents/
launchctl load ~/Library/LaunchAgents/com.dawn.my-app.plist
```

#### Windows Service (NSSM)

```bash
php artisan dawn:service windows
# Outputs: dawn-{app-name}.bat (foreground) + NSSM install instructions
```

To run as a Windows Service, install [NSSM](https://nssm.cc) and follow the printed instructions. Dawn also generates a `.bat` file for running in the foreground during development.

The generated configs include the correct paths to your PHP binary and project directory — no manual editing needed. On Forge servers, the user is auto-detected (including isolated sites).

### Deploying on Laravel Forge

Forge uses Supervisor under the hood, so Dawn integrates naturally.

#### 1. Install Dawn in your project

```bash
composer require pascallieverse/laravel-dawn
php artisan dawn:install
```

Commit the changes (`config/dawn.php`, `DawnServiceProvider.php`, `.env` updates) and deploy.

#### 2. Create a Daemon in Forge

In the Forge dashboard, go to your server → **Daemons** → **Create Daemon**.

You must use the **absolute path** to the Dawn binary and specify your PHP version explicitly. Replace `{user}` with your Forge site user (e.g. `forge`, or the isolated site user) and `{site}` with your site directory name:

| Field | Value |
|---|---|
| Command | See below |
| User | `{user}` |
| Directory | `/home/{user}/{site}` |
| Processes | `1` |
| Start Seconds | `1` |
| Stop Seconds | `15` |
| Stop Signal | `SIGTERM` |

**Command** (must be a single line with absolute paths):

```
/home/{user}/{site}/vendor/pascallieverse/laravel-dawn/bin/dawn-linux-x64 --php /usr/bin/php8.4 --log-file /home/{user}/.forge/dawn.log
```

Adjust `php8.4` to match your site's PHP version (`php8.3`, `php8.2`, etc.).

> **Important:** Only run **1 process** — Dawn manages its own worker pool internally. Running multiple Dawn processes will cause duplicate job execution.

Click **Save**. Forge will create and start the Supervisor config automatically.

#### 3. Configure the Dashboard Gate

Edit `app/Providers/DawnServiceProvider.php` to grant dashboard access:

```php
protected function gate(): void
{
    Gate::define('viewDawn', function ($user) {
        return in_array($user->email, [
            'your-email@example.com',
        ]);
    });
}
```

Visit `https://your-site.com/dawn` to access the dashboard.

#### 4. Add to your Deployment Script

In the Forge dashboard, go to your site → **Deployments** → **Deployment Script**. Add the following after `composer install`:

```bash
# Ensure Dawn binary is executable (Composer doesn't preserve permissions)
chmod +x vendor/pascallieverse/laravel-dawn/bin/dawn-linux-x64

# Restart Dawn (finishes current jobs, then Supervisor restarts it)
php artisan dawn:terminate
```

A full deployment script example:

```bash
cd /home/{user}/{site}

git pull origin $FORGE_SITE_BRANCH

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Ensure Dawn binary is executable
chmod +x vendor/pascallieverse/laravel-dawn/bin/dawn-linux-x64

php artisan migrate --force

# Restart Dawn (finishes current jobs, then Supervisor restarts it)
php artisan dawn:terminate

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### 5. Verify it's Running

```bash
# Check the Dawn log
cat /home/{user}/.forge/dawn.log

# Or check Supervisor directly
sudo supervisorctl status
```

#### Troubleshooting on Forge

- **Dawn won't start / stops immediately with no log** — Make sure the daemon command uses **absolute paths** (not relative). Supervisor does not reliably resolve relative paths even with a `directory` setting. Also ensure the binary is executable (`chmod +x`).
- **"Exec format error"** — You're running the wrong binary for your server's architecture. Forge servers are typically `linux-x64`. Use `dawn-linux-x64`, not the macOS or ARM binary.
- **Jobs not processing** — Verify `QUEUE_CONNECTION=dawn` in your `.env` and that Redis is running. Run `php artisan dawn:export-config` to check the resolved configuration.
- **Dashboard returns 403** — Update the `viewDawn` gate in `DawnServiceProvider.php` with your email.
- **Wrong PHP version** — Forge servers with multiple PHP versions need the explicit `--php /usr/bin/php8.x` flag. The bare `php` command may point to a different version or not be in Supervisor's PATH.

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
| `dawn:service` | Generate service config (Supervisor, systemd, launchd, Windows) |
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
