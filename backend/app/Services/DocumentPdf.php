<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Mpdf\Mpdf;

/**
 * A document as PDF (docs/dokumentenverwaltung.md §7.2): Markdown to HTML (HTML in the text is escaped, unsafe
 * links are off), a Blade print template in the language of the project, then mPDF. Synchronous, nothing is loaded
 * from outside while rendering. mPDF runs within the limits of the PHP of the server (memory_limit,
 * max_execution_time); nothing here raises them.
 */
final class DocumentPdf
{
    /**
     * The PDF as bytes.
     */
    public function render(Document $document): string
    {
        $project = $document->project;
        $locale = $this->localeOf($project);
        $texts = fn (string $key, array $replace = []): string => (string) __('documents.pdf.'.$key, $replace, $locale);

        $mpdf = $this->mpdf();
        $mpdf->SetTitle($document->title);
        $mpdf->SetCreator(config('app.name'));
        $mpdf->SetHTMLHeader(View::make('documents.pdf-header', ['project' => $project->name, 'title' => $document->title])->render());
        $mpdf->SetHTMLFooter(View::make('documents.pdf-footer', [
            'line' => $texts('footer', [
                'revision' => $document->revision,
                'date' => $document->updated_at?->format('d.m.Y H:i') ?? '',
            ]),
            'page' => $texts('page', ['current' => '{PAGENO}', 'total' => '{nbpg}']),
        ])->render());

        $mpdf->WriteHTML(View::make('documents.pdf', [
            'locale' => $locale,
            'title' => $document->title,
            'meta' => $this->meta($document, $project, $texts),
            'body' => $this->html($document->content),
        ])->render());

        return $mpdf->OutputBinaryData();
    }

    /** The language of the project (en or de). */
    private function localeOf(Project $project): string
    {
        return $project->getAttribute('locale')->value;
    }

    /** A file name from the title, without characters a download name must not have. */
    public function fileName(Document $document): string
    {
        $name = Str::slug($document->title);

        return ($name === '' ? 'document' : $name).'.pdf';
    }

    /**
     * @param  callable(string, array<string, mixed>=): string  $texts
     */
    private function meta(Document $document, Project $project, callable $texts): string
    {
        return implode(' · ', array_filter([
            $texts('kind').': '.$document->kind->label($this->localeOf($project)),
            $document->owner !== null ? $texts('owner').': '.$document->owner->name : null,
        ]));
    }

    private function html(string $markdown): string
    {
        $environment = new Environment(['html_input' => 'escape', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new TaskListExtension);

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    private function mpdf(): Mpdf
    {
        $temp = storage_path('framework/cache/mpdf');

        if (! is_dir($temp)) {
            mkdir($temp, 0775, true);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $temp,
            'default_font' => 'dejavusans',
            'margin_top' => 25,
            'margin_bottom' => 22,
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_header' => 10,
            'margin_footer' => 10,
        ]);
    }
}
