<?php

return [
    'greeting' => 'Hallo :name',
    'invitation' => [
        'subject' => 'Einladung zu :app',
        'intro' => 'Für dich wurde ein Konto bei :app angelegt. Lege über den folgenden Link dein Passwort fest.',
        'action' => 'Passwort festlegen',
        'expiry' => 'Der Link ist :days Tage gültig.',
        'second_factor' => 'Nach der ersten Anmeldung richtest du einen zweiten Faktor (Authenticator-App) oder einen Passkey ein.',
    ],
    'reset' => [
        'subject' => 'Passwort zurücksetzen',
        'intro' => 'Du erhältst diese E-Mail, weil für dein Konto ein neues Passwort angefordert wurde.',
        'action' => 'Passwort zurücksetzen',
        'expiry' => 'Der Link ist :minutes Minuten gültig.',
        'ignore' => 'Falls du kein neues Passwort angefordert hast, musst du nichts tun.',
    ],
];
