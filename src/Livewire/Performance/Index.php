<?php

namespace Dawn\Livewire\Performance;

use Dawn\Contracts\MetricsRepository;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
class Index extends Component
{
    public function render()
    {
        $metricsRepo = app(MetricsRepository::class);

        // Per-minute throughput from dawn:throughput:{minute} keys (last 60 min)
        $throughputByQueue = $metricsRepo->getRecentThroughput(60);

        // Group per-minute data into 1-hour blocks
        $hourlyByQueue = [];
        foreach ($throughputByQueue as $queue => $points) {
            foreach ($points as $point) {
                $hourTs = (int) (floor($point['timestamp'] / 3600) * 3600);
                if (! isset($hourlyByQueue[$queue][$hourTs])) {
                    $hourlyByQueue[$queue][$hourTs] = ['count' => 0, 'runtime' => 0];
                }
                $hourlyByQueue[$queue][$hourTs]['count'] += $point['count'];
                $hourlyByQueue[$queue][$hourTs]['runtime'] += $point['runtime'];
            }
        }

        // Aggregate across all queues by hour
        $aggregateByHour = [];
        foreach ($hourlyByQueue as $queue => $hours) {
            foreach ($hours as $hourTs => $data) {
                if (! isset($aggregateByHour[$hourTs])) {
                    $aggregateByHour[$hourTs] = ['count' => 0, 'runtime' => 0];
                }
                $aggregateByHour[$hourTs]['count'] += $data['count'];
                $aggregateByHour[$hourTs]['runtime'] += $data['runtime'];
            }
        }
        ksort($aggregateByHour);

        // Build aggregate throughput: [{timestamp, count}]
        $aggregateThroughput = [];
        foreach ($aggregateByHour as $ts => $data) {
            $aggregateThroughput[] = ['timestamp' => $ts, 'count' => $data['count']];
        }

        // Build aggregate runtime: [{timestamp, avg_runtime}]
        $aggregateRuntime = [];
        foreach ($aggregateByHour as $ts => $data) {
            $aggregateRuntime[] = [
                'timestamp' => $ts,
                'avg_runtime' => $data['count'] > 0
                    ? round($data['runtime'] / $data['count'])
                    : 0,
            ];
        }

        // Per-queue throughput (hourly): {queue: [{timestamp, count}]}
        $perQueueThroughput = [];
        foreach ($hourlyByQueue as $queue => $hours) {
            ksort($hours);
            $perQueueThroughput[$queue] = [];
            foreach ($hours as $ts => $data) {
                $perQueueThroughput[$queue][] = ['timestamp' => $ts, 'count' => $data['count']];
            }
        }

        return view('dawn::livewire.performance.index', [
            'aggregateThroughput' => $aggregateThroughput,
            'aggregateRuntime' => $aggregateRuntime,
            'perQueueThroughput' => $perQueueThroughput,
        ])->title('Performance');
    }
}
