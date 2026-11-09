<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
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
    <ul
      v-else
      class="users-list wa-list-plain"
    >
      <li
        v-for="user in users"
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
              v-if="user.isAdmin"
              variant="brand"
              appearance="filled"
            >
              {{ t('admin.users.admin') }}
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
