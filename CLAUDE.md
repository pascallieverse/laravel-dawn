# CLAUDE.md — Laravel Dawn

## What is this project?

Laravel Dawn is a **Rust-powered queue manager and dashboard for Laravel**. The compiled Rust binary pops jobs from Redis directly, delegates PHP execution via warm worker pools or isolated processes, and writes all metrics to Redis. A Livewire dashboard provides real-time visibility into queues.

**Status**: Experimental (MIT licensed)

## Tech Stack

- **PHP 8.1+** with Laravel 10/11/12
- **Redis** for queue storage and state
- **Rust binary** for queue supervision and worker orchestration (pre-compiled, in `bin/`)
- **Livewire 4** for the real-time dashboard UI
- **Orchestra Testbench** + PHPUnit for testing (dev dependencies declared but no tests written yet)

## Project Structure

```
bin/                    # Rust binaries (per-platform) + PHP wrapper script
config/dawn.php         # Package configuration (queues, workers, trimming, metrics)
public/                 # Static assets (dawn.css)
resources/views/        # Blade templates for dashboard (layouts, components, Livewire pages)
routes/web.php          # Dashboard routes (13 Livewire routes, all behind auth middleware)
src/                    # PHP source code
  Dawn.php              # Static facade for configuration (auth, notifications)
  DawnServiceProvider.php         # Main service provider (registers everything)
  DawnApplicationServiceProvider.php  # Base class users extend for auth config
  DawnQueue.php         # Custom Redis queue extending Laravel's RedisQueue
  DawnCommandQueue.php  # Sends commands to Rust binary via Redis
  JobPayload.php        # Prepares job payloads with Dawn metadata
  Connectors/           # Queue connector (DawnConnector)
  Console/Commands/     # 13 Artisan commands
  Contracts/            # 7 interfaces (repository pattern)
  Http/Middleware/       # Dashboard authentication
  Jobs/                 # DawnJob wrapper for Redis job payloads
  Livewire/             # Dashboard components + Concerns/ traits
  Repositories/         # 5 Redis-backed repository implementations
```

## Architecture Overview

### Three-layer separation
- **PHP layer**: Job dispatch, dashboard UI, Artisan commands
- **Rust layer**: Process management, queue supervision, worker orchestration
- **Redis layer**: Shared state, job storage, pub/sub, metrics

### Job execution modes
1. **Warm worker loop** (`dawn:loop`): Reuses a booted PHP process for speed. Reads jobs from stdin, returns JSON results to stdout.
2. **Isolated mode** (`dawn:run-job`): Fresh PHP process per job. Used for memory-leaky or long-running jobs. Triggered by implementing `Dawn\Contracts\Isolated` or listing in config.

### Design patterns
- **Repository pattern**: All Redis data access via contracts (`Contracts/`) bound to implementations (`Repositories/`)
- **Service provider pattern**: `DawnServiceProvider` registers all services, routes, commands
- **Marker interface**: `Isolated` interface flags jobs for isolated execution
- **Connector pattern**: `DawnConnector` + `DawnQueue` integrate with Laravel's queue system

### Key contracts
| Contract | Purpose |
|----------|---------|
| `JobRepository` | Job CRUD, failed job management, log storage |
| `SupervisorRepository` | Worker process status |
| `MasterSupervisorRepository` | Master supervisor state |
| `MetricsRepository` | Performance metrics and snapshots |
| `TagRepository` | Tag-based job monitoring |
| `CommandQueue` | Push commands to Rust binary |
| `Isolated` | Marker interface for isolated job execution |

## Namespace

All source code lives under the `Dawn\` namespace (PSR-4 mapped to `src/`).

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `dawn:install` | One-time setup (config, provider, .env) |
| `dawn:export-config` | Export config as JSON for Rust binary |
| `dawn:service` | Generate service configs (Supervisor, systemd, launchd, Windows) |
| `dawn:status` | Show supervisor status |
| `dawn:pause` / `dawn:continue` | Pause/resume queue processing |
| `dawn:terminate` | Gracefully stop the supervisor |
| `dawn:snapshot` | Capture metrics snapshot |
| `dawn:clear-metrics` | Clear stored metrics |
| `dawn:purge` | Purge all Dawn data from Redis |
| `dawn:loop` | Internal: warm worker event loop (called by Rust) |
| `dawn:run-job` | Internal: isolated job executor (called by Rust) |

## Development Workflow

### Prerequisites
- PHP 8.1+
- Composer
- Redis running locally

### Setup
```bash
composer install
```

### No CI/CD, linting, or test suite configured yet
- No `.github/workflows/`, no phpunit.xml, no tests/ directory
- No code style tools (Pint, PHP-CS-Fixer, PHPStan) configured
- Dev dependencies (PHPUnit, Orchestra Testbench) are declared in composer.json but unused
- Autoload-dev maps `Dawn\Tests\` to `tests/` for future use

### Code style conventions (observed)
- PSR-4 autoloading
- PHP 8.1+ features: readonly properties, named arguments, match expressions, null coalescing
- Short closures and arrow functions where appropriate
- Type declarations on method signatures
- No docblocks on self-explanatory methods

## Redis Key Structure

All keys use the prefix from `DAWN_PREFIX` env var (e.g., `dawn:my_app:`):

```
dawn:{prefix}job:{id}              # Job metadata (hash, TTL: 24h)
dawn:{prefix}failed:{id}           # Failed job details (TTL: 7d)
dawn:{prefix}logs:{id}             # Job execution logs (TTL: 7d)
dawn:{prefix}recent_jobs           # ZSET of recent job IDs
dawn:{prefix}pending_jobs          # ZSET of processing jobs
dawn:{prefix}completed_jobs        # ZSET of completed job IDs
dawn:{prefix}failed_jobs           # ZSET of failed job IDs
dawn:{prefix}masters               # SET of master supervisor names
dawn:{prefix}supervisor:{name}     # Supervisor config (JSON)
dawn:{prefix}supervisors:{master}  # SET of supervisor names per master
dawn:{prefix}commands:{target}     # LIST of commands for Rust
dawn:{prefix}metrics:job:{class}   # Job-level metrics (hash)
dawn:{prefix}metrics:queue:{name}  # Queue-level metrics (hash)
dawn:{prefix}tag:{tag}             # ZSET of monitored job IDs for tag
```

## Dashboard Routes

All routes are Livewire-based, protected by `Dawn\Http\Middleware\Authenticate`, and mounted under the configurable `dawn.path` (default: `/dawn`):

- `/` — Dashboard (live stats, workload)
- `/monitoring` — Tag-based monitoring
- `/metrics/jobs`, `/metrics/queues` — Throughput/runtime metrics
- `/performance` — Historical charts
- `/jobs/{type?}`, `/jobs/detail/{id}` — Job lists and detail
- `/failed`, `/failed/{id}` — Failed jobs
- `/batches`, `/batches/{id}` — Batch inspection

## Important Notes

- The Rust binary is **pre-compiled** and lives in `bin/`. The PHP wrapper (`bin/dawn`) auto-detects OS/architecture.
- Redis connections are **prefix-free** for Rust interoperability — PHP uses a special `dawn` connection that strips Laravel's default prefix.
- `dawn:loop` and `dawn:run-job` communicate with Rust via **stdin/stdout JSON** — they are not meant to be called directly.
- Job payloads are enriched by `JobPayload::prepare()` with UUID, tags (auto-detected from Eloquent models), timestamps, and isolation/silenced flags.
- Dashboard auth defaults to local environment access; production requires a `viewDawn` gate.
