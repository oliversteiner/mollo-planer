<?php

namespace App\Services;

use App\Core\Pdf\PdfFrame;
use App\Core\Pdf\PdfFrameSettings;
use App\Core\Pdf\PdfOptions;
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

/**
 * A document as PDF (docs/dokumentenverwaltung.md §7.2): Markdown to HTML (HTML in the text is escaped, unsafe
 * links are off), a Blade print template in the language of the project, then the PDF frame of the starter kit
 * (App\Core\Pdf\PdfFrame). Synchronous, nothing is loaded from outside while rendering.
 */
final class DocumentPdf
{
    public function __construct(private readonly PdfFrame $frame) {}

    /**
     * The PDF as bytes. With the letterhead (Auftrag 39 F, only if one is uploaded): the layout of letters, no own
     * header and footer (the letterhead has them).
     */
    public function render(Document $document, bool $withLetterhead = false): string
    {
        $frame = PdfFrameSettings::load();
        $letterhead = $withLetterhead ? $frame->usableLetterhead() : null;

        $project = $document->project;
        $locale = $this->localeOf($project);
        $texts = fn (string $key, array $replace = []): string => (string) __('documents.pdf.'.$key, $replace, $locale);

        $html = View::make('documents.pdf', [
            'locale' => $locale,
            'title' => $document->title,
            'meta' => $this->meta($document, $project, $texts),
            'body' => $this->html($document->content),
        ])->render();

        if ($letterhead !== null) {
            return $this->frame->render($html, new PdfOptions(
                title: $document->title,
                margins: PdfFrame::marginsOf($frame->layout),
                firstPageTop: $frame->layout->topFirst,
                font: $frame->font,
                letterhead: $letterhead,
            ));
        }

        return $this->frame->render($html, new PdfOptions(
            title: $document->title,
            header: $project->name.' · '.$document->title,
            footer: $texts('footer', [
                'revision' => $document->revision,
                'date' => $document->updated_at?->format('d.m.Y H:i') ?? '',
            ]),
            pageLabel: $texts('page', ['current' => '{current}', 'total' => '{total}']),
        ));
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
}
