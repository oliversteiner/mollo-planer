<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // GraphQL, the Sanctum CSRF cookie and the Fortify/passkey/invitation routes.
    'paths' => [
        'api',
        'status',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'two-factor-challenge',
        'forgot-password',
        'reset-password',
        'invitation',
        'email/*',
        'register',
        'user/*',
        'passkeys/*',
    ],

    'allowed_methods' => ['*'],

    // Only the SPA. Cookies (session, XSRF-TOKEN) require an exact origin. More origins (for example the
    // development server reached from a tablet in the local network) go into CORS_ALLOWED_ORIGINS, separated
    // by commas; production leaves it empty.
    'allowed_origins' => array_values(array_unique(array_filter([
        env('FRONTEND_URL', 'http://localhost:3000'),
        ...array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
