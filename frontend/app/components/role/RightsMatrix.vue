<script setup lang="ts">
import type { MatrixRow, RoleScopeKey } from '~/composables/useRoleFilters'

/**
 * The overview of one scope: rows are rights, columns are roles (formerly
 * its own page, now /admin/roles?view=matrix). Read from the backend, the same rules the policies use.
 * The table scrolls sideways in its frame; the column of the rights stays.
 */
type NamedRole = { key: string, names: { de: string, en: string } }

const props = defineProps<{
  scope: RoleScopeKey
  rows: readonly MatrixRow[]
  roles: readonly NamedRole[]
  query: string
  /** System roles that may act in every project, and those that only read: named in the hints. */
  manageAnyRoles?: readonly NamedRole[]
  readOnlyRoles?: readonly NamedRole[]
}>()

const { t } = useI18n()
const roleName = useRoleName()

/** „Administration“, „Lesend“: the names from the backend, quoted. */
function quoted(roles: readonly NamedRole[]): string {
  return roles.map(role => t('rights.quoted', { name: roleName(role) })).join(', ')
}

const manageAnyHint = computed(() => (props.manageAnyRoles?.length ? t('rights.admin', { roles: quoted(props.manageAnyRoles) }, props.manageAnyRoles.length) : null))
const readOnlyHint = computed(() => (props.readOnlyRoles?.length ? t('rights.readOnly', { roles: quoted(props.readOnlyRoles) }, props.readOnlyRoles.length) : null))

function has(row: MatrixRow, role: { key: string }): boolean {
  return row.roles.some(other => other.key === role.key)
}
</script>

<template>
  <div class="wa-stack wa-gap-s">
    <p class="rights-matrix-hint">
      {{ scope === 'SYSTEM' ? t('rights.systemHint') : t('rights.projectHint') }}
    </p>
    <div class="rights-matrix-scroll">
      <table class="rights-matrix">
        <thead>
          <tr>
            <th scope="col">
              {{ t('rights.right') }}
            </th>
            <th
              v-for="role in roles"
              :key="role.key"
              scope="col"
            >
              {{ roleName(role) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.right"
          >
            <th scope="row">
              <RoleHighlight
                :text="t(`rights.names.${row.right}`)"
                :query="query"
              />
            </th>
            <td
              v-for="role in roles"
              :key="role.key"
              class="rights-matrix-cell"
            >
              <wa-icon
                v-if="has(row, role)"
                name="check"
                :label="t('rights.yes')"
                class="rights-matrix-yes"
              ></wa-icon>
              <span
                v-else
                class="rights-matrix-no"
              >
                <span class="wa-visually-hidden">{{ t('rights.no') }}</span>
                –
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <wa-callout
      v-if="scope === 'SYSTEM' && (manageAnyHint || readOnlyHint)"
      variant="neutral"
    >
      <p v-if="manageAnyHint">
        {{ manageAnyHint }}
      </p>
      <p v-if="readOnlyHint">
        {{ readOnlyHint }}
      </p>
    </wa-callout>
  </div>
</template>

<style scoped>
.rights-matrix-hint {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

/*
 * Scrolls sideways in its frame. A shadow at the right edge shows that more
 * columns follow (scroll bars are hidden on macOS, docs/fix-m1-abnahme.md
 * F3.10): the covers move with the content, the shadow stays at the edge.
 */
.rights-matrix-scroll {
  --rights-matrix-cover: var(--wa-color-surface-default);
  --rights-matrix-shadow: color-mix(in oklab, var(--wa-color-text-normal) 25%, transparent);

  overflow-x: auto;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
  background:
    linear-gradient(to left, var(--rights-matrix-cover) 30%, transparent) right center / var(--wa-space-2xl) 100% no-repeat local,
    radial-gradient(farthest-side at 100% 50%, var(--rights-matrix-shadow), transparent) right center / var(--wa-space-s) 100% no-repeat scroll,
    var(--rights-matrix-cover);
}

.rights-matrix {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--wa-font-size-s);
}

.rights-matrix th,
.rights-matrix td {
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  text-align: start;
  vertical-align: middle;
  white-space: nowrap;
}

.rights-matrix tbody tr:last-child th,
.rights-matrix tbody tr:last-child td {
  border-block-end: none;
}

.rights-matrix thead th {
  color: var(--wa-color-text-quiet);
  font-weight: var(--wa-font-weight-semibold);
}

/* The column of the rights stays while the roles scroll. */
.rights-matrix th:first-child {
  position: sticky;
  inset-inline-start: 0;
  z-index: 1;
  background-color: var(--wa-color-surface-default);
}

.rights-matrix th[scope='row'] {
  font-weight: normal;
  white-space: normal;
  min-inline-size: 12rem;
}

.rights-matrix-cell {
  text-align: center;
}

.rights-matrix-yes {
  color: var(--wa-color-success-on-normal);
}

.rights-matrix-no {
  color: var(--wa-color-text-quiet);
}
</style>
