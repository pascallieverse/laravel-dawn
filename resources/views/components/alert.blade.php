@props(['type' => 'info'])

@php
    $classes = match($type) {
        'success' => 'bg-green-50 dark:bg-green-900/20 border-green-300 dark:border-green-700 text-green-800 dark:text-green-300',
        'warning' => 'bg-dawn-50 dark:bg-dawn-900/20 border-dawn-300 dark:border-dawn-700 text-dawn-800 dark:text-dawn-300',
        'danger' => 'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700 text-red-800 dark:text-red-300',
        default => 'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-300',
    };
@endphp

<div role="alert" class="rounded-lg border p-4 {{ $classes }}">
    {{ $slot }}
</div>
