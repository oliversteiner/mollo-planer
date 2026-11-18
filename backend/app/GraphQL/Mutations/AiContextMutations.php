<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Services\AiContexts;
use Illuminate\Http\UploadedFile;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * The AI context of a project (docs/auftrag-m6.md §3.2). The right (aiContext.update) and the archive are
 * checked by @can on the fields. Returns the project.
 */
class AiContextMutations
{
    use ResolvesViewer;

    public function __construct(private readonly AiContexts $contexts) {}

    /**
     * @param  array{projectId: int|string, input: array{text: string, sharedWithTools: bool}}  $args
     */
    public function set(mixed $root, array $args, GraphQLContext $context): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $this->contexts->setText($project, $this->viewer($context), $args['input']['text'] ?? '', (bool) ($args['input']['sharedWithTools'] ?? false));

        return $project->refresh();
    }

    /**
     * @param  array{projectId: int|string, file: UploadedFile}  $args
     */
    public function upload(mixed $root, array $args, GraphQLContext $context): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $this->contexts->setFile($project, $this->viewer($context), $args['file']);

        return $project->refresh();
    }

    /**
     * @param  array{projectId: int|string}  $args
     */
    public function removeFile(mixed $root, array $args, GraphQLContext $context): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $this->contexts->removeFile($project, $this->viewer($context));

        return $project->refresh();
    }

    /**
     * @param  array{projectId: int|string}  $args
     */
    public function clear(mixed $root, array $args): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $this->contexts->clear($project);

        return $project->refresh();
    }
}
