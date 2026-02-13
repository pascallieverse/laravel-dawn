<?php

namespace Dawn\Livewire\Metrics;

use Dawn\Contracts\MetricsRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
#[Title('Queue Metrics')]
class Queues extends Component
{
    use FormatsValues;

    public function render()
    {
        $metricsRepo = app(MetricsRepository::class);
        $measured = $metricsRepo->measuredQueues();
        $result = [];

        foreach ($measured as $queue) {
            $result[] = $metricsRepo->getQueueMetrics($queue);
        }

        usort($result, fn ($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        return view('dawn::livewire.metrics.queues', [
            'metrics' => $result,
        ]);
    }
}
