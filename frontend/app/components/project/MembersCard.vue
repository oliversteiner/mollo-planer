<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { memberCandidatesQuery, removeProjectMemberMutation, setProjectMemberMutation } from '~/graphql/settings'
import type { NamedRole } from '~/composables/useRoles'
import { classifyApolloError } from '~/utils/api'
import { copyToClipboard } from '~/utils/share'
import { assignableRoles, isOwnershipRole } from '~/utils/role-rights'

/**
 * Members and their roles. Who may grant or take the owner role, and that
 * the last owner stays, is decided by the backend; its answer is shown.
 * An agent account has the role Agent and no other, a person never has it
 * (Auftrag 58): for an agent the role is fixed, for a person «Agent» is not offered.
 */
const props = defineProps<{
  projectId: string
  members: { role: NamedRole, user: { id: string, name: string, email: string, avatarUrl?: string | null, isAgent?: boolean } }[]
  canManage: boolean
}>()

const { t } = useI18n()
const toast = useToast()
const { viewer } = useViewer()

/** The project roles come from the backend (shipped ones and those made in the settings). */
const { roles, defaultKey: defaultRole } = useRoles('PROJECT')
const roleName = useRoleName()
const { project, can } = useProject()

/**
 * Roles with project.delete or owner.manage are offered only to people with
 * owner.manage (§7.4, docs/fix-m1-abnahme.md F3.9); the current role of a
 * member is always shown.
 */
function roleItemsFor(currentKey: string | undefined, isAgent: boolean): { label: string, value: string }[] {
  if (isAgent) {
    const agentRole = roles.value.find(role => role.key === AGENT_ROLE)

    return agentRole ? [{ label: roleName(agentRole), value: agentRole.key }] : []
  }

  return assignableRoles(roles.value, project.value?.viewerRights ?? [], currentKey)
    .filter(role => role.key !== AGENT_ROLE || role.key === currentKey)
    .map(role => ({ label: roleName(role), value: role.key }))
}

const AGENT_ROLE = 'agent'

/** A member with an ownership role is changed only with owner.manage. */
function mayChangeRole(roleKey: string): boolean {
  const role = roles.value.find(entry => entry.key === roleKey)

  return can('OWNER_MANAGE') || !role || !isOwnershipRole(role)
}

const sortedMembers = computed(() => [...props.members].sort((a, b) => a.user.name.localeCompare(b.user.name)))

/**
 * Everybody who can join, shown at once; members stay in the list but cannot
 * be chosen, so nobody wonders why a person is missing.
 */
const { result: candidatesResult } = useQuery(memberCandidatesQuery, () => ({ projectId: props.projectId }), () => ({ enabled: props.canManage, fetchPolicy: 'cache-and-network' }))
const candidates = computed(() => candidatesResult.value?.memberCandidates ?? [])
const personGroups = computed(() => [{
  people: candidates.value.map(person => ({
    id: person.id,
    name: person.name,
    avatarUrl: person.avatarUrl,
    disabledReason: person.isMember ? t('settings.members.alreadyMember') : null
  }))
}])

const newMember = reactive<{ userId: string | null, role: string }>({ userId: null, role: '' })

/** The chosen person is an agent: the role is Agent, and it cannot be changed. */
const newIsAgent = computed(() => candidates.value.some(person => person.id === newMember.userId && person.isAgent))
const newRoleItems = computed(() => roleItemsFor(undefined, newIsAgent.value))

watch([defaultRole, newIsAgent], ([key, isAgent], [, wasAgent]) => {
  if (isAgent) {
    newMember.role = AGENT_ROLE
  } else if (!newMember.role || wasAgent || newMember.role === AGENT_ROLE) {
    newMember.role = key === AGENT_ROLE ? '' : key
  }
}, { immediate: true })
const pending = ref(false)
const removing = ref<{ id: string, name: string } | null>(null)

const { mutate: setMember } = useProjectMutation(setProjectMemberMutation)
const { mutate: removeMember } = useProjectMutation(removeProjectMemberMutation)

/** Runs a change; a validation message (for example "last owner") becomes a toast. */
async function change(action: () => Promise<unknown>, success: string): Promise<boolean> {
  pending.value = true
  try {
    await action()
    toast.add({ title: success, color: 'success' })

    return true
  } catch (error) {
    // A refusal (extensions.refusal) is shown by the api plugin; validation messages here.
    const apiError = classifyApolloError(error)
    const message = Object.values(apiError.fields)[0]?.[0]

    if (message) {
      toast.add({ title: message, color: 'error' })
    }

    return false
  } finally {
    pending.value = false
  }
}

async function setRole(userId: string, role: string): Promise<void> {
  await change(() => setMember({ projectId: props.projectId, userId, role }), t('settings.members.roleChanged'))
}

async function add(): Promise<void> {
  const userId = newMember.userId

  if (!userId) {
    return
  }
  const added = await change(() => setMember({ projectId: props.projectId, userId, role: newMember.role }), t('settings.members.added'))

  if (added) {
    Object.assign(newMember, { userId: null, role: defaultRole.value === AGENT_ROLE ? '' : defaultRole.value })
  }
}

async function copyIdentifier(value: string): Promise<void> {
  try {
    await copyToClipboard(value)
    toast.add({ title: t('claude.copied'), color: 'success' })
  } catch {
    toast.add({ title: t('help.copyFailed'), color: 'error' })
  }
}

async function confirmRemove(): Promise<void> {
  if (removing.value) {
    const id = removing.value.id
    await change(() => removeMember({ projectId: props.projectId, userId: id }), t('settings.members.removed'))
  }
}
</script>

<template>
  <wa-card>
    <div slot="header">
      <h2 class="wa-heading-m">
        {{ t('settings.members.title') }}
      </h2>
      <p
        class="wa-body-s wa-color-text-quiet"
        data-zen="hide"
      >
        {{ t('settings.members.intro') }}
      </p>
    </div>

    <ul class="members-list wa-list-plain">
      <li
        v-for="member in sortedMembers"
        :key="member.user.id"
        class="members-row"
      >
        <UserAvatar
          :name="member.user.name"
          :src="member.user.avatarUrl"
          :is-agent="member.user.isAgent"
          size="sm"
        />
        <span class="members-text">
          <span class="members-name">
            {{ member.user.name }}
            <AgentBadge v-if="member.user.isAgent" />
            <span
              v-if="member.user.id === viewer?.id"
              class="members-you"
            >({{ t('settings.members.you') }})</span>
          </span>
          <span
            v-if="member.user.isAgent"
            class="members-identifier"
          >
            <span class="members-email">{{ t('settings.members.identifier') }}: {{ member.user.email }}</span>
            <wa-button
              appearance="plain"
              variant="neutral"
              size="s"
              class="members-copy"
              :aria-label="t('settings.members.copyIdentifier', { name: member.user.name })"
              @click="copyIdentifier(member.user.email)"
            >
              <wa-icon name="copy"></wa-icon>
            </wa-button>
          </span>
          <span
            v-else
            class="members-email"
          >{{ member.user.email }}</span>
        </span>
        <FormSelect
          v-if="canManage"
          :model-value="member.role.key"
          :items="roleItemsFor(member.role.key, !!member.user.isAgent)"
          :disabled="pending || !!member.user.isAgent || !mayChangeRole(member.role.key)"
          :label="t('settings.members.roleOf', { name: member.user.name })"
          hide-label
          class="members-role"
          @update:model-value="role => role && setRole(member.user.id, String(role))"
        />
        <wa-badge
          v-else
          :title="t('settings.members.role')"
          variant="neutral"
          appearance="outlined"
        >
          {{ roleName(member.role) }}
        </wa-badge>
        <wa-button
          v-if="canManage"
          appearance="plain"
          variant="neutral"
          size="s"
          :disabled="pending"
          :aria-label="t('settings.members.remove', { name: member.user.name })"
          @click="removing = { id: member.user.id, name: member.user.name }"
        >
          <wa-icon name="user-minus"></wa-icon>
        </wa-button>
      </li>
    </ul>

    <form
      v-if="canManage"
      slot="footer"
      class="members-add"
      @submit.prevent="add"
    >
      <FormPersonSelect
        v-model="newMember.userId"
        :groups="personGroups"
        :label="t('settings.members.person')"
        class="members-person"
      />
      <FormSelect
        v-model="newMember.role"
        :items="newRoleItems"
        :label="t('settings.members.role')"
        :disabled="newIsAgent"
        :hint="newIsAgent ? t('settings.members.agentRoleFixed') : undefined"
        class="members-role"
      />
      <wa-button
        type="submit"
        variant="brand"
        :loading="pending"
        :disabled="!newMember.userId || !newMember.role"
      >
        <wa-icon
          slot="start"
          name="user-plus"
        ></wa-icon>
        {{ t('settings.members.add') }}
      </wa-button>
    </form>

    <ConfirmModal
      :open="removing !== null"
      :title="t('settings.members.removeTitle', { name: removing?.name ?? '' })"
      :description="t('settings.members.removeDescription')"
      :confirm-label="t('settings.members.removeConfirm')"
      :action="confirmRemove"
      @close="removing = null"
    />
  </wa-card>
</template>

<style scoped>
.members-list {
  margin: 0;
  padding: 0;
}

.members-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding-block: var(--wa-space-xs);
}

.members-row + .members-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.members-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.members-name {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.members-you {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  font-weight: var(--wa-font-weight-normal);
}

.members-email {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.members-identifier {
  display: flex;
  align-items: center;
  gap: var(--wa-space-2xs);
  min-inline-size: 0;
}

.members-copy {
  flex: none;
}

.members-role {
  inline-size: 9rem;
}

.members-add {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-s);
  inline-size: 100%;
}

.members-person {
  flex: 1;
  min-inline-size: 14rem;
}
</style>
