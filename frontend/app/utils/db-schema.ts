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
  'label_sets',
  'labels',
  'label_task',
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
      { name: 'role', type: 'varchar', default: 'mitglied', values: ['admin', 'projektleitung', 'koordination', 'mitglied', 'lesend'] },
      { name: 'job_function', type: 'varchar', nullable: true, values: ['designer', 'entwicklung', 'tester', 'content', 'technik', 'volunteer'] },
      { name: 'notify_by_email', type: 'tinyint', default: '1' },
      { name: 'avatar_path', type: 'varchar', nullable: true, private: true },
      { name: 'email_verified_at', type: 'timestamp', nullable: true, private: true },
      { name: 'approved_at', type: 'timestamp', nullable: true },
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
      { name: 'label_set_id', type: 'bigint', nullable: true, references: 'label_sets' },
      { name: 'locale', type: 'varchar', default: 'de' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['label_set_id'] }
    ]
  },
  project_user: {
    name: 'project_user',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'user_id', type: 'bigint', references: 'users' },
      { name: 'role', type: 'varchar', default: 'member', values: ['owner', 'manager', 'member'] },
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
      { name: 'status', type: 'varchar', default: 'open', values: ['open', 'done'] },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['project_id'] }
    ]
  },
  todos: {
    name: 'todos',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'milestone_id', type: 'bigint', references: 'milestones' },
      { name: 'project_id', type: 'bigint', references: 'projects' },
      { name: 'title', type: 'varchar' },
      { name: 'description', type: 'longtext', nullable: true },
      { name: 'icon', type: 'varchar', nullable: true },
      { name: 'due_date', type: 'date', nullable: true },
      { name: 'status', type: 'varchar', default: 'open', values: ['open', 'done'] },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['milestone_id'] }
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
      { name: 'icon', type: 'varchar', nullable: true },
      { name: 'status', type: 'varchar', default: 'open', values: ['open', 'in_progress', 'review', 'done'] },
      { name: 'position', type: 'int', default: '0' },
      { name: 'due_date', type: 'date', nullable: true },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['assigned_user_id'] },
      { kind: 'index', columns: ['todo_id', 'status'] },
      { kind: 'index', columns: ['project_id', 'status', 'position'] }
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
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['task_id'] },
      { kind: 'index', columns: ['user_id'] }
    ]
  },
  label_sets: {
    name: 'label_sets',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'key', type: 'varchar', nullable: true },
      { name: 'name', type: 'varchar' },
      { name: 'description', type: 'varchar', nullable: true },
      { name: 'created_by', type: 'bigint', nullable: true, references: 'users' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'index', columns: ['created_by'] },
      { kind: 'unique', columns: ['key'] }
    ]
  },
  labels: {
    name: 'labels',
    columns: [
      { name: 'id', type: 'bigint', primary: true },
      { name: 'label_set_id', type: 'bigint', references: 'label_sets' },
      { name: 'key', type: 'varchar', nullable: true },
      { name: 'name', type: 'varchar' },
      { name: 'category', type: 'varchar', nullable: true },
      { name: 'color', type: 'varchar', values: ['gray', 'red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'indigo', 'purple', 'pink'] },
      { name: 'position', type: 'smallint', default: '0' },
      { name: 'created_at', type: 'timestamp', nullable: true },
      { name: 'updated_at', type: 'timestamp', nullable: true }
    ],
    indexes: [
      { kind: 'unique', columns: ['label_set_id', 'key'] },
      { kind: 'unique', columns: ['label_set_id', 'name'] }
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
