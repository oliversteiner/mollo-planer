<?php

return [
    'greeting' => 'Hello :name',
    'invitation' => [
        'subject' => 'Invitation to :app',
        'intro' => 'An account has been created for you at :app. Use the link below to set your password.',
        'action' => 'Set password',
        'expiry' => 'The link is valid for :days days.',
        'second_factor' => 'After your first login you will set up a second factor (authenticator app) or a passkey.',
    ],
    'reset' => [
        'subject' => 'Reset password',
        'intro' => 'You are receiving this e-mail because a new password was requested for your account.',
        'action' => 'Reset password',
        'expiry' => 'The link is valid for :minutes minutes.',
        'ignore' => 'If you did not request a new password, no further action is required.',
    ],
];
