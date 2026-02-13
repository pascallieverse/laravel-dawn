<?php

namespace Dawn\Livewire\Batches;

use Illuminate\Bus\BatchRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
class Show extends Component
{
    use FormatsValues;

    public string $batchId;

    public function mount(string $id): void
    {
        $this->batchId = $id;
    }

    public function render()
    {
        $batch = null;

        try {
            if (app()->bound(BatchRepository::class)) {
                $raw = app(BatchRepository::class)->find($this->batchId);

                if ($raw) {
                    $batch = [
                        'id' => $raw->id,
                        'name' => $raw->name,
                        'totalJobs' => $raw->totalJobs,
                        'pendingJobs' => $raw->pendingJobs,
                        'processedJobs' => $raw->processedJobs(),
                        'progress' => $raw->progress(),
                        'failedJobs' => $raw->failedJobs,
                        'createdAt' => $raw->createdAt->toIso8601String(),
                        'cancelledAt' => $raw->cancelledAt?->toIso8601String(),
                        'finishedAt' => $raw->finishedAt?->toIso8601String(),
                    ];
                }
            }
        } catch (\Throwable) {
            // Table may not exist if batches migration hasn't been run
        }

        abort_unless($batch, 404);

        return view('dawn::livewire.batches.show', [
            'batch' => $batch,
        ])->title($batch['name'] ?? 'Batch Detail');
    }
}
