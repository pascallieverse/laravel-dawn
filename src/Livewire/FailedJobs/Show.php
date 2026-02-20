<?php

namespace Dawn\Livewire\FailedJobs;

use Dawn\Contracts\JobRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Dawn\Livewire\Concerns\ParsesException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
class Show extends Component
{
    use FormatsValues;
    use ParsesException;

    public string $jobId;

    public function mount(string $id): void
    {
        $this->jobId = $id;
    }

    public function retry(): void
    {
        $newId = app(JobRepository::class)->retry($this->jobId);

        if ($newId) {
            $this->redirect(route('dawn.jobs.show', $newId), navigate: true);
        }
    }

    public function delete(): void
    {
        app(JobRepository::class)->deleteFailed($this->jobId);

        $this->redirect(route('dawn.failed'), navigate: true);
    }

    public function render()
    {
        $repo = app(JobRepository::class);
        $job = $repo->findFailed($this->jobId);

        abort_unless($job, 404);

        // Merge status from dawn:job:{id} to show retried badge
        $jobStatus = $repo->find($this->jobId);
        if ($jobStatus) {
            $job['status'] = $jobStatus['status'] ?? 'failed';
        }

        $exception = null;
        $frames = [];

        if (! empty($job['exception'])) {
            $exception = $this->parseException($job['exception']);
            if ($exception['file'] && $exception['line']) {
                $exception['snippet'] = $this->readSnippet($exception['file'], $exception['line']);
                $exception['shortFile'] = $this->shortenPath($exception['file']);
            }
        }

        if (! empty($job['trace'])) {
            $frames = $this->parseTrace($job['trace']);
            foreach ($frames as &$frame) {
                if (! $frame['isVendor'] && $frame['file'] && $frame['line']) {
                    $frame['snippet'] = $this->readSnippet($frame['file'], $frame['line'], 5);
                    $frame['shortFile'] = $this->shortenPath($frame['file']);
                    break;
                }
            }
            unset($frame);
        }

        $logs = $repo->getJobLogs($this->jobId)
            ?: $this->getLogsAroundTimestamp($job['failed_at'] ?? null);

        return view('dawn::livewire.failed-jobs.show', [
            'job' => $job,
            'exception' => $exception,
            'frames' => $frames,
            'logs' => $logs,
        ])->title('Failed: ' . ($job['class'] ?? $this->jobId));
    }
}
