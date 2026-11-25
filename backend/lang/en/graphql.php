<?php

return [
    'unauthorized' => 'You are not allowed to do this.',
    'second_factor_setup_required' => 'Please set up a second factor (authenticator app) or a passkey first.',
    'agent_account' => 'An agent account has no access to the app, only through the connector.',
    'strong_authentication_required' => 'Please sign in again using your second factor or a passkey.',
    'account_not_ready_email' => 'Please confirm your email address first. The link is in the confirmation mail.',
    'account_not_ready_approval' => 'Please wait until an admin approves your account.',
    'creator_role_needs_delete' => 'This role may not delete a project. Choose a role with the right “Own project”.',
    'two_factor_not_switchable' => 'Two-factor authentication cannot be switched off in production.',
    'agent_identifier_reserved' => 'Addresses at @agent.invalid are identifiers of agents. Enter a real email address.',
    'agent_access' => [
        'not_an_agent' => 'The account of :name is not an agent account. Accesses exist for agents only; people make their own access in the profile.',
    ],
    'password_confirmation_required' => 'Please confirm your password first.',
];
