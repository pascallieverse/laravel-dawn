<?php

namespace Dawn\Livewire\Monitoring;

use Dawn\Contracts\TagRepository;
use Dawn\Livewire\Concerns\FormatsValues;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
class TagJobs extends Component
{
    use FormatsValues;

    public string $tag;

    public int $page = 1;

    public int $perPage = 50;

    public function mount(string $tag): void
    {
        $this->tag = $tag;
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
        $tagRepo = app(TagRepository::class);
        $offset = ($this->page - 1) * $this->perPage;
        $total = $tagRepo->countTaggedJobs($this->tag);
        $totalPages = max(1, (int) ceil($total / $this->perPage));

        if ($this->page > $totalPages) {
            $this->page = $totalPages;
        }

        $jobs = $tagRepo->taggedJobs($this->tag, $offset, $this->perPage);

        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + count($jobs), $total);

        return view('dawn::livewire.monitoring.tag-jobs', [
            'jobs' => $jobs,
            'total' => $total,
            'totalPages' => $totalPages,
            'from' => $from,
            'to' => $to,
        ])->title(urldecode($this->tag));
    }
}
