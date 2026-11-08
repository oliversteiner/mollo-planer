<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { DropdownMenuItem } from '@nuxt/ui'
import type { AdminUsersQuery } from '~/graphql/generated/graphql'
import { adminUsersQuery, deleteUserMutation, resetUserTwoFactorMutation } from '~/graphql/admin'
import { deleteUserAvatarMutation } from '~/graphql/profile'

type User = AdminUsersQuery['users'][number]

definePageMeta({ middleware: 'admin' })

const { t } = useI18n()
const toast = useToast()
const { viewer } = useViewer()

useHead({ title: () => t('admin.users.title') })

const { result, loading } = useQuery(adminUsersQuery, null, { fetchPolicy: 'cache-and-network' })
const query = ref('')
const users = computed(() => {
  const words = query.value.toLowerCase().split(/\s+/).filter(Boolean)

  return [...(result.value?.users ?? [])]
    .filter(user => words.every(word => `${user.name} ${user.email}`.toLowerCase().includes(word)))
    .sort((a, b) => a.name.localeCompare(b.name))
})

const editing = ref<{ user?: User } | null>(null)
const confirming = ref<{ kind: 'reset' | 'delete' | 'avatar', user: User } | null>(null)

const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: resetTwoFactor } = useMutation(resetUserTwoFactorMutation, options)
const { mutate: deleteUser } = useMutation(deleteUserMutation, options)
const { mutate: deleteAvatar } = useMutation(deleteUserAvatarMutation, options)

function startEditing(user?: User): void {
  editing.value = user ? { user } : {}
}

function ask(kind: 'reset' | 'delete' | 'avatar', user: User): void {
  confirming.value = { kind, user }
}

function actions(user: User): DropdownMenuItem[][] {
  const isSelf = user.id === viewer.value?.id
  const items: DropdownMenuItem[] = [
    { label: t('common.edit'), icon: 'i-fadl-pen', onSelect: () => startEditing(user) },
    { label: t('admin.users.resetTwoFactor'), icon: 'i-fadl-shield-halved', onSelect: () => ask('reset', user) }
  ]

  if (user.avatarUrl) {
    items.push({ label: t('admin.users.removeAvatar'), icon: 'i-fadl-image-slash', onSelect: () => ask('avatar', user) })
  }

  // The backend refuses deleting one's own account; do not offer it.
  return isSelf
    ? [items]
    : [items, [{ label: t('common.delete'), icon: 'i-fadl-trash', color: 'error', onSelect: () => ask('delete', user) }]]
}

async function confirm(): Promise<void> {
  const target = confirming.value

  if (!target) {
    return
  }
  const id = target.user.id

  if (target.kind === 'reset') {
    await resetTwoFactor({ id })
    toast.add({ title: t('admin.users.resetDone', { name: target.user.name }), color: 'success' })
  } else if (target.kind === 'avatar') {
    await deleteAvatar({ id })
  } else {
    await deleteUser({ id })
    toast.add({ title: t('admin.users.deleted', { name: target.user.name }), color: 'success' })
  }
}

const confirmTexts = computed(() => {
  const target = confirming.value
  const name = target?.user.name ?? ''

  switch (target?.kind) {
    case 'reset':
      return { title: t('admin.users.resetTitle', { name }), description: t('admin.users.resetDescription'), label: t('admin.users.resetTwoFactor') }
    case 'avatar':
      return { title: t('admin.users.avatarTitle', { name }), description: t('admin.users.avatarDescription'), label: t('admin.users.removeAvatar') }
    default:
      return { title: t('admin.users.deleteTitle', { name }), description: t('admin.users.deleteDescription'), label: t('common.delete') }
  }
})

function factorLabel(user: User): string {
  if (user.twoFactorConfirmed && user.hasPasskey) {
    return t('admin.users.factorBoth')
  }
  if (user.twoFactorConfirmed) {
    return t('admin.users.factorApp')
  }

  return user.hasPasskey ? t('admin.users.factorPasskey') : t('admin.users.factorNone')
}
</script>

<template>
  <UDashboardPanel id="admin-users">
    <template #header>
      <UDashboardNavbar :title="t('admin.users.title')">
        <template #right>
          <UButton
            icon="i-fadl-user-plus"
            :label="t('admin.users.invite')"
            @click="startEditing()"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UInput
        v-model="query"
        icon="i-fadl-magnifying-glass"
        :placeholder="t('settings.members.search')"
        :aria-label="t('settings.members.search')"
        class="w-full max-w-sm"
      />

      <USkeleton
        v-if="loading && !result"
        class="h-40"
      />
      <ul
        v-else
        class="divide-y divide-default rounded-lg border border-default"
      >
        <li
          v-for="user in users"
          :key="user.id"
          class="flex flex-wrap items-center gap-3 px-4 py-3"
        >
          <UAvatar
            :src="user.avatarUrl ?? undefined"
            :alt="user.name"
            size="md"
          />
          <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2 font-medium text-highlighted">
              <span class="truncate">{{ user.name }}</span>
              <UBadge
                v-if="user.isAdmin"
                color="primary"
                variant="subtle"
                size="sm"
              >
                {{ t('admin.users.admin') }}
              </UBadge>
              <span
                v-if="user.id === viewer?.id"
                class="text-sm font-normal text-muted"
              >({{ t('settings.members.you') }})</span>
            </span>
            <span class="block truncate text-sm text-muted">{{ user.email }}</span>
          </span>
          <UBadge
            :color="user.twoFactorConfirmed || user.hasPasskey ? 'success' : 'warning'"
            variant="subtle"
            size="sm"
          >
            {{ factorLabel(user) }}
          </UBadge>
          <UDropdownMenu :items="actions(user)">
            <UButton
              icon="i-fadl-ellipsis"
              color="neutral"
              variant="ghost"
              :aria-label="t('admin.users.actions', { name: user.name })"
            />
          </UDropdownMenu>
        </li>
      </ul>

      <AdminUserFormModal
        :target="editing"
        @close="editing = null"
      />
      <ConfirmModal
        :open="confirming !== null"
        :title="confirmTexts.title"
        :description="confirmTexts.description"
        :confirm-label="confirmTexts.label"
        :action="confirm"
        @close="confirming = null"
      />
    </template>
  </UDashboardPanel>
</template>
