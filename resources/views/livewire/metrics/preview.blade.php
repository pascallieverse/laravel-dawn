<div wire:poll.10s>
    <div class="mb-6">
        <a href="{{ route($type === 'jobs' ? 'dawn.metrics.jobs' : 'dawn.metrics.queues') }}" wire:navigate class="text-sm text-dawn-600 dark:text-dawn-400 hover:text-dawn-700 dark:hover:text-dawn-300">
            &larr; Back to {{ $type === 'jobs' ? 'Job' : 'Queue' }} Metrics
        </a>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold text-gray-900 dark:text-white break-all">{{ $decodedName }}</h1>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <x-dawn::stats-card label="Jobs Processed" :value="$metrics['count'] ?? 0" type="default" />
        <x-dawn::stats-card label="Average Runtime" :value="$this->formatRuntime($metrics['avg_runtime'] ?? 0)" type="warning" />
        <x-dawn::stats-card label="Total Runtime" :value="$this->formatRuntime($metrics['total_runtime'] ?? 0)" type="default" />
    </div>

    {{-- Chart --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-4 sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Throughput</h2>

        <div
            wire:ignore
            x-data="dawnChart(@js($snapshots))"
            x-init="initChart()"
            class="h-64"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>
</div>

@script
<script>
Alpine.data('dawnChart', (snapshots) => ({
    chart: null,
    initChart() {
        const ctx = this.$refs.canvas.getContext('2d');
        const isDark = document.documentElement.classList.contains('dark');
        const labels = snapshots.map(s => {
            const d = new Date((s.timestamp || 0) * 1000);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        });
        const data = snapshots.map(s => parseInt(s.count) || 0);

        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Throughput',
                    data: data,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.12)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHitRadius: 10,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { color: isDark ? '#374151' : '#e5e7eb' },
                        ticks: { color: isDark ? '#9ca3af' : '#6b7280' },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: isDark ? '#374151' : '#e5e7eb' },
                        ticks: { color: isDark ? '#9ca3af' : '#6b7280' },
                    },
                },
            },
        });
    }
}));
</script>
@endscript
