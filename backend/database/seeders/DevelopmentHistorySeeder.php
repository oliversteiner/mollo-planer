<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Planer\Import;
use App\Planer\ImportMode;
use App\Support\DeploymentEnvironment;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the project "Mollo Planer · Entwicklung" from docs/entwicklung.planer.json
 * (a planer-v2 file; docs/entwicklung.md is the text it was made from). The importer
 * does the work as a new project in full mode, by the first admin. Local only.
 *
 *   php artisan db:seed --class=DevelopmentHistorySeeder
 *
 * Runs once: if the project exists, nothing is changed.
 */
class DevelopmentHistorySeeder extends Seeder
{
    public const PROJECT_NAME = 'Mollo Planer · Entwicklung';

    public function run(): void
    {
        if (DeploymentEnvironment::isLive()) {
            throw new RuntimeException('DevelopmentHistorySeeder must not run on a server (production or staging).');
        }

        if (Project::query()->where('name', self::PROJECT_NAME)->exists()) {
            $this->command?->info('The project '.self::PROJECT_NAME.' already exists, nothing changed.');

            return;
        }

        $admin = User::query()->where('role_id', Role::idOf(Role::SCOPE_SYSTEM, UserRole::ADMIN->value))->orderBy('id')->first()
            ?? throw new RuntimeException('There is no admin to create the project. Create one: php artisan app:create-admin');

        $document = json_decode((string) file_get_contents(base_path('../docs/entwicklung.planer.json')), true, 512, JSON_THROW_ON_ERROR);
        $result = app(Import::class)->run(null, $document, ImportMode::Full, false, false, $admin);

        if (! $result->ok()) {
            $first = $result->errors[0];
            throw new RuntimeException("docs/entwicklung.planer.json is invalid ({$first['path']}): {$first['message']}");
        }

        $this->command?->info('Development history created from docs/entwicklung.planer.json.');
    }
}
