<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { AdminUsersQuery } from '~/graphql/generated/graphql'
import { adminUsersQuery, approveUserMutation, createAgentTokenMutation, deleteUserMutation, rejectUserMutation, resetUserTwoFactorMutation, revokeAgentTokenMutation } from '~/graphql/admin'
import { deleteUserAvatarMutation } from '~/graphql/profile'
import { formatDateTime } from '~/utils/dates'

type User = AdminUsersQuery['users'][number]

const { t, localeProperties } = useI18n()
const language = computed(() => localeProperties.value.language ?? 'de-CH')
const roleName = useRoleName()
const toast = useToast()
const { viewer } = useViewer()
const { ensurePasswordConfirmed } = useSecurity()

useHead({ title: () => t('admin.users.title') })

const { result, loading, refetch } = useQuery(adminUsersQuery, null, { fetchPolicy: 'cache-and-network' })
const query = ref('')

// The button is in the page actions, which the calm view hides.
useCommands(() => [{
  id: 'users-invite',
  label: t('admin.users.invite'),
  icon: 'user-plus',
  group: 'action',
  run: () => startEditing()
}])
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
const { mutate: createAgentToken } = useMutation(createAgentTokenMutation, options)
const { mutate: revokeAgentToken } = useMutation(revokeAgentTokenMutation, options)

/**
 * Connector accesses of an agent account (task 32). An agent cannot log in to make its own, so an admin makes it
 * here: after a recent password confirmation, shown once in a dialog. Never for a person's account.
 */
const accessNames = ref<Record<string, string>>({})
const creatingFor = ref<string | null>(null)
const created = ref<{ agent: string, plainTextToken: string } | null>(null)
const revoking = ref<{ user: User, tokenId: string, name: string } | null>(null)
const { errors, pending, run } = useGraphqlForm()

async function createAccess(user: User): Promise<void> {
  if (!await ensurePasswordConfirmed()) {
    return
  }
  creatingFor.value = user.id
  const result = await run(() => createAgentToken({ userId: user.id, name: (accessNames.value[user.id] ?? '').trim() }))
  const token = result?.data?.createAgentToken

  if (token) {
    created.value = { agent: user.name, plainTextToken: token.plainTextToken }
    accessNames.value[user.id] = ''
  }
}

async function copyToken(): Promise<void> {
  if (created.value) {
    await navigator.clipboard.writeText(created.value.plainTextToken)
    toast.add({ title: t('claude.copied'), color: 'success' })
  }
}

async function confirmRevoke(): Promise<void> {
  const target = revoking.value

  if (!target) {
    return
  }

  // A cancelled password prompt leaves the question open instead of closing it as if the access were revoked.
  if (!await ensurePasswordConfirmed()) {
    throw new Error('password confirmation cancelled')
  }
  await revokeAgentToken({ userId: target.user.id, tokenId: target.tokenId })
  toast.add({ title: t('admin.users.agent.revoked'), color: 'success' })
}

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
    { label: t('common.edit'), icon: 'pen', onSelect: () => startEditing(user) }
  ]

  // An agent has no second factor and no password to reset: its access is a token (below the name, or app:user-token).
  if (!user.isAgent) {
    items.push({ label: t('admin.users.resetTwoFactor'), icon: 'shield-halved', onSelect: () => ask('reset', user) })
  }

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
    try {
      await deleteUser({ id })
    } catch (error) {
      // For example: the only person who may delete a project (§7.3).
      const reason = refusalMessage(error)

      if (reason) {
        toast.add({ title: reason, color: 'error', duration: 0 })
      }
      return
    }
    toast.add({ title: t('admin.users.deleted', { name: target.user.name }), color: 'success' })
  }
}

type ConfirmKind = 'reset' | 'delete' | 'avatar' | 'reject'

/** Title, text and button of each confirmation; the name of the person fills the title. */
const confirmCopy: Record<ConfirmKind, (name: string) => { title: string, description: string, label: string }> = {
  reset: name => ({ title: t('admin.users.resetTitle', { name }), description: t('admin.users.resetDescription', { name }), label: t('admin.users.resetConfirm') }),
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
      name="people-filter"
      autocomplete="off"
      data-1p-ignore
      data-lpignore="true"
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
              <AgentBadge v-if="user.isAgent" />
              <wa-badge
                v-if="!user.role.isDefault"
                :title="t('admin.users.role')"
                variant="brand"
                appearance="filled"
              >
                {{ roleName(user.role) }}
              </wa-badge>
              <span
                v-if="user.id === viewer?.id"
                class="users-you"
              >({{ t('settings.members.you') }})</span>
            </span>
            <span class="users-email">{{ user.email }}</span>
          </span>
          <wa-badge
            v-if="!user.isAgent"
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
          <div
            v-if="user.isAgent"
            class="users-access wa-stack wa-gap-s"
          >
            <h3 class="wa-heading-s">
              {{ t('admin.users.agent.title') }}
            </h3>
            <ul
              v-if="user.apiTokens?.length"
              class="users-access-list wa-list-plain"
            >
              <li
                v-for="token in user.apiTokens"
                :key="token.id"
                class="users-access-row"
              >
                <wa-icon
                  name="key"
                  class="users-access-icon"
                ></wa-icon>
                <span class="users-text">
                  <span class="users-access-name">{{ token.name }}</span>
                  <span class="users-access-sub">
                    {{ token.lastUsedAt ? t('claude.lastUsed', { date: formatDateTime(token.lastUsedAt, language) }) : t('claude.neverUsed') }}
                    <template v-if="token.expiresAt"> · {{ t('claude.expires', { date: formatDateTime(token.expiresAt, language) }) }}</template>
                  </span>
                </span>
                <wa-button
                  appearance="plain"
                  variant="neutral"
                  size="s"
                  :aria-label="t('admin.users.agent.revoke', { name: token.name })"
                  @click="revoking = { user, tokenId: token.id, name: token.name }"
                >
                  <wa-icon name="trash"></wa-icon>
                </wa-button>
              </li>
            </ul>
            <p
              v-else
              class="users-access-sub"
            >
              {{ t('admin.users.agent.none') }}
            </p>
            <form
              class="users-access-form"
              @submit.prevent="createAccess(user)"
            >
              <FormInput
                v-model="accessNames[user.id]"
                class="users-access-input"
                :label="t('admin.users.agent.name')"
                :hint="t('admin.users.agent.nameHint', { agent: user.name })"
                :error="creatingFor === user.id ? (errors.name ?? errors.password ?? errors.userId) : undefined"
                maxlength="60"
              />
              <wa-button
                type="submit"
                variant="brand"
                :loading="pending && creatingFor === user.id"
              >
                <wa-icon
                  slot="start"
                  name="key"
                ></wa-icon>
                {{ t('admin.users.agent.create') }}
              </wa-button>
            </form>
            <p class="users-access-sub">
              {{ t('admin.users.agent.cli') }}
            </p>
          </div>
        </li>
      </ul>
    </template>

    <AdminUserFormModal
      :target="editing"
      @close="editing = null"
    />
    <AppDialog
      :open="created !== null"
      :label="t('admin.users.agent.showOnceTitle', { agent: created?.agent ?? '' })"
      @update:open="created = null"
    >
      <div class="wa-stack wa-gap-s">
        <AppCallout
          variant="warning"
          :title="t('admin.users.agent.showOnce')"
        />
        <FormField :label="t('admin.users.agent.token')">
          <div class="users-access-copy">
            <FormInput
              :model-value="created?.plainTextToken ?? ''"
              :label="t('admin.users.agent.token')"
              hide-label
              readonly
              class="users-access-token"
            />
            <wa-button
              appearance="outlined"
              variant="neutral"
              :aria-label="t('admin.users.agent.copyToken')"
              @click="copyToken"
            >
              <wa-icon name="copy"></wa-icon>
            </wa-button>
          </div>
        </FormField>
      </div>
      <template #footer>
        <wa-button
          variant="brand"
          @click="created = null"
        >
          {{ t('security.done') }}
        </wa-button>
      </template>
    </AppDialog>
    <ConfirmModal
      :open="confirming !== null"
      :title="confirmTexts.title"
      :description="confirmTexts.description"
      :confirm-label="confirmTexts.label"
      :action="confirm"
      @close="confirming = null"
    />
    <ConfirmModal
      :open="revoking !== null"
      :title="t('admin.users.agent.revokeTitle', { name: revoking?.name ?? '' })"
      :description="t('admin.users.agent.revokeDescription', { agent: revoking?.user.name ?? '' })"
      :confirm-label="t('admin.users.agent.revokeConfirm')"
      :action="confirmRevoke"
      @close="revoking = null"
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

.users-access {
  flex-basis: 100%;
  padding-block-start: var(--wa-space-xs);
}

.users-access-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
}

.users-access-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-s);
}

.users-access-row + .users-access-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.users-access-icon {
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.users-access-name {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.users-access-sub {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.users-access-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-s);
}

.users-access-input {
  flex: 1;
  min-inline-size: 12rem;
}

.users-access-copy {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  inline-size: 100%;
}

.users-access-token {
  flex: 1;
  font-family: var(--wa-font-family-code);
}

.users-email {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
