<div>
    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mb-6">Performance</h1>

    {{-- Aggregate Throughput --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-4 sm:p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Throughput (Jobs / Snapshot)</h2>

        <div
            wire:ignore
            x-data="dawnThroughputChart(@js($aggregateThroughput))"
            x-init="initChart()"
            class="h-64 sm:h-80"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>

    {{-- Aggregate Runtime --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-4 sm:p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Average Runtime (ms)</h2>

        <div
            wire:ignore
            x-data="dawnRuntimeChart(@js($aggregateRuntime))"
            x-init="initChart()"
            class="h-64 sm:h-80"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>

    {{-- Per-Queue Throughput --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-4 sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Throughput by Queue</h2>

        <div
            wire:ignore
            x-data="dawnPerQueueChart(@js($perQueueThroughput))"
            x-init="initChart()"
            class="h-64 sm:h-80"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>
</div>

@script
<script>
const dawnChartColors = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

function dawnGridColor() {
    return document.documentElement.classList.contains('dark') ? '#374151' : '#e5e7eb';
}
function dawnTickColor() {
    return document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280';
}
function formatTimestamp(ts) {
    const d = new Date((ts || 0) * 1000);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

Alpine.data('dawnThroughputChart', (snapshots) => ({
    chart: null,
    initChart() {
        const ctx = this.$refs.canvas.getContext('2d');
        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: snapshots.map(s => formatTimestamp(s.timestamp)),
                datasets: [{
                    label: 'Throughput',
                    data: snapshots.map(s => s.count),
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
                        grid: { color: dawnGridColor() },
                        ticks: { color: dawnTickColor() },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: dawnGridColor() },
                        ticks: { color: dawnTickColor() },
                    },
                },
            },
        });
    }
}));

Alpine.data('dawnRuntimeChart', (snapshots) => ({
    chart: null,
    initChart() {
        const ctx = this.$refs.canvas.getContext('2d');
        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: snapshots.map(s => formatTimestamp(s.timestamp)),
                datasets: [{
                    label: 'Avg Runtime (ms)',
                    data: snapshots.map(s => s.avg_runtime),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.12)',
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
                        grid: { color: dawnGridColor() },
                        ticks: { color: dawnTickColor() },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: dawnGridColor() },
                        ticks: { color: dawnTickColor() },
                    },
                },
            },
        });
    }
}));

Alpine.data('dawnPerQueueChart', (queueData) => ({
    chart: null,
    initChart() {
        const ctx = this.$refs.canvas.getContext('2d');
        const queues = Object.keys(queueData);

        // Collect all unique timestamps across queues for labels
        const allTimestamps = new Set();
        queues.forEach(q => queueData[q].forEach(s => allTimestamps.add(s.timestamp)));
        const sortedTimestamps = [...allTimestamps].sort((a, b) => a - b);
        const labels = sortedTimestamps.map(ts => formatTimestamp(ts));

        // Build one dataset per queue
        const datasets = queues.map((queue, i) => {
            const byTs = {};
            queueData[queue].forEach(s => byTs[s.timestamp] = s.count);
            return {
                label: queue,
                data: sortedTimestamps.map(ts => byTs[ts] ?? 0),
                borderColor: dawnChartColors[i % dawnChartColors.length],
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.4,
                pointRadius: 0,
                pointHitRadius: 10,
            };
        });

        this.chart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: {
                        display: queues.length > 0,
                        labels: {
                            color: dawnTickColor(),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { color: dawnGridColor() },
                        ticks: { color: dawnTickColor() },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: dawnGridColor() },
                        ticks: { color: dawnTickColor() },
                    },
                },
            },
        });
    }
}));
</script>
@endscript
