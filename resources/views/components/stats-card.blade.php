@props(['label', 'value', 'type' => 'default'])

@php
    $colors = match($type) {
        'success' => 'text-green-600 dark:text-green-400',
        'danger' => 'text-red-600 dark:text-red-400',
        'warning' => 'text-dawn-600 dark:text-dawn-400',
        default => 'text-gray-600 dark:text-gray-400',
    };
    $iconBg = match($type) {
        'success' => 'bg-green-100 dark:bg-green-900/30',
        'danger' => 'bg-red-100 dark:bg-red-900/30',
        'warning' => 'bg-dawn-100 dark:bg-dawn-900/30',
        default => 'bg-gray-100 dark:bg-gray-800',
    };
@endphp

<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5 shadow-sm">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="mt-1 text-2xl font-bold {{ $colors }}">
                {{ is_numeric($value) ? number_format((int) $value) : $value }}
            </p>
        </div>
        @if(isset($icon))
            <div class="flex-shrink-0 w-12 h-12 rounded-lg {{ $iconBg }} flex items-center justify-center">
                {{ $icon }}
            </div>
        @endif
    </div>
</div>
