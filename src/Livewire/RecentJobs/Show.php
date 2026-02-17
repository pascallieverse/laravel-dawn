<?php

namespace Dawn\Livewire\RecentJobs;

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

        $this->redirect(route('dawn.jobs'), navigate: true);
    }

    public function render()
    {
        $repo = app(JobRepository::class);
        $job = $repo->find($this->jobId);

        abort_unless($job, 404);

        $exception = null;
        $frames = [];
        $logs = [];

        // For failed jobs, merge in the trace from the failed detail record
        if (($job['status'] ?? '') === 'failed') {
            $failedDetail = $repo->findFailed($this->jobId);
            if ($failedDetail) {
                $job['trace'] = $failedDetail['trace'] ?? null;
                $job['exception'] = $job['exception'] ?? $failedDetail['exception'] ?? null;
            }

            if (! empty($job['exception'])) {
                $exception = $this->parseException($job['exception']);
                if ($exception['file'] && $exception['line']) {
                    $exception['snippet'] = $this->readSnippet($exception['file'], $exception['line']);
                    $exception['shortFile'] = $this->shortenPath($exception['file']);
                }
            }

            if (! empty($job['trace'])) {
                $frames = $this->parseTrace($job['trace']);
                // Load snippet for the first app frame
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
        } else {
            $logs = $this->getLogsAroundTimestamp(
                $job['completed_at'] ?? $job['reserved_at'] ?? $job['pushed_at'] ?? null
            );
        }

        return view('dawn::livewire.recent-jobs.show', [
            'job' => $job,
            'exception' => $exception,
            'frames' => $frames,
            'logs' => $logs,
        ])->title('Job: ' . ($job['class'] ?? $this->jobId));
    }
}
