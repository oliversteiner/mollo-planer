<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers in the language the frontend asks for (Accept-Language), de or
 * en. Without the header the app locale (APP_LOCALE) applies. Mails about
 * a project use the project's language instead (see notifications).
 */
class SetLocaleFromRequest
{
    public const SUPPORTED = ['de', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('Accept-Language')) {
            $preferred = $request->getPreferredLanguage(self::SUPPORTED);

            if ($preferred !== null) {
                app()->setLocale($preferred);
            }
        }

        return $next($request);
    }
}
