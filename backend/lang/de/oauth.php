<?php

return [
    'scopes' => [
        'openid' => 'Deine Identität bestätigen',
        'profile' => 'Deinen Namen lesen',
        'email' => 'Deine E-Mail-Adresse lesen',
        'api' => 'Im Planer mit deinen Rechten arbeiten',
        'mcp:use' => 'Den Planer über den Connector benutzen',
        'mcp:read' => 'Pläne und Kommentare lesen',
        'mcp:write' => 'Pläne, Aufgaben und Kommentare ändern',
    ],

    'client' => [
        'redirect_count' => 'Es sind 1 bis :max Redirect-Adressen erlaubt.',
        'redirect_invalid' => '«:uri» ist keine erlaubte Redirect-Adresse. Erlaubt: https, http nur für localhost, ein eigenes URL-Schema nur für öffentliche Clients; keine Platzhalter, kein Fragment.',
    ],
];
