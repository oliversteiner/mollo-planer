<?php

return [
    /*
     * Version of the GraphQL API (the endpoint /api). Breaking changes are announced first.
     */
    'version' => 'v1',

    /* Name of the site, shown on the status page. */
    'site_name' => 'Projektverwaltung',

    /*
     * Version of the application (shown on the status page). Set APP_VERSION when releasing.
     */
    'app_version' => env('APP_VERSION', '0.7.0'),
];
