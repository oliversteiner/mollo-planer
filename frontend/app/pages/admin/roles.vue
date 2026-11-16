<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { RoleFormTarget } from '~/components/role/FormModal.vue'
import type { RoleFunctionFormTarget } from '~/components/role/FunctionFormModal.vue'
import type { RoleSettingsQuery } from '~/graphql/generated/graphql'
import {
  EMPTY_ROLE_FILTER,
  filterFunctions,
  filterMatrix,
  filterRoles,
  parseRolePageState,
  toRoleQuery,
  type RolePageState
} from '~/composables/useRoleFilters'
import { appSettingsQuery } from '~/graphql/admin'
import { permissionMatrixQuery } from '~/graphql/rights'
import {
  deleteRoleFunctionMutation,
  deleteRoleMutation,
  duplicateRoleFunctionMutation,
  duplicateRoleMutation,
  resetRoleFunctionMutation,
  resetRoleMutation,
  roleSettingsQuery,
  setDefaultRoleMutation
} from '~/graphql/roles'

/**
 * Roles and functions of the app (right roles.manage): the shipped ones
 * and those made here. A function bundles rights, a role bundles functions.
 * The backend refuses changes that would lock everybody out or leave a
 * project without anybody who may delete it; the reason is shown.
 */

const { t } = useI18n()
const toast = useToast()
const roleName = useRoleName()

useHead({ title: () => t('roleSettings.title') })

type Role = RoleSettingsQuery['roles'][number]
type RoleFunction = RoleSettingsQuery['roleFunctions'][number]

const { result, loading } = useQuery(roleSettingsQuery, null, { fetchPolicy: 'cache-and-network' })
const roles = computed(() => result.value?.roles ?? [])
const functions = computed(() => result.value?.roleFunctions ?? [])
const systemRights = computed(() => result.value?.systemRights ?? [])
const projectRights = computed(() => result.value?.projectRights ?? [])

const editingRole = ref<RoleFormTarget | null>(null)
const editingFunction = ref<RoleFunctionFormTarget | null>(null)

const { mutate: duplicateRole } = useMutation(duplicateRoleMutation, roleSettingsRefresh)
const { mutate: deleteRole } = useMutation(deleteRoleMutation, roleSettingsRefresh)
const { mutate: setDefaultRole } = useMutation(setDefaultRoleMutation, roleSettingsRefresh)
const { mutate: resetRole } = useMutation(resetRoleMutation, roleSettingsRefresh)
const { mutate: duplicateFunction } = useMutation(duplicateRoleFunctionMutation, roleSettingsRefresh)
const { mutate: deleteFunction } = useMutation(deleteRoleFunctionMutation, roleSettingsRefresh)
const { mutate: resetFunction } = useMutation(resetRoleFunctionMutation, roleSettingsRefresh)

/** Runs a change without a form; a refusal of the server is shown as a message. */
async function attempt<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    const reason = refusalMessage(error)

    if (reason) {
      toast.add({ title: reason, color: 'error', duration: 0 })
    }

    throw error
  }
}

const roleDeletion = useConfirmedDelete<Role>(role => attempt(() => deleteRole({ id: role.id })), {
  onDeleted: () => toast.add({ title: t('roleSettings.deleted'), color: 'success' })
})
const functionDeletion = useConfirmedDelete<RoleFunction>(entry => attempt(() => deleteFunction({ id: entry.id })), {
  onDeleted: () => toast.add({ title: t('roleSettings.deleted'), color: 'success' })
})
const roleReset = useConfirmedDelete<Role>(role => attempt(() => resetRole({ id: role.id })), {
  onDeleted: role => toast.add({ title: t('roleSettings.resetDone', { name: roleName(role) }), color: 'success' })
})
const functionReset = useConfirmedDelete<RoleFunction>(entry => attempt(() => resetFunction({ id: entry.id })), {
  onDeleted: entry => toast.add({ title: t('roleSettings.resetDone', { name: roleName(entry) }), color: 'success' })
})

async function copyRole(role: Role): Promise<void> {
  const copy = (await attempt(() => duplicateRole({ id: role.id })).catch(() => null))?.data?.duplicateRole

  if (copy) {
    toast.add({ title: t('roleSettings.duplicated', { name: roleName(copy) }), color: 'success' })
    const fresh = roles.value.find(entry => entry.id === copy.id)

    if (fresh) {
      editingRole.value = { role: fresh }
    }
  }
}

async function copyFunction(source: RoleFunction): Promise<void> {
  const copy = (await attempt(() => duplicateFunction({ id: source.id })).catch(() => null))?.data?.duplicateRoleFunction

  if (copy) {
    toast.add({ title: t('roleSettings.duplicated', { name: roleName(copy) }), color: 'success' })
    const fresh = functions.value.find(entry => entry.id === copy.id)

    if (fresh) {
      editingFunction.value = { function: fresh }
    }
  }
}

async function makeDefault(role: Role): Promise<void> {
  const saved = await attempt(() => setDefaultRole({ id: role.id })).catch(() => null)

  if (saved) {
    toast.add({ title: t('roleSettings.defaultSet', { name: roleName(role) }), color: 'success' })
  }
}

/** "Delete (still in use)": says why the entry is disabled. */
function deleteLabel(reason: string | null): string {
  return reason ? `${t('common.delete')} (${reason})` : t('common.delete')
}

function roleActions(role: Role): ActionMenuItem[][] {
  const blocked = role.usageCount > 0 ? t('roleSettings.inUse') : role.isDefault ? t('roleSettings.isDefault') : null
  const items: ActionMenuItem[] = [
    { label: t('common.edit'), icon: 'pen', onSelect: () => (editingRole.value = { role }) },
    { label: t('roleSettings.duplicate'), icon: 'copy', onSelect: () => copyRole(role) }
  ]

  if (!role.isDefault) {
    items.push({ label: t('roleSettings.makeDefault'), icon: 'star', onSelect: () => makeDefault(role) })
  }

  return [items, [role.isStandard
    ? { label: t('roleSettings.reset'), icon: 'rotate-left', onSelect: () => roleReset.ask(role) }
    : { label: deleteLabel(blocked), icon: 'trash', color: 'error', disabled: blocked !== null, onSelect: () => roleDeletion.ask(role) }]]
}

function functionActions(entry: RoleFunction): ActionMenuItem[][] {
  const blocked = entry.usageCount > 0 ? t('roleSettings.inUse') : null

  return [
    [
      { label: t('common.edit'), icon: 'pen', onSelect: () => (editingFunction.value = { function: entry }) },
      { label: t('roleSettings.duplicate'), icon: 'copy', onSelect: () => copyFunction(entry) }
    ],
    [entry.isStandard
      ? { label: t('roleSettings.reset'), icon: 'rotate-left', onSelect: () => functionReset.ask(entry) }
      : { label: deleteLabel(blocked), icon: 'trash', color: 'error', disabled: blocked !== null, onSelect: () => functionDeletion.ask(entry) }]
  ]
}

const route = useRoute()
const router = useRouter()

/** Tab, view and filters live in the URL, so reloading and the back button keep them. */
const state = computed(() => parseRolePageState(route.query as Record<string, string | string[] | null>))

function setState(change: Partial<RolePageState>): void {
  void router.replace({ query: toRoleQuery({ ...state.value, ...change }) })
}

const VIEWS = ['roles', 'functions', 'matrix'] as const

const { result: matrixResult } = useQuery(permissionMatrixQuery, null, { fetchPolicy: 'cache-and-network' })
const { result: settingsResult } = useQuery(appSettingsQuery)
const creatorKey = computed(() => settingsResult.value?.appSettings.projectCreatorRole.key ?? null)

const rightText = (right: string): string => t(`rights.names.${right}`)

const scopeRoles = computed(() => roles.value.filter(role => role.scope === state.value.scope))
const scopeFunctions = computed(() => functions.value.filter(entry => entry.scope === state.value.scope))
const scopeRights = computed(() => (state.value.scope === 'SYSTEM' ? systemRights.value : projectRights.value))
const scopeMatrix = computed(() => (state.value.scope === 'SYSTEM' ? matrixResult.value?.permissionMatrix.system : matrixResult.value?.permissionMatrix.project) ?? [])

const shownRoles = computed(() => filterRoles(scopeRoles.value, scopeFunctions.value, state.value.filter, rightText))
const shownFunctions = computed(() => filterFunctions(scopeFunctions.value, state.value.filter, rightText))
const shownMatrix = computed(() => filterMatrix(scopeMatrix.value, scopeRoles.value, state.value.filter, rightText))

/** Hints under the system overview, with the names of the roles they are about (F3.10). */
const manageAnyRoles = computed(() => {
  const keys = (matrixResult.value?.permissionMatrix.system ?? []).find(row => row.right === 'PROJECTS_MANAGE_ANY')?.roles.map(role => role.key) ?? []

  return roles.value.filter(role => role.scope === 'SYSTEM' && keys.includes(role.key))
})
const readOnlyRoles = computed(() => roles.value.filter(role => role.scope === 'SYSTEM' && role.projectCap?.length === 0))

/** "3 of 9": what the current view shows of all of the scope. */
const counts = computed(() => {
  switch (state.value.view) {
    case 'functions':
      return { shown: shownFunctions.value.length, total: scopeFunctions.value.length }
    case 'matrix':
      return { shown: shownMatrix.value.rows.length, total: scopeMatrix.value.length }
    default:
      return { shown: shownRoles.value.length, total: scopeRoles.value.length }
  }
})

const nothingShown = computed(() => counts.value.total > 0 && counts.value.shown === 0)

function onTab(event: Event): void {
  const name = (event as CustomEvent<{ name: string }>).detail?.name
  if (name === 'system' || name === 'project') {
    setState({ scope: name === 'system' ? 'SYSTEM' : 'PROJECT' })
  }
}

function onView(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  if ((VIEWS as readonly string[]).includes(value)) {
    setState({ view: value as RolePageState['view'] })
  }
}

function resetFilters(): void {
  setState({ filter: { ...EMPTY_ROLE_FILTER } })
}

/** The button follows the view; the scope of the tab is preset in the form. */
function create(): void {
  if (state.value.view === 'functions') {
    editingFunction.value = { scope: state.value.scope }
  } else {
    editingRole.value = { scope: state.value.scope }
  }
}
</script>

<template>
  <PageFrame
    :title="t('roleSettings.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('roleSettings.title') }]"
    :description="t('roleSettings.intro')"
  >
    <template #actions>
      <wa-button
        v-if="state.view !== 'matrix'"
        variant="brand"
        @click="create"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ state.view === 'functions' ? t('roleSettings.createFunction') : t('roleSettings.createRole') }}
      </wa-button>
    </template>

    <wa-tab-group
      :active="state.scope === 'SYSTEM' ? 'system' : 'project'"
      class="role-settings-tabs"
      @wa-tab-show="onTab"
    >
      <wa-tab
        slot="nav"
        panel="system"
      >
        {{ t('roleSettings.scopes.SYSTEM') }}
      </wa-tab>
      <wa-tab
        slot="nav"
        panel="project"
      >
        {{ t('roleSettings.scopes.PROJECT') }}
      </wa-tab>

      <wa-tab-panel
        v-for="panel in ['system', 'project']"
        :key="panel"
        :name="panel"
      >
        <div
          v-if="panel === (state.scope === 'SYSTEM' ? 'system' : 'project')"
          class="wa-stack wa-gap-m"
        >
          <wa-radio-group
            :value="state.view"
            orientation="horizontal"
            :label="t('roleSettings.viewLabel')"
            class="role-settings-view wa-visually-hidden-label"
            @change="onView"
          >
            <wa-radio
              v-for="view in VIEWS"
              :key="view"
              :value="view"
              appearance="button"
            >
              {{ t(`roleSettings.views.${view}`) }}
            </wa-radio>
          </wa-radio-group>

          <RoleFilters
            :filter="state.filter"
            :rights="scopeRights"
            :shown="counts.shown"
            :total="counts.total"
            @update:filter="filter => setState({ filter })"
          />

          <wa-skeleton
            v-if="loading && !result"
            effect="sheen"
            class="role-settings-skeleton"
          ></wa-skeleton>
          <EmptyState
            v-else-if="nothingShown"
            icon="filter-circle-xmark"
            :title="t(`roleSettings.empty.${state.view}`)"
            size="sm"
          >
            <wa-button
              appearance="outlined"
              variant="neutral"
              @click="resetFilters"
            >
              {{ t('roleSettings.filters.reset') }}
            </wa-button>
          </EmptyState>
          <RoleList
            v-else-if="state.view === 'roles'"
            :roles="shownRoles"
            :functions="scopeFunctions"
            :scope="state.scope"
            :creator-key="creatorKey"
            :project-rights-total="projectRights.length"
            :query="state.filter.q"
            :actions="roleActions"
          />
          <RoleFunctionList
            v-else-if="state.view === 'functions'"
            :functions="shownFunctions"
            :roles="scopeRoles"
            :query="state.filter.q"
            :actions="functionActions"
          />
          <RoleRightsMatrix
            v-else
            :scope="state.scope"
            :rows="shownMatrix.rows"
            :roles="shownMatrix.roles"
            :query="state.filter.q"
            :manage-any-roles="manageAnyRoles"
            :read-only-roles="readOnlyRoles"
          />
        </div>
      </wa-tab-panel>
    </wa-tab-group>

    <RoleFormModal
      :target="editingRole"
      :functions="functions"
      :project-rights="projectRights"
      @close="editingRole = null"
    />
    <RoleFunctionFormModal
      :target="editingFunction"
      :system-rights="systemRights"
      :project-rights="projectRights"
      @close="editingFunction = null"
    />
    <ConfirmModal
      :open="roleDeletion.isOpen"
      :title="t('roleSettings.deleteTitle', { name: roleDeletion.target ? roleName(roleDeletion.target) : '' })"
      :description="t('roleSettings.deleteRoleDescription')"
      :confirm-label="t('common.delete')"
      :action="roleDeletion.confirm"
      @close="roleDeletion.close"
    />
    <ConfirmModal
      :open="functionDeletion.isOpen"
      :title="t('roleSettings.deleteTitle', { name: functionDeletion.target ? roleName(functionDeletion.target) : '' })"
      :description="t('roleSettings.deleteFunctionDescription')"
      :confirm-label="t('common.delete')"
      :action="functionDeletion.confirm"
      @close="functionDeletion.close"
    />
    <ConfirmModal
      :open="roleReset.isOpen"
      :title="t('roleSettings.resetTitle', { name: roleReset.target ? roleName(roleReset.target) : '' })"
      :description="t('roleSettings.resetRoleDescription')"
      :confirm-label="t('roleSettings.reset')"
      :action="roleReset.confirm"
      @close="roleReset.close"
    />
    <ConfirmModal
      :open="functionReset.isOpen"
      :title="t('roleSettings.resetTitle', { name: functionReset.target ? roleName(functionReset.target) : '' })"
      :description="t('roleSettings.resetFunctionDescription')"
      :confirm-label="t('roleSettings.reset')"
      :action="functionReset.confirm"
      @close="functionReset.close"
    />
  </PageFrame>
</template>

<style scoped>
.role-settings-skeleton {
  block-size: 12rem;
}

.role-settings-tabs::part(body) {
  padding-block-start: var(--wa-space-m);
}
</style>
