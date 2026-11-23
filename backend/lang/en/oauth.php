<?php

return [
    'scopes' => [
        'openid' => 'Confirm your identity',
        'profile' => 'Read your name',
        'email' => 'Read your email address',
        'api' => 'Work in the planer with your permissions',
        'mcp:use' => 'Use the planer through the connector',
        'mcp:read' => 'Read plans and comments',
        'mcp:write' => 'Change plans, tasks and comments',
    ],

    'client' => [
        'redirect_count' => '1 to :max redirect addresses are allowed.',
        'redirect_invalid' => '“:uri” is not an allowed redirect address. Allowed: https, http only for localhost, an own URL scheme only for public clients; no wildcards, no fragment.',
    ],
];
