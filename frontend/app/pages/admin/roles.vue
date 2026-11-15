<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { RoleFormTarget } from '~/components/role/FormModal.vue'
import type { RoleFunctionFormTarget } from '~/components/role/FunctionFormModal.vue'
import type { RoleSettingsQuery } from '~/graphql/generated/graphql'
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
definePageMeta({ middleware: 'roles' })

const { t } = useI18n()
const toast = useToast()
const roleName = useRoleName()

useHead({ title: () => t('roleSettings.title') })

type Role = RoleSettingsQuery['roles'][number]
type RoleFunction = RoleSettingsQuery['roleFunctions'][number]

const SCOPES = ['SYSTEM', 'PROJECT'] as const

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

function capText(role: Role): string {
  if (role.projectCap == null) {
    return t('roleSettings.capAll')
  }

  return role.projectCap.length === 0
    ? t('roleSettings.capNone')
    : t('roleSettings.capSome', { count: role.projectCap.length, total: projectRights.value.length })
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
        appearance="outlined"
        variant="neutral"
        @click="editingFunction = {}"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('roleSettings.createFunction') }}
      </wa-button>
      <wa-button
        variant="brand"
        @click="editingRole = {}"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('roleSettings.createRole') }}
      </wa-button>
    </template>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="role-settings-skeleton"
    ></wa-skeleton>
    <div
      v-else
      class="wa-stack wa-gap-l"
    >
      <section
        v-for="scope in SCOPES"
        :key="`roles-${scope}`"
        class="wa-stack wa-gap-s"
      >
        <h2 class="wa-heading-m">
          {{ t(`roleSettings.roles.${scope}`) }}
        </h2>
        <ul class="role-settings-list wa-list-plain">
          <li
            v-for="role in roles.filter(entry => entry.scope === scope)"
            :key="role.id"
          >
            <wa-card class="role-settings-card">
              <div
                slot="header"
                class="role-settings-head"
              >
                <div class="role-settings-title">
                  <h3 class="wa-heading-s">
                    {{ roleName(role) }}
                  </h3>
                  <wa-badge
                    v-if="role.isStandard"
                    variant="neutral"
                    appearance="outlined"
                  >
                    {{ t('roleSettings.standard') }}
                  </wa-badge>
                  <wa-badge
                    v-if="role.isDefault"
                    variant="brand"
                    appearance="outlined"
                  >
                    {{ t('roleSettings.default') }}
                  </wa-badge>
                </div>
                <ActionMenu
                  :items="roleActions(role)"
                  :label="t('roleSettings.actionsOf', { name: roleName(role) })"
                />
              </div>
              <div class="wa-stack wa-gap-s">
                <p class="role-settings-text">
                  {{ role.functions.length ? role.functions.map(roleName).join(', ') : t('roleSettings.noFunctions') }}
                </p>
                <p
                  v-if="scope === 'SYSTEM'"
                  class="role-settings-text"
                >
                  {{ capText(role) }}
                </p>
                <p class="role-settings-usage">
                  {{ t(`roleSettings.roleUsage.${scope}`, role.usageCount) }}
                </p>
              </div>
            </wa-card>
          </li>
        </ul>
      </section>

      <section
        v-for="scope in SCOPES"
        :key="`functions-${scope}`"
        class="wa-stack wa-gap-s"
      >
        <h2 class="wa-heading-m">
          {{ t(`roleSettings.functions.${scope}`) }}
        </h2>
        <ul class="role-settings-list wa-list-plain">
          <li
            v-for="entry in functions.filter(other => other.scope === scope)"
            :key="entry.id"
          >
            <wa-card class="role-settings-card">
              <div
                slot="header"
                class="role-settings-head"
              >
                <div class="role-settings-title">
                  <h3 class="wa-heading-s">
                    {{ roleName(entry) }}
                  </h3>
                  <wa-badge
                    v-if="entry.isStandard"
                    variant="neutral"
                    appearance="outlined"
                  >
                    {{ t('roleSettings.standard') }}
                  </wa-badge>
                </div>
                <ActionMenu
                  :items="functionActions(entry)"
                  :label="t('roleSettings.actionsOf', { name: roleName(entry) })"
                />
              </div>
              <div class="wa-stack wa-gap-s">
                <p class="role-settings-text">
                  {{ entry.rights.length ? entry.rights.map(right => t(`rights.names.${right}`)).join(', ') : t('roleSettings.noRights') }}
                </p>
                <p class="role-settings-usage">
                  {{ t('roleSettings.functionUsage', entry.usageCount) }}
                </p>
              </div>
            </wa-card>
          </li>
        </ul>
      </section>

      <AppCallout
        variant="neutral"
        :title="t('roleSettings.matrixHint')"
      >
        <NuxtLink to="/system/rechte">
          {{ t('roleSettings.matrixLink') }}
        </NuxtLink>
      </AppCallout>
    </div>

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

.role-settings-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 22rem), 1fr));
  gap: var(--wa-space-l);
  margin: 0;
  padding: 0;
}

.role-settings-card {
  block-size: 100%;
}

.role-settings-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-s);
}

.role-settings-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
}

.role-settings-title h3 {
  margin: 0;
}

.role-settings-text,
.role-settings-usage {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.role-settings-usage {
  font-size: var(--wa-font-size-xs);
}
</style>
