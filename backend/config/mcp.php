<?php

/*
 * MCP server and its OAuth (docs/oauth-sso.md, docs/claude-connector.md). Only what the planer sets; the rest comes
 * from the package.
 */
return [
    /*
     * Where a client may be sent back to after it registered itself (dynamic client registration, RFC 7591): the
     * addresses of Claude, and localhost for the desktop and command line clients. Never "*". More with the
     * environment variable MCP_REDIRECT_DOMAINS (comma separated, with the scheme).
     */
    'redirect_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'MCP_REDIRECT_DOMAINS',
        'https://claude.ai/,https://claude.com/,http://localhost,http://127.0.0.1'
    ))))),

    /* Schemes of apps that may register (for example cursor://). None by default. */
    'custom_schemes' => [],

    /* The issuer of the authorization server is the API of this environment, the same as in the id_token. */
    'authorization_server' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
];
