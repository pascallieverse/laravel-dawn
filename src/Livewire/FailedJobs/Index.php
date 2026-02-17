<?php

namespace Dawn\Livewire\FailedJobs;

use Dawn\Contracts\JobRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
#[Title('Failed Jobs')]
class Index extends Component
{
    use FormatsValues;

    public int $page = 1;

    public int $perPage = 50;

    public function retry(string $id): void
    {
        $newId = app(JobRepository::class)->retry($id);

        if ($newId) {
            $this->redirect(route('dawn.jobs.show', $newId), navigate: true);
        }
    }

    public function retryAll(): void
    {
        app(JobRepository::class)->retryAll();
    }

    public function delete(string $id): void
    {
        app(JobRepository::class)->deleteFailed($id);
    }

    public function deleteAll(): void
    {
        $jobRepo = app(JobRepository::class);
        $jobs = $jobRepo->getFailed(0, 500);

        foreach ($jobs as $job) {
            $jobRepo->deleteFailed($job['id'] ?? '');
        }
    }

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
        $jobRepo = app(JobRepository::class);
        $offset = ($this->page - 1) * $this->perPage;
        $total = $jobRepo->countFailed();
        $totalPages = max(1, (int) ceil($total / $this->perPage));

        if ($this->page > $totalPages) {
            $this->page = $totalPages;
        }

        $jobs = $jobRepo->getFailed($offset, $this->perPage);

        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + count($jobs), $total);

        return view('dawn::livewire.failed-jobs.index', [
            'jobs' => $jobs,
            'total' => $total,
            'totalPages' => $totalPages,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
