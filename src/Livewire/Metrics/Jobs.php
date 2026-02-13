<?php

namespace Dawn\Livewire\Metrics;

use Dawn\Contracts\MetricsRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
#[Title('Job Metrics')]
class Jobs extends Component
{
    use FormatsValues;

    public function render()
    {
        $metricsRepo = app(MetricsRepository::class);
        $measured = $metricsRepo->measuredJobs();
        $result = [];

        foreach ($measured as $class) {
            $result[] = $metricsRepo->getJobMetrics($class);
        }

        usort($result, fn ($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        return view('dawn::livewire.metrics.jobs', [
            'metrics' => $result,
        ]);
    }
}
