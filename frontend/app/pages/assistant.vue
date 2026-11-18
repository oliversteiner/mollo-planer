<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ChatChoice, ChatMessage } from '~/components/assistant/Chat.vue'
import type { DraftPlanMutation } from '~/graphql/generated/graphql'
import { assistantProjectQuery, draftPlanMutation } from '~/graphql/assistant'
import { createIdeaMutation } from '~/graphql/ideas'
import { importPlanMutation } from '~/graphql/planer'
import { classifyApolloError } from '~/utils/api'
import { planText, readPlan, withoutStruck } from '~/utils/assistant'

/**
 * AI assistant (assistant.use, docs/auftrag-m6.md §4): the person chats, by
 * text or voice; Claude asks with buttons where something is unclear and adds
 * to a project (or plans a new one). The preview shows what would be added;
 * new elements can be struck out. "Jetzt eintragen" saves, "In Ideen ablegen"
 * keeps the draft, nothing else is written.
 */

type Preview = NonNullable<DraftPlanMutation['draftPlan']['preview']>

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const { $apiErrors } = useNuxtApp()

useHead({ title: () => t('assistant.title') })

const STORAGE_KEY = 'assistant-draft-v2'

const messages = ref<ChatMessage[]>([])
const choices = ref<ChatChoice[]>([])
const plan = ref<string | null>(null)
const preview = ref<Preview | null>(null)
const hints = ref<string[]>([])
const struck = ref<Set<string>>(new Set())
const projectId = ref<string | null>(typeof route.query.project === 'string' ? route.query.project : null)
const templateId = ref<string | null>(null)
const ideaId = typeof route.query.idea === 'string' ? route.query.idea : null
const importError = ref<string | null>(null)

// The chat survives a reload of the page (this tab only), unless an idea or project opens it.
onMounted(() => {
  try {
    const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? 'null') as Record<string, unknown> | null

    if (saved && !ideaId && !route.query.project) {
      messages.value = saved.messages as ChatMessage[]
      plan.value = saved.plan as string | null
      projectId.value = saved.projectId as string | null
      templateId.value = saved.templateId as string | null
      hints.value = (saved.hints as string[]) ?? []
      preview.value = (saved.preview as Preview | null) ?? null
      choices.value = (saved.choices as ChatChoice[]) ?? []
    }
  } catch {
    // No storage (private mode) or an old format: start empty.
  }

  if (ideaId && messages.value.length === 0) {
    void send(t('assistant.chat.planIdea'))
  }
})

watch([messages, plan, projectId, templateId, hints, preview, choices], () => {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
      messages: messages.value,
      plan: plan.value,
      projectId: projectId.value,
      templateId: templateId.value,
      hints: hints.value,
      preview: preview.value,
      choices: choices.value
    }))
  } catch {
    // Storage is a convenience only.
  }
}, { deep: true })

const { result: projectResult } = useQuery(assistantProjectQuery, () => ({ id: projectId.value ?? '' }), () => ({ enabled: projectId.value !== null }))
const projectName = computed(() => (projectId.value ? projectResult.value?.project?.name ?? null : null))

const silent = { context: { silentErrors: true } }
const { mutate: draft, loading: drafting } = useMutation(draftPlanMutation, silent)
const { mutate: importPlan, loading: importing } = useMutation(importPlanMutation, {
  ...silent,
  refetchQueries: ['SidebarProjects']
})
const { mutate: createIdea, loading: savingIdea } = useMutation(createIdeaMutation, silent)
const checking = ref(false)

/**
 * Message of a failed request. Login and the like are still handled by
 * the app (redirects), the message stays in the chat or the preview.
 */
function failure(error: unknown): string {
  const apiError = classifyApolloError(error)

  if (apiError.kind !== 'validation' && apiError.kind !== 'unknown') {
    $apiErrors.trigger(apiError)
  }

  return Object.values(apiError.fields)[0]?.[0] ?? apiError.message ?? t('apiError.unknown')
}

/** The plan without what the person struck out. */
function currentPlanText(): string | null {
  const document = readPlan(plan.value)

  return document ? planText(withoutStruck(document, struck.value)) : null
}

async function send(text: string) {
  messages.value.push({ role: 'USER', content: text })
  choices.value = []

  try {
    const response = await draft({
      messages: messages.value.filter(message => !message.failed).map(({ role, content }) => ({ role, content })),
      projectId: projectId.value,
      templateId: templateId.value,
      currentPlan: currentPlanText(),
      ideaId
    })
    const answer = response?.data?.draftPlan

    if (answer) {
      messages.value.push({ role: 'ASSISTANT', content: answer.reply || t('assistant.chat.planReady') })
      choices.value = answer.choices.map(choice => ({ ...choice }))
      hints.value = [...answer.hints]

      if (answer.plan) {
        plan.value = answer.plan
        preview.value = answer.preview ?? null
        struck.value = new Set()
        importError.value = null
      }
    }
  } catch (error) {
    messages.value.push({ role: 'ASSISTANT', content: failure(error), failed: true })
  }
}

/** A button: it fixes project or template where it says so, and goes on as a message. */
function choose(choice: ChatChoice) {
  if (choice.kind === 'IDEA') {
    void toIdeas(choice.label)

    return
  }

  if (choice.kind === 'PROJECT') {
    projectId.value = choice.value ?? null
  } else if (choice.kind === 'NEW_PROJECT') {
    projectId.value = null
  } else if (choice.kind === 'TEMPLATE') {
    templateId.value = choice.value ?? null
  } else if (choice.kind === 'NO_TEMPLATE') {
    templateId.value = null
  }

  const text = choice.kind === 'SHOW_ALL'
    ? t('assistant.chat.showAll')
    : t('assistant.chat.chose', { label: choice.label }) + (choice.value && ['MILESTONE', 'APPEND_TO_TODO'].includes(choice.kind) ? ` (${choice.value})` : '')

  void send(text)
}

/** Striking out an element checks the rest again (dry run). */
async function toggle(id: string) {
  const next = new Set(struck.value)

  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }

  struck.value = next
  checking.value = true

  try {
    const text = currentPlanText()
    const response = text
      ? await importPlan({ projectId: projectId.value, document: text, mode: 'PARTIAL', dryRun: true, appendOnly: true, templateId: projectId.value ? null : templateId.value })
      : null

    if (response?.data?.importPlan) {
      preview.value = response.data.importPlan
    }
  } catch (error) {
    importError.value = failure(error)
  } finally {
    checking.value = false
  }
}

async function save(notifyAssignees: boolean) {
  const text = currentPlanText()

  if (!text) {
    return
  }

  importError.value = null

  try {
    const response = await importPlan({
      projectId: projectId.value,
      document: text,
      mode: 'PARTIAL',
      dryRun: false,
      appendOnly: true,
      notifyAssignees,
      templateId: projectId.value ? null : templateId.value,
      ideaId
    })
    const result = response?.data?.importPlan

    if (result?.ok && result.projectId) {
      toast.add({ title: t('assistant.imported'), color: 'success' })
      reset()
      await navigateTo(`/projects/${result.projectId}`)
    } else if (result) {
      preview.value = result
    }
  } catch (error) {
    importError.value = failure(error)
  }
}

/** Keeps the draft (or just the request) as an idea. */
async function toIdeas(title?: string) {
  const firstUser = messages.value.find(message => message.role === 'USER')?.content ?? ''
  const name = (readPlan(plan.value)?.project.name || firstUser || title || t('assistant.title')).trim().slice(0, 200)

  try {
    await createIdea({ input: { title: name.length >= 2 ? name : t('assistant.title'), projectId: projectId.value, draft: currentPlanText() } })
    toast.add({ title: t('assistant.ideaSaved'), color: 'success' })
    reset()
    await navigateTo('/ideas')
  } catch (error) {
    importError.value = failure(error)
  }
}

function reset() {
  messages.value = []
  choices.value = []
  plan.value = null
  preview.value = null
  hints.value = []
  struck.value = new Set()
  importError.value = null
  templateId.value = null
}

/** Side by side from "lg", stacked on small screens. */
const wide = ref(true)

onMounted(() => {
  const query = window.matchMedia('(min-width: 1024px)')
  const update = () => {
    wide.value = query.matches
  }

  update()
  query.addEventListener('change', update)
  onBeforeUnmount(() => query.removeEventListener('change', update))
})

/**
 * Share of the chat in percent while a draft is shown, remembered in the
 * browser. Without a draft the preview is a narrow strip (the chat gets
 * almost everything); the first draft opens it.
 */
const SPLIT_KEY = 'assistant-splitter'
const COLLAPSED_POSITION = 95

function savedPosition(): number {
  try {
    const value = Number(localStorage.getItem(SPLIT_KEY))

    return value >= 20 && value <= 80 ? value : 40
  } catch {
    return 40
  }
}

const chatShare = ref(40)
const collapsed = computed(() => plan.value === null)
const position = computed(() => (collapsed.value ? COLLAPSED_POSITION : chatShare.value))

onMounted(() => {
  chatShare.value = savedPosition()
})

function onReposition(event: Event): void {
  const value = (event.target as HTMLElement & { position: number }).position

  if (!collapsed.value && value >= 20 && value <= 80) {
    chatShare.value = value

    try {
      localStorage.setItem(SPLIT_KEY, String(Math.round(value)))
    } catch {
      // Storage is a convenience only.
    }
  }
}

/** New key per draft, so its appearance is animated again. */
const planVersion = ref(0)

watch(plan, (current, previous) => {
  if (current && current !== previous) {
    planVersion.value++
  }
})
</script>

<template>
  <PageFrame
    :title="t('assistant.title')"
    :breadcrumbs="[{ label: t('nav.workspace'), to: '/' }, { label: t('assistant.title') }]"
    :description="projectName ? t('assistant.addingTo', { name: projectName }) : undefined"
    fill
  >
    <wa-split-panel
      class="assistant-split"
      :orientation="wide ? 'horizontal' : 'vertical'"
      :position="position"
      @wa-reposition="onReposition"
    >
      <section
        slot="start"
        class="assistant-pane"
        :aria-label="t('assistant.chat.title')"
      >
        <AssistantChat
          :messages="messages"
          :choices="choices"
          :pending="drafting"
          @send="send"
          @choose="choose"
          @reset="reset"
        />
      </section>

      <section
        slot="end"
        class="assistant-pane"
        :aria-label="t('assistant.preview.title')"
      >
        <!-- Collapsed: a narrow strip until the first draft -->
        <div
          v-if="collapsed"
          class="assistant-strip"
          :data-wide="wide ? '' : undefined"
        >
          <wa-icon name="timeline"></wa-icon>
          <span class="assistant-strip-title">{{ t('assistant.preview.title') }}</span>
        </div>

        <div
          v-else
          class="assistant-preview"
        >
          <AssistantPlanPreview
            :key="planVersion"
            :plan="plan!"
            :preview="preview"
            :hints="hints"
            :struck="struck"
            :existing-project="projectId !== null"
            :busy="importing || savingIdea"
            :checking="checking"
            :error="importError"
            @toggle="toggle"
            @import="save"
            @ideas="toIdeas()"
            @discard="reset"
          />
        </div>
      </section>
    </wa-split-panel>
  </PageFrame>
</template>

<style scoped>
.assistant-split {
  block-size: 100%;
  --min: 20%;
  --max: 95%;
}

.assistant-pane {
  block-size: 100%;
  min-block-size: 0;
  min-inline-size: 0;
}

.assistant-strip {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--wa-space-xs);
  block-size: 100%;
  background-color: var(--wa-color-surface-raised);
  color: var(--wa-color-text-quiet);
}

.assistant-strip[data-wide] {
  flex-direction: column;
  border-inline-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.assistant-strip:not([data-wide]) {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.assistant-strip-title {
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.assistant-strip[data-wide] .assistant-strip-title {
  writing-mode: vertical-rl;
}

.assistant-preview {
  block-size: 100%;
  overflow-y: auto;
  padding: var(--wa-space-l) var(--wa-space-l) 0;
}
</style>
