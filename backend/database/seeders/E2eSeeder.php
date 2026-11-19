<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Planer\TemplateSync;
use Illuminate\Database\Seeder;
use Laravel\Fortify\Fortify;
use RuntimeException;

/**
 * The accounts of the browser tests (frontend/e2e): an admin and a member, both confirmed, approved and with an
 * authenticator app. Password and authenticator secret come from the environment of the tests
 * (E2E_PASSWORD, E2E_TOTP_SECRET, E2E_RECOVERY_CODES in frontend/e2e/support/environment.ts), not from this code:
 * like the DemoSeeder, the seeder itself holds no known password. It refuses to run in production and
 * without these values.
 *
 *   php artisan migrate:fresh --seed --seeder=E2eSeeder     (with DB_DATABASE pointing at the test database)
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('E2eSeeder must not run in production.');
        }

        $password = (string) env('E2E_PASSWORD');
        $secret = (string) env('E2E_TOTP_SECRET');
        $recovery = array_values(array_filter(explode(',', (string) env('E2E_RECOVERY_CODES'))));

        if ($password === '' || $secret === '' || $recovery === []) {
            throw new RuntimeException('E2eSeeder needs E2E_PASSWORD, E2E_TOTP_SECRET and E2E_RECOVERY_CODES (see frontend/e2e/support/environment.ts).');
        }

        TemplateSync::sync();

        foreach ([['Ada Admin', 'admin@e2e.test', UserRole::ADMIN], ['Mia Member', 'member@e2e.test', UserRole::MITGLIED]] as [$name, $email, $role]) {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->role = $role;
            $user->forceFill([
                'locale' => 'de',
                'email_verified_at' => now(),
                'approved_at' => now(),
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recovery)),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }
    }
}
