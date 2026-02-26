<div wire:poll.10s>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Job Metrics</h1>

    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
        @if(count($metrics) > 0)
            <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Job</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Processing</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Completed</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Failed</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Runtime</th>
                        <th class="px-3 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($metrics as $metric)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-3 sm:px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $metric['name'] ?? '-' }}</td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if(($metric['pending'] ?? 0) > 0)
                                    <span class="text-yellow-600 dark:text-yellow-400 font-medium">{{ number_format($metric['pending']) }}</span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">0</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if(($metric['processing'] ?? 0) > 0)
                                    <span class="text-blue-600 dark:text-blue-400 font-medium">{{ number_format($metric['processing']) }}</span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">0</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if(($metric['completed'] ?? 0) > 0)
                                    <span class="text-green-600 dark:text-green-400 font-medium">{{ number_format($metric['completed']) }}</span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">0</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if(($metric['failed'] ?? 0) > 0)
                                    <span class="text-red-600 dark:text-red-400 font-medium">{{ number_format($metric['failed']) }}</span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">0</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $this->formatRuntime($metric['avg_runtime'] ?? 0) }}</td>
                            <td class="px-3 sm:px-6 py-4 text-right">
                                <a
                                    href="{{ route('dawn.metrics.preview', ['type' => 'jobs', 'id' => base64_encode($metric['name'] ?? '')]) }}"
                                    wire:navigate
                                    class="text-sm text-dawn-600 dark:text-dawn-400 hover:text-dawn-700 dark:hover:text-dawn-300"
                                >
                                    View &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @else
            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No job metrics recorded yet.
            </div>
        @endif
    </div>
</div>
