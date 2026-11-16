<script setup lang="ts">
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { RoleSettingsQuery } from '~/graphql/generated/graphql'
import { rightsOfRole, roleMatchesOnlyInRights, type RoleScopeKey } from '~/composables/useRoleFilters'

type Role = RoleSettingsQuery['roles'][number]
type RoleFunction = RoleSettingsQuery['roleFunctions'][number]

/** Most functions shown in a row; the rest as "+n". */
const MAX_FUNCTIONS = 3

/**
 * Roles of one scope as compact rows (docs/fix-m1-rollenseite.md §2): name,
 * badges, functions, cap (system roles), people, actions. A row opens to show
 * all rights of the role by area, each with the functions that give it.
 */
const props = defineProps<{
  roles: readonly Role[]
  /** All functions of the scope, to find the rights of a role. */
  functions: readonly RoleFunction[]
  scope: RoleScopeKey
  /** Key of the project role a creator gets (project scope). */
  creatorKey?: string | null
  /** Number of project rights, for the cap of system roles. */
  projectRightsTotal: number
  query: string
  actions: (role: Role) => ActionMenuItem[][]
}>()

const { t } = useI18n()
const roleName = useRoleName()

const rightText = (right: string): string => t(`rights.names.${right}`)

/** Opened or closed by hand; otherwise a row opens when the search finds it only in its rights. */
const toggled = ref(new Map<string, boolean>())

function isOpen(role: Role): boolean {
  return toggled.value.get(role.id) ?? roleMatchesOnlyInRights(role, props.functions, props.query, rightText)
}

function toggle(role: Role): void {
  toggled.value = new Map(toggled.value).set(role.id, !isOpen(role))
}

function rightsOf(role: Role): string[] {
  return rightsOfRole(role, props.functions)
}

/** Right → names of the role's functions that give it. */
function viaOf(role: Role): Record<string, string[]> {
  const byId = new Map(props.functions.map(entry => [entry.id, entry]))
  const via: Record<string, string[]> = {}

  for (const entry of role.functions) {
    for (const right of byId.get(entry.id)?.rights ?? []) {
      via[right] = [...(via[right] ?? []), roleName(entry)]
    }
  }

  return via
}

function capText(role: Role): string {
  if (role.projectCap == null) {
    return t('roleSettings.capShort.ALL')
  }

  return role.projectCap.length === 0
    ? t('roleSettings.capShort.NONE')
    : t('roleSettings.capShort.SOME', { count: role.projectCap.length, total: props.projectRightsTotal })
}
</script>

<template>
  <ul class="role-list wa-list-plain">
    <li
      v-for="role in roles"
      :key="role.id"
      class="role-row"
    >
      <div class="role-row-line">
        <span class="role-row-head">
          <button
            type="button"
            class="role-row-toggle"
            :aria-expanded="isOpen(role) ? 'true' : 'false'"
            :aria-controls="`role-rights-${role.id}`"
            :aria-label="t('roleSettings.showRights', { name: roleName(role) })"
            @click="toggle(role)"
          >
            <wa-icon
              :name="isOpen(role) ? 'chevron-down' : 'chevron-right'"
              class="role-row-chevron"
            ></wa-icon>
            <span class="role-row-name"><RoleHighlight
              :text="roleName(role)"
              :query="query"
            /></span>
          </button>
          <span class="role-row-badges">
            <wa-badge
              variant="neutral"
              :appearance="role.isStandard ? 'outlined' : 'filled'"
            >
              {{ role.isStandard ? t('roleSettings.standard') : t('roleSettings.own') }}
            </wa-badge>
            <wa-badge
              v-if="role.isDefault"
              variant="brand"
              appearance="outlined"
            >
              {{ t('roleSettings.default') }}
            </wa-badge>
            <wa-badge
              v-if="scope === 'PROJECT' && creatorKey === role.key"
              variant="brand"
              appearance="outlined"
            >
              {{ t('roleSettings.creator') }}
            </wa-badge>
          </span>
        </span>
        <span class="role-row-info">
          <span class="role-row-functions">
            <template v-if="role.functions.length">
              <wa-badge
                v-for="entry in role.functions.slice(0, MAX_FUNCTIONS)"
                :key="entry.id"
                variant="neutral"
                appearance="filled-outlined"
              >
                <RoleHighlight
                  :text="roleName(entry)"
                  :query="query"
                />
              </wa-badge>
              <span
                v-if="role.functions.length > MAX_FUNCTIONS"
                class="role-row-meta"
              >{{ t('roleSettings.more', { count: role.functions.length - MAX_FUNCTIONS }) }}</span>
            </template>
            <span
              v-else
              class="role-row-meta"
            >{{ t('roleSettings.noFunctions') }}</span>
          </span>
          <span
            v-if="scope === 'SYSTEM'"
            class="role-row-meta"
          >{{ capText(role) }}</span>
          <span class="role-row-meta">{{ t(`roleSettings.roleUsage.${scope}`, role.usageCount) }}</span>
        </span>
        <span class="role-row-actions">
          <ActionMenu
            :items="actions(role)"
            :label="t('roleSettings.actionsOf', { name: roleName(role) })"
            size="m"
          />
        </span>
      </div>
      <div
        v-if="isOpen(role)"
        :id="`role-rights-${role.id}`"
        class="role-row-body"
      >
        <RoleRightGroups
          :rights="rightsOf(role)"
          :via="viaOf(role)"
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

.role-row-badges,
.role-row-functions {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-2xs);
}

.role-row-functions {
  flex: 1;
  min-inline-size: 0;
}

.role-row-meta {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.role-row-body {
  padding: 0 var(--wa-space-m) var(--wa-space-m) calc(var(--wa-space-m) + var(--wa-space-l));
}

/* Phone width: name, badges and menu on top, functions and counts below. */
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
