<?php

namespace Dawn\Livewire\Batches;

use Illuminate\Bus\BatchRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
#[Title('Batches')]
class Index extends Component
{
    public function render()
    {
        $batches = [];

        try {
            if (app()->bound(BatchRepository::class)) {
                $batches = collect(app(BatchRepository::class)->get(50, null))
                    ->map(fn ($batch) => [
                        'id' => $batch->id,
                        'name' => $batch->name,
                        'totalJobs' => $batch->totalJobs,
                        'pendingJobs' => $batch->pendingJobs,
                        'processedJobs' => $batch->processedJobs(),
                        'progress' => $batch->progress(),
                        'failedJobs' => $batch->failedJobs,
                        'createdAt' => $batch->createdAt->toIso8601String(),
                        'cancelledAt' => $batch->cancelledAt?->toIso8601String(),
                        'finishedAt' => $batch->finishedAt?->toIso8601String(),
                    ])->all();
            }
        } catch (\Throwable) {
            // Table may not exist if batches migration hasn't been run
        }

        return view('dawn::livewire.batches.index', [
            'batches' => $batches,
        ]);
    }
}
