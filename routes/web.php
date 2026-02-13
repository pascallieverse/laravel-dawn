<?php

use Dawn\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware([Authenticate::class])->group(function () {
    // Dashboard
    Route::livewire('/', 'dawn::dashboard')->name('dawn.dashboard');

    // Monitoring
    Route::livewire('/monitoring', 'dawn::monitoring.index')->name('dawn.monitoring');
    Route::livewire('/monitoring/{tag}', 'dawn::monitoring.tag-jobs')
        ->where('tag', '.*')->name('dawn.monitoring.tag');

    // Metrics
    Route::livewire('/metrics/jobs', 'dawn::metrics.jobs')->name('dawn.metrics.jobs');
    Route::livewire('/metrics/queues', 'dawn::metrics.queues')->name('dawn.metrics.queues');
    Route::livewire('/metrics/{type}/{id}', 'dawn::metrics.preview')->name('dawn.metrics.preview');
    Route::livewire('/performance', 'dawn::performance.index')->name('dawn.performance');

    // Jobs (detail route must come before the optional type route)
    Route::livewire('/jobs/detail/{id}', 'dawn::recent-jobs.show')->name('dawn.jobs.show');
    Route::livewire('/jobs/{type?}', 'dawn::recent-jobs.index')->name('dawn.jobs');

    // Failed Jobs
    Route::livewire('/failed', 'dawn::failed-jobs.index')->name('dawn.failed');
    Route::livewire('/failed/{id}', 'dawn::failed-jobs.show')->name('dawn.failed.show');

    // Batches
    Route::livewire('/batches', 'dawn::batches.index')->name('dawn.batches');
    Route::livewire('/batches/{id}', 'dawn::batches.show')->name('dawn.batches.show');
});
