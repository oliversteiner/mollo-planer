<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ProjectRole } from '~/graphql/generated/graphql'
import { removeProjectMemberMutation, setProjectMemberMutation, usersQuery } from '~/graphql/settings'
import { classifyApolloError } from '~/utils/api'

/**
 * Members and their roles. Who may grant or take the owner role, and that
 * the last owner stays, is decided by the backend; its answer is shown.
 */
const props = defineProps<{
  projectId: string
  members: { role: ProjectRole, user: { id: string, name: string, email: string, avatarUrl?: string | null } }[]
  canManage: boolean
}>()

const { t } = useI18n()
const toast = useToast()
const { viewer } = useViewer()

const roles: ProjectRole[] = ['OWNER', 'MANAGER', 'MEMBER']
const roleItems = computed(() => roles.map(role => ({ label: t(`role.${role}`), value: role })))

const sortedMembers = computed(() => [...props.members].sort((a, b) => a.user.name.localeCompare(b.user.name)))

const { result: usersResult } = useQuery(usersQuery, null, () => ({ enabled: props.canManage }))
const candidates = computed(() => {
  const memberIds = new Set(props.members.map(member => member.user.id))

  return (usersResult.value?.users ?? [])
    .filter(user => !memberIds.has(user.id))
    .sort((a, b) => a.name.localeCompare(b.name))
    .map(user => ({ label: `${user.name} (${user.email})`, value: user.id }))
})

const newMember = reactive<{ userId: string, role: ProjectRole }>({ userId: '', role: 'MEMBER' })
const pending = ref(false)
const removing = ref<{ id: string, name: string } | null>(null)

const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: setMember } = useMutation(setProjectMemberMutation, options)
const { mutate: removeMember } = useMutation(removeProjectMemberMutation, options)

/** Runs a change; a validation message (for example "last owner") becomes a toast. */
async function change(action: () => Promise<unknown>, success: string): Promise<boolean> {
  pending.value = true
  try {
    await action()
    toast.add({ title: success, color: 'success' })

    return true
  } catch (error) {
    const apiError = classifyApolloError(error as Parameters<typeof classifyApolloError>[0])
    const message = Object.values(apiError.fields)[0]?.[0]

    if (message) {
      toast.add({ title: message, color: 'error' })
    }

    return false
  } finally {
    pending.value = false
  }
}

async function setRole(userId: string, role: ProjectRole): Promise<void> {
  await change(() => setMember({ projectId: props.projectId, userId, role }), t('settings.members.roleChanged'))
}

async function add(): Promise<void> {
  if (!newMember.userId) {
    return
  }
  const added = await change(() => setMember({ projectId: props.projectId, userId: newMember.userId, role: newMember.role }), t('settings.members.added'))

  if (added) {
    Object.assign(newMember, { userId: '', role: 'MEMBER' })
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
      <p class="wa-body-s wa-color-text-quiet">
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
          size="sm"
        />
        <span class="members-text">
          <span class="members-name">
            {{ member.user.name }}
            <span
              v-if="member.user.id === viewer?.id"
              class="members-you"
            >({{ t('settings.members.you') }})</span>
          </span>
          <span class="members-email">{{ member.user.email }}</span>
        </span>
        <FormSelect
          v-if="canManage"
          :model-value="member.role"
          :items="roleItems"
          :disabled="pending"
          :label="t('settings.members.roleOf', { name: member.user.name })"
          hide-label
          class="members-role"
          @update:model-value="role => role && setRole(member.user.id, role as ProjectRole)"
        />
        <wa-badge
          v-else
          variant="neutral"
          appearance="outlined"
        >
          {{ t(`role.${member.role}`) }}
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
      <FormSelect
        v-model="newMember.userId"
        :items="candidates"
        searchable
        :label="t('settings.members.person')"
        class="members-person"
      />
      <FormSelect
        v-model="newMember.role"
        :items="roleItems"
        :label="t('settings.members.role')"
        class="members-role"
      />
      <wa-button
        type="submit"
        variant="brand"
        :loading="pending"
        :disabled="!newMember.userId"
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
