@props(['page', 'totalPages', 'total', 'from', 'to'])

@if($totalPages > 1)
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-4 sm:px-6 py-3 border-t border-gray-200 dark:border-gray-700">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Showing <span class="font-medium text-gray-700 dark:text-gray-300">{{ $from }}</span>
            to <span class="font-medium text-gray-700 dark:text-gray-300">{{ $to }}</span>
            of <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($total) }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <button
                wire:click="previousPage"
                @disabled($page <= 1)
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors
                    {{ $page <= 1
                        ? 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                        : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
            >
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Previous
            </button>

            @php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                if ($end - $start < 4) {
                    $start = max(1, $end - 4);
                    $end = min($totalPages, $start + 4);
                }
            @endphp

            @if($start > 1)
                <button wire:click="goToPage(1)" class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">1</button>
                @if($start > 2)
                    <span class="text-gray-400 dark:text-gray-500 px-0.5">...</span>
                @endif
            @endif

            @for($i = $start; $i <= $end; $i++)
                <button
                    wire:click="goToPage({{ $i }})"
                    class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-md transition-colors
                        {{ $i === $page
                            ? 'bg-dawn-500 text-white'
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                >{{ $i }}</button>
            @endfor

            @if($end < $totalPages)
                @if($end < $totalPages - 1)
                    <span class="text-gray-400 dark:text-gray-500 px-0.5">...</span>
                @endif
                <button wire:click="goToPage({{ $totalPages }})" class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">{{ $totalPages }}</button>
            @endif

            <button
                wire:click="nextPage"
                @disabled($page >= $totalPages)
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors
                    {{ $page >= $totalPages
                        ? 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                        : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
            >
                Next
                <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>
    </div>
@endif
