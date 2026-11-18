<?php

namespace App\Console\Commands;

use App\Support\RightsDefaults;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync-defaults')]
#[Description('Write the standard roles and rights from config/permissions.php back into the tables')]
class PermissionsSyncDefaults extends Command
{
    /**
     * Restores the standard rows. Roles and functions that are not in the
     * config stay as they are.
     */
    public function handle(): int
    {
        RightsDefaults::apply();

        $this->info('Standard rights written.');

        return self::SUCCESS;
    }
}
