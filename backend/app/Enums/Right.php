<?php

namespace App\Enums;

/**
 * Every right the code checks (docs/rollen-funktionen-rechte.md, §4). The
 * values match the GraphQL enum Right. A right belongs to exactly one scope:
 * project rights hold within one project, system rights in the whole app.
 *
 * Which role has which right is data (tables roles, functions,
 * function_rights, role_functions) and is answered only by
 * App\Support\Rights. The short texts for the settings page are in
 * lang/{de,en}/rights.php.
 */
enum Right: string
{
    // Project
    case PROJECT_UPDATE = 'project.update';
    case PROJECT_DELETE = 'project.delete';
    /** Archive a project and bring it back. */
    case PROJECT_ARCHIVE = 'project.archive';
    case MEMBERS_MANAGE = 'members.manage';
    /** Grant or take away a role that carries project.delete or owner.manage. */
    case OWNER_MANAGE = 'owner.manage';
    case MILESTONE_CREATE = 'milestone.create';
    case MILESTONE_UPDATE = 'milestone.update';
    case MILESTONE_DELETE = 'milestone.delete';
    case TODO_CREATE = 'todo.create';
    case TODO_UPDATE = 'todo.update';
    case TODO_DELETE = 'todo.delete';
    case TASK_CREATE = 'task.create';
    case TASK_UPDATE = 'task.update';
    /** Change tasks created by the person (not those of others). */
    case TASK_UPDATE_OWN = 'task.updateOwn';
    case TASK_ASSIGN = 'task.assign';
    /** Assign a task to oneself (or remove oneself as assignee). */
    case TASK_ASSIGN_SELF = 'task.assignSelf';
    case TASK_MOVE = 'task.move';
    case TASK_DELETE = 'task.delete';
    case COMMENT_CREATE = 'comment.create';
    /** Delete comments written by someone else. Editing them is never allowed. */
    case COMMENT_MODERATE = 'comment.moderate';
    case TRASH_RESTORE = 'trash.restore';
    case TRASH_PURGE = 'trash.purge';
    case PLANER_EXPORT = 'planer.export';
    case PLANER_IMPORT = 'planer.import';
    case AI_CONTEXT_UPDATE = 'aiContext.update';

    // System
    case PROJECTS_CREATE = 'projects.create';
    /** See every project, even without membership. */
    case PROJECTS_VIEW_ALL = 'projects.viewAll';
    /** Every project right in every project, whatever the membership. */
    case PROJECTS_MANAGE_ANY = 'projects.manageAny';
    /** Full user list, create, change and delete users. */
    case USERS_MANAGE = 'users.manage';
    /** Roles and functions in the settings. */
    case ROLES_MANAGE = 'roles.manage';
    /** Create and duplicate templates, change and delete one's own. */
    case TEMPLATES_MANAGE = 'templates.manage';
    /** Change and delete the templates of others. */
    case TEMPLATES_MANAGE_ANY = 'templates.manageAny';
    case ASSISTANT_USE = 'assistant.use';
    case IDEAS_CREATE = 'ideas.create';
    case IDEAS_VIEW_ANY = 'ideas.viewAny';
    /** App settings, for example the two-factor switch. */
    case SETTINGS_MANAGE = 'settings.manage';
    /** The status page. */
    case SYSTEM_VIEW = 'system.view';
    /** The database documentation. */
    case DATABASE_VIEW = 'database.view';

    public const SCOPE_PROJECT = 'project';

    public const SCOPE_SYSTEM = 'system';

    /** Rights that hold in the whole app. Every other right is a project right. */
    private const SYSTEM = [
        self::PROJECTS_CREATE,
        self::PROJECTS_VIEW_ALL,
        self::PROJECTS_MANAGE_ANY,
        self::USERS_MANAGE,
        self::ROLES_MANAGE,
        self::TEMPLATES_MANAGE,
        self::TEMPLATES_MANAGE_ANY,
        self::ASSISTANT_USE,
        self::IDEAS_CREATE,
        self::IDEAS_VIEW_ANY,
        self::SETTINGS_MANAGE,
        self::SYSTEM_VIEW,
        self::DATABASE_VIEW,
    ];

    public function scope(): string
    {
        return in_array($this, self::SYSTEM, true) ? self::SCOPE_SYSTEM : self::SCOPE_PROJECT;
    }

    /**
     * Still holds in an archived project (rollen-funktionen-rechte.md §7.7):
     * reading (the export is reading), bringing it back, restoring from its
     * trash. Every other project right ends with the archive.
     */
    public function holdsWhenArchived(): bool
    {
        return in_array($this, [self::PROJECT_ARCHIVE, self::TRASH_RESTORE, self::PLANER_EXPORT], true);
    }

    public function isSystem(): bool
    {
        return $this->scope() === self::SCOPE_SYSTEM;
    }

    /** Short text for the settings page. */
    public function label(?string $locale = null): string
    {
        return (string) __('rights.'.$this->value, [], $locale);
    }

    /**
     * All rights of one scope, in the order of the enum.
     *
     * @return list<self>
     */
    public static function ofScope(string $scope): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $right): bool => $right->scope() === $scope,
        ));
    }
}
