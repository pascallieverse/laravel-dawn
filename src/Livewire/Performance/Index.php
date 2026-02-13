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
        $queues = $metricsRepo->measuredQueues();

        // Collect all snapshots keyed by queue.
        // Snapshots contain cumulative count/total_runtime, so we must
        // compute deltas between consecutive snapshots for throughput.
        $snapshotsByQueue = [];
        foreach ($queues as $queue) {
            $snapshotsByQueue[$queue] = $metricsRepo->snapshotsForQueue($queue);
        }

        // Convert each queue's cumulative snapshots to per-interval deltas
        $deltasByQueue = [];
        foreach ($snapshotsByQueue as $queue => $snapshots) {
            $deltas = [];
            $prev = null;
            foreach ($snapshots as $snapshot) {
                $count = (int) ($snapshot['count'] ?? 0);
                $runtime = (int) ($snapshot['total_runtime'] ?? 0);
                $ts = $snapshot['timestamp'] ?? 0;

                if ($prev !== null) {
                    $deltaCount = max(0, $count - $prev['count']);
                    $deltaRuntime = max(0, $runtime - $prev['total_runtime']);
                    $deltas[] = [
                        'timestamp' => $ts,
                        'count' => $deltaCount,
                        'total_runtime' => $deltaRuntime,
                    ];
                }

                $prev = ['count' => $count, 'total_runtime' => $runtime];
            }
            $deltasByQueue[$queue] = $deltas;
        }

        // Aggregate deltas by timestamp across all queues
        $aggregateByTimestamp = [];
        foreach ($deltasByQueue as $queue => $deltas) {
            foreach ($deltas as $delta) {
                $ts = $delta['timestamp'];
                if (! isset($aggregateByTimestamp[$ts])) {
                    $aggregateByTimestamp[$ts] = ['count' => 0, 'total_runtime' => 0];
                }
                $aggregateByTimestamp[$ts]['count'] += $delta['count'];
                $aggregateByTimestamp[$ts]['total_runtime'] += $delta['total_runtime'];
            }
        }
        ksort($aggregateByTimestamp);

        // Build aggregate throughput dataset: [{timestamp, count}]
        $aggregateThroughput = [];
        foreach ($aggregateByTimestamp as $ts => $data) {
            $aggregateThroughput[] = [
                'timestamp' => $ts,
                'count' => $data['count'],
            ];
        }

        // Build aggregate runtime dataset: [{timestamp, avg_runtime}]
        $aggregateRuntime = [];
        foreach ($aggregateByTimestamp as $ts => $data) {
            $aggregateRuntime[] = [
                'timestamp' => $ts,
                'avg_runtime' => $data['count'] > 0
                    ? round($data['total_runtime'] / $data['count'])
                    : 0,
            ];
        }

        // Build per-queue throughput from deltas: {queue: [{timestamp, count}]}
        $perQueueThroughput = [];
        foreach ($deltasByQueue as $queue => $deltas) {
            $perQueueThroughput[$queue] = array_map(fn ($d) => [
                'timestamp' => $d['timestamp'],
                'count' => $d['count'],
            ], $deltas);
        }

        return view('dawn::livewire.performance.index', [
            'aggregateThroughput' => $aggregateThroughput,
            'aggregateRuntime' => $aggregateRuntime,
            'perQueueThroughput' => $perQueueThroughput,
        ])->title('Performance');
    }
}
