<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ChatMessage } from '~/components/assistant/Chat.vue'
import { assistantPeopleQuery, draftProjectPlanMutation, importProjectPlanMutation } from '~/graphql/assistant'
import { classifyApolloError } from '~/utils/api'
import { toPlanInput, type ProjectPlan } from '~/utils/plan'

/**
 * AI assistant (admins): describe a project in the chat, by text or voice;
 * Claude drafts name, description and the timeline of milestones, todos
 * and tasks. The preview shows the draft, "Jetzt eintragen" saves it.
 */
definePageMeta({ middleware: 'admin' })

const { t } = useI18n()
const toast = useToast()
const { $apiErrors } = useNuxtApp()

useHead({ title: () => t('assistant.title') })

const STORAGE_KEY = 'assistant-draft'

const messages = ref<ChatMessage[]>([])
const plan = ref<ProjectPlan | null>(null)
const importError = ref<string | null>(null)

// The chat survives a reload of the page (this tab only).
onMounted(() => {
  try {
    const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? 'null') as { messages: ChatMessage[], plan: ProjectPlan | null } | null

    if (saved) {
      messages.value = saved.messages
      plan.value = saved.plan
    }
  } catch {
    // No storage (private mode) or an old format: start empty.
  }
})

watch([messages, plan], () => {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ messages: messages.value, plan: plan.value }))
  } catch {
    // Storage is a convenience only.
  }
}, { deep: true })

const { result: peopleResult } = useQuery(assistantPeopleQuery, null, { fetchPolicy: 'cache-first' })
const people = computed(() => Object.fromEntries((peopleResult.value?.users ?? [])
  .map(user => [user.email.toLowerCase(), { name: user.name, avatarUrl: user.avatarUrl ?? null }])))

const silent = { context: { silentErrors: true } }
const { mutate: draft, loading: drafting } = useMutation(draftProjectPlanMutation, silent)
const { mutate: importPlan, loading: importing } = useMutation(importProjectPlanMutation, {
  ...silent,
  refetchQueries: ['SidebarProjects']
})

/**
 * Message of a failed request. Login and the like are still handled by
 * the app (redirects), the message stays in the chat or the preview.
 */
function failure(error: unknown): string {
  const apiError = classifyApolloError(error as Parameters<typeof classifyApolloError>[0])

  if (apiError.kind !== 'validation' && apiError.kind !== 'unknown') {
    $apiErrors.trigger(apiError)
  }

  return Object.values(apiError.fields)[0]?.[0] ?? apiError.message ?? t('apiError.unknown')
}

async function send(text: string) {
  messages.value.push({ role: 'USER', content: text })

  try {
    const response = await draft({
      messages: messages.value.filter(message => !message.failed).map(({ role, content }) => ({ role, content })),
      currentPlan: plan.value ? toPlanInput(plan.value) : null
    })
    const answer = response?.data?.draftProjectPlan

    if (answer) {
      messages.value.push({ role: 'ASSISTANT', content: answer.reply || t('assistant.chat.planReady') })

      if (answer.plan) {
        plan.value = answer.plan
        importError.value = null
      }
    }
  } catch (error) {
    messages.value.push({ role: 'ASSISTANT', content: failure(error), failed: true })
  }
}

async function save(notifyAssignees: boolean) {
  if (!plan.value) {
    return
  }

  importError.value = null

  try {
    const response = await importPlan({ plan: toPlanInput(plan.value), notifyAssignees })
    const project = response?.data?.importProjectPlan

    if (project) {
      toast.add({ title: t('assistant.imported', { name: project.name }), color: 'success' })
      reset()
      await navigateTo(`/projects/${project.id}`)
    }
  } catch (error) {
    importError.value = failure(error)
  }
}

function reset() {
  messages.value = []
  plan.value = null
  importError.value = null
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
          :pending="drafting"
          @send="send"
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
            :people="people"
            :importing="importing"
            :error="importError"
            @import="save"
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
