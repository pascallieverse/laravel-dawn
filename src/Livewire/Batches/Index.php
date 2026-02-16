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
    public int $page = 1;

    public int $perPage = 50;

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render()
    {
        $batches = [];
        $total = 0;

        try {
            if (app()->bound(BatchRepository::class)) {
                $repo = app(BatchRepository::class);
                $allBatches = collect($repo->get($this->perPage * $this->page, null))
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
                    ]);

                $total = $allBatches->count();
                $offset = ($this->page - 1) * $this->perPage;
                $batches = $allBatches->slice($offset, $this->perPage)->values()->all();
            }
        } catch (\Throwable) {
            // Table may not exist if batches migration hasn't been run
        }

        $totalPages = max(1, (int) ceil($total / $this->perPage));

        if ($this->page > $totalPages) {
            $this->page = $totalPages;
        }

        $offset = ($this->page - 1) * $this->perPage;
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + count($batches), $total);

        return view('dawn::livewire.batches.index', [
            'batches' => $batches,
            'total' => $total,
            'totalPages' => $totalPages,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
