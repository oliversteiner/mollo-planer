<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { AdminUsersQuery } from '~/graphql/generated/graphql'
import { agentProjectsQuery, createAgentTokenMutation, revokeAgentTokenMutation } from '~/graphql/admin'
import { removeProjectMemberMutation, setProjectMemberMutation } from '~/graphql/settings'
import { classifyApolloError } from '~/utils/api'
import { agentAccessState, defaultAccessName } from '~/utils/agent-access'
import { formatDateTime } from '~/utils/dates'

/**
 * Access and projects of an agent account (docs/verwaltung-agenten-anfasser.md §3.3): what an agent is, the steps to
 * set it up (with their state), its accesses (a new token is shown once, in this dialog) and the projects it works in.
 * Questions (revoke, remove) and the password confirmation happen inside this dialog, in the row or above the button
 * (Auftrag 55, rule R11): there is never a second dialog on top of it.
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
const nameTouched = ref(false)
const nameError = ref('')
const created = ref<string | null>(null)
const revoking = ref<{ tokenId: string, name: string } | null>(null)
const { errors, run } = useGraphqlForm()
const createStep = usePasswordStep()
const revokeStep = usePasswordStep()

const state = computed(() => agentAccessState(props.agent?.apiTokens))

/** The name of a new access: the variable the token goes into, changeable (Auftrag 55 D). */
const suggestedName = computed(() => defaultAccessName(props.agent?.name ?? '', (props.agent?.apiTokens ?? []).map(token => token.name)))

watch(suggestedName, (name) => {
  if (!nameTouched.value) {
    accessName.value = name
  }
}, { immediate: true })

function onName(value: string): void {
  accessName.value = value
  nameTouched.value = true
  nameError.value = ''
}

/** Something is being made or revoked: closing would lose the answer (a token is shown once). */
const busy = computed(() => createStep.working || createStep.checking || revokeStep.working || revokeStep.checking)

/**
 * One access at a time, from the click to the answer (password field included): a second click must not make a second
 * token that nobody sees. A token on screen is not replaced either: first «Fertig», then the next one (Auftrag 52).
 */
async function createAccess(): Promise<void> {
  const agent = props.agent

  if (!agent || createStep.working || created.value !== null) {
    return
  }

  if (!accessName.value.trim()) {
    nameError.value = t('admin.users.agent.nameMissing')

    return
  }
  nameError.value = ''

  await createStep.run(async () => {
    const result = await run(() => createAgentToken({ userId: agent.id, name: accessName.value.trim() }))

    // The confirmation ran out meanwhile: the step asks again and keeps what is typed here.
    if (errors.value.password) {
      return NEEDS_PASSWORD
    }
    const token = result?.data?.createAgentToken

    if (token) {
      created.value = token.plainTextToken
      nameTouched.value = false
      accessName.value = suggestedName.value
    }
  })
}

async function copy(text: string): Promise<void> {
  await navigator.clipboard.writeText(text)
  toast.add({ title: t('claude.copied'), color: 'success' })
}

function askRevoke(token: { id: string, name: string }): void {
  removing.value = null
  revokeStep.cancel()
  revoking.value = { tokenId: token.id, name: token.name }
}

function cancelRevoke(): void {
  revoking.value = null
  revokeStep.cancel()
}

/** The question of the row was answered with «Widerrufen»: asks for the password in the row first, if needed. */
async function confirmRevoke(): Promise<void> {
  const target = revoking.value
  const agent = props.agent

  if (!target || !agent || revokeStep.working) {
    return
  }

  await revokeStep.run(async () => {
    try {
      await revokeAgentToken({ userId: agent.id, tokenId: target.tokenId })
    } catch (failure) {
      // Without a recent confirmation the server asks for it: the row shows the field. Anything else is reported.
      return classifyApolloError(failure).fields.password ? NEEDS_PASSWORD : undefined
    }
    toast.add({ title: t('admin.users.agent.revoked'), color: 'success' })
    revoking.value = null
  })
}

// ─── Projects (loaded when the dialog opens) ─────────────────────

const { result: projectsResult } = useQuery(agentProjectsQuery, null, () => ({ enabled: props.agent !== null, fetchPolicy: 'cache-and-network' }))

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
const assignment = useGraphqlForm()
const removing = ref<{ projectId: string, name: string } | null>(null)

const assignError = computed(() => Object.values(assignment.errors.value)[0])

/**
 * An agent has the role Agent in every project (Auftrag 58): nothing else to choose, so choosing the project assigns it
 * at once. There is no separate «Zuweisen» that «Fertig» could skip, and no choice is lost without a word (Auftrag 63, H1).
 */
async function assign(projectId: string | null): Promise<void> {
  const agent = props.agent
  const project = assignable.value.find(entry => entry.id === projectId)

  if (!agent || !project || assignment.pending.value) {
    return
  }
  newProjectId.value = projectId
  const result = await assignment.run(() => setMember({ projectId: project.id, userId: agent.id, role: 'agent' }))

  // On a refusal the choice stays, next to its message; after success the field is free for the next project.
  if (result) {
    toast.add({ title: t('admin.users.access.assigned', { agent: agent.name, project: project.name }), color: 'success' })
    newProjectId.value = null
  }
}

function askRemove(entry: { projectId: string, name: string }): void {
  cancelRevoke()
  removing.value = entry
}

async function confirmRemove(): Promise<void> {
  if (removing.value && props.agent) {
    try {
      await removeMember({ projectId: removing.value.projectId, userId: props.agent.id })
    } catch {
      // Reported by the api plugin; the question stays.
      return
    }
    toast.add({ title: t('settings.members.removed'), color: 'success' })
    removing.value = null
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

const visible = computed(() => props.agent !== null)

/**
 * Closing is the person's wish (close button, Escape, «Fertig»), but not while an access is being made or revoked: its
 * token would be lost. `created` is cleared only here or by «Fertig».
 */
/**
 * wa-dialog closes itself when it is told to (Escape, the X): while something is being made it must not, or the dialog
 * would be gone with the token in it. Cancelling the event keeps it open (it pulses once).
 */
function keepOpenWhileBusy(event: Event): void {
  // `visible` is false when the page itself closes the dialog: then it goes.
  if (visible.value && busy.value && event.target === event.currentTarget) {
    event.preventDefault()
  }
}

function onOpenChange(open: boolean): void {
  if (!open && props.agent && !busy.value) {
    created.value = null
    revoking.value = null
    removing.value = null
    createStep.cancel()
    revokeStep.cancel()
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
      @wa-hide="keepOpenWhileBusy"
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
            {{ t('admin.users.access.identifier') }}
          </h3>
          <div class="access-copy">
            <FormInput
              :model-value="agent?.email ?? ''"
              :label="t('admin.users.access.identifier')"
              hide-label
              readonly
              class="access-copy-field"
            />
            <wa-button
              appearance="outlined"
              variant="neutral"
              :aria-label="t('admin.users.access.copyIdentifier')"
              @click="copy(agent?.email ?? '')"
            >
              <wa-icon name="copy"></wa-icon>
            </wa-button>
          </div>
          <p class="access-sub">
            {{ t('admin.users.access.identifierHint') }}
          </p>
        </section>

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
                {{ t('admin.users.agent.tokenStored') }}
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
              :data-asking="revoking?.tokenId === token.id ? '' : undefined"
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
                v-if="revoking?.tokenId !== token.id"
                appearance="plain"
                variant="neutral"
                size="s"
                :aria-label="t('admin.users.agent.revoke', { name: token.name })"
                @click="askRevoke(token)"
              >
                <wa-icon name="trash"></wa-icon>
              </wa-button>
              <div
                v-else
                class="access-question wa-stack wa-gap-s"
                role="group"
                :aria-label="t('admin.users.agent.revokeTitle', { name: token.name })"
              >
                <strong>{{ t('admin.users.agent.revokeTitle', { name: token.name }) }}</strong>
                <p class="access-sub">
                  {{ t('admin.users.agent.revokeDescription', { agent: agent.name }) }}
                </p>
                <PasswordConfirmField :step="revokeStep" />
                <div
                  v-if="!revokeStep.waiting"
                  class="wa-cluster wa-gap-s"
                >
                  <wa-button
                    variant="danger"
                    size="s"
                    :loading="revokeStep.working"
                    @click="confirmRevoke"
                  >
                    {{ t('admin.users.agent.revokeConfirm') }}
                  </wa-button>
                  <wa-button
                    appearance="plain"
                    variant="neutral"
                    size="s"
                    @click="cancelRevoke"
                  >
                    {{ t('common.cancel') }}
                  </wa-button>
                </div>
              </div>
            </li>
          </ul>
          <p
            v-else
            class="access-sub"
          >
            {{ t('admin.users.agent.none') }}
          </p>

          <form
            class="access-form access-form-name"
            @submit.prevent="createAccess"
          >
            <FormInput
              :model-value="accessName"
              class="access-form-input"
              name="access-name"
              :label="t('admin.users.agent.name')"
              :hint="t('admin.users.agent.nameHint')"
              :error="nameError || errors.name || errors.userId"
              maxlength="60"
              @update:model-value="onName"
            />
            <wa-button
              type="submit"
              variant="brand"
              :loading="createStep.working"
              :disabled="created !== null || createStep.waiting"
            >
              <wa-icon
                slot="start"
                name="key"
              ></wa-icon>
              {{ t('admin.users.agent.create') }}
            </wa-button>
          </form>
          <PasswordConfirmField :step="createStep" />
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
                v-if="canManage(entry.project) && removing?.projectId !== entry.project.id"
                appearance="plain"
                variant="neutral"
                size="s"
                :aria-label="t('admin.users.access.removeFrom', { project: entry.project.name })"
                @click="askRemove({ projectId: entry.project.id, name: entry.project.name })"
              >
                <wa-icon name="user-minus"></wa-icon>
              </wa-button>
              <div
                v-if="removing?.projectId === entry.project.id"
                class="access-question wa-stack wa-gap-s"
                role="group"
                :aria-label="t('settings.members.removeTitle', { name: agent?.name ?? '' })"
              >
                <strong>{{ t('settings.members.removeTitle', { name: agent?.name ?? '' }) }}</strong>
                <p class="access-sub">
                  {{ t('settings.members.removeDescription') }}
                </p>
                <div class="wa-cluster wa-gap-s">
                  <wa-button
                    variant="danger"
                    size="s"
                    @click="confirmRemove"
                  >
                    {{ t('settings.members.removeConfirm') }}
                  </wa-button>
                  <wa-button
                    appearance="plain"
                    variant="neutral"
                    size="s"
                    @click="removing = null"
                  >
                    {{ t('common.cancel') }}
                  </wa-button>
                </div>
              </div>
            </li>
          </ul>
          <p
            v-else
            class="access-sub"
          >
            {{ t('admin.users.access.noProjects') }}
          </p>

          <FormSelect
            v-if="assignable.length"
            :model-value="newProjectId"
            :items="assignable.map(project => ({ label: project.name, value: project.id }))"
            :label="t('admin.users.access.assign')"
            :error="assignError"
            :disabled="assignment.pending.value"
            class="access-assign"
            @update:model-value="value => assign(value ? String(value) : null)"
          />
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

.access-question {
  flex-basis: 100%;
  padding-block-start: var(--wa-space-xs);
}

.access-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-s);
}

.access-assign {
  max-inline-size: 24rem;
}

.access-form-input {
  flex: 1;
  min-inline-size: 12rem;
}

/* The suggested name is long (MOLLOPLANER_<AGENT>_TOKEN): the field has the whole width, the button goes below it. */
.access-form-name .access-form-input {
  flex-basis: 100%;
}
</style>
