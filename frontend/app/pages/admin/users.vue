<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { AdminUsersQuery } from '~/graphql/generated/graphql'
import { adminUsersQuery, approveUserMutation, deleteUserMutation, rejectUserMutation, resetUserTwoFactorMutation } from '~/graphql/admin'
import { deleteUserAvatarMutation } from '~/graphql/profile'
import { agentAccessState, type AgentAccessState } from '~/utils/agent-access'
import { copyToClipboard } from '~/utils/share'

type User = AdminUsersQuery['users'][number]

const { t } = useI18n()
const roleName = useRoleName()
const toast = useToast()
const { viewer } = useViewer()

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
}, {
  id: 'users-create-agent',
  label: t('admin.users.newAgent.title'),
  icon: 'robot',
  group: 'action',
  run: () => { creatingAgent.value = true }
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

/**
 * Access of an agent (task 32): the list only shows its state; the dialog makes, shows and revokes the accesses
 * and assigns projects (docs/verwaltung-agenten-anfasser.md §3.2, §3.3).
 */
const accessAgentId = ref<string | null>(null)
const creatingAgent = ref(false)

/**
 * A new agent is made in one step and the dialog closes (Auftrag 55, rule R11: no dialog opens from a dialog). The next
 * thing it needs, «Zugang einrichten», is a button in its row: the row is marked for a moment and the button has the
 * focus, so Enter goes on.
 */
const newAgentId = ref<string | null>(null)
let highlightTimer: ReturnType<typeof setTimeout> | undefined
let leftPage = false

onBeforeUnmount(() => {
  leftPage = true
  clearTimeout(highlightTimer)
})

async function onAgentCreated(id: string): Promise<void> {
  creatingAgent.value = false
  newAgentId.value = id
  clearTimeout(highlightTimer)
  highlightTimer = setTimeout(() => {
    newAgentId.value = null
  }, 4000)

  // The list has the new row once the dialog is gone and the page has drawn it. A dialog that closes gives the focus
  // back to the button that opened it, after its fade: wait for that, then the focus goes to the new row.
  await nextTick()

  // Each step checks that the page is still there: after leaving it (or at the end of a test) nothing is left to focus.
  for (let waited = 0; !leftPage && waited < 2000 && document.querySelector('wa-dialog[open]'); waited += 50) {
    await new Promise(resolve => setTimeout(resolve, 50))
  }
  await new Promise(resolve => setTimeout(resolve, 100))

  if (leftPage) {
    return
  }

  document.querySelector<HTMLElement>(`[data-agent-access="${id}"]`)?.focus()
}
const accessAgent = computed(() => allUsers.value.find(user => user.id === accessAgentId.value) ?? null)

const accessIndicator: Record<AgentAccessState, { variant: 'brand' | 'success' | 'warning' | 'neutral', icon: string, appearance: 'filled' | 'outlined' }> = {
  none: { variant: 'brand', icon: 'plus', appearance: 'outlined' },
  active: { variant: 'success', icon: 'key', appearance: 'outlined' },
  expiring: { variant: 'warning', icon: 'key', appearance: 'outlined' },
  expired: { variant: 'warning', icon: 'key', appearance: 'outlined' }
}

function accessState(user: User): AgentAccessState {
  return agentAccessState(user.apiTokens)
}

/**
 * Self-registered accounts waiting for approval, newest first. The search does
 * not hide them: the approval queue always shows everyone who waits.
 */
const pendingUsers = computed(() => allUsers.value
  .filter(user => !user.approved)
  .sort((a, b) => b.createdAt.localeCompare(a.createdAt)))
const people = computed(() => users.value.filter(user => user.approved && !user.isAgent))
const agents = computed(() => users.value.filter(user => user.approved && user.isAgent))

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

  // An agent has no second factor and no password to reset: its access is a token (dialog "Access and projects").
  if (user.isAgent) {
    items.push({ label: t('admin.users.access.menu'), icon: 'key', onSelect: () => void (accessAgentId.value = user.id) })
  } else {
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

const groups = computed(() => [
  { key: 'people', title: t('admin.users.sections.users'), list: people.value, total: allUsers.value.filter(user => user.approved && !user.isAgent).length, empty: t('admin.users.sections.noUsers') },
  { key: 'agents', title: t('admin.users.sections.agents'), list: agents.value, total: allUsers.value.filter(user => user.approved && user.isAgent).length, empty: t('admin.users.sections.noAgents') }
])

function factorLabel(user: User): string {
  if (user.twoFactorConfirmed && user.hasPasskey) {
    return t('admin.users.factorBoth')
  }
  if (user.twoFactorConfirmed) {
    return t('admin.users.factorApp')
  }

  return user.hasPasskey ? t('admin.users.factorPasskey') : t('admin.users.factorNone')
}

async function copyIdentifier(value: string): Promise<void> {
  try {
    await copyToClipboard(value)
    toast.add({ title: t('claude.copied'), color: 'success' })
  } catch {
    toast.add({ title: t('help.copyFailed'), color: 'error' })
  }
}
</script>

<template>
  <PageFrame
    :title="t('admin.users.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('admin.users.title') }]"
  >
    <template #actions>
      <wa-button
        variant="neutral"
        appearance="outlined"
        @click="creatingAgent = true"
      >
        <wa-icon
          slot="start"
          name="robot"
        ></wa-icon>
        {{ t('admin.users.newAgent.title') }}
      </wa-button>
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

      <section
        v-for="group in groups"
        :key="group.key"
        class="users-section"
      >
        <h2 class="wa-heading-m">
          <TabLabel
            :label="group.title"
            :count="group.list.length"
          />
        </h2>
        <div
          v-if="!group.list.length"
          class="users-empty wa-cluster wa-gap-s"
        >
          <p>{{ query.trim() && group.total ? t('admin.users.sections.noMatches') : group.empty }}</p>
          <wa-button
            v-if="group.key === 'agents' && !group.total"
            size="s"
            variant="neutral"
            appearance="outlined"
            @click="creatingAgent = true"
          >
            <wa-icon
              slot="start"
              name="robot"
            ></wa-icon>
            {{ t('admin.users.newAgent.title') }}
          </wa-button>
        </div>
        <ul
          v-else
          class="users-list wa-list-plain"
        >
          <li
            v-for="user in group.list"
            :key="user.id"
            class="users-row"
            :data-new="user.id === newAgentId ? '' : undefined"
          >
            <UserAvatar
              :name="user.name"
              :src="user.avatarUrl"
              :is-agent="user.isAgent"
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
              <span
                v-if="user.isAgent"
                class="users-identifier"
              >
                <span class="users-email">{{ t('settings.members.identifier') }}: {{ user.email }}</span>
                <wa-button
                  appearance="plain"
                  variant="neutral"
                  size="s"
                  class="users-copy"
                  :aria-label="t('settings.members.copyIdentifier', { name: user.name })"
                  @click="copyIdentifier(user.email)"
                >
                  <wa-icon name="copy"></wa-icon>
                </wa-button>
              </span>
              <span
                v-else
                class="users-email"
              >{{ user.email }}</span>
            </span>
            <wa-button
              v-if="user.isAgent"
              :variant="accessIndicator[accessState(user)].variant"
              :appearance="accessIndicator[accessState(user)].appearance"
              size="s"
              class="users-access"
              :data-state="accessState(user)"
              :data-agent-access="user.id"
              @click="accessAgentId = user.id"
            >
              <wa-icon
                slot="start"
                :name="accessIndicator[accessState(user)].icon"
              ></wa-icon>
              {{ t(`admin.users.access.state.${accessState(user)}`) }}
            </wa-button>
            <wa-badge
              v-else
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
      </section>
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
    <AdminAgentFormModal
      :open="creatingAgent"
      @close="creatingAgent = false"
      @created="onAgentCreated"
    />
    <AdminAgentAccessDialog
      v-if="accessAgent"
      :agent="accessAgent"
      @close="accessAgentId = null"
    />
  </PageFrame>
</template>

<style scoped>
.users-section {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-s);
}

.users-empty {
  color: var(--wa-color-text-quiet);
}

.users-empty p {
  margin: 0;
}

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

.users-row[data-new] {
  background-color: var(--wa-color-brand-fill-quiet);
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

.users-identifier {
  display: flex;
  align-items: center;
  gap: var(--wa-space-2xs);
  min-inline-size: 0;
}

.users-copy {
  flex: none;
}

.users-email {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
