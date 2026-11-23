/**
 * Structure of the database tables for the documentation pages (/system/db): the
 * application tables, not the tables of Laravel packages. Technical facts from the
 * schema (backend/database/migrations), checked by tests/unit/db-schema.test.ts.
 * The texts (titles, labels, descriptions) live in i18n/locales under "db".
 */

export interface DbColumn {
  name: string
  type: string
  primary?: boolean
  nullable?: boolean
  /** Table this column points to (foreign key). */
  references?: string
  /** Default value, as the schema has it. */
  default?: string
  /** Allowed values of a column that holds one of a fixed set. */
  values?: string[]
  /** Personal data: marked in the documentation. */
  private?: boolean
}

export interface DbIndex {
  kind: 'unique' | 'index'
  columns: string[]
}

export interface DbTable {
  name: string
  columns: DbColumn[]
  indexes: DbIndex[]
}

/** Order of the navigation on /system/db. */
export const DB_TABLE_NAMES = [
  'users',
  'projects',
  'project_user',
  'milestones',
  'todos',
  'tasks',
  'comments',
  'templates',
  'labels',
  'label_task',
  'project_webhooks',
  'project_ai_contexts',
  'ideas',
  'roles',
  'functions',
  'function_rights',
  'role_functions',
  'app_settings'
] as const

export type DbTableName = typeof DB_TABLE_NAMES[number]

export function isDbTableName(name: string): name is DbTableName {
  return (DB_TABLE_NAMES as readonly string[]).includes(name)
}

const TABLES: Record<DbTableName, DbTable> = {
  users: {
    name: 'users',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'name', type: 'varchar' },
      { name: 'email', type: 'varchar', private: true },
      { name: 'role_id', type: 'bigint', nullable: true, references: 'roles' },
      { name: 'activity', type: 'varchar', nullable: true, values: ['designer', 'entwicklung', 'tester', 'content', 'technik', 'volunteer'] },
      { name: 'notify_by_email', type: 'tinyint', default: '1' },
      { name: 'avatar_path', type: 'varchar', nullable: true, private: true },
      { name: 'locale', type: 'varchar', nullable: true },
      { name: 'email_verified_at', type: 'timestamp', nullable: true, private: true },
      { name: 'approved_at', type: 'timestamp', nullable: true },
      { name: 'is_agent', type: 'tinyint', default: '0' },
      { name: 'password', type: 'varchar', private: true },
      { name: 'two_factor_secret', type: 'text', nullable: true, private: true },
      { name: 'two_factor_recovery_codes', type: 'text', nullable: true, private: true },
      { name: 'two_factor_confirmed_at', type: 'timestamp', nullable: true, private: true },
      { name: 'remember_token', type: 'varchar', nullable: true, private: true },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['email'] }
    ]
  },
  projects: {
    name: 'projects',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'name', type: 'varchar' },
      { name: 'description', type: 'text', nullable: true },
      { name: 'icon', type: 'varchar', nullable: true },
      { name: 'template_id', type: 'bigint', nullable: true, references: 'templates' },
      { name: 'locale', type: 'varchar', default: 'de' },
      { name: 'planer_revision', type: 'int', default: '0' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true },
      { name: 'archived_at', type: 'timestamp', nullable: true },
      { name: 'deleted_at', type: 'timestamp', nullable: true },
      { name: 'deleted_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'deletion_id', type: 'varchar', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['deletion_id'] },
      { kind: 'index', columns: ['label_set_id'] }
    ]
  },
  project_user: {
    name: 'project_user',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'user_id', type: 'bigint', references: 'users' },
      { name: 'role_id', type: 'bigint', nullable: true, references: 'roles' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['project_id', 'user_id'] },
      { kind: 'index', columns: ['user_id'] }
    ]
  },
  milestones: {
    name: 'milestones',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'position', type: 'int', default: '0' },
      { name: 'title', type: 'varchar' },
      { name: 'description', type: 'longtext', nullable: true },
      { name: 'icon', type: 'varchar', nullable: true },
      { name: 'due_date', type: 'date', nullable: true },
      { name: 'release_version', type: 'varchar', nullable: true },
      { name: 'release_status', type: 'varchar', nullable: true, values: ['planned', 'released'] },
      { name: 'release_date', type: 'date', nullable: true },
      { name: 'status', type: 'varchar', default: 'open', values: ['open', 'done'] },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true },
      { name: 'deleted_at', type: 'timestamp', nullable: true },
      { name: 'deleted_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'deletion_id', type: 'varchar', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['deletion_id'] },
      { kind: 'index', columns: ['project_id'] }
    ]
  },
  todos: {
    name: 'todos',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'milestone_id', type: 'bigint', references: 'milestones' },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'position', type: 'int', default: '0' },
      { name: 'title', type: 'varchar' },
      { name: 'description', type: 'longtext', nullable: true },
      { name: 'icon', type: 'varchar', nullable: true },
      { name: 'due_date', type: 'date', nullable: true },
      { name: 'status', type: 'varchar', default: 'open', values: ['open', 'done'] },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true },
      { name: 'deleted_at', type: 'timestamp', nullable: true },
      { name: 'deleted_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'deletion_id', type: 'varchar', nullable: true },
      { name: 'idea_id', type: 'bigint', nullable: true, references: 'ideas' }
    ],
    indexes: [
      { kind: 'index', columns: ['deletion_id'] },
      { kind: 'index', columns: ['milestone_id'] },
      { kind: 'index', columns: ['milestone_id', 'position'] }
    ]
  },
  tasks: {
    name: 'tasks',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'todo_id', type: 'bigint', references: 'todos' },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'assigned_user_id', type: 'bigint', nullable: true, references: 'users' },
      { name: 'title', type: 'varchar' },
      { name: 'description', type: 'longtext', nullable: true },
      { name: 'user_story', type: 'json', nullable: true },
      { name: 'icon', type: 'varchar', nullable: true },
      { name: 'status', type: 'varchar', default: 'open', values: ['idea', 'planned', 'open', 'in_progress', 'review', 'done'] },
      { name: 'done_at', type: 'timestamp', nullable: true },
      { name: 'commit', type: 'varchar', nullable: true },
      { name: 'board_position', type: 'int', default: '0' },
      { name: 'position', type: 'int', default: '0' },
      { name: 'due_date', type: 'date', nullable: true },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true },
      { name: 'deleted_at', type: 'timestamp', nullable: true },
      { name: 'deleted_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'deletion_id', type: 'varchar', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['deletion_id'] },
      { kind: 'index', columns: ['assigned_user_id'] },
      { kind: 'index', columns: ['todo_id', 'status'] },
      { kind: 'index', columns: ['project_id', 'status', 'board_position'] },
      { kind: 'index', columns: ['todo_id', 'position'] }
    ]
  },
  comments: {
    name: 'comments',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'task_id', type: 'bigint', references: 'tasks' },
      { name: 'user_id', type: 'bigint', nullable: true, references: 'users' },
      { name: 'body', type: 'longtext' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true },
      { name: 'deleted_at', type: 'timestamp', nullable: true },
      { name: 'deleted_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'deletion_id', type: 'varchar', nullable: true },
      { name: 'edited_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['deletion_id'] },
      { kind: 'index', columns: ['task_id'] },
      { kind: 'index', columns: ['user_id'] }
    ]
  },
  templates: {
    name: 'templates',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'key', type: 'varchar', nullable: true },
      { name: 'name', type: 'varchar' },
      { name: 'description', type: 'text', nullable: true },
      { name: 'locale', type: 'varchar' },
      { name: 'content', type: 'json' },
      { name: 'created_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['created_by'] },
      { kind: 'unique', columns: ['key'] },
      { kind: 'unique', columns: ['name'] }
    ]
  },
  labels: {
    name: 'labels',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'key', type: 'varchar' },
      { name: 'names', type: 'json' },
      { name: 'category', type: 'varchar', nullable: true },
      { name: 'color', type: 'varchar', values: ['gray', 'red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'indigo', 'purple', 'pink'] },
      { name: 'position', type: 'smallint', default: '0' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['project_id', 'key'] }
    ]
  },
  project_webhooks: {
    name: 'project_webhooks',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'kind', type: 'varchar', values: ['github', 'generic'] },
      { name: 'target', type: 'varchar' },
      { name: 'secret', type: 'text', private: true },
      { name: 'last_sent_revision', type: 'int', nullable: true },
      { name: 'last_sent_at', type: 'timestamp', nullable: true },
      { name: 'last_failed_at', type: 'timestamp', nullable: true },
      { name: 'last_error', type: 'varchar', nullable: true },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['project_id'] }
    ]
  },
  project_ai_contexts: {
    name: 'project_ai_contexts',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'text', type: 'longtext', nullable: true },
      { name: 'file_name', type: 'varchar', nullable: true },
      { name: 'file_text', type: 'longtext', nullable: true },
      { name: 'shared_with_tools', type: 'boolean', default: '0' },
      { name: 'updated_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['project_id'] }
    ]
  },
  ideas: {
    name: 'ideas',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'user_id', type: 'bigint', references: 'users' },
      { name: 'project_id', type: 'bigint', nullable: true, references: 'projects' },
      { name: 'title', type: 'varchar' },
      { name: 'description', type: 'json', nullable: true },
      { name: 'draft', type: 'longtext', nullable: true },
      { name: 'status', type: 'varchar', default: 'open', values: ['open', 'adopted', 'discarded'] },
      { name: 'adopted_at', type: 'timestamp', nullable: true },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true },
      { name: 'deleted_at', type: 'timestamp', nullable: true },
      { name: 'deleted_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'deletion_id', type: 'varchar', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['deletion_id'] },
      { kind: 'index', columns: ['user_id', 'status'] }
    ]
  },
  label_task: {
    name: 'label_task',
    columns: [
      { name: 'label_id', type: 'bigint', primary: true, references: 'labels' },
      { name: 'task_id', type: 'bigint', primary: true, references: 'tasks' }
    ],
    indexes: [
      { kind: 'index', columns: ['task_id'] }
    ]
  },
  roles: {
    name: 'roles',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'scope', type: 'varchar', values: ['system', 'project'] },
      { name: 'key', type: 'varchar' },
      { name: 'names', type: 'json' },
      { name: 'project_cap', type: 'json', nullable: true },
      { name: 'is_default', type: 'tinyint', default: '0' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['scope', 'key'] }
    ]
  },
  functions: {
    name: 'functions',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'scope', type: 'varchar', values: ['system', 'project'] },
      { name: 'key', type: 'varchar' },
      { name: 'names', type: 'json' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['scope', 'key'] }
    ]
  },
  function_rights: {
    name: 'function_rights',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'function_id', type: 'bigint', references: 'functions' },
      { name: 'right', type: 'varchar' }
    ],
    indexes: [
      { kind: 'unique', columns: ['function_id', 'right'] }
    ]
  },
  role_functions: {
    name: 'role_functions',
    columns: [
      { name: 'role_id', type: 'bigint', primary: true, references: 'roles' },
      { name: 'function_id', type: 'bigint', primary: true, references: 'functions' }
    ],
    indexes: [
      { kind: 'index', columns: ['function_id'] }
    ]
  },
  app_settings: {
    name: 'app_settings',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'key', type: 'varchar' },
      { name: 'value', type: 'longtext' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['key'] }
    ]
  }
}

/** The documented table with the given name. */
export function dbTable(name: DbTableName): DbTable {
  return TABLES[name]
}
