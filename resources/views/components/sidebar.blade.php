@php
    $dawnPath = config('dawn.path', 'dawn');
@endphp

<aside
    class="fixed inset-y-0 left-0 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col z-40 transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0"
    :class="sidebarOpen && 'translate-x-0'"
>
    {{-- Logo --}}
    <div class="p-4 flex items-center space-x-3 border-b border-gray-200 dark:border-gray-700">
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-dawn-400 to-dawn-600 flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
            </svg>
        </div>
        <span class="text-lg font-bold text-gray-900 dark:text-white">Dawn</span>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto p-3 space-y-1" @click="if ($event.target.closest('a')) sidebarOpen = false">
        <p class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Overview</p>
        <x-dawn::sidebar-link href="{{ route('dawn.dashboard') }}" icon="dashboard" label="Dashboard" :active="request()->routeIs('dawn.dashboard')" />
        <x-dawn::sidebar-link href="{{ route('dawn.monitoring') }}" icon="eye" label="Monitoring" :active="request()->routeIs('dawn.monitoring*')" />

        <p class="px-3 pt-4 pb-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Metrics</p>
        <x-dawn::sidebar-link href="{{ route('dawn.metrics.jobs') }}" icon="chart" label="Jobs" :active="request()->routeIs('dawn.metrics.jobs')" />
        <x-dawn::sidebar-link href="{{ route('dawn.metrics.queues') }}" icon="chart" label="Queues" :active="request()->routeIs('dawn.metrics.queues')" />
        <x-dawn::sidebar-link href="{{ route('dawn.performance') }}" icon="chart" label="Performance" :active="request()->routeIs('dawn.performance')" />

        <p class="px-3 pt-4 pb-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Jobs</p>
        <x-dawn::sidebar-link href="{{ route('dawn.jobs', ['type' => 'pending']) }}" icon="clock" label="Pending" :active="request()->routeIs('dawn.jobs') && in_array(request()->route('type', 'pending'), ['pending', null])" />
        <x-dawn::sidebar-link href="{{ route('dawn.jobs', ['type' => 'processing']) }}" icon="refresh" label="Processing" :active="request()->routeIs('dawn.jobs') && request()->route('type') === 'processing'" />
        <x-dawn::sidebar-link href="{{ route('dawn.jobs', ['type' => 'completed']) }}" icon="check" label="Completed" :active="request()->routeIs('dawn.jobs') && request()->route('type') === 'completed'" />
        <x-dawn::sidebar-link href="{{ route('dawn.jobs', ['type' => 'failed']) }}" icon="x" label="Failed" :active="request()->routeIs('dawn.jobs') && request()->route('type') === 'failed' || request()->routeIs('dawn.failed*')" />
        <x-dawn::sidebar-link href="{{ route('dawn.jobs', ['type' => 'silenced']) }}" icon="mute" label="Silenced" :active="request()->routeIs('dawn.jobs') && request()->route('type') === 'silenced'" />

        <p class="px-3 pt-4 pb-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Batches</p>
        <x-dawn::sidebar-link href="{{ route('dawn.batches') }}" icon="layers" label="Batches" :active="request()->routeIs('dawn.batches*')" />
    </nav>

    {{-- Dark Mode Toggle --}}
    <div class="p-3 border-t border-gray-200 dark:border-gray-700">
        <button
            x-on:click="darkMode = !darkMode; localStorage.setItem('dawn-dark-mode', darkMode)"
            class="w-full flex items-center px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
        >
            {{-- Sun icon (shown in dark mode) --}}
            <svg x-show="darkMode" class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
            </svg>
            {{-- Moon icon (shown in light mode) --}}
            <svg x-show="!darkMode" class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
            </svg>
            <span x-text="darkMode ? 'Light Mode' : 'Dark Mode'"></span>
        </button>
    </div>
</aside>
