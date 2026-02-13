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

    public function mount(string $tag): void
    {
        $this->tag = $tag;
    }

    public function render()
    {
        $tagRepo = app(TagRepository::class);

        return view('dawn::livewire.monitoring.tag-jobs', [
            'jobs' => $tagRepo->taggedJobs($this->tag),
            'total' => $tagRepo->countTaggedJobs($this->tag),
        ])->title(urldecode($this->tag));
    }
}
