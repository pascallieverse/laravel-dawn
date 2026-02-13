<?php

namespace Dawn;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class DawnApplicationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->authorization();
    }

    /**
     * Configure the Dawn authorization services.
     */
    protected function authorization(): void
    {
        $this->gate();

        Dawn::auth(function ($request) {
            return app()->environment('local')
                || Gate::check('viewDawn', [$request->user()]);
        });
    }

    /**
     * Register the Dawn gate.
     *
     * This gate determines who can access Dawn in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewDawn', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
