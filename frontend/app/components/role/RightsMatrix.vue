<script setup lang="ts">
import type { MatrixRow, RoleScopeKey } from '~/composables/useRoleFilters'

/**
 * The overview of one scope: rows are rights, columns are roles (formerly
 * its own page, now /admin/roles?view=matrix). Read from the backend, the same rules the policies use.
 * The role columns are narrow (only ✓/–, names on two lines at most), so six
 * to eight roles fit. When they do not, the table scrolls in a frame of its
 * own height: the head and the column of the rights stay, a shadow shows on the
 * side where more columns follow, and a line says so (docs/fix-m1-f4.md F4.4).
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

const scroller = ref<HTMLElement | null>(null)
const { edges } = useScrollEdges(scroller)

function has(row: MatrixRow, role: { key: string }): boolean {
  return row.roles.some(other => other.key === role.key)
}
</script>

<template>
  <div class="wa-stack wa-gap-s">
    <p class="rights-matrix-hint">
      {{ scope === 'SYSTEM' ? t('rights.systemHint') : t('rights.projectHint') }}
    </p>
    <p
      v-if="edges.start || edges.end"
      class="rights-matrix-hint"
    >
      {{ t('rights.scrollHint') }}
    </p>
    <div
      class="rights-matrix-frame"
      :class="{ 'is-start-hidden': edges.start, 'is-end-hidden': edges.end }"
    >
      <div
        ref="scroller"
        class="rights-matrix-scroll"
        tabindex="0"
        role="region"
        :aria-label="t('rights.tableLabel')"
      >
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
                class="rights-matrix-role"
                :title="roleName(role)"
              >
                <span class="rights-matrix-role-name">{{ roleName(role) }}</span>
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

/* Holds the shadow on the right; the one on the left sits on the column of the rights. */
.rights-matrix-frame {
  --rights-matrix-shadow: color-mix(in oklab, var(--wa-color-text-normal) 22%, transparent);

  position: relative;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
  overflow: hidden;
}

.rights-matrix-frame::after {
  content: '';
  position: absolute;
  inset-block: 0;
  inset-inline-end: 0;
  inline-size: var(--wa-space-l);
  background: linear-gradient(to left, var(--rights-matrix-shadow), transparent);
  opacity: 0;
  pointer-events: none;
  transition: opacity var(--wa-transition-fast);
}

.rights-matrix-frame.is-end-hidden::after {
  opacity: 1;
}

/*
 * Scrolls both ways in a frame of its own height, so the scroll bar at the
 * bottom stays in view and the head can stay at the top.
 */
.rights-matrix-scroll {
  max-block-size: 70dvh;
  overflow: auto;
  background-color: var(--wa-color-surface-default);
}

.rights-matrix-scroll:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: calc(-1 * var(--wa-focus-ring-width));
}

.rights-matrix {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
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

/* The head stays while the rights scroll. */
.rights-matrix thead th {
  position: sticky;
  inset-block-start: 0;
  z-index: 2;
  background-color: var(--wa-color-surface-default);
  color: var(--wa-color-text-quiet);
  font-weight: var(--wa-font-weight-semibold);
  vertical-align: bottom;
}

/* Role columns: only ✓/–, so narrow; the name on two lines at most. */
.rights-matrix-role {
  min-inline-size: 4rem;
  max-inline-size: 7rem;
  padding-inline: var(--wa-space-2xs);
}

.rights-matrix thead .rights-matrix-role {
  text-align: center;
  white-space: normal;
}

.rights-matrix-role-name {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow-wrap: break-word;
}

/* The column of the rights stays while the roles scroll. */
.rights-matrix th:first-child {
  position: sticky;
  inset-inline-start: 0;
  z-index: 1;
  background-color: var(--wa-color-surface-default);
}

.rights-matrix thead th:first-child {
  z-index: 3;
}

/* Shadow on the left: columns scrolled out of view behind the rights. */
.rights-matrix th:first-child::after {
  content: '';
  position: absolute;
  inset-block: 0;
  inset-inline-start: 100%;
  inline-size: var(--wa-space-s);
  background: linear-gradient(to right, var(--rights-matrix-shadow), transparent);
  opacity: 0;
  pointer-events: none;
  transition: opacity var(--wa-transition-fast);
}

.is-start-hidden .rights-matrix th:first-child::after {
  opacity: 1;
}

.rights-matrix th[scope='row'] {
  font-weight: normal;
  white-space: normal;
  min-inline-size: min(12rem, 40vw);
}

.rights-matrix-cell {
  padding-inline: var(--wa-space-2xs);
  text-align: center;
}

.rights-matrix-yes {
  color: var(--wa-color-success-on-normal);
}

.rights-matrix-no {
  color: var(--wa-color-text-quiet);
}
</style>
