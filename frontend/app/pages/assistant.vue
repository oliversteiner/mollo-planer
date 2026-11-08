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

const panes = [
  { id: 'chat', slot: 'chat', defaultSize: 40, minSize: 25 },
  { id: 'preview', slot: 'preview', defaultSize: 60, minSize: 30, collapsible: true, collapsedSize: 5 }
]

/**
 * The preview stays collapsed to a narrow strip until there is a draft,
 * then opens (animated, except while the user drags the handle).
 */
type PanelHandle = { collapse(): void, expand(): void, resize(size: number): void, getSize(): number }
const splitter = ref<{ panelsRef: PanelHandle[] } | null>(null)
const dragging = ref(false)
/** New key per draft, so its appearance is animated again. */
const planVersion = ref(0)

function fitPreview() {
  const preview = splitter.value?.panelsRef[1]

  if (!preview) {
    return
  }

  if (!plan.value) {
    preview.collapse()
  } else if (preview.getSize() < 40) {
    preview.expand()
    if (preview.getSize() < 40) {
      preview.resize(60)
    }
  }
}

watch(() => plan.value !== null, () => nextTick(fitPreview))
watch(plan, (current, previous) => {
  if (current && current !== previous) {
    planVersion.value++
  }
})
onMounted(() => nextTick(fitPreview))
</script>

<template>
  <UDashboardPanel
    id="assistant"
    :ui="{ body: 'p-0 sm:p-0 overflow-hidden' }"
  >
    <template #header>
      <UDashboardNavbar
        :title="t('assistant.title')"
        icon="i-fadl-wand-magic-sparkles"
      />
    </template>

    <template #body>
      <USplitter
        id="assistant-splitter"
        ref="splitter"
        auto-save-id="assistant-splitter"
        :orientation="wide ? 'horizontal' : 'vertical'"
        :items="panes"
        class="h-full"
        :ui="{ panel: dragging ? '' : 'motion-safe:transition-[flex-grow] motion-safe:duration-500 motion-safe:ease-out' }"
        @dragging="(_, value) => dragging = value"
      >
        <template #chat>
          <section
            class="h-full min-h-0"
            :aria-label="t('assistant.chat.title')"
          >
            <AssistantChat
              :messages="messages"
              :pending="drafting"
              @send="send"
              @reset="reset"
            />
          </section>
        </template>

        <template #preview="{ collapsed }">
          <section
            class="relative h-full w-full min-w-0"
            :aria-label="t('assistant.preview.title')"
          >
            <!-- Collapsed: a narrow strip until the first draft -->
            <div
              v-if="collapsed"
              class="flex h-full w-full items-center justify-center gap-2 border-default bg-elevated/40 text-muted"
              :class="wide ? 'flex-col border-s' : 'border-t'"
            >
              <UIcon
                name="i-fadl-timeline"
                class="size-5"
              />
              <span
                class="text-xs font-medium uppercase tracking-wide"
                :class="wide ? '[writing-mode:vertical-rl]' : ''"
              >{{ t('assistant.preview.title') }}</span>
            </div>

            <div
              v-else
              class="h-full overflow-y-auto px-4 pt-4 sm:px-6 sm:pt-6"
            >
              <AssistantPlanPreview
                v-if="plan"
                :key="planVersion"
                :plan="plan"
                :people="people"
                :importing="importing"
                :error="importError"
                @import="save"
                @discard="reset"
              />
              <UEmpty
                v-else
                icon="i-fadl-timeline"
                :title="t('assistant.preview.emptyTitle')"
                :description="t('assistant.preview.emptyDescription')"
                variant="naked"
                class="h-full"
              />
            </div>
          </section>
        </template>
      </USplitter>
    </template>
  </UDashboardPanel>
</template>
