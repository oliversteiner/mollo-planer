<?php

namespace App\GraphQL\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Schema\Directives\EqDirective;

/**
 * Like @eq, for optional filters: an explicit null means "no filter".
 * Lighthouse's @eq turns null into "column IS NULL", so a client sending
 * { status: null } would find nothing.
 */
class FilterDirective extends EqDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Optional equality filter: applies like @eq, but null (or a missing argument) applies no filter.
"""
directive @filter(
  """
  The database column to compare, if it differs from the argument name.
  """
  key: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * @param  QueryBuilder|EloquentBuilder<Model>|Relation<Model>  $builder
     * @return QueryBuilder|EloquentBuilder<Model>|Relation<Model>
     */
    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        return $value === null ? $builder : parent::handleBuilder($builder, $value);
    }
}
