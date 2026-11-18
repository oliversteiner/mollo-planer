<?php

namespace Database\Seeders;

use App\Planer\TemplateSync;
use Illuminate\Database\Seeder;

/**
 * Seeds no user on purpose, so "migrate --seed" never creates one with a
 * known password. It writes the built-in templates (spec/templates), which
 * the app needs and which change no data of anybody. The first admin comes
 * from "php artisan app:create-admin", demo data from DemoSeeder (see README).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        TemplateSync::sync();

        $this->command?->info('No default data. First admin: php artisan app:create-admin. Demo data: php artisan db:seed --class=DemoSeeder');
    }
}
