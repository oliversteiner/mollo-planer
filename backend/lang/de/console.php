<?php

return [
    'create_admin' => [
        'name' => 'Name der Admin-Person',
        'email' => 'E-Mail-Adresse (für die Anmeldung)',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwort wiederholen',
        'password_mismatch' => 'Die Passwörter stimmen nicht überein.',
        'password_needs_interaction' => 'Mit --password wird das Passwort interaktiv abgefragt. Ohne Terminal stattdessen eine Einladung schicken (ohne --password).',
        'invalid_locale' => 'Die Sprache muss de oder en sein.',
        'invited' => 'Admin :email angelegt. Die Einladung wird über die Queue verschickt. Beim ersten Login wird ein zweiter Faktor oder Passkey eingerichtet.',
        'created' => 'Admin :email angelegt. Beim ersten Login wird ein zweiter Faktor oder Passkey eingerichtet.',
    ],
];
