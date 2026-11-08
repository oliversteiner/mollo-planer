<?php

return [
    'create_admin' => [
        'name' => 'Name of the admin',
        'email' => 'Email address (used to log in)',
        'password' => 'Password',
        'password_confirmation' => 'Repeat the password',
        'password_mismatch' => 'The passwords do not match.',
        'password_needs_interaction' => 'With --password the password is asked interactively. Without a terminal, send an invitation instead (without --password).',
        'invalid_locale' => 'The language must be de or en.',
        'invited' => 'Admin :email created. The invitation is sent through the queue. A second factor or passkey is set up at the first login.',
        'created' => 'Admin :email created. A second factor or passkey is set up at the first login.',
    ],
];
