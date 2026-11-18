<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Template;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Template.viewerCanUpdate / viewerCanDelete, answered by the TemplatePolicy.
 */
class TemplateViewerCan
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     */
    public function update(Template $template, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('update', $template);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function delete(Template $template, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('delete', $template);
    }
}
