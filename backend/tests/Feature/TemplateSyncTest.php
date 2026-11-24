<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Planer\TemplateSync;
use App\Support\StrongAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * planer:sync-templates (docs/auftrag-m3.md 3.2): the built-in templates are
 * the files in spec/templates, matched by their key, run again and again.
 * Templates the app builds itself pass `tools/planer validate --template`.
 */
class TemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    private function validateWithNode(array $content): ?string
    {
        $file = tempnam(sys_get_temp_dir(), 'template').'.planer.json';
        file_put_contents($file, json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $process = new Process(['node', dirname(base_path()).'/tools/planer/cli/bin/mollo-planer.mjs', 'validate', $file, '--template']);
        $process->run();
        unlink($file);

        return $process->isSuccessful() ? null : trim($process->getOutput().$process->getErrorOutput());
    }

    private function nodeIsThere(): bool
    {
        return (new Process(['node', '--version']))->run() === 0;
    }

    public function test_the_built_in_templates_are_written_in_their_order(): void
    {
        $this->artisan('planer:sync-templates')->expectsOutputToContain('software-development, event, podcast')->assertSuccessful();

        $templates = Template::query()->orderBy('id')->get();

        $this->assertSame(['software-development', 'event', 'podcast'], $templates->pluck('key')->all());
        $this->assertSame(['Softwareentwicklung', 'Event', 'Podcast'], $templates->pluck('name')->all());
        $this->assertSame(['de', 'de', 'de'], $templates->pluck('locale')->all());
        $this->assertSame(['Research', 'Design', 'Feature', 'Bug', 'Refactor', 'Chore', 'Docs', 'Tests', 'Security', 'Planning', 'Blocked'], array_column(array_column($templates[0]->labels(), 'names'), 'en'));
        $this->assertNull($templates[0]->created_by);
        $this->assertSame(11, count($templates[0]->labels()));
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        TemplateSync::sync();
        $before = Template::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->travel(1)->hour();
        TemplateSync::sync();

        $this->assertSame($before, Template::query()->orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_a_changed_file_updates_the_template_with_the_same_key(): void
    {
        TemplateSync::sync();
        $id = Template::query()->where('key', 'event')->value('id');

        $directory = sys_get_temp_dir().'/templates-'.uniqid();
        mkdir($directory);
        $content = json_decode((string) file_get_contents(TemplateSync::directory().'/event.planer.json'), true);
        $content['project']['description'] = 'Neu';
        file_put_contents($directory.'/event.planer.json', json_encode($content));

        TemplateSync::sync($directory);

        $this->assertSame($id, Template::query()->where('key', 'event')->value('id'));
        $this->assertSame('Neu', Template::query()->where('key', 'event')->value('description'));
        unlink($directory.'/event.planer.json');
        rmdir($directory);
    }

    public function test_an_own_template_with_the_name_of_a_built_in_one_makes_way(): void
    {
        Template::factory()->create(['name' => 'Event']);

        TemplateSync::sync();

        $this->assertSame('Event', Template::query()->where('key', 'event')->value('name'));
        $this->assertTrue(Template::query()->where('name', 'Event (2)')->whereNull('key')->exists());
    }

    public function test_a_file_that_is_not_valid_stops_the_sync_and_writes_nothing(): void
    {
        $directory = sys_get_temp_dir().'/templates-'.uniqid();
        mkdir($directory);
        $content = json_decode((string) file_get_contents(TemplateSync::directory().'/event.planer.json'), true);
        $content['project']['labels'][0]['key'] = 'Not Valid';
        $content['milestones'][0]['todos'][0]['tasks'][0]['status'] = 'open';
        file_put_contents($directory.'/event.planer.json', json_encode($content));
        copy(TemplateSync::directory().'/podcast.planer.json', $directory.'/podcast.planer.json');

        try {
            TemplateSync::sync($directory);
            $this->fail('should refuse');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('event:', $error->getMessage());
            $this->assertStringContainsString('project.labels.0.key', $error->getMessage());
            $this->assertStringContainsString('status must be planned', $error->getMessage());
        }

        $this->assertSame(0, Template::query()->count());
        array_map('unlink', glob($directory.'/*'));
        rmdir($directory);
    }

    public function test_the_built_in_files_pass_the_validator_of_the_planer_tool(): void
    {
        if (! $this->nodeIsThere()) {
            $this->markTestSkipped('Node is not installed.');
        }

        foreach (glob(TemplateSync::directory().'/*.planer.json') ?: [] as $file) {
            $this->assertNull($this->validateWithNode(json_decode((string) file_get_contents($file), true)), basename($file));
        }
    }

    public function test_templates_the_app_builds_pass_the_validator_of_the_planer_tool(): void
    {
        if (! $this->nodeIsThere()) {
            $this->markTestSkipped('Node is not installed.');
        }

        TemplateSync::sync();
        $user = User::factory()->admin()->create();
        $gql = fn (string $query, array $variables = []) => $this->actingAs($user)
            ->withSession([StrongAuthentication::SESSION_KEY => (string) $user->id])
            ->withHeader('Referer', 'http://localhost')
            ->postJson('/api', ['query' => $query, 'variables' => $variables])->json();

        $created = $gql('mutation ($in: TemplateInput!) { createTemplate(input: $in) { id } }', ['in' => [
            'name' => 'Eigene', 'description' => 'Mit Beschreibung', 'locale' => 'EN',
            'labels' => [['names' => [['locale' => 'EN', 'name' => 'First'], ['locale' => 'DE', 'name' => 'Erstes']], 'category' => 'Phase', 'color' => 'GREEN']],
        ]]);
        $duplicate = $gql('mutation ($id: ID!) { duplicateTemplate(id: $id) { id } }', ['id' => Template::query()->where('key', 'software-development')->value('id')]);
        $changed = $gql('mutation ($id: ID!, $in: UpdateTemplateInput!) { updateTemplate(id: $id, input: $in) { id } }', ['id' => $duplicate['data']['duplicateTemplate']['id'], 'in' => [
            'name' => 'Kopie angepasst', 'labels' => [['key' => 'bug', 'names' => [['locale' => 'DE', 'name' => 'Käfer']], 'color' => 'RED']],
        ]]);

        foreach ([$created['data']['createTemplate']['id'], $duplicate['data']['duplicateTemplate']['id'], $changed['data']['updateTemplate']['id']] as $id) {
            $this->assertNull($this->validateWithNode(Template::query()->findOrFail($id)->content), "template {$id}");
        }
    }
}
