<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAiContext;
use App\Models\User;
use App\Planer\PlanerLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The only place that writes the AI context of a project (docs/auftrag-m6.md §3): text, the text of one
 * uploaded file, the release for external tools. Text and file together have at most
 * PlanerLimits::AI_CONTEXT_CHARACTERS characters. A file is .txt or .md, UTF-8, at most 100 KB; only its text
 * is kept, with plain line ends. None of this changes a plan, so no revision.
 */
class AiContexts
{
    public const FILE_MAX_BYTES = 102_400;

    /**
     * @throws ValidationException
     */
    public function setText(Project $project, User $actor, string $text, bool $sharedWithTools): ProjectAiContext
    {
        $context = $this->current($project);
        $context->text = trim($this->lineEnds($text)) === '' ? null : $this->lineEnds($text);
        $context->shared_with_tools = $sharedWithTools;

        return $this->save($context, $actor, 'input.text');
    }

    /**
     * @throws ValidationException
     */
    public function setFile(Project $project, User $actor, UploadedFile $file): ProjectAiContext
    {
        $name = $file->getClientOriginalName();

        if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['txt', 'md'], true)) {
            throw ValidationException::withMessages(['file' => __('ai_context.extension')]);
        }

        if ($file->getSize() > self::FILE_MAX_BYTES) {
            throw ValidationException::withMessages(['file' => __('ai_context.too_big', ['kb' => self::FILE_MAX_BYTES / 1024])]);
        }

        $raw = (string) file_get_contents($file->getRealPath());
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        if (! mb_check_encoding($raw, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => __('ai_context.encoding')]);
        }

        $context = $this->current($project);
        $context->file_name = mb_substr($name, 0, 255);
        $context->file_text = $this->lineEnds($raw);

        return $this->save($context, $actor, 'file');
    }

    public function removeFile(Project $project, User $actor): ?ProjectAiContext
    {
        $context = $project->aiContext()->first();

        if ($context === null) {
            return null;
        }

        $context->file_name = null;
        $context->file_text = null;

        return $this->save($context, $actor, 'file');
    }

    public function clear(Project $project): void
    {
        $project->aiContext()->delete();
    }

    private function current(Project $project): ProjectAiContext
    {
        return $project->aiContext()->first() ?? (new ProjectAiContext)->forceFill(['project_id' => $project->getKey()]);
    }

    /**
     * @throws ValidationException
     */
    private function save(ProjectAiContext $context, User $actor, string $field): ProjectAiContext
    {
        if ($context->characters > PlanerLimits::AI_CONTEXT_CHARACTERS) {
            throw ValidationException::withMessages([$field => __('ai_context.too_long', ['max' => PlanerLimits::AI_CONTEXT_CHARACTERS, 'count' => $context->characters])]);
        }

        $context->updated_by = $actor->getKey();
        $context->save();

        return $context;
    }

    private function lineEnds(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }
}
