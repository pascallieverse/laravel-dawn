<div>
    <div class="mb-6">
        <a href="{{ route('dawn.monitoring') }}" wire:navigate class="text-sm text-dawn-600 dark:text-dawn-400 hover:text-dawn-700 dark:hover:text-dawn-300">
            &larr; Back to Monitoring
        </a>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold text-gray-900 dark:text-white break-all">{{ urldecode($tag) }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $total }} jobs</p>
    </div>

    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
        @if(count($jobs) > 0)
            <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Job</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($jobs as $job)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-3 sm:px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $job['class'] ?? $job['name'] ?? '-' }}</td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $job['id'] ?? '-' }}</td>
                            <td class="px-3 sm:px-6 py-4"><x-dawn::job-status-badge :status="$job['status'] ?? 'unknown'" /></td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $this->formatDate($job['completed_at'] ?? $job['failed_at'] ?? $job['reserved_at'] ?? $job['pushed_at'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <x-dawn::pagination :page="$page" :totalPages="$totalPages" :total="$total" :from="$from" :to="$to" />
        @else
            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No jobs found for this tag.
            </div>
        @endif
    </div>
</div>
