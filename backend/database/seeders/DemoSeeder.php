<?php

namespace Database\Seeders;

use App\Enums\Locale;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Enums\UserFunction;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\LabelSet;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\AppSettings;
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
 * and comments.
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
     * @var array<string, array{name: string, email: string, role: UserRole, job_function: UserFunction|null}>
     */
    private const USERS = [
        'anna' => ['name' => 'Anna Keller', 'email' => 'anna.keller@example.ch', 'role' => UserRole::ADMIN, 'job_function' => null],
        'ben' => ['name' => 'Ben Müller', 'email' => 'ben.mueller@example.ch', 'role' => UserRole::MITGLIED, 'job_function' => UserFunction::ENTWICKLUNG],
        'chiara' => ['name' => 'Chiara Rossi', 'email' => 'chiara.rossi@example.ch', 'role' => UserRole::PROJEKTLEITUNG, 'job_function' => null],
        'david' => ['name' => 'David Frei', 'email' => 'david.frei@example.ch', 'role' => UserRole::MITGLIED, 'job_function' => UserFunction::DESIGNER],
    ];

    /** @var array<string, User> */
    private array $users = [];

    public function __construct(private readonly TwoFactorAuthenticationProvider $twoFactor) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        if (User::query()->exists()) {
            throw new RuntimeException('DemoSeeder needs an empty database. Use: php artisan migrate:fresh --seed --seeder=DemoSeeder');
        }

        $password = $this->password();

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
            $user->job_function = $person['job_function'];
            $user->forceFill([
                'email_verified_at' => $createdAt,
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

    private function createWebsiteRelaunch(): void
    {
        $project = $this->project('Website-Relaunch', 'Neue Website mit Terminen, Berichten und Kontaktformular.', '2026-09-10 10:00', [
            'ben' => ProjectRole::OWNER,
            'chiara' => ProjectRole::MANAGER,
            'david' => ProjectRole::MEMBER,
        ]);
        $this->useLabelSet($project, 'software_dev');

        $design = $this->milestone($project, 'Design freigegeben', 'Styleguide und Entwürfe sind abgenommen.', 'palette', '2026-11-20');
        $content = $this->milestone($project, 'Inhalte migriert', 'Alle Seiten und Texte sind im neuen System.', 'file-import', '2026-12-18');
        $goLive = $this->milestone($project, 'Go-live', 'Die neue Website ist online.', 'rocket', '2027-01-29');

        $styleguide = $this->todo($design, 'Styleguide erstellen', $this->doc(
            $this->paragraph($this->text('Farben, Schriften und Komponenten.')),
            $this->bullets('Farbpalette', 'Schriften', 'Buttons und Formulare'),
        ), 'swatchbook', '2026-11-13');
        $homepage = $this->todo($design, 'Startseite entwerfen', $this->plain('Wireframe und Mockup für Desktop und Mobil.'), 'browser', '2026-11-18');
        $inventory = $this->todo($content, 'Inhaltsinventar erstellen', $this->plain('Alle bestehenden Seiten erfassen und bewerten.'), null, '2026-11-27');
        $this->todo($content, 'Texte überarbeiten', $this->plain('Veraltete Texte aktualisieren.'), null, '2026-12-11');
        $server = $this->todo($goLive, 'Server und Domain vorbereiten', $this->plain('Hosting bestellen, Subdomain und Zertifikat einrichten.'), null, '2027-01-15');

        $this->task($styleguide, 'Farben und Schriften festlegen', TaskStatus::DONE, 0, 'chiara', '2026-11-06');
        $components = $this->task($styleguide, 'Komponenten dokumentieren', TaskStatus::IN_PROGRESS, 0, 'david', '2026-11-12', 'book', $this->doc(
            $this->paragraph($this->text('Bitte '), $this->text('alle', ['bold']), $this->text(' Bausteine beschreiben:')),
            $this->bullets('Buttons und Formulare', 'Icons', 'Farben und Abstände'),
        ));
        $wireframes = $this->task($homepage, 'Wireframes zeichnen', TaskStatus::REVIEW, 0, 'chiara', '2026-11-12');
        $mockup = $this->task($homepage, 'Mockup Desktop', TaskStatus::OPEN, 0, 'david', '2026-11-17');
        $this->task($inventory, 'Alle Seiten auflisten', TaskStatus::DONE, 1, 'david', '2026-11-20');
        $this->task($inventory, 'Veraltete Seiten markieren', TaskStatus::OPEN, 1, null, '2026-11-26');
        $hosting = $this->task($server, 'Hosting bestellen', TaskStatus::OPEN, 2, 'ben', '2027-01-08');

        $this->label($components, 'design', 'feature');
        $this->label($wireframes, 'research', 'design');
        $this->label($mockup, 'design');
        $this->label($hosting, 'blocked');

        $this->comment($components, 'ben', 'Bitte dokumentiere auch die Icons.', '2026-11-04 09:15');
        $this->comment($components, 'david', 'Mache ich. Morgen bin ich damit durch.', '2026-11-04 10:02');
        $this->comment($wireframes, 'chiara', 'Die mobile Version folgt nach dem Review.', '2026-11-05 14:30');
    }

    private function createSummerParty(): void
    {
        $project = $this->project('Sommerfest 2027', 'Planung des Fests im Juni 2027.', '2026-10-05 14:30', [
            'chiara' => ProjectRole::OWNER,
            'david' => ProjectRole::MEMBER,
        ]);
        $this->useLabelSet($project, 'event');

        $budget = $this->milestone($project, 'Budget und Termin', 'Budget genehmigt, Datum und Ort fest.', 'coins', '2026-12-04');
        $this->milestone($project, 'Programm steht', 'Ablauf und Helferplan sind fertig.', 'calendar-days', '2027-03-12');

        $estimate = $this->todo($budget, 'Budget aufstellen', $this->plain('Kosten schätzen und Freigabe einholen.'), 'calculator', '2026-11-25');

        $this->label($this->task($estimate, 'Kostenschätzung', TaskStatus::IN_PROGRESS, 0, 'chiara', '2026-11-24'), 'location');
    }

    /**
     * @param  array<string, ProjectRole>  $members
     */
    private function project(string $name, string $description, string $createdAt, array $members): Project
    {
        $project = new Project(['name' => $name, 'description' => $description, 'locale' => Locale::DE]);
        $project->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        foreach ($members as $key => $role) {
            $project->members()->attach($this->users[$key], ['role' => $role->value]);
        }

        return $project;
    }

    private function milestone(Project $project, string $title, string $description, string $icon, string $dueDate): Milestone
    {
        return Milestone::create([
            'project_id' => $project->id,
            'title' => $title,
            'description' => $this->plain($description),
            'icon' => $icon,
            'due_date' => $dueDate,
        ]);
    }

    /**
     * @param  array<string, mixed>  $description
     */
    private function todo(Milestone $milestone, string $title, array $description, ?string $icon, string $dueDate): Todo
    {
        return Todo::create([
            'milestone_id' => $milestone->id,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'due_date' => $dueDate,
        ]);
    }

    /**
     * Status and position are set directly, as moveTask would leave them
     * (positions per project and column, starting at 0).
     *
     * @param  array<string, mixed>|null  $description
     */
    private function task(Todo $todo, string $title, TaskStatus $status, int $position, ?string $assignee, string $dueDate, ?string $icon = null, ?array $description = null): Task
    {
        $task = new Task([
            'todo_id' => $todo->id,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'due_date' => $dueDate,
        ]);
        $task->status = $status;
        $task->position = $position;
        $task->assigned_user_id = $assignee === null ? null : $this->users[$assignee]->id;
        $task->save();

        return $task;
    }

    /**
     * The project uses one of the built-in label sets (created by the migration).
     */
    private function useLabelSet(Project $project, string $key): void
    {
        $project->labelSet()->associate(LabelSet::query()->where('key', $key)->firstOrFail())->save();
    }

    /**
     * Labels of the project's set, by their key.
     */
    private function label(Task $task, string ...$keys): void
    {
        $set = $task->owningProject()->labelSet()->firstOrFail();

        $task->labels()->attach($set->labels()->whereIn('key', $keys)->pluck('labels.id'));
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
     * @param  array<string, mixed>  ...$blocks
     * @return array<string, mixed>
     */
    private function doc(array ...$blocks): array
    {
        return ['type' => 'doc', 'content' => $blocks];
    }

    /**
     * @return array<string, mixed>
     */
    private function plain(string $text): array
    {
        return $this->doc($this->paragraph($this->text($text)));
    }

    /**
     * @param  array<string, mixed>  ...$texts
     * @return array<string, mixed>
     */
    private function paragraph(array ...$texts): array
    {
        return ['type' => 'paragraph', 'content' => $texts];
    }

    /**
     * @param  list<string>  $marks
     * @return array<string, mixed>
     */
    private function text(string $text, array $marks = []): array
    {
        $node = ['type' => 'text', 'text' => $text];

        if ($marks !== []) {
            $node['marks'] = array_map(fn (string $mark) => ['type' => $mark], $marks);
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function bullets(string ...$items): array
    {
        return [
            'type' => 'bulletList',
            'content' => array_map(fn (string $item) => ['type' => 'listItem', 'content' => [$this->paragraph($this->text($item))]], $items),
        ];
    }
}
