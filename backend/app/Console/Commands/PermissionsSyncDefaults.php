<?php

namespace App\Console\Commands;

use App\Core\Rights\DefaultsAddMissing;
use App\Support\RightsDefaults;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync-defaults {--check : Only list what is missing in the tables (standard roles and functions, and what --add-missing would add), change nothing (exit code 2 when there is anything)} {--add-missing : Add only what is new in config/permissions.php, overwrite nothing (safe for every deployment)}')]
#[Description('Write the standard roles and rights from config/permissions.php into the tables (without options: reset them; --add-missing: only add what is new)')]
class PermissionsSyncDefaults extends Command
{
    /**
     * Exit code of --check when something is missing. Every other failure (an exception, no database) ends with the usual
     * code 1, so a caller (deploy/deploy.sh) can tell "something is missing" from "the check could not run".
     */
    public const EXIT_MISSING = 2;

    /**
     * Without an option: restores the standard rows (RESETS what was changed in the settings; roles and functions that are not
     * in the config stay). --add-missing only adds what is new and overwrites nothing. --check writes nothing.
     */
    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->check();
        }

        if ($this->option('add-missing')) {
            return $this->addMissing();
        }

        RightsDefaults::apply();

        $this->info('Standard rights written.');

        return self::SUCCESS;
    }

    private function addMissing(): int
    {
        $added = DefaultsAddMissing::apply();

        if ($added === []) {
            $this->components->info(__('console.permissions_add.nothing'));
        } else {
            foreach ($added as $entry) {
                $this->line("  + $entry");
            }

            $this->components->info(__('console.permissions_add.added', ['count' => count($added)]));
        }

        $extra = DefaultsAddMissing::notInTheFile();

        if ($extra !== []) {
            $this->line(__('console.permissions_add.extra', ['entries' => implode(', ', $extra)]));
        }

        return self::SUCCESS;
    }

    private function check(): int
    {
        $missing = array_values(array_unique([...RightsDefaults::missing(), ...DefaultsAddMissing::plan()]));

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
