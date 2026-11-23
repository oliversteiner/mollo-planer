<?php

namespace App\Console\Commands;

use App\Support\RightsDefaults;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync-defaults {--check : Only list standard roles and functions that are missing in the tables, change nothing (exit code 2 when there are any)}')]
#[Description('Write the standard roles and rights from config/permissions.php back into the tables')]
class PermissionsSyncDefaults extends Command
{
    /**
     * Exit code of --check when something is missing. Every other failure (an exception, no database) ends with the usual
     * code 1, so a caller (deploy/deploy.sh) can tell "something is missing" from "the check could not run".
     */
    public const EXIT_MISSING = 2;

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

        return self::EXIT_MISSING;
    }
}
