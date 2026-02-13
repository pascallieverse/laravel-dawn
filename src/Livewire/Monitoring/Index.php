<?php

namespace Dawn\Livewire\Monitoring;

use Dawn\Contracts\TagRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('dawn::layouts.app')]
#[Title('Monitoring')]
class Index extends Component
{
    public string $newTag = '';

    public function addTag(): void
    {
        $this->validate([
            'newTag' => 'required|string|max:255',
        ]);

        app(TagRepository::class)->monitor(trim($this->newTag));
        $this->newTag = '';
    }

    public function removeTag(string $tag): void
    {
        app(TagRepository::class)->stopMonitoring($tag);
    }

    public function render()
    {
        return view('dawn::livewire.monitoring.index', [
            'tags' => app(TagRepository::class)->monitoring(),
        ]);
    }
}
