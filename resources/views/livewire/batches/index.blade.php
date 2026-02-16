<div wire:poll.5s>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Batches</h1>

    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
        @if(count($batches) > 0)
            <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Progress</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($batches as $batch)
                        <tr
                            class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                            onclick="Livewire.navigate('{{ route('dawn.batches.show', ['id' => $batch['id']]) }}')"
                        >
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $batch['name'] ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1">
                                        <x-dawn::progress-bar :progress="$batch['progress']" />
                                    </div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 w-12 text-right">{{ number_format($batch['progress'], 0) }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ number_format($batch['totalJobs']) }}</td>
                            <td class="px-6 py-4 text-sm {{ ($batch['failedJobs'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ number_format($batch['failedJobs'] ?? 0) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($batch['createdAt'])->toDateTimeString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <x-dawn::pagination :page="$page" :totalPages="$totalPages" :total="$total" :from="$from" :to="$to" />
        @else
            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No batches found.
            </div>
        @endif
    </div>
</div>
