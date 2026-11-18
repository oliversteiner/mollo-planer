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
    'date_format' => 'Y-m-d',
    'settings_hint' => 'You can turn off e-mail notifications in your profile.',
    'task' => [
        'location' => 'Project: :project, to-do: :todo',
        'due' => 'Due on :date.',
        'action' => 'Open task',
    ],
    'task_assigned' => [
        'subject' => '[:project] Task assigned: :task',
        'intro' => ':actor assigned the task “:task” to you.',
    ],
    'task_commented' => [
        'subject' => '[:project] New comment: :task',
        'intro' => ':actor commented on the task “:task”.',
        'action' => 'Read comment',
    ],
    'awaiting_approval' => [
        'subject' => '[:app] New account waiting for approval',
        'intro' => ':name registered and confirmed the email address. The account is waiting for approval.',
        'action' => 'Open users',
    ],
    'already_registered' => [
        'subject' => 'You already have an account at :app',
        'intro' => 'Somebody registered at :app with your email address, but you already have an account. If that was you, sign in.',
        'action' => 'Sign in',
        'forgot' => 'Forgot your password? Set a new one here: :url',
        'ignore' => 'If you did not register, you need not do anything. Your account stays as it is.',
    ],
    'registration_pending' => [
        'subject' => 'Your registration at :app is not confirmed yet',
        'intro' => 'There is already a registration for this address at :app that is not confirmed yet. If it is yours: have the confirmation mail sent again. If you do not know the password: set a new one with “Forgot password”.',
        'action' => 'Send the confirmation mail again',
    ],
    'verify_email' => [
        'subject' => 'Please confirm your email address',
        'intro' => 'You registered at :app. Please confirm your email address with the button below.',
        'action' => 'Confirm email address',
        'expiry' => 'The link is valid for :minutes minutes.',
        'ignore' => 'If you did not register, do not click the link and do nothing.',
        'approval' => 'After that you set up your second factor, and an admin releases your account.',
    ],
];
