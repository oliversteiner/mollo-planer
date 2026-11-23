<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Support\DocumentSearch;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * The search in titles and content (DocumentSearch). On MariaDB the FULLTEXT index is used; an InnoDB index sees
 * a row only after the commit, so this class does not run in the transaction of RefreshDatabase but migrates
 * itself (the rows are committed when they are created).
 */
class DocumentSearchTest extends TestCase
{
    use DatabaseMigrations;

    /** @return list<string> */
    private function found(string $term): array
    {
        $query = Document::query()->orderBy('title');
        DocumentSearch::apply($query, $term);

        return $query->pluck('title')->all();
    }

    public function test_the_search_finds_words_in_the_content_and_in_the_title(): void
    {
        $project = Project::factory()->create();
        Document::factory()->for($project)->create(['title' => 'Release planen', 'content' => "Die Migration der Datenbank dauert lange.\n"]);
        Document::factory()->for($project)->create(['title' => 'Protokoll', 'content' => "Wir sprachen über das Budget und die Kosten.\n"]);

        $this->assertSame(['Release planen'], $this->found('Datenbank'));
        $this->assertSame(['Release planen'], $this->found('datenbank migration'));
        $this->assertSame(['Protokoll'], $this->found('Kosten'));
        $this->assertSame(['Release planen'], $this->found('Rel'), 'a word is found by its beginning');
        $this->assertSame(['Protokoll', 'Release planen'], $this->found(''), 'no word, no filter');
        $this->assertSame([], $this->found('gibtesnicht'));
    }

    public function test_special_characters_of_the_search_do_no_harm(): void
    {
        $project = Project::factory()->create();
        Document::factory()->for($project)->create(['title' => '100% sicher', 'content' => "Text\n"]);

        $this->assertSame(['100% sicher'], $this->found('+-*()"<>~@ %_'), 'only signs: no word, no filter');
        $this->assertSame([], $this->found('"sicher" -nicht +(x)'), 'operators of the index are plain signs here');
        $this->assertSame(['100% sicher'], $this->found('sicher'));
    }
}
