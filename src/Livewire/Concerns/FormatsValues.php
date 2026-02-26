<?php

namespace Dawn\Livewire\Concerns;

use Carbon\Carbon;

trait FormatsValues
{
    protected function formatRuntime(mixed $ms): string
    {
        if (! $ms) {
            return '0ms';
        }

        $ms = (int) $ms;

        if ($ms < 1000) {
            return "{$ms}ms";
        }

        return number_format($ms / 1000, 2) . 's';
    }

    protected function formatDate(mixed $timestamp): string
    {
        if (! $timestamp) {
            return '-';
        }

        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp($timestamp)->toDateTimeString();
        }

        return Carbon::parse($timestamp)->toDateTimeString();
    }

    protected function formatWait(int $seconds): string
    {
        if ($seconds <= 0) {
            return '-';
        }

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            $m = (int) floor($seconds / 60);
            $s = $seconds % 60;
            return $s > 0 ? "{$m}m {$s}s" : "{$m}m";
        }

        $h = (int) floor($seconds / 3600);
        $m = (int) floor(($seconds % 3600) / 60);
        return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
    }

    protected function formatNumber(mixed $value): string
    {
        return number_format((int) $value);
    }
}
