<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * projects.description becomes a TipTap document like the descriptions of
     * milestones (schema-review C2, docs/auftrag-m2.md §5). Existing plain
     * text becomes paragraphs: blank lines separate them.
     */
    public function up(): void
    {
        foreach (DB::table('projects')->whereNotNull('description')->get(['id', 'description']) as $project) {
            DB::table('projects')->where('id', $project->id)->update(['description' => $this->document((string) $project->description)]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->json('description')->nullable()->change(); // TipTap document, never HTML
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        foreach (DB::table('projects')->whereNotNull('description')->get(['id', 'description']) as $project) {
            DB::table('projects')->where('id', $project->id)->update(['description' => $this->text((string) $project->description)]);
        }
    }

    /** Plain text as a document of paragraphs, null for blank text. */
    private function document(string $text): ?string
    {
        $paragraphs = array_values(array_filter(array_map(
            fn (string $block): string => trim((string) preg_replace('/\s+/u', ' ', $block)),
            preg_split('/\R\s*\R/u', trim($text)) ?: [],
        )));

        if ($paragraphs === []) {
            return null;
        }

        return json_encode([
            'type' => 'doc',
            'content' => array_map(fn (string $paragraph): array => [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $paragraph]],
            ], $paragraphs),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** The texts of the paragraphs, separated by blank lines. */
    private function text(string $json): ?string
    {
        $document = json_decode($json, true);
        $texts = [];

        foreach (is_array($document['content'] ?? null) ? $document['content'] : [] as $block) {
            $texts[] = implode('', array_map(fn (mixed $node): string => is_array($node) ? (string) ($node['text'] ?? '') : '', $block['content'] ?? []));
        }

        $text = trim(implode("\n\n", $texts));

        return $text === '' ? null : $text;
    }
};
