<script setup lang="ts">
import type { PlanTargetKind } from '~/graphql/generated/graphql'
/**
 * Chat with the AI assistant: messages, a text field with voice input
 * and a send button. Enter sends, Shift+Enter makes a new line.
 */
export interface ChatMessage {
  role: 'USER' | 'ASSISTANT'
  content: string
  /** Shown in red; not sent to Claude again */
  failed?: boolean
  /** Goes to Claude with the message (an id), not shown */
  note?: string
}

export interface ChatChoice {
  kind: string
  label: string
  detail?: string | null
  value?: string | null
  recommended: boolean
}

const props = defineProps<{
  messages: ChatMessage[]
  pending?: boolean
  /** Offer "start anew" even without messages (a project is set: "another project") */
  canReset?: boolean
  /** Buttons of the last answer (docs/auftrag-m6.md §4.1) */
  choices?: readonly ChatChoice[]
  targetKind?: PlanTargetKind | null
  targetKindLocked?: boolean
}>()

const emit = defineEmits<{
  'send': [text: string]
  'reset': []
  'choose': [choice: ChatChoice]
  'update:targetKind': [targetKind: PlanTargetKind | null]
}>()

const { t, localeProperties } = useI18n()

const targetKindItems = computed(() => [
  { label: t('assistant.chat.targetAutomatic'), value: '' },
  { label: t('assistant.chat.targetMilestone'), value: 'MILESTONE' },
  { label: t('assistant.chat.targetTodo'), value: 'TODO' },
  { label: t('assistant.chat.targetTask'), value: 'TASK' },
  { label: t('assistant.chat.targetIdea'), value: 'IDEA' }
])

const input = ref('')
const list = ref<HTMLElement | null>(null)
const voiceId = useId()

const speech = useSpeechInput(
  () => localeProperties.value.language ?? 'de-CH',
  (text) => {
    input.value = input.value.trim() ? `${input.value.trim()} ${text}` : text
  }
)

/** Text field plus what is being recognized right now. */
const shown = computed({
  get: () => (speech.interim.value ? `${input.value} ${speech.interim.value}`.trim() : input.value),
  set: (value: string) => {
    input.value = value
  }
})

function send() {
  const text = input.value.trim()

  if (!text || props.pending) {
    return
  }

  speech.stop()
  emit('send', text)
  input.value = ''
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault()
    send()
  }
}

watch(() => [props.messages.length, props.pending], async () => {
  await nextTick()
  list.value?.scrollTo({ top: list.value.scrollHeight, behavior: 'smooth' })
})
</script>

<template>
  <div class="chat">
    <div
      ref="list"
      class="chat-log"
      :data-empty="messages.length ? undefined : ''"
      role="log"
      aria-live="polite"
      :aria-label="t('assistant.chat.log')"
    >
      <div class="chat-column wa-stack wa-gap-m">
        <EmptyState
          v-if="!messages.length"
          icon="message-bot"
          :title="t('assistant.chat.emptyTitle')"
          :description="t('assistant.chat.emptyDescription')"
          bare
        />

        <div
          v-for="(message, index) in messages"
          :key="index"
          class="chat-message"
          :data-user="message.role === 'USER' ? '' : undefined"
        >
          <wa-avatar
            :label="message.role === 'USER' ? t('assistant.chat.you') : t('assistant.chat.assistant')"
            style="--size: 2rem"
          >
            <wa-icon
              slot="icon"
              :name="message.role === 'USER' ? 'user' : 'robot'"
            ></wa-icon>
          </wa-avatar>
          <div
            class="chat-bubble"
            :data-failed="message.failed ? '' : undefined"
          >
            <span class="wa-visually-hidden">{{ message.role === 'USER' ? t('assistant.chat.you') : t('assistant.chat.assistant') }}:</span>
            {{ message.content }}
          </div>
        </div>

        <div
          v-if="choices?.length && !pending"
          class="chat-choices"
          role="group"
          :aria-label="t('assistant.chat.choices')"
        >
          <wa-button
            v-for="(choice, index) in choices"
            :key="`${choice.kind}-${choice.value ?? index}`"
            :variant="choice.recommended ? 'brand' : 'neutral'"
            :appearance="choice.recommended ? 'filled' : 'outlined'"
            @click="emit('choose', choice)"
          >
            <span class="chat-choice">
              <span>{{ choice.label }}<span v-if="choice.recommended"> · {{ t('assistant.chat.recommended') }}</span></span>
              <small v-if="choice.detail">{{ choice.detail }}</small>
            </span>
          </wa-button>
        </div>

        <div
          v-if="pending"
          class="chat-thinking"
        >
          <wa-avatar
            :label="t('assistant.chat.assistant')"
            style="--size: 2rem"
          >
            <wa-icon
              slot="icon"
              name="robot"
            ></wa-icon>
          </wa-avatar>
          <wa-spinner></wa-spinner>
          <span>{{ t('assistant.chat.thinking') }}</span>
        </div>
      </div>
    </div>

    <div class="chat-input">
      <form
        class="chat-column wa-stack wa-gap-xs"
        @submit.prevent="send"
      >
        <FormSelect
          :model-value="targetKind ?? ''"
          :items="targetKindItems"
          :label="t('assistant.chat.targetKind')"
          :hint="t('assistant.chat.targetHint')"
          :disabled="targetKindLocked || pending"
          @update:model-value="emit('update:targetKind', $event ? $event as PlanTargetKind : null)"
        />
        <FormTextarea
          v-model="shown"
          :placeholder="speech.listening.value ? t('assistant.chat.listening') : t('assistant.chat.placeholder')"
          :label="t('assistant.chat.placeholder')"
          hide-label
          :rows="3"
          resize="auto"
          :disabled="pending"
          @keydown="onKeydown"
        />
        <div class="wa-split wa-gap-s">
          <div class="wa-cluster wa-gap-3xs">
            <template v-if="speech.supported.value">
              <wa-button
                :id="voiceId"
                :appearance="speech.listening.value ? 'filled' : 'plain'"
                :variant="speech.listening.value ? 'danger' : 'neutral'"
                :aria-label="speech.listening.value ? t('assistant.chat.voiceStop') : t('assistant.chat.voiceStart')"
                :aria-pressed="speech.listening.value"
                :disabled="pending"
                @click="speech.toggle()"
              >
                <wa-icon :name="speech.listening.value ? 'microphone-slash' : 'microphone'"></wa-icon>
              </wa-button>
              <wa-tooltip :for="voiceId">
                {{ t('assistant.chat.voiceHint') }}
              </wa-tooltip>
            </template>
            <wa-button
              v-if="messages.length || canReset"
              appearance="plain"
              variant="neutral"
              :disabled="pending"
              @click="emit('reset')"
            >
              <wa-icon
                slot="start"
                name="arrow-rotate-left"
              ></wa-icon>
              {{ messages.length ? t('assistant.chat.reset') : t('assistant.chat.otherProject') }}
            </wa-button>
          </div>
          <wa-button
            type="submit"
            variant="brand"
            :disabled="!input.trim()"
            :loading="pending"
          >
            <wa-icon
              slot="start"
              name="paper-plane-top"
            ></wa-icon>
            {{ t('assistant.chat.send') }}
          </wa-button>
        </div>
        <p
          v-if="speech.error.value"
          class="wa-caption-s"
          data-form-error
        >
          {{ t('assistant.chat.voiceError', { error: speech.error.value }) }}
        </p>
      </form>
    </div>
  </div>
</template>

<style scoped>
.chat {
  display: flex;
  flex-direction: column;
  block-size: 100%;
  min-block-size: 0;
}

.chat-log {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-block-size: 0;
  padding: var(--wa-space-l);
  overflow-y: auto;
}

/* Without messages the introduction sits in the middle of the free space, not at the top. */
.chat-log[data-empty] > .chat-column {
  margin-block: auto;
}

.chat-column {
  inline-size: 100%;
  max-inline-size: 48rem;
  margin-inline: auto;
}

.chat-message {
  display: flex;
  gap: var(--wa-space-s);
}

.chat-message[data-user] {
  flex-direction: row-reverse;
}

.chat-bubble {
  max-inline-size: 85%;
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-radius: var(--wa-border-radius-l);
  background-color: var(--wa-color-neutral-fill-quiet);
  font-size: var(--wa-font-size-s);
  white-space: pre-line;
}

.chat-message[data-user] .chat-bubble {
  background-color: var(--wa-color-brand-fill-quiet);
}

.chat-bubble[data-failed] {
  background-color: var(--wa-color-danger-fill-quiet);
  color: var(--wa-color-danger-on-quiet);
}

.chat-choices {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wa-space-xs);
  padding-inline-start: calc(2rem + var(--wa-space-s));
}

.chat-choice {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  white-space: normal;
  text-align: start;
}

.chat-choice small {
  color: var(--wa-color-text-quiet);
}

.chat-thinking {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.chat-input {
  padding: var(--wa-space-m);
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}
</style>
