<?php

namespace Database\Seeders;

use App\Enums\UserActivity;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Template;
use App\Models\Todo;
use App\Models\User;
use App\Planer\Import;
use App\Planer\ImportMode;
use App\Planer\TemplateSync;
use App\Support\AppSettings;
use App\Support\DeploymentEnvironment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;
use RuntimeException;

/**
 * Demo data for trying the API (Postman, frontend), the same as in the
 * GraphQL demo: four people, two projects with milestones, todos, tasks
 * and comments. The projects are planer files in database/seeders/demo,
 * read in by the importer.
 *
 * Only for an empty database outside production:
 *   php artisan migrate:fresh --seed --seeder=DemoSeeder
 *   php artisan db:seed --class=DemoSeeder
 *
 * No password is stored in the code. The seeder asks for one password for
 * all demo users (or generates one without a terminal) and gives every
 * user a confirmed authenticator app. It prints the password, the 2FA
 * secrets and recovery codes once; nothing of it is in the repository.
 * Statuses of todos and milestones are computed by the observers.
 *
 * Two-factor authentication starts switched off (App\Support\AppSettings),
 * so the password is enough for trying the API. The authenticator apps
 * are set up anyway: after updateAppSettings(twoFactorEnabled: true) the
 * login with code works at once.
 */
class DemoSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, email: string, role: UserRole, activity: UserActivity|null}>
     */
    private const USERS = [
        'anna' => ['name' => 'Anna Keller', 'email' => 'anna.keller@example.ch', 'role' => UserRole::ADMIN, 'activity' => null],
        'ben' => ['name' => 'Ben Müller', 'email' => 'ben.mueller@example.ch', 'role' => UserRole::MITGLIED, 'activity' => UserActivity::ENTWICKLUNG],
        'chiara' => ['name' => 'Chiara Rossi', 'email' => 'chiara.rossi@example.ch', 'role' => UserRole::PROJEKTLEITUNG, 'activity' => null],
        'david' => ['name' => 'David Frei', 'email' => 'david.frei@example.ch', 'role' => UserRole::MITGLIED, 'activity' => UserActivity::DESIGNER],
    ];

    /** @var array<string, User> */
    private array $users = [];

    public function __construct(private readonly TwoFactorAuthenticationProvider $twoFactor) {}

    public function run(): void
    {
        if (DeploymentEnvironment::isLive()) {
            throw new RuntimeException('DemoSeeder must not run on a server (production or staging).');
        }

        if (User::query()->exists()) {
            throw new RuntimeException('DemoSeeder needs an empty database. Use: php artisan migrate:fresh --seed --seeder=DemoSeeder');
        }

        $password = $this->password();

        // The built-in templates give the projects their labels.
        TemplateSync::sync();

        $credentials = DB::transaction(function () use ($password): array {
            $credentials = $this->createUsers($password);
            $this->createWebsiteRelaunch();
            $this->createSummerParty();
            AppSettings::setTwoFactorEnabled(false, $this->users['anna']);

            return $credentials;
        });

        $this->report($password, $credentials);
    }

    /**
     * @return array<string, array{secret: string, uri: string, recovery: list<string>}>
     */
    private function createUsers(string $password): array
    {
        $credentials = [];
        $createdAt = Carbon::parse('2026-09-01 08:00');

        foreach (self::USERS as $key => $person) {
            $email = $person['email'];
            $secret = $this->twoFactor->generateSecretKey();
            $recovery = Collection::times(8, fn () => RecoveryCode::generate())->all();

            $user = new User(['name' => $person['name'], 'email' => $email, 'password' => $password]);
            $user->role = $person['role'];
            $user->activity = $person['activity'];
            $user->forceFill([
                'email_verified_at' => $createdAt,
                'approved_at' => $createdAt,
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recovery)),
                'two_factor_confirmed_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            $this->users[$key] = $user;
            $credentials[$email] = [
                'secret' => $secret,
                'uri' => $this->twoFactor->qrCodeUrl(config('app.name'), $email, $secret),
                'recovery' => $recovery,
            ];
            $createdAt = $createdAt->copy()->addMinutes(5);
        }

        return $credentials;
    }

    /**
     * The projects are planer files (database/seeders/demo/*.planer.json), imported as
     * a new project by Anna (an admin may create projects), like a person would from a
     * file. What the format does not hold is added afterwards: icons, the template a
     * project came from, the time it was created, comments.
     */
    private function createWebsiteRelaunch(): void
    {
        $project = $this->import('website-relaunch', '2026-09-10 10:00', 'software-development');

        $this->icons($project, [
            'Design freigegeben' => 'palette', 'Inhalte migriert' => 'file-import', 'Go-live' => 'rocket',
            'Styleguide erstellen' => 'swatchbook', 'Startseite entwerfen' => 'browser',
            'Komponenten dokumentieren' => 'book',
        ]);

        $components = $this->taskTitled($project, 'Komponenten dokumentieren');
        $wireframes = $this->taskTitled($project, 'Wireframes zeichnen');

        $this->comment($components, 'ben', 'Bitte dokumentiere auch die Icons.', '2026-11-04 09:15');
        $this->comment($components, 'david', 'Mache ich. Morgen bin ich damit durch.', '2026-11-04 10:02');
        $this->comment($wireframes, 'chiara', 'Die mobile Version folgt nach dem Review.', '2026-11-05 14:30');
    }

    private function createSummerParty(): void
    {
        $project = $this->import('sommerfest-2027', '2026-10-05 14:30', 'event');

        $this->icons($project, [
            'Budget und Termin' => 'coins', 'Einladung versendet' => 'envelope', 'Programm steht' => 'calendar-days',
            'Budget aufstellen' => 'calculator',
        ]);
    }

    /**
     * Imports a demo file as a new project (full mode, Anna), then lets Anna go: she
     * imported it, but the demo has her as an admin without a seat in the project.
     */
    private function import(string $file, string $createdAt, string $templateKey): Project
    {
        $document = json_decode((string) file_get_contents(database_path("seeders/demo/{$file}.planer.json")), true, 512, JSON_THROW_ON_ERROR);
        $result = app(Import::class)->run(null, $document, ImportMode::Full, false, false, $this->users['anna']);

        if (! $result->ok()) {
            throw new RuntimeException("The demo file {$file} is not valid: ".json_encode($result->errors, JSON_UNESCAPED_UNICODE));
        }

        $project = Project::query()->findOrFail($result->projectId);
        $project->members()->detach($this->users['anna']);
        $project->forceFill([
            'template_id' => Template::query()->where('key', $templateKey)->value('id'),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $project;
    }

    /**
     * Icons are not in the file; they are set by title.
     *
     * @param  array<string, string>  $icons
     */
    private function icons(Project $project, array $icons): void
    {
        foreach ([Milestone::class, Todo::class, Task::class] as $model) {
            $model::query()->where('project_id', $project->getKey())->whereIn('title', array_keys($icons))->get()
                ->each(fn (Model $item) => $item->forceFill(['icon' => $icons[$item->title]])->saveQuietly());
        }
    }

    private function taskTitled(Project $project, string $title): Task
    {
        return Task::query()->where('project_id', $project->getKey())->where('title', $title)->firstOrFail();
    }

    private function comment(Task $task, string $author, string $text, string $createdAt): void
    {
        $comment = new Comment(['body' => $this->plain($text)]);
        $comment->task()->associate($task);
        $comment->author()->associate($this->users[$author]);
        $comment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
    }

    private function password(): string
    {
        if ($this->command !== null && ! $this->command->option('no-interaction')) {
            $password = (string) $this->command->secret('Password for all demo users (empty = generate one)');

            if ($password !== '') {
                return $password;
            }
        }

        return Str::password(16, symbols: false);
    }

    /**
     * @param  array<string, array{secret: string, uri: string, recovery: list<string>}>  $credentials
     */
    private function report(string $password, array $credentials): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Demo data created. Shown only now, not stored anywhere else:');
        $this->command->line('Password for all demo users: '.$password);
        $this->command->warn('Two-factor authentication is OFF: the password is enough. Switch it on with updateAppSettings(twoFactorEnabled: true); then use the 2FA secrets below.');

        foreach ($credentials as $email => $credential) {
            $this->command->newLine();
            $this->command->line('<options=bold>'.$email.'</>');
            $this->command->line('  2FA secret:     '.$credential['secret']);
            $this->command->line('  otpauth URI:    '.$credential['uri']);
            $this->command->line('  Recovery codes: '.implode(' ', $credential['recovery']));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function plain(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }
}
