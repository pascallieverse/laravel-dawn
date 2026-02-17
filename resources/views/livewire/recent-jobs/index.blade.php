<div wire:poll.3s>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Jobs</h1>

    {{-- Tabs --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 overflow-x-auto">
            @foreach(['all', 'pending', 'processing', 'completed', 'failed', 'silenced'] as $tab)
                <button
                    wire:click="setTab('{{ $tab }}')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors whitespace-nowrap {{ $activeTab === $tab ? 'bg-white dark:bg-gray-700 text-dawn-700 dark:text-dawn-300 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
                >
                    {{ ucfirst($tab) }}
                </button>
            @endforeach
        </div>

        {{-- Cancel Actions (only for pending and processing tabs) --}}
        @if(in_array($activeTab, ['pending', 'processing']) && count($jobs) > 0)
            <div class="flex items-center gap-2">
                @if(count($selected) > 0)
                    <button
                        wire:click="cancelSelected"
                        wire:confirm="Cancel {{ count($selected) }} selected job(s)?"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 dark:text-red-400 dark:bg-red-900/20 dark:hover:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-md transition-colors"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        Cancel Selected ({{ count($selected) }})
                    </button>
                @endif
                <button
                    wire:click="cancelAll"
                    wire:confirm="Cancel ALL {{ $activeTab }} jobs? This cannot be undone."
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 dark:text-red-400 dark:bg-red-900/20 dark:hover:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-md transition-colors"
                >
                    <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    Cancel All
                </button>
            </div>
        @endif
    </div>

    {{-- Jobs Table --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
        @if(count($jobs) > 0)
            <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        @if(in_array($activeTab, ['pending', 'processing']))
                            <th class="w-10 px-4 py-3">
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-600 text-dawn-600 focus:ring-dawn-500"
                                    x-on:change="
                                        if ($event.target.checked) {
                                            $wire.set('selected', {{ json_encode(array_column($jobs, 'id')) }})
                                        } else {
                                            $wire.set('selected', [])
                                        }
                                    "
                                    @checked(count($selected) === count($jobs) && count($jobs) > 0)
                                />
                            </th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Job</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Queue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            @if($activeTab === 'pending')
                                Date / Delayed Until
                            @else
                                Date
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            @if($activeTab === 'processing')
                                Elapsed
                            @elseif($activeTab === 'pending')
                                Waiting
                            @else
                                Runtime
                            @endif
                        </th>
                        @if(in_array($activeTab, ['pending', 'processing']))
                            <th class="w-20 px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($jobs as $job)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ in_array($activeTab, ['pending', 'processing']) ? '' : 'cursor-pointer' }}"
                            @if(!in_array($activeTab, ['pending']) && ($job['status'] ?? '') !== 'pending')
                                onclick="Livewire.navigate('{{ route('dawn.jobs.show', ['id' => $job['id'] ?? '']) }}')"
                            @endif
                        >
                            @if(in_array($activeTab, ['pending', 'processing']))
                                <td class="w-10 px-4 py-4" onclick="event.stopPropagation()">
                                    <input
                                        type="checkbox"
                                        value="{{ $job['id'] ?? '' }}"
                                        wire:model.live="selected"
                                        class="rounded border-gray-300 dark:border-gray-600 text-dawn-600 focus:ring-dawn-500"
                                    />
                                </td>
                            @endif
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ class_basename($job['name'] ?? $job['class'] ?? '-') }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $job['queue'] ?? '-' }}</td>
                            <td class="px-6 py-4"><x-dawn::job-status-badge :status="$job['status'] ?? 'unknown'" /></td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                @if(($job['status'] ?? '') === 'delayed' && isset($job['delayed_until']))
                                    {{ $this->formatDate($job['delayed_until']) }}
                                @else
                                    {{ $this->formatDate($job['completed_at'] ?? $job['failed_at'] ?? $job['reserved_at'] ?? $job['pushed_at'] ?? null) }}
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if(($job['status'] ?? '') === 'delayed' && isset($job['delayed_until']))
                                    @php $remaining = $job['delayed_until'] - now()->timestamp; @endphp
                                    @if($remaining > 0)
                                        {{ $this->formatRuntime($remaining * 1000) }}
                                    @else
                                        ready
                                    @endif
                                @elseif(($job['status'] ?? '') === 'reserved' && isset($job['reserved_at']))
                                    {{ $this->formatRuntime((now()->timestamp - $job['reserved_at']) * 1000) }}
                                @elseif(($job['status'] ?? '') === 'pending' && isset($job['pushed_at']))
                                    {{ $this->formatRuntime((now()->timestamp - $job['pushed_at']) * 1000) }}
                                @elseif(isset($job['runtime']))
                                    {{ $this->formatRuntime($job['runtime'] * 1000) }}
                                @else
                                    —
                                @endif
                            </td>
                            @if(in_array($activeTab, ['pending', 'processing']))
                                <td class="w-20 px-4 py-4 text-right" onclick="event.stopPropagation()">
                                    @if($activeTab === 'pending')
                                        <button
                                            wire:click="cancelPendingJob('{{ $job['id'] ?? '' }}', '{{ $job['queue'] ?? 'default' }}')"
                                            wire:confirm="Cancel this pending job?"
                                            class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                            title="Cancel job"
                                        >
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        </button>
                                    @elseif($activeTab === 'processing')
                                        <button
                                            wire:click="cancelProcessingJob('{{ $job['id'] ?? '' }}')"
                                            wire:confirm="Stop this processing job? The worker will be killed."
                                            class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                            title="Stop job"
                                        >
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd"/></svg>
                                        </button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <x-dawn::pagination :page="$page" :totalPages="$totalPages" :total="$total" :from="$from" :to="$to" />
        @else
            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No {{ $activeTab }} jobs found.
            </div>
        @endif
    </div>
</div>
