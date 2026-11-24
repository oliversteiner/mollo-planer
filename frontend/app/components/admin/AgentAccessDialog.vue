<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { AdminUsersQuery } from '~/graphql/generated/graphql'
import { agentProjectsQuery, createAgentTokenMutation, revokeAgentTokenMutation } from '~/graphql/admin'
import { removeProjectMemberMutation, setProjectMemberMutation } from '~/graphql/settings'
import { agentAccessState } from '~/utils/agent-access'
import { formatDateTime } from '~/utils/dates'
import { assignableRoles } from '~/utils/role-rights'

/**
 * Access and projects of an agent account (docs/verwaltung-agenten-anfasser.md §3.3): what an agent is, the steps to
 * set it up (with their state), its accesses (a new token is shown once, in this dialog) and the projects it works in.
 * Confirmations (password, revoke, remove) hide this dialog while they are open and bring it back afterwards, so
 * there are never two dialogs on top of each other.
 */
type User = AdminUsersQuery['users'][number]

const props = defineProps<{
  /** The agent whose access is shown; null: closed. */
  agent: User | null
}>()

const emit = defineEmits<{ close: [] }>()

const { t, localeProperties, locale } = useI18n()
const language = computed(() => localeProperties.value.language ?? 'de-CH')
const roleName = useRoleName()
const toast = useToast()
const { ensurePasswordConfirmed } = useSecurity()
/** The prompt for the password is a dialog of its own: this one steps aside only while it is really shown. */
const { open: promptOpen } = usePasswordPrompt()
const apiBase = useApiBase()

const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: createAgentToken } = useMutation(createAgentTokenMutation, options)
const { mutate: revokeAgentToken } = useMutation(revokeAgentTokenMutation, options)
const { mutate: setMember } = useMutation(setProjectMemberMutation, options)
const { mutate: removeMember } = useMutation(removeProjectMemberMutation, options)

const serverAddress = computed(() => `${apiBase.replace(/\/$/, '')}/mcp`)
const guideUrl = computed(() => (locale.value === 'en' ? '/mcp/' : '/mcp/de/'))

// ─── Accesses ────────────────────────────────────────────────────

const accessName = ref('')
const created = ref<string | null>(null)
const revoking = ref<{ tokenId: string, name: string } | null>(null)
const { errors, pending, run } = useGraphqlForm()

const state = computed(() => agentAccessState(props.agent?.apiTokens))

async function createAccess(): Promise<void> {
  const agent = props.agent

  if (!agent) {
    return
  }

  if (!await ensurePasswordConfirmed()) {
    return
  }
  const result = await run(() => createAgentToken({ userId: agent.id, name: accessName.value.trim() }))
  const token = result?.data?.createAgentToken

  if (token) {
    created.value = token.plainTextToken
    accessName.value = ''
  }
}

async function copy(text: string): Promise<void> {
  await navigator.clipboard.writeText(text)
  toast.add({ title: t('claude.copied'), color: 'success' })
}

async function confirmRevoke(): Promise<void> {
  const target = revoking.value

  if (!target || !props.agent) {
    return
  }

  // A cancelled password prompt leaves the question open instead of closing it as if the access were revoked.
  if (!await ensurePasswordConfirmed()) {
    throw new Error('password confirmation cancelled')
  }
  await revokeAgentToken({ userId: props.agent.id, tokenId: target.tokenId })
  toast.add({ title: t('admin.users.agent.revoked'), color: 'success' })
}

// ─── Projects (loaded when the dialog opens) ─────────────────────

const { result: projectsResult } = useQuery(agentProjectsQuery, null, () => ({ enabled: props.agent !== null, fetchPolicy: 'cache-and-network' }))
const { roles, defaultKey } = useRoles('PROJECT')

type ProjectEntry = NonNullable<typeof projectsResult.value>['projects'][number]

const memberships = computed(() => (projectsResult.value?.projects ?? [])
  .flatMap((project) => {
    const member = project.members.find(entry => entry.user.id === props.agent?.id)

    return member ? [{ project, role: member.role }] : []
  })
  .sort((a, b) => a.project.name.localeCompare(b.project.name)))

const canManage = (project: ProjectEntry): boolean => project.viewerRights.includes('MEMBERS_MANAGE')

/** Projects the person may add the agent to: not yet a member, and the right to manage members. */
const assignable = computed(() => (projectsResult.value?.projects ?? [])
  .filter(project => canManage(project) && !project.members.some(entry => entry.user.id === props.agent?.id))
  .sort((a, b) => a.name.localeCompare(b.name)))

const newProjectId = ref<string | null>(null)
const newRole = ref('')
const assignment = useGraphqlForm()
const removing = ref<{ projectId: string, name: string } | null>(null)

const selectedProject = computed(() => assignable.value.find(project => project.id === newProjectId.value) ?? null)
const roleItems = computed(() => assignableRoles(roles.value, selectedProject.value?.viewerRights ?? [])
  .map(role => ({ label: roleName(role), value: role.key })))

// The agent role is the natural one for an agent; otherwise the default role of the project roles.
const preferredRole = computed(() => roles.value.find(role => role.key === 'agent')?.key ?? defaultKey.value)

watch([newProjectId, preferredRole], () => {
  if (!newRole.value || !roleItems.value.some(item => item.value === newRole.value)) {
    newRole.value = roleItems.value.some(item => item.value === preferredRole.value) ? preferredRole.value : ''
  }
}, { immediate: true })

const assignError = computed(() => Object.values(assignment.errors.value)[0])

async function assign(): Promise<void> {
  const agent = props.agent

  if (!agent || !newProjectId.value || !newRole.value) {
    return
  }
  const result = await assignment.run(() => setMember({ projectId: newProjectId.value!, userId: agent.id, role: newRole.value }))

  if (result) {
    toast.add({ title: t('settings.members.added'), color: 'success' })
    newProjectId.value = null
  }
}

async function confirmRemove(): Promise<void> {
  if (removing.value && props.agent) {
    await removeMember({ projectId: removing.value.projectId, userId: props.agent.id })
    toast.add({ title: t('settings.members.removed'), color: 'success' })
  }
}

// ─── Steps ───────────────────────────────────────────────────────

/** done: null = the app cannot know (token filed, connector entered). */
const steps = computed(() => [
  { key: 'create', done: state.value === 'active' || state.value === 'expiring' },
  { key: 'store', done: null },
  { key: 'connect', done: null },
  { key: 'project', done: memberships.value.length > 0 }
])

/** Hidden while another dialog asks something (password, revoke, remove); it comes back afterwards. */
const stepsAside = computed(() => promptOpen.value || revoking.value !== null || removing.value !== null)
const visible = computed(() => props.agent !== null && !stepsAside.value)

/**
 * Closing counts only as the person's wish: not while the dialog steps aside, and not while an access is being made
 * (its token would be lost). `created` is cleared only here or by «Fertig».
 */
function onOpenChange(open: boolean): void {
  if (!open && props.agent && !stepsAside.value && !pending.value) {
    created.value = null
    emit('close')
  }
}
</script>

<template>
  <div>
    <AppDialog
      :open="visible"
      :label="t('admin.users.access.title', { agent: agent?.name ?? '' })"
      class="access-dialog"
      @update:open="onOpenChange"
    >
      <div class="wa-stack wa-gap-l">
        <p class="access-intro">
          {{ t('admin.users.access.intro') }}
        </p>

        <ol
          class="access-steps wa-list-plain"
          :aria-label="t('admin.users.access.stepsLabel')"
        >
          <li
            v-for="(step, index) in steps"
            :key="step.key"
            class="access-step"
            :data-done="step.done === null ? undefined : String(step.done)"
          >
            <span class="access-step-number">{{ index + 1 }}</span>
            <span class="access-step-text">
              {{ t(`admin.users.access.steps.${step.key}`) }}
              <span
                v-if="step.done !== null"
                class="wa-visually-hidden"
              >: {{ step.done ? t('admin.users.access.done') : t('admin.users.access.open') }}</span>
              <wa-icon
                v-if="step.done"
                name="circle-check"
                class="access-step-done"
              ></wa-icon>
            </span>
          </li>
        </ol>

        <section class="wa-stack wa-gap-xs">
          <h3 class="wa-heading-s">
            {{ t('admin.users.access.address') }}
          </h3>
          <div class="access-copy">
            <FormInput
              :model-value="serverAddress"
              :label="t('admin.users.access.address')"
              hide-label
              readonly
              class="access-copy-field"
            />
            <wa-button
              appearance="outlined"
              variant="neutral"
              :aria-label="t('admin.users.access.copyAddress')"
              @click="copy(serverAddress)"
            >
              <wa-icon name="copy"></wa-icon>
            </wa-button>
          </div>
          <a
            :href="guideUrl"
            target="_blank"
            rel="noopener"
            class="access-sub"
          >{{ t('admin.users.access.guide') }}</a>
        </section>

        <section class="wa-stack wa-gap-s">
          <h3 class="wa-heading-s">
            {{ t('admin.users.agent.title') }}
          </h3>

          <div
            v-if="created"
            class="wa-stack wa-gap-s"
          >
            <AppCallout
              variant="warning"
              :title="t('admin.users.agent.showOnceTitle', { agent: agent?.name ?? '' })"
              :description="t('admin.users.agent.showOnce')"
            />
            <div class="access-copy">
              <FormInput
                :model-value="created"
                :label="t('admin.users.agent.token')"
                readonly
                class="access-copy-field access-token"
              />
              <wa-button
                appearance="outlined"
                variant="neutral"
                :aria-label="t('admin.users.agent.copyToken')"
                @click="copy(created)"
              >
                <wa-icon name="copy"></wa-icon>
              </wa-button>
            </div>
            <div>
              <wa-button
                variant="brand"
                @click="created = null"
              >
                {{ t('security.done') }}
              </wa-button>
            </div>
          </div>

          <ul
            v-if="agent?.apiTokens?.length"
            class="access-list wa-list-plain"
          >
            <li
              v-for="token in agent.apiTokens"
              :key="token.id"
              class="access-row"
            >
              <wa-icon
                name="key"
                class="access-icon"
              ></wa-icon>
              <span class="access-text">
                <span class="access-name">{{ token.name }}</span>
                <span class="access-sub">
                  {{ token.lastUsedAt ? t('claude.lastUsed', { date: formatDateTime(token.lastUsedAt, language) }) : t('claude.neverUsed') }}
                  <template v-if="token.expiresAt"> · {{ t('claude.expires', { date: formatDateTime(token.expiresAt, language) }) }}</template>
                </span>
              </span>
              <wa-button
                appearance="plain"
                variant="neutral"
                size="s"
                :aria-label="t('admin.users.agent.revoke', { name: token.name })"
                @click="revoking = { tokenId: token.id, name: token.name }"
              >
                <wa-icon name="trash"></wa-icon>
              </wa-button>
            </li>
          </ul>
          <p
            v-else
            class="access-sub"
          >
            {{ t('admin.users.agent.none') }}
          </p>

          <form
            class="access-form"
            @submit.prevent="createAccess"
          >
            <FormInput
              v-model="accessName"
              class="access-form-input"
              :label="t('admin.users.agent.name')"
              :hint="t('admin.users.agent.nameHint', { agent: agent?.name ?? '' })"
              :error="errors.name ?? errors.password ?? errors.userId"
              maxlength="60"
            />
            <wa-button
              type="submit"
              variant="brand"
              :loading="pending"
            >
              <wa-icon
                slot="start"
                name="key"
              ></wa-icon>
              {{ t('admin.users.agent.create') }}
            </wa-button>
          </form>
          <p class="access-sub">
            {{ t('admin.users.agent.cli') }}
          </p>
        </section>

        <section class="wa-stack wa-gap-s">
          <h3 class="wa-heading-s">
            {{ t('admin.users.access.projects') }}
          </h3>
          <ul
            v-if="memberships.length"
            class="access-list wa-list-plain"
          >
            <li
              v-for="entry in memberships"
              :key="entry.project.id"
              class="access-row"
            >
              <span class="access-text">
                <NuxtLink
                  :to="`/projects/${entry.project.id}/members`"
                  class="access-name"
                  @click="emit('close')"
                >{{ entry.project.name }}</NuxtLink>
              </span>
              <wa-badge
                variant="neutral"
                appearance="outlined"
              >
                {{ roleName(entry.role) }}
              </wa-badge>
              <wa-button
                v-if="canManage(entry.project)"
                appearance="plain"
                variant="neutral"
                size="s"
                :aria-label="t('admin.users.access.removeFrom', { project: entry.project.name })"
                @click="removing = { projectId: entry.project.id, name: entry.project.name }"
              >
                <wa-icon name="user-minus"></wa-icon>
              </wa-button>
            </li>
          </ul>
          <p
            v-else
            class="access-sub"
          >
            {{ t('admin.users.access.noProjects') }}
          </p>

          <form
            v-if="assignable.length"
            class="access-form"
            @submit.prevent="assign"
          >
            <FormSelect
              v-model="newProjectId"
              :items="assignable.map(project => ({ label: project.name, value: project.id }))"
              :label="t('admin.users.access.assign')"
              :error="assignError"
              class="access-form-input"
            />
            <FormSelect
              v-model="newRole"
              :items="roleItems"
              :label="t('settings.members.role')"
              :disabled="!newProjectId"
              class="access-form-input"
            />
            <wa-button
              type="submit"
              variant="brand"
              :disabled="!newProjectId || !newRole"
              :loading="assignment.pending.value"
            >
              <wa-icon
                slot="start"
                name="user-plus"
              ></wa-icon>
              {{ t('admin.users.access.assignConfirm') }}
            </wa-button>
          </form>
        </section>
      </div>
      <template #footer>
        <wa-button
          variant="neutral"
          appearance="outlined"
          @click="onOpenChange(false)"
        >
          {{ t('security.done') }}
        </wa-button>
      </template>
    </AppDialog>

    <ConfirmModal
      :open="revoking !== null"
      :title="t('admin.users.agent.revokeTitle', { name: revoking?.name ?? '' })"
      :description="t('admin.users.agent.revokeDescription', { agent: agent?.name ?? '' })"
      :confirm-label="t('admin.users.agent.revokeConfirm')"
      :action="confirmRevoke"
      @close="revoking = null"
    />
    <ConfirmModal
      :open="removing !== null"
      :title="t('settings.members.removeTitle', { name: agent?.name ?? '' })"
      :description="t('settings.members.removeDescription')"
      :confirm-label="t('settings.members.removeConfirm')"
      :action="confirmRemove"
      @close="removing = null"
    />
  </div>
</template>

<style scoped>
.access-intro {
  margin: 0;
  color: var(--wa-color-text-quiet);
}

.access-steps {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  margin: 0;
  padding: 0;
}

.access-step {
  display: flex;
  align-items: baseline;
  gap: var(--wa-space-s);
}

.access-step-number {
  display: inline-flex;
  flex: none;
  align-items: center;
  justify-content: center;
  inline-size: 1.5rem;
  block-size: 1.5rem;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: 50%;
  font-size: var(--wa-font-size-xs);
}

.access-step[data-done='true'] .access-step-number {
  border-color: var(--wa-color-success-border-normal);
  color: var(--wa-color-success-on-quiet);
}

.access-step-done {
  margin-inline-start: var(--wa-space-2xs);
  color: var(--wa-color-success-on-quiet);
}

.access-copy {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
}

.access-copy-field {
  flex: 1;
  min-inline-size: 0;
}

.access-token {
  font-family: var(--wa-font-family-code);
}

.access-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
}

.access-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-s);
}

.access-row + .access-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.access-icon {
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.access-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.access-name {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.access-sub {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.access-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-s);
}

.access-form-input {
  flex: 1;
  min-inline-size: 12rem;
}
</style>
