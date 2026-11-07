<?php

namespace App\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

/**
 * A TipTap document as a JSON object.
 *
 * The scalar only checks that the input is an object. The whitelist is
 * checked by App\Rules\RichTextRule, so errors appear per field under
 * extensions.validation.
 */
class RichText extends ScalarType
{
    public ?string $description = 'Formatted text as a TipTap document (JSON object with type "doc").';

    /**
     * @return array<string, mixed>|null
     */
    public function serialize($value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            throw new InvariantViolation('RichText cannot represent a non-object value.');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function parseValue($value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new Error('RichText must be a JSON object (TipTap document).');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $variables
     * @return array<string, mixed>
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): array
    {
        if (! $valueNode instanceof ObjectValueNode) {
            throw new Error('RichText must be a JSON object (TipTap document).', $valueNode);
        }

        return $this->parseValue(AST::valueFromASTUntyped($valueNode, $variables));
    }
}
