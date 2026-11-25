<?php

namespace App\Core\Accounts\Local;

/**
 * Which tables are part of an account for the local snapshots (Auftrag 45). Every table of the schema is named here,
 * either as account data or with the reason why it is not: AccountTablesTest fails for a new table until it is sorted
 * in, so a new kind of credential cannot be forgotten.
 */
final class AccountTables
{
    /** Saved and restored: the account, its credentials and what belongs to the person. */
    public const ACCOUNT = [
        'users' => 'the account: password, second factor, system role, approval, settings of the person',
        'passkeys' => 'credentials of the account',
        'personal_access_tokens' => 'accesses of people and agents (connector)',
        'password_reset_tokens' => 'open invitations and password links, by address',
        'oauth_clients' => 'applications that log in through the planer, with their owner and who registered them',
    ];

    /** Not part of an account: content, settings of the app, framework tables, or credentials of one session. */
    public const NOT_ACCOUNT = [
        'app_settings' => 'settings of the app',
        'billing_settings' => 'settings of the billing (sender)',
        'cache' => 'framework',
        'cache_locks' => 'framework',
        'comments' => 'content',
        'customers' => 'content (billing)',
        'document_shares' => 'content (sharing of a document)',
        'document_versions' => 'content',
        'documents' => 'content',
        'failed_jobs' => 'framework',
        'function_rights' => 'roles and rights (config/permissions.php)',
        'functions' => 'roles and rights (config/permissions.php)',
        'ideas' => 'content',
        'job_batches' => 'framework',
        'jobs' => 'framework',
        'label_sets' => 'content',
        'label_task' => 'content',
        'labels' => 'content',
        'mcp_tool_calls' => 'usage log of the connector',
        'migrations' => 'framework',
        'milestones' => 'content',
        'oauth_access_tokens' => 'one login of an application; made again by signing in',
        'oauth_auth_code_claims' => 'one login of an application',
        'oauth_auth_codes' => 'one login of an application',
        'oauth_device_codes' => 'one login of an application',
        'oauth_refresh_tokens' => 'one login of an application',
        'project_ai_contexts' => 'content',
        'project_user' => 'membership in a project is content, not account (Auftrag 45 A)',
        'project_webhooks' => 'content (settings of a project)',
        'projects' => 'content',
        'rights_defaults_applied' => 'roles and rights',
        'role_functions' => 'roles and rights',
        'roles' => 'roles and rights (the system role of an account is saved by its key)',
        'sessions' => 'one session; made again by signing in',
        'tasks' => 'content',
        'templates' => 'content',
        'todos' => 'content',
    ];
}
