<?php

namespace App\GraphQL\Errors;

use Closure;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Execution\ErrorHandler;

/**
 * Replaces the default English refusal of Laravel and Lighthouse ("This
 * action is unauthorized.") with the sentence in the language of the
 * request. Refusals with their own message (archived project, parent in
 * the trash, ground rules) pass unchanged.
 */
class TranslatedRefusalHandler implements ErrorHandler
{
    public function __invoke(?Error $error, Closure $next): ?array
    {
        if ($error === null || $error->getMessage() !== AuthorizationException::MESSAGE) {
            return $next($error);
        }

        return $next(new Error(
            __('graphql.unauthorized'),
            $error->getNodes(),
            $error->getSource(),
            $error->getPositions(),
            $error->getPath(),
            $error->getPrevious(),
            $error->getExtensions(),
        ));
    }

    /**
     * Whether a message is the default refusal, in any language of the app.
     */
    public static function isDefaultRefusal(string $message): bool
    {
        foreach ([AuthorizationException::MESSAGE, trans('graphql.unauthorized', [], 'de'), trans('graphql.unauthorized', [], 'en')] as $refusal) {
            if (str_contains($message, $refusal)) {
                return true;
            }
        }

        return false;
    }
}
