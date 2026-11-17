<?php

namespace Tests\Unit;

use App\Planer\TemplateContent;
use PHPUnit\Framework\TestCase;

class TemplateContentTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return TemplateContent::blank('Vorlage', 'Text', 'de', [
            ['key' => 'eins', 'names' => ['de' => 'Eins', 'en' => 'One'], 'category' => 'phase', 'color' => 'blue'],
        ]);
    }

    public function test_a_template_the_app_builds_has_no_problems(): void
    {
        $this->assertSame([], TemplateContent::problems($this->valid()));
        $this->assertSame(['eins'], array_column(TemplateContent::labels($this->valid()), 'key'));
    }

    public function test_the_fixed_values_of_a_template_are_checked(): void
    {
        $content = $this->valid();
        $content['revision'] = 3;
        $content['project']['members'] = [['email' => 'a@b.ch', 'role' => 'owner']];
        $content['milestones'] = [['id' => 'm-1', 'title' => 'M', 'release' => ['version' => '1', 'status' => 'released'], 'todos' => [
            ['title' => 'T', 'tasks' => [['title' => 'K', 'status' => 'open', 'dueDate' => '2027-01-01', 'labels' => ['unbekannt']]]],
        ]]];

        $problems = implode("\n", TemplateContent::problems($content));

        foreach (['revision must be 0', 'project.members must be empty', 'milestones.0.id must be a -new- id', 'release.status must be planned', 'status must be planned', 'dueDate must be missing', 'unknown label unbekannt'] as $expected) {
            $this->assertStringContainsString($expected, $problems);
        }
    }

    public function test_the_label_rules(): void
    {
        $content = $this->valid();
        $content['project']['labels'] = [
            ['key' => 'ok', 'names' => ['de' => 'Gleich'], 'color' => 'blue'],
            ['key' => 'ok', 'names' => ['en' => 'Only English', 'fr' => 'Non'], 'color' => 'magenta'],
            ['key' => 'x', 'names' => ['de' => 'gleich'], 'color' => 'red'],
        ];

        $problems = implode("\n", TemplateContent::problems($content));

        foreach (['labels.1.key is used twice', 'labels.1.color', 'labels.1.names needs the language of the template (de)', 'names.fr', 'labels.2.names.de is used twice'] as $expected) {
            $this->assertStringContainsString($expected, $problems);
        }

        $content['project']['labels'] = [];
        $this->assertSame(['project.labels needs at least one label'], array_values(array_filter(TemplateContent::problems($content), fn (string $problem): bool => str_contains($problem, 'labels'))));
    }

    public function test_the_outline_shows_titles_versions_and_label_keys(): void
    {
        $content = $this->valid();
        $content['milestones'] = [['title' => 'M1', 'release' => ['version' => '1.0'], 'todos' => [['title' => 'T', 'tasks' => [['title' => 'K', 'labels' => ['eins']]]]]]];

        $this->assertSame(
            [['title' => 'M1', 'version' => '1.0', 'todos' => [['title' => 'T', 'tasks' => [['title' => 'K', 'labels' => ['eins']]]]]]],
            TemplateContent::outline($content),
        );
    }
}
