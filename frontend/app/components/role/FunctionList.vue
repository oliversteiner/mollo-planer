<script setup lang="ts">
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { RoleSettingsQuery } from '~/graphql/generated/graphql'
import { functionMatchesOnlyInRights } from '~/composables/useRoleFilters'

type Role = RoleSettingsQuery['roles'][number]
type RoleFunction = RoleSettingsQuery['roleFunctions'][number]

/** Most roles named in a row; the rest as "+n". */
const MAX_ROLES = 3

/**
 * Functions of one scope as compact rows: name, badge, number of rights, the
 * roles that use it, actions. A row opens to show its rights by area.
 */
const props = defineProps<{
  functions: readonly RoleFunction[]
  /** All roles of the scope, to name those that use a function. */
  roles: readonly Role[]
  query: string
  actions: (entry: RoleFunction) => ActionMenuItem[][]
}>()

const { t } = useI18n()
const roleName = useRoleName()

const rightText = (right: string): string => t(`rights.names.${right}`)

const toggled = ref(new Map<string, boolean>())

function isOpen(entry: RoleFunction): boolean {
  return toggled.value.get(entry.id) ?? functionMatchesOnlyInRights(entry, props.query, rightText)
}

function toggle(entry: RoleFunction): void {
  toggled.value = new Map(toggled.value).set(entry.id, !isOpen(entry))
}

function usedBy(entry: RoleFunction): string {
  const names = props.roles.filter(role => role.functions.some(other => other.id === entry.id)).map(roleName)

  if (names.length === 0) {
    return t('roleSettings.functionUsage', 0)
  }

  const shown = names.slice(0, MAX_ROLES).join(', ')
  const rest = names.length - MAX_ROLES

  return t('roleSettings.usedBy', { names: rest > 0 ? `${shown} ${t('roleSettings.more', { count: rest })}` : shown })
}
</script>

<template>
  <ul class="role-list wa-list-plain">
    <li
      v-for="entry in functions"
      :key="entry.id"
      class="role-row"
    >
      <div class="role-row-line">
        <span class="role-row-head">
          <button
            type="button"
            class="role-row-toggle"
            :aria-expanded="isOpen(entry) ? 'true' : 'false'"
            :aria-controls="`function-rights-${entry.id}`"
            :aria-label="t('roleSettings.showRights', { name: roleName(entry) })"
            @click="toggle(entry)"
          >
            <wa-icon
              :name="isOpen(entry) ? 'chevron-down' : 'chevron-right'"
              class="role-row-chevron"
            ></wa-icon>
            <span><RoleHighlight
              :text="roleName(entry)"
              :query="query"
            /></span>
          </button>
          <wa-badge
            variant="neutral"
            :appearance="entry.isStandard ? 'outlined' : 'filled'"
          >
            {{ entry.isStandard ? t('roleSettings.standard') : t('roleSettings.own') }}
          </wa-badge>
        </span>
        <span class="role-row-info">
          <span class="role-row-meta role-row-grow">{{ t('roleSettings.rightsCount', entry.rights.length) }}</span>
          <span class="role-row-meta">{{ usedBy(entry) }}</span>
        </span>
        <span class="role-row-actions">
          <ActionMenu
            :items="actions(entry)"
            :label="t('roleSettings.actionsOf', { name: roleName(entry) })"
            size="m"
          />
        </span>
      </div>
      <div
        v-if="isOpen(entry)"
        :id="`function-rights-${entry.id}`"
        class="role-row-body"
      >
        <RoleRightGroups
          :rights="entry.rights"
          :query="query"
        />
      </div>
    </li>
  </ul>
</template>

<style scoped>
.role-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.role-row + .role-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.role-row-line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs) var(--wa-space-s);
  padding: var(--wa-space-s) var(--wa-space-m);
}

.role-row-toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-xs);
  margin: 0;
  padding: 0;
  border: none;
  background: none;
  color: inherit;
  font: inherit;
  font-weight: var(--wa-font-weight-semibold);
  text-align: start;
  cursor: pointer;
}

.role-row-toggle:focus-visible {
  border-radius: var(--wa-border-radius-s);
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.role-row-chevron {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.role-row-head,
.role-row-info {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs) var(--wa-space-s);
}

.role-row-info {
  flex: 1;
  min-inline-size: 0;
}

.role-row-grow {
  flex: 1;
}

.role-row-meta {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.role-row-body {
  padding: 0 var(--wa-space-m) var(--wa-space-m) calc(var(--wa-space-m) + var(--wa-space-l));
}

/* Phone width: name, badge and menu on top, counts below. */
@media (max-width: 40em) {
  .role-row-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .role-row-head {
    grid-column: 1;
  }

  .role-row-actions {
    grid-column: 2;
    grid-row: 1;
  }

  .role-row-info {
    grid-column: 1 / -1;
  }

  .role-row-body {
    padding-inline-start: var(--wa-space-m);
  }
}
</style>
