@props(['progress' => 0])

@php
    $progress = max(0, min(100, (float) $progress));
@endphp

<div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
    <div
        class="bg-dawn-500 h-2 rounded-full transition-all duration-300"
        style="width: {{ $progress }}%"
    ></div>
</div>
