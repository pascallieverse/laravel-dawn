@props(['status'])

@php
    $classes = match($status) {
        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'reserved', 'processing' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'pending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'retried' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };

    $label = match($status) {
        'reserved' => 'Processing',
        default => ucfirst($status),
    };
@endphp

<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $classes }}">
    {{ $label }}
</span>
