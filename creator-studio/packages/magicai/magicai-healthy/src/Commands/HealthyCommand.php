<?php

namespace MagicAI\Healthy\Commands;

use Illuminate\Console\Command;

class HealthyCommand extends Command
{
    public $signature = 'magicai-updater';

    public $description = 'My command';

    public function handle(): int
    {

        $this->comment('All done');

        return self::SUCCESS;
    }
}
