import { readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import de from '../../i18n/locales/de.json'
import en from '../../i18n/locales/en.json'
import { DB_TABLE_NAMES, dbTable } from '../../app/utils/db-schema'

const migrationsDir = fileURLToPath(new URL('../../../backend/database/migrations', import.meta.url))

/** Blueprint methods that do not define a column. */
const NOT_A_COLUMN = new Set(['index', 'unique', 'primary', 'foreign', 'dropColumn', 'dropIndex', 'dropForeign', 'renameColumn', 'renameIndex'])

/** The "up" part of every migration, in the order the migrations run. */
function upSources(): string[] {
  return readdirSync(migrationsDir)
    .filter(name => name.endsWith('.php'))
    .sort()
    .map(name => readFileSync(`${migrationsDir}/${name}`, 'utf8').split('public function down()')[0] ?? '')
}

/**
 * The columns of a table after all migrations: created by Schema::create,
 * added by Schema::table, and removed again by dropColumn. Events are applied
 * in the order they appear in each block.
 */
function columnsAfterMigrations(table: string): string[] {
  let columns = new Set<string>()

  for (const source of upSources()) {
    const blocks = [...source.matchAll(/Schema::(?:create|table)\('(\w+)'/g)]

    blocks.forEach((match, index) => {
      if (match[1] !== table) return

      const start = match.index ?? 0
      const end = blocks[index + 1]?.index ?? source.length
      const body = source.slice(start, end)

      const events: { at: number, add?: string[], drop?: string[], rename?: [string, string] }[] = []

      for (const column of body.matchAll(/\$table->(\w+)\('(\w+)'/g)) {
        if (!NOT_A_COLUMN.has(column[1] ?? '')) events.push({ at: column.index, add: [column[2] ?? ''] })
      }
      for (const drop of body.matchAll(/\$table->dropColumn\((\[[^\]]*\]|'\w+')\)/g)) {
        events.push({ at: drop.index, drop: [...(drop[1] ?? '').matchAll(/'(\w+)'/g)].map(name => name[1] ?? '') })
      }
      for (const rename of body.matchAll(/\$table->renameColumn\('(\w+)',\s*'(\w+)'\)/g)) {
        events.push({ at: rename.index, rename: [rename[1] ?? '', rename[2] ?? ''] })
      }
      if (/\$table->id\(\)/.test(body)) events.push({ at: body.indexOf('$table->id()'), add: ['id'] })
      if (/\$table->timestamps\(\)/.test(body)) events.push({ at: body.indexOf('$table->timestamps()'), add: ['created_at', 'updated_at'] })
      if (/\$table->rememberToken\(\)/.test(body)) events.push({ at: body.indexOf('$table->rememberToken()'), add: ['remember_token'] })

      for (const event of events.sort((a, b) => a.at - b.at)) {
        if (event.add) event.add.forEach(name => columns.add(name))
        if (event.drop) columns = new Set([...columns].filter(name => !event.drop?.includes(name)))
        if (event.rename && columns.has(event.rename[0])) {
          columns.delete(event.rename[0])
          columns.add(event.rename[1])
        }
      }
    })
  }

  return [...columns].sort()
}

type Locale = typeof de

describe('db documentation', () => {
  it('has a navigation entry and a title and description in both languages for every table', () => {
    expect(DB_TABLE_NAMES).toHaveLength(11)

    for (const name of DB_TABLE_NAMES) {
      for (const locale of [de, en] as Locale[]) {
        expect(locale.db.tables[name]?.title, `${name} title`).toBeTruthy()
        expect(locale.db.tables[name]?.description, `${name} description`).toBeTruthy()
      }
    }
  })

  for (const name of DB_TABLE_NAMES) {
    it(`documents the columns of ${name} exactly as the migrations leave them`, () => {
      const documented = dbTable(name).columns.map(column => column.name).sort()

      expect(documented).toEqual(columnsAfterMigrations(name))
    })

    it(`has a label and a description in both languages for every column of ${name}`, () => {
      for (const column of dbTable(name).columns) {
        for (const locale of [de, en] as Locale[]) {
          const text = (locale.db.columns as Record<string, Record<string, { label?: string, description?: string }>>)[name]?.[column.name]

          expect(text?.label, `${name}.${column.name} label`).toBeTruthy()
          expect(text?.description, `${name}.${column.name} description`).toBeTruthy()
        }
      }
    })
  }

  it('does not document personal data without marking it', () => {
    const users = dbTable('users').columns

    for (const name of ['email', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token']) {
      expect(users.find(column => column.name === name)?.private, name).toBe(true)
    }
  })
})
