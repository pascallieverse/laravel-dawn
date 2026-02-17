@php
    // Read dark mode preference from cookie (set by JavaScript, not encrypted)
    $isDark = ($_COOKIE['dawn_dark_mode'] ?? '') === '1';
@endphp
<!DOCTYPE html>
<html lang="en" class="{{ $isDark ? 'dark' : '' }}"
      x-data="{ darkMode: {{ $isDark ? 'true' : 'false' }} }"
      x-init="$watch('darkMode', val => {
          document.cookie = 'dawn_dark_mode=' + (val ? '1' : '0') + '; path=/; max-age=31536000; SameSite=Lax';
          document.documentElement.classList.toggle('dark', val);
      })"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ?? 'Dawn' }} - {{ config('app.name', 'Laravel') }}</title>

    {{-- Migrate localStorage users to cookie on first visit --}}
    <script data-navigate-once>
        if (!document.cookie.includes('dawn_dark_mode') && localStorage.getItem('dawn-dark-mode') === 'true') {
            document.cookie = 'dawn_dark_mode=1; path=/; max-age=31536000; SameSite=Lax';
            document.documentElement.classList.add('dark');
            location.reload();
        }
    </script>

    <link rel="stylesheet" href="{{ asset('vendor/dawn/dawn.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dawn: {
                            50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d',
                            400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309',
                            800: '#92400e', 900: '#78350f',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

    @livewireStyles
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased">
    <div class="min-h-screen flex" x-data="{ sidebarOpen: false }" x-init="document.addEventListener('livewire:navigating', () => sidebarOpen = false)">
        {{-- Mobile backdrop --}}
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition-opacity duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-900/50 z-30 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <x-dawn::sidebar />

        {{-- Mobile hamburger --}}
        <button
            @click="sidebarOpen = true"
            class="fixed top-4 left-4 z-20 p-2 rounded-md bg-white dark:bg-gray-800 shadow-md border border-gray-200 dark:border-gray-700 lg:hidden"
        >
            <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <main class="flex-1 min-w-0 overflow-x-hidden ml-0 lg:ml-64 p-4 sm:p-6 pt-16 lg:pt-6">
            {{ $slot }}
        </main>
    </div>

    <script data-navigate-once>
        function dawnFormatMs(ms) {
            if (ms < 1000) return Math.max(0, Math.round(ms)) + 'ms';
            var s = ms / 1000;
            if (s < 60) return s.toFixed(1) + 's';
            var m = Math.floor(s / 60);
            var sec = Math.floor(s % 60);
            if (m < 60) return m + 'm ' + sec + 's';
            var h = Math.floor(m / 60);
            m = m % 60;
            return h + 'h ' + m + 'm';
        }

        document.addEventListener('alpine:init', function () {
            Alpine.data('dawnCountdown', function (targetTimestamp) {
                return {
                    display: '',
                    interval: null,
                    init() {
                        this.update();
                        this.interval = setInterval(() => this.update(), 1000);
                    },
                    update() {
                        var remaining = (targetTimestamp - Date.now() / 1000) * 1000;
                        this.display = remaining > 0 ? dawnFormatMs(remaining) : 'ready';
                    },
                    destroy() {
                        if (this.interval) clearInterval(this.interval);
                    }
                };
            });

            Alpine.data('dawnElapsed', function (startTimestamp) {
                return {
                    display: '',
                    interval: null,
                    init() {
                        this.update();
                        this.interval = setInterval(() => this.update(), 1000);
                    },
                    update() {
                        var elapsed = (Date.now() / 1000 - startTimestamp) * 1000;
                        this.display = dawnFormatMs(Math.max(0, elapsed));
                    },
                    destroy() {
                        if (this.interval) clearInterval(this.interval);
                    }
                };
            });
        });
    </script>

    @livewireScripts
</body>
</html>
