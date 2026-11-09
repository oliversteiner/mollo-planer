<script setup lang="ts">
/**
 * Chat with the AI assistant: messages, a text field with voice input
 * and a send button. Enter sends, Shift+Enter makes a new line.
 */
export interface ChatMessage {
  role: 'USER' | 'ASSISTANT'
  content: string
  /** Shown in red; not sent to Claude again */
  failed?: boolean
}

const props = defineProps<{
  messages: ChatMessage[]
  pending?: boolean
}>()

const emit = defineEmits<{
  send: [text: string]
  reset: []
}>()

const { t, localeProperties } = useI18n()

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
        <FormTextarea
          v-model="shown"
          :placeholder="speech.listening.value ? t('assistant.chat.listening') : t('assistant.chat.placeholder')"
          :aria-label="t('assistant.chat.placeholder')"
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
              v-if="messages.length"
              appearance="plain"
              variant="neutral"
              :disabled="pending"
              @click="emit('reset')"
            >
              <wa-icon
                slot="start"
                name="arrow-rotate-left"
              ></wa-icon>
              {{ t('assistant.chat.reset') }}
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
  flex: 1;
  min-block-size: 0;
  padding: var(--wa-space-l);
  overflow-y: auto;
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
