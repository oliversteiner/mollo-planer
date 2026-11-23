<?php

namespace App\Console\Commands;

use App\Support\RightsDefaults;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync-defaults {--check : Only list standard roles and functions that are missing in the tables, change nothing (exit code 1 when there are any)}')]
#[Description('Write the standard roles and rights from config/permissions.php back into the tables')]
class PermissionsSyncDefaults extends Command
{
    /**
     * Restores the standard rows. Roles and functions that are not in the
     * config stay as they are. With --check nothing is written.
     */
    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->check();
        }

        RightsDefaults::apply();

        $this->info('Standard rights written.');

        return self::SUCCESS;
    }

    private function check(): int
    {
        $missing = RightsDefaults::missing();

        if ($missing === []) {
            $this->components->info(__('console.permissions_check.complete'));

            return self::SUCCESS;
        }

        foreach ($missing as $entry) {
            $this->line("  $entry");
        }

        $this->components->error(__('console.permissions_check.missing', ['count' => count($missing)]));

        return self::FAILURE;
    }
}
