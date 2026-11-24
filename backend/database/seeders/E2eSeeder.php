<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Planer\TemplateSync;
use App\Support\DeploymentEnvironment;
use Illuminate\Database\Seeder;
use Laravel\Fortify\Fortify;
use RuntimeException;

/**
 * The accounts of the browser tests (frontend/e2e): an admin and a member, both confirmed, approved and with an
 * authenticator app, and one project with both of them ("E2E Dokumente": the admin owns it, the member is in it;
 * for the tests of the documents) and one with a task to drag ("E2E Zeitachse", the admin owns it). Password and authenticator secret come from the environment of the tests
 * (E2E_PASSWORD, E2E_TOTP_SECRET, E2E_RECOVERY_CODES in frontend/e2e/support/environment.ts), not from this code:
 * like the DemoSeeder, the seeder itself holds no known password. It refuses to run on a server (production or staging) and
 * without these values.
 *
 *   php artisan migrate:fresh --seed --seeder=E2eSeeder     (with DB_DATABASE pointing at the test database)
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        if (DeploymentEnvironment::isLive()) {
            throw new RuntimeException('E2eSeeder must not run on a server (production or staging).');
        }

        $password = (string) env('E2E_PASSWORD');
        $secret = (string) env('E2E_TOTP_SECRET');
        $recovery = array_values(array_filter(explode(',', (string) env('E2E_RECOVERY_CODES'))));

        if ($password === '' || $secret === '' || $recovery === []) {
            throw new RuntimeException('E2eSeeder needs E2E_PASSWORD, E2E_TOTP_SECRET and E2E_RECOVERY_CODES (see frontend/e2e/support/environment.ts).');
        }

        TemplateSync::sync();

        $people = [];

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
            $people[$email] = $user;
        }

        $project = Project::factory()
            ->withMember($people['admin@e2e.test'], ProjectRole::OWNER)
            ->withMember($people['member@e2e.test'], ProjectRole::MEMBER)
            ->create(['name' => 'E2E Dokumente']);

        // The timeline: two todos, one task in the first (for the test of the drag handle).
        $timeline = Project::factory()
            ->withMember($people['admin@e2e.test'], ProjectRole::OWNER)
            ->create(['name' => 'E2E Zeitachse']);
        $milestone = Milestone::factory()->for($timeline)->create(['title' => 'E2E Etappe']);
        $source = Todo::factory()->for($milestone)->create(['title' => 'E2E Quelle']);
        Todo::factory()->for($milestone)->create(['title' => 'E2E Ziel']);
        Task::factory()->for($source)->create(['title' => 'E2E Beweglich']);
        // One milestone, todo and task, so the forms that pick an icon (Auftrag 36 B, frontend/e2e/icon-picker.spec.ts)
        // can be opened in every place: the todo form needs a milestone.
        $milestone = Milestone::factory()->for($project)->create(['title' => 'Symbole prüfen']);
        $todo = Todo::factory()->for($milestone)->create(['title' => 'Auswahl testen']);
        Task::factory()->for($todo)->create(['title' => 'Symbol wählen']);
    }
}
