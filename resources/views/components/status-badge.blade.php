@props(['status'])

@php
    $classes = match($status) {
        'running' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'paused' => 'bg-dawn-100 text-dawn-800 dark:bg-dawn-900/30 dark:text-dawn-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };
    $dot = match($status) {
        'running' => 'bg-green-500',
        'paused' => 'bg-dawn-500',
        default => 'bg-gray-500',
    };
@endphp

<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $classes }}">
    <span class="w-2 h-2 rounded-full {{ $dot }} mr-2"></span>
    {{ ucfirst($status) }}
</span>
