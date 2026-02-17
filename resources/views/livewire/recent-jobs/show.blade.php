<div wire:poll.3s>
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6 sm:mb-8">
        <div class="min-w-0">
            <a href="{{ route('dawn.jobs') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-dawn-600 dark:hover:text-dawn-400 transition-colors group">
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to Jobs
            </a>
            <h1 class="mt-2 text-xl sm:text-2xl font-bold text-gray-900 dark:text-white truncate">
                {{ $job['class'] ?? $job['name'] ?? 'Job Detail' }}
            </h1>
        </div>
        @if(($job['status'] ?? '') === 'failed')
            <button
                wire:click="retry"
                class="self-start shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-dawn-500 hover:bg-dawn-600 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-dawn-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                </svg>
                Retry Job
            </button>
        @endif
    </div>

    {{-- Job Info Card --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700/50">
            {{-- Status --}}
            <div class="px-4 py-4 sm:px-5 sm:py-5">
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Status</dt>
                <dd class="mt-2"><x-dawn::job-status-badge :status="$job['status'] ?? 'unknown'" /></dd>
            </div>
            {{-- Queue --}}
            <div class="px-4 py-4 sm:px-5 sm:py-5">
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Queue</dt>
                <dd class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $job['queue'] ?? '-' }}</dd>
            </div>
            {{-- Timing --}}
            <div class="px-4 py-4 sm:px-5 sm:py-5">
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                    @if(($job['status'] ?? '') === 'failed') Failed At
                    @elseif(($job['status'] ?? '') === 'reserved') Elapsed
                    @else Runtime
                    @endif
                </dt>
                <dd class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                    @if(($job['status'] ?? '') === 'failed' && isset($job['failed_at']))
                        {{ $this->formatDate($job['failed_at']) }}
                    @elseif(($job['status'] ?? '') === 'reserved' && isset($job['reserved_at']))
                        <span x-data="dawnElapsed({{ (float) $job['reserved_at'] }})" x-text="display"></span>
                    @elseif(isset($job['runtime']))
                        {{ $this->formatRuntime($job['runtime'] * 1000) }}
                    @else —
                    @endif
                </dd>
            </div>
            {{-- Attempts --}}
            <div class="px-4 py-4 sm:px-5 sm:py-5">
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Attempts</dt>
                <dd class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $job['attempts'] ?? 0 }}</dd>
            </div>
        </div>

        {{-- Extended info row --}}
        <div class="border-t border-gray-100 dark:border-gray-700/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700/50">
                <div class="px-4 py-4 sm:px-5 sm:py-5">
                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Job Name</dt>
                    <dd class="mt-2 text-sm text-gray-900 dark:text-white break-all">{{ $job['class'] ?? $job['name'] ?? '-' }}</dd>
                </div>
                <div class="px-4 py-4 sm:px-5 sm:py-5">
                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">ID</dt>
                    <dd class="mt-2 text-xs text-gray-900 dark:text-white font-mono break-all">{{ $job['id'] ?? '-' }}</dd>
                </div>
            </div>
        </div>

        @if(!empty($job['uuid']))
            <div class="border-t border-gray-100 dark:border-gray-700/50 px-4 py-4 sm:px-5 sm:py-5">
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">UUID</dt>
                <dd class="mt-2 text-xs text-gray-900 dark:text-white font-mono break-all">{{ $job['uuid'] }}</dd>
            </div>
        @endif
    </div>

    {{-- Tags --}}
    @if(!empty($job['tags']))
        <div class="mt-5 sm:mt-6">
            <h3 class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2.5">Tags</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($job['tags'] as $tag)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 ring-1 ring-inset ring-gray-200 dark:ring-gray-600">
                        {{ $tag }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tabbed Exception / Logs --}}
    <div x-data="{ tab: '{{ ($exception || !empty($frames)) ? 'exception' : 'logs' }}' }" class="mt-6 sm:mt-8">
        {{-- Tab buttons --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex gap-4 sm:gap-6 -mb-px overflow-x-auto" aria-label="Tabs">
                @if($exception || !empty($frames))
                    <button @click="tab = 'exception'"
                            :class="tab === 'exception' ? 'border-red-500 text-red-600 dark:text-red-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                        Exception
                    </button>
                @endif
                <button @click="tab = 'logs'"
                        :class="tab === 'logs' ? 'border-dawn-500 text-dawn-600 dark:text-dawn-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    Laravel Log
                    @if(!empty($logs))
                        <span class="ml-1.5 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">{{ count($logs) }}</span>
                    @endif
                </button>
            </nav>
        </div>

            {{-- Exception Tab --}}
            <div x-show="tab === 'exception'" x-cloak class="mt-5 sm:mt-6">
                @if($exception)
                    {{-- Exception header card --}}
                    <div class="mb-5 sm:mb-6 bg-red-50 dark:bg-red-500/5 border border-red-200 dark:border-red-500/20 rounded-xl p-4 sm:p-5">
                        @if($exception['class'])
                            <h2 class="text-lg sm:text-xl font-semibold text-red-600 dark:text-red-400 break-words">{{ $exception['class'] }}</h2>
                        @endif
                        <p class="mt-2 text-sm sm:text-base font-light text-gray-800 dark:text-gray-300 break-words">{{ $exception['message'] }}</p>
                        @if($exception['file'])
                            <p class="mt-3 font-mono text-xs sm:text-sm text-gray-500 dark:text-gray-400 break-all">
                                {{ $exception['shortFile'] ?? $this->shortenPath($exception['file']) }}<span class="text-red-500 dark:text-red-400">:{{ $exception['line'] }}</span>
                            </p>
                        @endif
                    </div>

                    {{-- Code snippet at exception location --}}
                    @if(!empty($exception['snippet']))
                        <div class="mb-5 sm:mb-6 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="bg-gray-900 overflow-x-auto">
                                <div class="min-w-full w-fit">
                                @foreach($exception['snippet'] as $num => $code)
                                    <div class="flex font-mono text-xs leading-6 {{ $num === $exception['line'] ? 'bg-red-500/20' : ($loop->even ? 'bg-gray-900' : 'bg-gray-800/80') }}">
                                        <span class="select-none w-10 sm:w-14 shrink-0 text-right pr-3 sm:pr-4 {{ $num === $exception['line'] ? 'text-red-400 font-bold' : 'text-gray-600' }}">{{ $num }}</span>
                                        <span class="pr-4 {{ $num === $exception['line'] ? 'text-red-200' : 'text-gray-300' }} whitespace-pre">{{ $code }}</span>
                                    </div>
                                @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Exception Trace --}}
                @if(!empty($frames))
                    <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl p-2 sm:p-3">
                        {{-- Trace header --}}
                        <div class="flex items-center gap-3 p-2 mb-2">
                            <div class="flex items-center justify-center w-6 h-6 bg-red-100 dark:bg-red-500/20 border border-red-200 dark:border-red-500/30 rounded-md">
                                <svg class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Exception trace</h3>
                        </div>

                        {{-- Frames --}}
                        <div class="flex flex-col gap-1.5">
                            @php
                                $vendorGroup = [];
                                $groupedFrames = [];
                                foreach ($frames as $frame) {
                                    if ($frame['isVendor']) {
                                        $vendorGroup[] = $frame;
                                    } else {
                                        if (!empty($vendorGroup)) {
                                            $groupedFrames[] = ['type' => 'vendor', 'frames' => $vendorGroup];
                                            $vendorGroup = [];
                                        }
                                        $groupedFrames[] = ['type' => 'app', 'frame' => $frame];
                                    }
                                }
                                if (!empty($vendorGroup)) {
                                    $groupedFrames[] = ['type' => 'vendor', 'frames' => $vendorGroup];
                                }
                            @endphp

                            @foreach($groupedFrames as $gi => $group)
                                @if($group['type'] === 'app')
                                    @php $frame = $group['frame']; @endphp
                                    <div x-data="{ open: {{ $gi === 0 ? 'true' : 'false' }} }"
                                         class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                                        {{-- Frame header --}}
                                        <div class="flex items-start sm:items-center gap-2 sm:gap-3 bg-white dark:bg-gray-800 pr-3 pl-3 sm:pl-4 py-2.5 sm:py-0 sm:h-11 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                             @click="open = !open">
                                            <div class="shrink-0 w-2 h-2 rounded-full mt-1.5 sm:mt-0" :class="open ? 'bg-red-500' : 'bg-red-200 dark:bg-red-800'"></div>
                                            <div class="flex-1 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-4 min-w-0 overflow-hidden">
                                                <span class="font-mono text-xs text-gray-900 dark:text-gray-200 truncate">{{ $frame['call'] }}</span>
                                                @if($frame['file'])
                                                    <span class="font-mono text-xs text-gray-400 dark:text-gray-500 truncate sm:shrink-0 sm:text-right">
                                                        {{ $this->shortenPath($frame['file']) }}:{{ $frame['line'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <svg class="w-4 h-4 shrink-0 mt-0.5 sm:mt-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" :style="open ? 'color: var(--dawn-500, #f59e0b)' : 'color: #9ca3af'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                        </div>

                                        {{-- Code snippet --}}
                                        @if(!empty($frame['snippet']))
                                            <div x-show="open" x-collapse class="border-t border-gray-200 dark:border-gray-700 bg-gray-900 overflow-x-auto">
                                                <div class="min-w-full w-fit">
                                                @foreach($frame['snippet'] as $num => $code)
                                                    <div class="flex font-mono text-xs leading-6 {{ $num === $frame['line'] ? 'bg-red-500/20' : ($loop->even ? 'bg-gray-900' : 'bg-gray-800/80') }}">
                                                        <span class="select-none w-10 sm:w-14 shrink-0 text-right pr-3 sm:pr-4 {{ $num === $frame['line'] ? 'text-red-400 font-bold' : 'text-gray-600' }}">{{ $num }}</span>
                                                        <span class="pr-4 {{ $num === $frame['line'] ? 'text-red-200' : 'text-gray-300' }} whitespace-pre">{{ $code }}</span>
                                                    </div>
                                                @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    {{-- Vendor frames group --}}
                                    <div x-data="{ open: false }" class="rounded-lg overflow-hidden"
                                         :class="open ? 'border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm' : 'border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50'">
                                        <div class="flex items-center gap-3 h-11 pr-3 pl-3 sm:pl-4 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                             @click="open = !open">
                                            <svg class="w-4 h-4 shrink-0" :style="open ? 'color: var(--dawn-500, #f59e0b)' : 'color: #9ca3af'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                                            </svg>
                                            <span class="flex-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                                                {{ count($group['frames']) }} vendor {{ count($group['frames']) === 1 ? 'frame' : 'frames' }}
                                            </span>
                                            <svg class="w-4 h-4 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" style="color: #9ca3af" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                        </div>
                                        <div x-show="open" x-collapse class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach($group['frames'] as $vf)
                                                <div class="flex items-start sm:items-center gap-2 sm:gap-3 pr-3 pl-6 sm:pl-8 py-2.5 sm:py-0 sm:h-10 overflow-hidden">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600 shrink-0 mt-1.5 sm:mt-0"></div>
                                                    <div class="flex-1 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-4 min-w-0 overflow-hidden">
                                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400 truncate">{{ $vf['call'] }}</span>
                                                        @if($vf['file'])
                                                            <span class="font-mono text-xs text-gray-400 dark:text-gray-500 truncate sm:shrink-0">
                                                                {{ $this->shortenPath($vf['file']) }}:{{ $vf['line'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Logs Tab --}}
            <div x-show="tab === 'logs'" x-cloak class="mt-5 sm:mt-6">
                @if(!empty($logs))
                    <div class="space-y-3">
                        @foreach($logs as $entry)
                            @php
                                $isError = str_contains($entry['text'], '.ERROR:') || str_contains($entry['text'], '.CRITICAL:') || str_contains($entry['text'], '.EMERGENCY:');
                                $isWarning = str_contains($entry['text'], '.WARNING:');
                            @endphp
                            <div class="rounded-xl overflow-hidden border {{ $isError ? 'border-red-300 dark:border-red-500/30' : ($isWarning ? 'border-yellow-300 dark:border-yellow-500/30' : 'border-gray-200 dark:border-gray-700') }}">
                                <div class="flex items-center gap-2 px-3 py-2 {{ $isError ? 'bg-red-50 dark:bg-red-500/10' : ($isWarning ? 'bg-yellow-50 dark:bg-yellow-500/10' : 'bg-gray-50 dark:bg-gray-800') }}">
                                    @if($isError)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-red-500 text-white">ERROR</span>
                                    @elseif($isWarning)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-yellow-500 text-white">WARN</span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-gray-400 text-white">INFO</span>
                                    @endif
                                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400 truncate">{{ $entry['timestamp'] }}</span>
                                </div>
                                <div class="bg-gray-900 p-3 sm:p-4 overflow-x-auto">
                                    <pre class="text-xs leading-5 text-gray-300 font-mono whitespace-pre-wrap break-words">{{ trim($entry['text']) }}</pre>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-6 py-8 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No log entries found around the time this job ran.</p>
                    </div>
                @endif
            </div>
        </div>
</div>
