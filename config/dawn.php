<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dawn Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where the Dawn dashboard will be accessible from.
    |
    */

    'path' => env('DAWN_PATH', 'dawn'),

    /*
    |--------------------------------------------------------------------------
    | Dawn Domain
    |--------------------------------------------------------------------------
    |
    | If set, the Dawn dashboard will only be accessible on this subdomain.
    |
    */

    'domain' => env('DAWN_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | The base Redis connection Dawn will use. Dawn automatically creates a
    | prefix-free variant of this connection for reading Rust-written keys.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Redis Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all Dawn keys in Redis. Change this if you run multiple
    | applications on the same Redis server.
    |
    */

    'prefix' => env('DAWN_PREFIX', 'dawn:'),

    /*
    |--------------------------------------------------------------------------
    | Queue Workers
    |--------------------------------------------------------------------------
    |
    | Define your queue workers here. In most cases the defaults are all you
    | need — just list the queues you want processed. All other options
    | (balance strategy, process counts, timeouts) have sensible defaults
    | and can be added when you need to tune them.
    |
    | Available options (with defaults):
    |   'connection'          => 'dawn'
    |   'queue'               => ['default']
    |   'balance'             => 'auto'       // 'auto', 'simple', or false
    |   'minProcesses'        => 1
    |   'maxProcesses'        => 10
    |   'tries'               => 3
    |   'timeout'             => 3600         // seconds
    |   'memory'              => 128          // MB per worker
    |   'balanceCooldown'     => 3            // seconds between scale events
    |   'balanceMaxShift'     => 1            // max workers added/removed per scale
    |   'nice'                => 0
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'queue' => ['default'],
            'maxProcesses' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Overrides
    |--------------------------------------------------------------------------
    |
    | Override supervisor settings per environment. Keys here are merged on
    | top of the defaults above when the app runs in that environment.
    |
    */

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Isolated Jobs
    |--------------------------------------------------------------------------
    |
    | These jobs run in a fresh PHP process instead of the warm worker pool.
    | Use this for memory-leaky or long-running jobs. You can also implement
    | the Dawn\Contracts\Isolated interface on the job class itself.
    |
    */

    'isolated' => [
        // App\Jobs\HeavyJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | These jobs won't appear in monitoring or metrics. Useful for
    | high-frequency jobs you don't need to track individually.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trimming
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) to keep job records. Recent/pending/completed
    | jobs default to 1 hour, failed jobs to 7 days.
    |
    */

    'trim' => [
        'recent' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed_jobs' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced
    |--------------------------------------------------------------------------
    */

    'waits' => [
        'redis:default' => 3600,
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'memory_limit' => 512,

    'fast_terminate' => false,

];
