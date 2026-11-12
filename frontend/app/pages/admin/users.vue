<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { AdminUsersQuery } from '~/graphql/generated/graphql'
import { adminUsersQuery, approveUserMutation, deleteUserMutation, rejectUserMutation, resetUserTwoFactorMutation } from '~/graphql/admin'
import { deleteUserAvatarMutation } from '~/graphql/profile'

type User = AdminUsersQuery['users'][number]

definePageMeta({ middleware: 'admin' })

const { t } = useI18n()
const toast = useToast()
const { viewer } = useViewer()

useHead({ title: () => t('admin.users.title') })

const { result, loading, refetch } = useQuery(adminUsersQuery, null, { fetchPolicy: 'cache-and-network' })
const query = ref('')
const allUsers = computed(() => [...(result.value?.users ?? [])])
const users = computed(() => {
  const words = query.value.toLowerCase().split(/\s+/).filter(Boolean)

  return allUsers.value
    .filter(user => words.every(word => `${user.name} ${user.email}`.toLowerCase().includes(word)))
    .sort((a, b) => a.name.localeCompare(b.name))
})

const editing = ref<{ user?: User } | null>(null)
const confirming = ref<{ kind: ConfirmKind, user: User } | null>(null)

const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: resetTwoFactor } = useMutation(resetUserTwoFactorMutation, options)
const { mutate: deleteUser } = useMutation(deleteUserMutation, options)
const { mutate: deleteAvatar } = useMutation(deleteUserAvatarMutation, options)
const { mutate: approveUser } = useMutation(approveUserMutation, options)
const { mutate: rejectUser } = useMutation(rejectUserMutation, options)

/**
 * Self-registered accounts waiting for approval, newest first. The search does
 * not hide them: the approval queue always shows everyone who waits.
 */
const pendingUsers = computed(() => allUsers.value
  .filter(user => !user.approved)
  .sort((a, b) => b.createdAt.localeCompare(a.createdAt)))
const activeUsers = computed(() => users.value.filter(user => user.approved))

/**
 * A failed approval or rejection (for example because the account was already
 * approved in the meantime) reloads the list, so the screen shows the real state.
 */
async function approve(user: User): Promise<void> {
  try {
    await approveUser({ id: user.id }, { context: { silentErrors: true } })
    toast.add({ title: t('admin.users.pending.approved', { name: user.name }), color: 'success' })
  } catch {
    await refetch()
    toast.add({ title: t('admin.users.pending.failed'), color: 'error' })
  }
}

function startEditing(user?: User): void {
  editing.value = user ? { user } : {}
}

function ask(kind: ConfirmKind, user: User): void {
  confirming.value = { kind, user }
}

function actions(user: User): ActionMenuItem[][] {
  const isSelf = user.id === viewer.value?.id
  const items: ActionMenuItem[] = [
    { label: t('common.edit'), icon: 'pen', onSelect: () => startEditing(user) },
    { label: t('admin.users.resetTwoFactor'), icon: 'shield-halved', onSelect: () => ask('reset', user) }
  ]

  if (user.avatarUrl) {
    items.push({ label: t('admin.users.removeAvatar'), icon: 'image-slash', onSelect: () => ask('avatar', user) })
  }

  // The backend refuses deleting one's own account; do not offer it.
  return isSelf
    ? [items]
    : [items, [{ label: t('common.delete'), icon: 'trash', color: 'error', onSelect: () => ask('delete', user) }]]
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
  } else if (target.kind === 'reject') {
    try {
      await rejectUser({ id }, { context: { silentErrors: true } })
    } catch {
      await refetch()
      toast.add({ title: t('admin.users.pending.failed'), color: 'error' })
      return
    }
    toast.add({ title: t('admin.users.pending.rejected', { name: target.user.name }), color: 'success' })
  } else {
    await deleteUser({ id })
    toast.add({ title: t('admin.users.deleted', { name: target.user.name }), color: 'success' })
  }
}

type ConfirmKind = 'reset' | 'delete' | 'avatar' | 'reject'

/** Title, text and button of each confirmation; the name of the person fills the title. */
const confirmCopy: Record<ConfirmKind, (name: string) => { title: string, description: string, label: string }> = {
  reset: name => ({ title: t('admin.users.resetTitle', { name }), description: t('admin.users.resetDescription'), label: t('admin.users.resetTwoFactor') }),
  avatar: name => ({ title: t('admin.users.avatarTitle', { name }), description: t('admin.users.avatarDescription'), label: t('admin.users.removeAvatar') }),
  reject: name => ({ title: t('admin.users.pending.rejectTitle', { name }), description: t('admin.users.pending.rejectDescription'), label: t('admin.users.pending.reject') }),
  delete: name => ({ title: t('admin.users.deleteTitle', { name }), description: t('admin.users.deleteDescription'), label: t('common.delete') })
}

const confirmTexts = computed(() => confirmCopy[confirming.value?.kind ?? 'delete'](confirming.value?.user.name ?? ''))

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
  <PageFrame
    :title="t('admin.users.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('admin.users.title') }]"
  >
    <template #actions>
      <wa-button
        variant="brand"
        @click="startEditing()"
      >
        <wa-icon
          slot="start"
          name="user-plus"
        ></wa-icon>
        {{ t('admin.users.invite') }}
      </wa-button>
    </template>

    <FormInput
      v-model="query"
      :placeholder="t('settings.members.search')"
      type="search"
      autocomplete="off"
      :label="t('settings.members.search')"
      hide-label
      class="users-search"
    >
      <template #start>
        <wa-icon
          slot="start"
          name="magnifying-glass"
        ></wa-icon>
      </template>
    </FormInput>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="users-skeleton"
    ></wa-skeleton>
    <template v-else>
      <AdminPendingUsers
        :users="pendingUsers"
        @approve="approve"
        @reject="ask('reject', $event)"
      />

      <ul class="users-list wa-list-plain">
        <li
          v-for="user in activeUsers"
          :key="user.id"
          class="users-row"
        >
          <UserAvatar
            :name="user.name"
            :src="user.avatarUrl"
            size="md"
          />
          <span class="users-text">
            <span class="users-name">
              <span class="users-name-text">{{ user.name }}</span>
              <wa-badge
                v-if="user.role !== 'MITGLIED'"
                variant="brand"
                appearance="filled"
              >
                {{ t(`userRole.${user.role}`) }}
              </wa-badge>
              <span
                v-if="user.id === viewer?.id"
                class="users-you"
              >({{ t('settings.members.you') }})</span>
            </span>
            <span class="users-email">{{ user.email }}</span>
          </span>
          <wa-badge
            :variant="user.twoFactorConfirmed || user.hasPasskey ? 'success' : 'warning'"
            appearance="filled"
          >
            {{ factorLabel(user) }}
          </wa-badge>
          <ActionMenu
            :items="actions(user)"
            :label="t('admin.users.actions', { name: user.name })"
            size="m"
          />
        </li>
      </ul>
    </template>

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
  </PageFrame>
</template>

<style scoped>
.users-search {
  max-inline-size: 24rem;
}

.users-skeleton {
  block-size: 10rem;
}

.users-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.users-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-s) var(--wa-space-m);
}

.users-row + .users-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.users-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.users-name {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
  font-weight: var(--wa-font-weight-semibold);
}

.users-name-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.users-you {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  font-weight: var(--wa-font-weight-normal);
}

.users-email {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
