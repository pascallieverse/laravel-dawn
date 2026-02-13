<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    protected $signature = 'dawn:publish {--force : Overwrite any existing files}';
    protected $description = 'Publish all of the Dawn resources';

    public function handle(): int
    {
        $this->callSilent('vendor:publish', [
            '--tag' => 'dawn-config',
            '--force' => $this->option('force'),
        ]);

        $this->callSilent('vendor:publish', [
            '--tag' => 'dawn-assets',
            '--force' => $this->option('force'),
        ]);

        $this->info('Dawn resources published successfully.');

        return 0;
    }
}
