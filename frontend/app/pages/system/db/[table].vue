<script setup lang="ts">
import type { DbColumn, DbIndex } from '~/utils/db-schema'
import { dbTable, isDbTableName } from '~/utils/db-schema'

/**
 * One page per table: the title, the name in the database, the columns (label,
 * name, type, properties, description) and the indexes.
 */
definePageMeta({ middleware: 'admin' })

const { t } = useI18n()
const route = useRoute()

const tableParam = String(route.params.table)

if (!isDbTableName(tableParam)) {
  throw createError({ statusCode: 404, statusMessage: 'Table not found', fatal: false })
}

const table = dbTable(tableParam)

useHead({ title: () => t(`db.tables.${table.name}.title`) })

/** Properties of a column as short words, in the current language. */
function properties(column: DbColumn): string[] {
  const list: string[] = []

  if (column.primary) list.push(t('db.flags.primary'))
  if (column.references) list.push(t('db.flags.references', { table: column.references }))
  if (column.nullable) list.push(t('db.flags.nullable'))
  if (column.default !== undefined) list.push(t('db.flags.default', { value: column.default }))
  if (column.values) list.push(t('db.flags.values', { values: column.values.join(', ') }))
  if (column.private) list.push(t('db.flags.private'))

  return list
}

/** An index as text, for example "Index: task_id". */
function indexText(index: DbIndex): string {
  return t(`db.constraint.${index.kind}`, { columns: index.columns.join(', ') })
}
</script>

<template>
  <section class="db-table-page">
    <h2 class="wa-heading-m db-title">
      {{ t(`db.tables.${table.name}.title`) }}
    </h2>
    <p class="db-original">
      {{ t('db.originalName', { name: table.name }) }}
    </p>
    <p class="db-description">
      {{ t(`db.tables.${table.name}.description`) }}
    </p>

    <div class="db-scroll">
      <table class="db-table">
        <thead>
          <tr>
            <th scope="col">
              {{ t('db.headers.field') }}
            </th>
            <th scope="col">
              {{ t('db.headers.type') }}
            </th>
            <th scope="col">
              {{ t('db.headers.properties') }}
            </th>
            <th scope="col">
              {{ t('db.headers.description') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="column in table.columns"
            :key="column.name"
          >
            <th scope="row">
              <span class="db-label">{{ t(`db.columns.${table.name}.${column.name}.label`) }}</span>
              <code class="db-code">{{ column.name }}</code>
            </th>
            <td><code class="db-code">{{ column.type }}</code></td>
            <td class="db-props">
              {{ properties(column).join(' · ') }}
            </td>
            <td>{{ t(`db.columns.${table.name}.${column.name}.description`) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="table.indexes.length"
      class="db-indexes"
    >
      <ul class="wa-list-plain">
        <li
          v-for="index in table.indexes"
          :key="index.columns.join(',')"
        >
          <code class="db-code">{{ indexText(index) }}</code>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.db-original,
.db-description {
  margin: 0;
  color: var(--wa-color-text-quiet);
}

.db-description {
  margin-block: var(--wa-space-xs) var(--wa-space-m);
}

.db-scroll {
  overflow-x: auto;
}

.db-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--wa-font-size-s);
}

.db-table th,
.db-table td {
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  text-align: start;
  vertical-align: top;
}

.db-table th[scope='row'] {
  font-weight: normal;
}

.db-label {
  display: block;
  font-weight: var(--wa-font-weight-semibold);
}

.db-code {
  font-size: var(--wa-font-size-xs);
  overflow-wrap: anywhere;
}

.db-props {
  color: var(--wa-color-text-quiet);
}

.db-indexes {
  margin-block-start: var(--wa-space-m);
}
</style>
