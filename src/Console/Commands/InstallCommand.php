<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'dawn:install';
    protected $description = 'Install the Dawn components and resources';

    public function handle(): int
    {
        $this->comment('Publishing Dawn Service Provider...');
        $this->callSilent('vendor:publish', ['--tag' => 'dawn-provider']);

        $this->comment('Publishing Dawn Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'dawn-config']);

        $this->comment('Publishing Dawn Assets...');
        $this->callSilent('vendor:publish', ['--tag' => 'dawn-assets']);

        $this->registerServiceProvider();
        $this->setQueueConnection();
        $this->setDawnPrefix();

        $this->info('Dawn installed successfully.');
        $this->newLine();
        $this->info('Start the worker with:');
        $this->line(PHP_OS_FAMILY === 'Windows' ? '  vendor\\bin\\dawn' : '  ./vendor/bin/dawn');

        return 0;
    }

    /**
     * Register the Dawn service provider in the application configuration.
     */
    protected function registerServiceProvider(): void
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        $providerPath = app_path('Providers/DawnServiceProvider.php');

        if (! file_exists($providerPath)) {
            $stub = <<<PHP
            <?php

            namespace {$namespace}\Providers;

            use Dawn\DawnApplicationServiceProvider;

            class DawnServiceProvider extends DawnApplicationServiceProvider
            {
                /**
                 * Bootstrap any application services.
                 */
                public function boot(): void
                {
                    parent::boot();

                    // Dawn::routeMailNotificationsTo('admin@example.com');
                    // Dawn::routeSlackNotificationsTo('webhook-url', '#channel');
                }

                /**
                 * Register the Dawn gate.
                 *
                 * This gate determines who can access Dawn in non-local environments.
                 */
                protected function gate(): void
                {
                    \Illuminate\Support\Facades\Gate::define('viewDawn', function (\$user) {
                        return in_array(\$user->email, [
                            //
                        ]);
                    });
                }
            }
            PHP;

            if (! is_dir(dirname($providerPath))) {
                mkdir(dirname($providerPath), 0755, true);
            }

            file_put_contents($providerPath, $stub);
        }

        $provider = "{$namespace}\\Providers\\DawnServiceProvider";

        // Laravel 11+ uses bootstrap/providers.php
        if (file_exists($this->laravel->bootstrapPath('providers.php'))) {
            ServiceProvider::addProviderToBootstrapFile($provider);
        } else {
            // Laravel 10 and earlier use config/app.php
            $appConfig = file_get_contents(config_path('app.php'));

            if (Str::contains($appConfig, $provider)) {
                return;
            }

            file_put_contents(config_path('app.php'), str_replace(
                "{$namespace}\\Providers\\EventServiceProvider::class,".PHP_EOL,
                "{$namespace}\\Providers\\EventServiceProvider::class,".PHP_EOL.
                "        {$namespace}\\Providers\\DawnServiceProvider::class,".PHP_EOL,
                $appConfig
            ));
        }
    }

    /**
     * Set QUEUE_CONNECTION=dawn in the .env file.
     */
    protected function setQueueConnection(): void
    {
        $envPath = $this->laravel->environmentFilePath();

        if (! file_exists($envPath)) {
            return;
        }

        $env = file_get_contents($envPath);

        if (preg_match('/^QUEUE_CONNECTION=dawn$/m', $env)) {
            return;
        }

        $this->comment('Setting QUEUE_CONNECTION=dawn in .env...');

        if (preg_match('/^QUEUE_CONNECTION=.*$/m', $env)) {
            $env = preg_replace('/^QUEUE_CONNECTION=.*$/m', 'QUEUE_CONNECTION=dawn', $env);
        } else {
            $env .= "\nQUEUE_CONNECTION=dawn\n";
        }

        file_put_contents($envPath, $env);
    }

    /**
     * Set a unique DAWN_PREFIX in .env based on the app name.
     *
     * This ensures multiple Dawn instances on the same Redis server
     * don't collide on keys like dawn:masters, dawn:job:*, etc.
     */
    protected function setDawnPrefix(): void
    {
        $envPath = $this->laravel->environmentFilePath();

        if (! file_exists($envPath)) {
            return;
        }

        $env = file_get_contents($envPath);

        // Don't overwrite if already set
        if (preg_match('/^DAWN_PREFIX=/m', $env)) {
            return;
        }

        $appName = Str::slug(config('app.name', 'laravel'), '_');
        $prefix = "dawn:{$appName}:";

        $this->comment("Setting DAWN_PREFIX={$prefix} in .env...");

        $env .= "\nDAWN_PREFIX={$prefix}\n";

        file_put_contents($envPath, $env);
    }

}
