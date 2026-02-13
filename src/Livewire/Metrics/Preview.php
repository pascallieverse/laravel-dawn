<?php

namespace Dawn\Livewire\Metrics;

use Dawn\Contracts\MetricsRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
class Preview extends Component
{
    use FormatsValues;

    public string $type;
    public string $id;

    public function mount(string $type, string $id): void
    {
        $this->type = $type;
        $this->id = $id;
    }

    public function render()
    {
        $metricsRepo = app(MetricsRepository::class);
        $decoded = base64_decode($this->id);

        if ($this->type === 'jobs') {
            $metrics = $metricsRepo->getJobMetrics($decoded);
            $snapshots = $metricsRepo->snapshotsForJob($decoded);
        } else {
            $metrics = $metricsRepo->getQueueMetrics($decoded);
            $snapshots = $metricsRepo->snapshotsForQueue($decoded);
        }

        return view('dawn::livewire.metrics.preview', [
            'metrics' => $metrics,
            'snapshots' => $snapshots,
            'decodedName' => $decoded,
        ])->title($decoded);
    }
}
