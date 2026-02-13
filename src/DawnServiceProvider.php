<?php

namespace Dawn;

use Dawn\Connectors\DawnConnector;
use Dawn\Contracts\CommandQueue as CommandQueueContract;
use Dawn\Contracts\JobRepository as JobRepositoryContract;
use Dawn\Contracts\MasterSupervisorRepository as MasterSupervisorRepositoryContract;
use Dawn\Contracts\MetricsRepository as MetricsRepositoryContract;
use Dawn\Contracts\SupervisorRepository as SupervisorRepositoryContract;
use Dawn\Contracts\TagRepository as TagRepositoryContract;
use Dawn\Repositories\DawnJobRepository;
use Dawn\Repositories\DawnMasterSupervisorRepository;
use Dawn\Repositories\DawnMetricsRepository;
use Dawn\Repositories\DawnSupervisorRepository;
use Dawn\Repositories\DawnTagRepository;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class DawnServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dawn.php', 'dawn');

        $this->registerRedisConnection();
        $this->registerQueueConnection();
        $this->registerRepositories();
        $this->registerCommandQueue();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->excludeDarkModeCookie();
        $this->registerRoutes();
        $this->registerResources();
        $this->registerLivewireComponents();
        $this->registerCommands();
        $this->registerQueueConnector();
        $this->registerPublishing();
    }

    /**
     * Register a dedicated Redis connection for Dawn with no key prefix.
     *
     * Rust writes keys like `dawn:masters` directly. Laravel's default Redis
     * connection prepends its own prefix (e.g. `laravel_database_`), so a
     * dedicated prefix-free connection is needed for PHP to read what Rust wrote.
     */
    protected function registerRedisConnection(): void
    {
        $config = $this->app['config'];

        // Copy the base connection and strip the prefix.
        // The prefix must live inside the 'options' array so that
        // PhpRedisConnector picks it up and overrides the global
        // database.redis.options.prefix value.
        $use = $config->get('dawn.use', 'default');
        $base = $config->get("database.redis.{$use}", []);

        $dawn = $base;
        $dawn['prefix'] = '';
        $dawn['options'] = array_merge($dawn['options'] ?? [], ['prefix' => '']);

        $config->set('database.redis.dawn', $dawn);
    }

    /**
     * Register the Dawn queue connection at runtime so users don't need
     * to manually edit config/queue.php.
     */
    protected function registerQueueConnection(): void
    {
        $config = $this->app['config'];

        if ($config->get('queue.connections.dawn')) {
            return;
        }

        $config->set('queue.connections.dawn', [
            'driver' => 'dawn',
            'connection' => $config->get('dawn.use', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ]);
    }

    /**
     * Register the Dawn repository bindings.
     */
    protected function registerRepositories(): void
    {
        $prefix = config('dawn.prefix', 'dawn:');

        $this->app->singleton(JobRepositoryContract::class, function ($app) use ($prefix) {
            return new DawnJobRepository($app->make('redis'), $prefix);
        });

        $this->app->singleton(MetricsRepositoryContract::class, function ($app) use ($prefix) {
            return new DawnMetricsRepository($app->make('redis'), $prefix);
        });

        $this->app->singleton(SupervisorRepositoryContract::class, function ($app) use ($prefix) {
            return new DawnSupervisorRepository($app->make('redis'), $prefix);
        });

        $this->app->singleton(MasterSupervisorRepositoryContract::class, function ($app) use ($prefix) {
            return new DawnMasterSupervisorRepository($app->make('redis'), $prefix);
        });

        $this->app->singleton(TagRepositoryContract::class, function ($app) use ($prefix) {
            return new DawnTagRepository($app->make('redis'), $prefix);
        });
    }

    /**
     * Register the command queue binding.
     */
    protected function registerCommandQueue(): void
    {
        $this->app->singleton(CommandQueueContract::class, function ($app) {
            return new DawnCommandQueue(
                $app->make('redis'),
                config('dawn.prefix', 'dawn:'),
            );
        });
    }

    /**
     * Exclude the dark mode cookie from encryption so the Blade layout
     * can read it server-side via $_COOKIE before rendering.
     */
    protected function excludeDarkModeCookie(): void
    {
        EncryptCookies::except(['dawn_dark_mode']);
    }

    /**
     * Register the Dawn routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'domain' => config('dawn.domain'),
            'prefix' => config('dawn.path', 'dawn'),
            'middleware' => config('dawn.middleware', ['web']),
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }

    /**
     * Register the Dawn resources.
     */
    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dawn');
    }

    /**
     * Register the Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::addNamespace(
            namespace: 'dawn',
            classNamespace: 'Dawn\\Livewire',
            classPath: __DIR__ . '/Livewire',
            classViewPath: __DIR__ . '/../resources/views/livewire',
        );
    }

    /**
     * Register the Dawn Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\DawnLoopCommand::class,
                Console\Commands\DawnRunJobCommand::class,
                Console\Commands\ExportConfigCommand::class,
                Console\Commands\InstallCommand::class,
                Console\Commands\PublishCommand::class,
                Console\Commands\PauseCommand::class,
                Console\Commands\ContinueCommand::class,
                Console\Commands\TerminateCommand::class,
                Console\Commands\StatusCommand::class,
                Console\Commands\SnapshotCommand::class,
                Console\Commands\ClearMetricsCommand::class,
                Console\Commands\PurgeCommand::class,
                Console\Commands\GenerateServiceCommand::class,
            ]);
        }
    }

    /**
     * Register the Dawn queue connector.
     */
    protected function registerQueueConnector(): void
    {
        $this->app->afterResolving('queue', function ($manager) {
            $manager->addConnector('dawn', function () {
                return new DawnConnector($this->app->make('redis'));
            });
        });
    }

    /**
     * Register the publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/dawn.php' => config_path('dawn.php'),
            ], 'dawn-config');

            $this->publishes([
                __DIR__ . '/../public' => public_path('vendor/dawn'),
            ], 'dawn-assets');

            $this->publishes([
                __DIR__ . '/../stubs/DawnServiceProvider.stub' => app_path('Providers/DawnServiceProvider.php'),
            ], 'dawn-provider');
        }
    }
}
