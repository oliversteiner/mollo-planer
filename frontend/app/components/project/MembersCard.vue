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
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ t('settings.members.title') }}
      </h2>
      <p class="text-sm text-muted">
        {{ t('settings.members.intro') }}
      </p>
    </template>

    <ul class="divide-y divide-default">
      <li
        v-for="member in sortedMembers"
        :key="member.user.id"
        class="flex flex-wrap items-center gap-3 py-2"
      >
        <UAvatar
          :src="member.user.avatarUrl ?? undefined"
          :alt="member.user.name"
          size="sm"
        />
        <span class="min-w-0 flex-1">
          <span class="block truncate font-medium">
            {{ member.user.name }}
            <span
              v-if="member.user.id === viewer?.id"
              class="text-sm font-normal text-muted"
            >({{ t('settings.members.you') }})</span>
          </span>
          <span class="block truncate text-sm text-muted">{{ member.user.email }}</span>
        </span>
        <USelect
          v-if="canManage"
          :model-value="member.role"
          :items="roleItems"
          :disabled="pending"
          :aria-label="t('settings.members.roleOf', { name: member.user.name })"
          class="w-36"
          @update:model-value="role => setRole(member.user.id, role as ProjectRole)"
        />
        <UBadge
          v-else
          color="neutral"
          variant="outline"
        >
          {{ t(`role.${member.role}`) }}
        </UBadge>
        <UButton
          v-if="canManage"
          icon="i-fadl-user-minus"
          color="neutral"
          variant="ghost"
          size="sm"
          :disabled="pending"
          :aria-label="t('settings.members.remove', { name: member.user.name })"
          @click="removing = { id: member.user.id, name: member.user.name }"
        />
      </li>
    </ul>

    <template
      v-if="canManage"
      #footer
    >
      <form
        class="flex flex-wrap items-end gap-2"
        @submit.prevent="add"
      >
        <UFormField
          :label="t('settings.members.person')"
          class="min-w-56 flex-1"
        >
          <USelectMenu
            v-model="newMember.userId"
            :items="candidates"
            value-key="value"
            :placeholder="t('settings.members.choose')"
            :search-input="{ placeholder: t('settings.members.search') }"
            class="w-full"
          />
        </UFormField>
        <UFormField :label="t('settings.members.role')">
          <USelect
            v-model="newMember.role"
            :items="roleItems"
            class="w-36"
          />
        </UFormField>
        <UButton
          type="submit"
          icon="i-fadl-user-plus"
          :label="t('settings.members.add')"
          :loading="pending"
          :disabled="!newMember.userId"
        />
      </form>
    </template>

    <ConfirmModal
      :open="removing !== null"
      :title="t('settings.members.removeTitle', { name: removing?.name ?? '' })"
      :description="t('settings.members.removeDescription')"
      :confirm-label="t('settings.members.removeConfirm')"
      :action="confirmRemove"
      @close="removing = null"
    />
  </UCard>
</template>
