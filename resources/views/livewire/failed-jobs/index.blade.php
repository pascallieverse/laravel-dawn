<div wire:poll.3s>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Failed Jobs</h1>
        @if($total > 0)
            <button
                wire:click="retryAll"
                wire:confirm="Are you sure you want to retry all {{ $total }} failed jobs?"
                class="px-4 py-2 bg-dawn-500 hover:bg-dawn-600 text-white text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-dawn-500 focus:ring-offset-2"
            >
                Retry All
            </button>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
        @if(count($jobs) > 0)
            <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Job</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Failed At</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Error</th>
                        <th class="px-3 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($jobs as $job)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-3 sm:px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <a
                                        href="{{ route('dawn.failed.show', ['id' => $job['id'] ?? '']) }}"
                                        wire:navigate
                                        class="text-sm font-medium text-dawn-600 dark:text-dawn-400 hover:text-dawn-700 dark:hover:text-dawn-300"
                                    >
                                        {{ $job['class'] ?? $job['name'] ?? '-' }}
                                    </a>
                                    @if(($job['status'] ?? '') === 'retried')
                                        <x-dawn::job-status-badge status="retried" />
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $job['id'] ?? '-' }}</td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $this->formatDate($job['failed_at'] ?? null) }}</td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-red-600 dark:text-red-400 truncate max-w-xs">{{ $job['error'] ?? $job['exception'] ?? '-' }}</td>
                            <td class="px-3 sm:px-6 py-4 text-right">
                                @if(($job['status'] ?? '') !== 'retried')
                                    <button
                                        wire:click="retry('{{ $job['id'] ?? '' }}')"
                                        class="text-sm text-dawn-600 dark:text-dawn-400 hover:text-dawn-700 dark:hover:text-dawn-300 font-medium"
                                    >
                                        Retry
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <x-dawn::pagination :page="$page" :totalPages="$totalPages" :total="$total" :from="$from" :to="$to" />
        @else
            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No failed jobs.
            </div>
        @endif
    </div>
</div>
