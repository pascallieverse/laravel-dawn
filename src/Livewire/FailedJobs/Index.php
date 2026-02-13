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

    public function retry(string $id): void
    {
        app(JobRepository::class)->retry($id);
    }

    public function retryAll(): void
    {
        app(JobRepository::class)->retryAll();
    }

    public function render()
    {
        $jobRepo = app(JobRepository::class);

        return view('dawn::livewire.failed-jobs.index', [
            'jobs' => $jobRepo->getFailed(),
            'total' => $jobRepo->countFailed(),
        ]);
    }
}
