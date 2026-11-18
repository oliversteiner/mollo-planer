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
    'date_format' => 'd.m.Y',
    'settings_hint' => 'Du kannst E-Mail-Benachrichtigungen in deinem Profil abschalten.',
    'task' => [
        'location' => 'Projekt: :project, ToDo: :todo',
        'due' => 'Fällig am :date.',
        'action' => 'Task öffnen',
    ],
    'task_assigned' => [
        'subject' => '[:project] Task zugewiesen: :task',
        'intro' => ':actor hat dir den Task «:task» zugewiesen.',
    ],
    'task_commented' => [
        'subject' => '[:project] Neuer Kommentar: :task',
        'intro' => ':actor hat den Task «:task» kommentiert.',
        'action' => 'Kommentar lesen',
    ],
    'awaiting_approval' => [
        'subject' => '[:app] Neues Konto wartet auf Freigabe',
        'intro' => ':name hat sich registriert und die E-Mail-Adresse bestätigt. Das Konto wartet auf Freigabe.',
        'action' => 'Benutzer öffnen',
    ],
    'already_registered' => [
        'subject' => 'Du hast schon ein Konto bei :app',
        'intro' => 'Jemand hat sich mit deiner E-Mail-Adresse bei :app registriert, aber du hast schon ein Konto. Wenn du das warst, melde dich an.',
        'action' => 'Anmelden',
        'forgot' => 'Passwort vergessen? Setze es hier neu: :url',
        'ignore' => 'Wenn du dich nicht registriert hast, musst du nichts tun. Dein Konto bleibt, wie es ist.',
    ],
    'registration_pending' => [
        'subject' => 'Deine Registrierung bei :app ist noch nicht bestätigt',
        'intro' => 'Für diese Adresse gibt es bei :app schon eine Registrierung, die noch nicht bestätigt ist. Ist sie von dir: Lass dir die Bestätigungsmail erneut senden. Kennst du das Passwort nicht: Setze es mit «Passwort vergessen» neu.',
        'action' => 'Bestätigungsmail erneut senden',
    ],
    'security_change' => [
        'not_you' => 'Warst du das nicht, melde dich bei einem Admin.',
        'password' => [
            'subject' => 'Dein Passwort wurde geändert',
            'line' => 'Das Passwort deines Kontos bei :app wurde geändert.',
        ],
        'authenticator_added' => [
            'subject' => 'Eine Authenticator-App wurde eingerichtet',
            'line' => 'Für dein Konto bei :app wurde eine Authenticator-App als zweiter Faktor eingerichtet.',
        ],
        'authenticator_removed' => [
            'subject' => 'Die Authenticator-App wurde entfernt',
            'line' => 'Die Authenticator-App wurde von deinem Konto bei :app entfernt.',
        ],
        'passkey_added' => [
            'subject' => 'Ein Passkey wurde hinzugefügt',
            'line' => 'Zu deinem Konto bei :app wurde ein Passkey hinzugefügt.',
        ],
        'passkey_removed' => [
            'subject' => 'Ein Passkey wurde entfernt',
            'line' => 'Von deinem Konto bei :app wurde ein Passkey entfernt.',
        ],
        'admin_reset' => [
            'subject' => 'Dein zweiter Faktor wurde zurückgesetzt',
            'line' => 'Dein zweiter Faktor wurde von einem Admin zurückgesetzt. Du richtest bei der nächsten Anmeldung einen neuen ein.',
            'not_you' => 'Warst du das nicht, melde dich sofort.',
        ],
        'recovery_codes' => [
            'subject' => 'Neue Wiederherstellungscodes',
            'line' => 'Für dein Konto bei :app wurden neue Wiederherstellungscodes erzeugt. Die alten gelten nicht mehr.',
        ],
    ],
    'verify_email' => [
        'subject' => 'Bitte bestätige deine E-Mail-Adresse',
        'intro' => 'Du hast dich bei :app registriert. Bitte bestätige deine E-Mail-Adresse mit dem Button unten.',
        'action' => 'E-Mail-Adresse bestätigen',
        'expiry' => 'Der Link ist :minutes Minuten gültig.',
        'ignore' => 'Wenn du dich nicht registriert hast, klick nicht auf den Link und tu nichts.',
        'approval' => 'Danach richtest du deinen zweiten Faktor ein, und ein Admin gibt dein Konto frei.',
    ],
];
