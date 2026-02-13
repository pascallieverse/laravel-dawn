<?php

namespace Dawn\Contracts;

/**
 * Marker interface for jobs that should run in isolated mode.
 *
 * Jobs implementing this interface will be executed in a fresh PHP process
 * (`php artisan dawn:run-job`) instead of the warm worker pool. This provides
 * full process isolation at the cost of ~100-200ms Laravel bootstrap overhead.
 *
 * Use this for:
 * - Memory-leaky jobs
 * - Jobs that modify global state
 * - Jobs that load extensions or modify PHP configuration
 * - Any job where process isolation is more important than speed
 */
interface Isolated
{
    //
}
