<?php

namespace App\Console\Commands;

use App\Planer\TemplateSync;
use Illuminate\Console\Command;
use RuntimeException;

class SyncTemplates extends Command
{
    protected $signature = 'planer:sync-templates';

    protected $description = 'Write the built-in templates (spec/templates/*.planer.json) into the database';

    public function handle(): int
    {
        try {
            $keys = TemplateSync::sync();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('Templates synced: '.implode(', ', $keys));

        return self::SUCCESS;
    }
}
