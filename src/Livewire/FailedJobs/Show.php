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
        app(JobRepository::class)->retry($this->jobId);

        $this->redirect(route('dawn.failed'), navigate: true);
    }

    public function render()
    {
        $job = app(JobRepository::class)->findFailed($this->jobId);

        abort_unless($job, 404);

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

        $logs = $this->getLogsAroundTimestamp($job['failed_at'] ?? null);

        return view('dawn::livewire.failed-jobs.show', [
            'job' => $job,
            'exception' => $exception,
            'frames' => $frames,
            'logs' => $logs,
        ])->title('Failed: ' . ($job['class'] ?? $this->jobId));
    }
}
