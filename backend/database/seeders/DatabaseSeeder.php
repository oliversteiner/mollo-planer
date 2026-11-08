<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds nothing on purpose, so "migrate --seed" never creates a user with
 * a known password. The first admin comes from "php artisan
 * app:create-admin", demo data from DemoSeeder (see README).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('No default data. First admin: php artisan app:create-admin. Demo data: php artisan db:seed --class=DemoSeeder');
    }
}
