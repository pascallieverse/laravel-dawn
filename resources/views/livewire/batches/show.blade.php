<div>
    <div class="mb-6">
        <a href="{{ route('dawn.batches') }}" wire:navigate class="text-sm text-dawn-600 dark:text-dawn-400 hover:text-dawn-700 dark:hover:text-dawn-300">
            &larr; Back to Batches
        </a>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold text-gray-900 dark:text-white break-words">{{ $batch['name'] ?? 'Batch Detail' }}</h1>
    </div>

    {{-- Progress --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-4 sm:p-6 mb-6">
        <div class="flex items-center gap-3 sm:gap-6">
            <div class="flex-1">
                <x-dawn::progress-bar :progress="$batch['progress']" />
            </div>
            <span class="text-2xl sm:text-3xl font-bold text-dawn-600 dark:text-dawn-400">{{ number_format($batch['progress'], 0) }}%</span>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-dawn::stats-card label="Total" :value="$batch['totalJobs']" type="default" />
        <x-dawn::stats-card label="Processed" :value="$batch['processedJobs']" type="success" />
        <x-dawn::stats-card label="Pending" :value="$batch['pendingJobs']" type="warning" />
        <x-dawn::stats-card label="Failed" :value="$batch['failedJobs']" type="danger" />
    </div>

    {{-- Details --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-gray-200 dark:bg-gray-700">
            <div class="bg-white dark:bg-gray-800 p-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono break-all">{{ $batch['id'] }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($batch['createdAt'])->toDateTimeString() }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Finished</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $batch['finishedAt'] ? \Carbon\Carbon::parse($batch['finishedAt'])->toDateTimeString() : 'In progress' }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Cancelled</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $batch['cancelledAt'] ? \Carbon\Carbon::parse($batch['cancelledAt'])->toDateTimeString() : 'No' }}</dd>
            </div>
        </div>
    </div>
</div>
