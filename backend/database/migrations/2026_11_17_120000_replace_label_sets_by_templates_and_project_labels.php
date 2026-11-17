<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Step S7 (docs/auftrag-m3.md section 3.1): templates replace the label sets,
 * every project has labels of its own.
 *
 * - templates: the planer template (JSON, spec/format-v2.md 3.8) with name and
 *   language; the built-in ones are written by planer:sync-templates.
 * - labels: project_id instead of label_set_id, key (stable, a-z0-9_-) and
 *   names (language => name) instead of name.
 * - projects: template_id (where the project came from) instead of label_set_id.
 * - Existing data: every project gets a copy of the labels of its set, and the
 *   labels its tasks carry from other sets; the tasks keep their labels.
 * - The rights labelSets.manage and labelSets.manageAny are gone.
 *
 * The data of the old tables is carried over; going back keeps the structure only.
 */
return new class extends Migration
{
    /** Names of the built-in labels in both languages (from the former set translations). */
    private const BUILTIN_NAMES = [
        'research' => ['Recherche', 'Research'],
        'design' => ['Design', 'Design'],
        'feature' => ['Feature', 'Feature'],
        'bug' => ['Fehler', 'Bug'],
        'planning' => ['Planung', 'Planning'],
        'blocked' => ['Blockiert', 'Blocked'],
        'location' => ['Location', 'Location'],
        'speakers' => ['Speaker', 'Speakers'],
        'marketing' => ['Marketing', 'Marketing'],
        'confirmed' => ['Bestätigt', 'Confirmed'],
        'topic_idea' => ['Themenidee', 'Topic idea'],
        'guest_booking' => ['Gästebuchung', 'Guest booking'],
        'audio_editing' => ['Audioschnitt', 'Audio editing'],
        'social_media' => ['Social Media', 'Social media'],
    ];

    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->nullable()->unique(); // only built-in templates
            $table->string('name', 60)->unique();
            $table->text('description')->nullable();
            $table->string('locale', 5);
            $table->json('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('label_task', function (Blueprint $table): void {
            $table->dropForeign(['label_id']);
        });

        Schema::rename('labels', 'labels_old');

        Schema::create('labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->json('names');
            $table->string('category', 40)->nullable();
            $table->string('color', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });

        $this->copyLabelsToProjects();

        Schema::table('label_task', function (Blueprint $table): void {
            $table->foreign('label_id')->references('id')->on('labels')->cascadeOnDelete();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('label_set_id');
            $table->foreignId('template_id')->nullable()->after('icon')->constrained('templates')->nullOnDelete();
        });

        Schema::drop('labels_old');
        Schema::drop('label_sets');

        DB::table('function_rights')->whereIn('right', ['labelSets.manage', 'labelSets.manageAny'])->delete();
    }

    /**
     * Brings the structure back, empty: the labels of the projects are gone,
     * and so is what tasks had of them. A backup is the way back for real data.
     */
    public function down(): void
    {
        Schema::table('label_task', function (Blueprint $table): void {
            $table->dropForeign(['label_id']);
        });
        DB::table('label_task')->delete();

        Schema::drop('labels');

        Schema::create('label_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->nullable()->unique();
            $table->string('name', 60);
            $table->string('description', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('label_set_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60)->nullable();
            $table->string('name', 40);
            $table->string('category', 40)->nullable();
            $table->string('color', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['label_set_id', 'name']);
            $table->unique(['label_set_id', 'key']);
        });

        Schema::table('label_task', function (Blueprint $table): void {
            $table->foreign('label_id')->references('id')->on('labels')->cascadeOnDelete();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('template_id');
            $table->foreignId('label_set_id')->nullable()->after('icon')->constrained()->restrictOnDelete();
        });

        Schema::drop('templates');
    }

    /**
     * A copy of the set's labels for each project, then the labels of the tasks
     * (also from other sets), and the pivot rows pointed at the copies.
     */
    private function copyLabelsToProjects(): void
    {
        $old = DB::table('labels_old')->orderBy('position')->orderBy('id')->get()->keyBy('id');
        $projects = DB::table('projects')->pluck('locale', 'id');

        /** @var array<int, array<int, int>> $copies project id => old label id => new label id */
        $copies = [];
        $next = [];
        $keys = [];

        $copy = function (int $projectId, object $label) use (&$copies, &$next, &$keys, $projects): int {
            $builtin = $label->key !== null ? (self::BUILTIN_NAMES[$label->key] ?? null) : null;
            $locale = $projects[$projectId] ?? 'de';

            $key = $label->key ?? Str::slug($label->name, '_');
            $key = $key === '' ? 'label' : $key;
            $base = mb_substr($key, 0, 40);
            $key = $base;
            for ($number = 2; isset($keys[$projectId][$key]); $number++) {
                $key = mb_substr($base, 0, 40 - strlen("_{$number}"))."_{$number}";
            }
            $keys[$projectId][$key] = true;

            $names = $builtin !== null ? ['de' => $builtin[0], 'en' => $builtin[1]] : [$locale => $label->name];

            $id = DB::table('labels')->insertGetId([
                'project_id' => $projectId,
                'key' => $key,
                'names' => json_encode($names, JSON_UNESCAPED_UNICODE),
                'category' => $label->category,
                'color' => $label->color,
                'position' => $next[$projectId] = ($next[$projectId] ?? -1) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $copies[$projectId][$label->id] = $id;
        };

        $setIds = DB::table('projects')->whereNotNull('label_set_id')->pluck('label_set_id', 'id');
        foreach ($setIds as $projectId => $setId) {
            foreach ($old->where('label_set_id', $setId) as $label) {
                $copy((int) $projectId, $label);
            }
        }

        $pivot = DB::table('label_task')
            ->join('tasks', 'tasks.id', '=', 'label_task.task_id')
            ->select('label_task.label_id', 'label_task.task_id', 'tasks.project_id')
            ->get();

        $rows = [];
        foreach ($pivot as $row) {
            $projectId = (int) $row->project_id;
            $id = $copies[$projectId][$row->label_id] ?? (isset($old[$row->label_id]) ? $copy($projectId, $old[$row->label_id]) : null);

            if ($id !== null) {
                $rows[] = ['label_id' => $id, 'task_id' => $row->task_id];
            }
        }

        DB::table('label_task')->delete();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('label_task')->insert($chunk);
        }
    }
};
