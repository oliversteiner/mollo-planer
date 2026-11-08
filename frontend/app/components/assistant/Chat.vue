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
const field = ref<{ textareaRef?: HTMLTextAreaElement } | null>(null)

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

const examples = computed(() => [t('assistant.chat.example1'), t('assistant.chat.example2')])

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

function useExample(text: string) {
  input.value = text
  field.value?.textareaRef?.focus()
}

watch(() => [props.messages.length, props.pending], async () => {
  await nextTick()
  list.value?.scrollTo({ top: list.value.scrollHeight, behavior: 'smooth' })
})
</script>

<template>
  <div class="flex h-full min-h-0 flex-col">
    <div
      ref="list"
      class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6"
      role="log"
      aria-live="polite"
      :aria-label="t('assistant.chat.log')"
    >
      <div class="mx-auto w-full max-w-3xl space-y-4">
        <div
          v-if="!messages.length"
          class="space-y-4"
        >
          <UEmpty
            icon="i-fadl-message-bot"
            :title="t('assistant.chat.emptyTitle')"
            :description="t('assistant.chat.emptyDescription')"
            variant="naked"
          />
          <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">
              {{ t('assistant.chat.examples') }}
            </p>
            <UButton
              v-for="example in examples"
              :key="example"
              variant="soft"
              color="neutral"
              block
              class="justify-start whitespace-normal text-start"
              icon="i-fadl-sparkles"
              :label="example"
              @click="useExample(example)"
            />
          </div>
        </div>

        <div
          v-for="(message, index) in messages"
          :key="index"
          class="flex gap-3"
          :class="message.role === 'USER' ? 'flex-row-reverse' : ''"
        >
          <UAvatar
            :icon="message.role === 'USER' ? 'i-fadl-user' : 'i-fadl-robot'"
            size="sm"
            class="shrink-0"
            :alt="message.role === 'USER' ? t('assistant.chat.you') : t('assistant.chat.assistant')"
          />
          <div
            class="max-w-[85%] whitespace-pre-line rounded-lg px-3 py-2 text-sm"
            :class="message.failed
              ? 'bg-error/10 text-error'
              : message.role === 'USER' ? 'bg-primary/10 text-highlighted' : 'bg-elevated text-default'"
          >
            <span class="sr-only">{{ message.role === 'USER' ? t('assistant.chat.you') : t('assistant.chat.assistant') }}:</span>
            {{ message.content }}
          </div>
        </div>

        <div
          v-if="pending"
          class="flex items-center gap-3 text-sm text-muted"
        >
          <UAvatar
            icon="i-fadl-robot"
            size="sm"
            :alt="t('assistant.chat.assistant')"
          />
          <UIcon
            name="i-fadl-spinner-third"
            class="size-4 animate-spin"
          />
          <span>{{ t('assistant.chat.thinking') }}</span>
        </div>
      </div>
    </div>

    <div class="border-t border-default p-4">
      <form
        class="mx-auto w-full max-w-3xl space-y-2"
        @submit.prevent="send"
      >
        <UTextarea
          ref="field"
          v-model="shown"
          :placeholder="speech.listening.value ? t('assistant.chat.listening') : t('assistant.chat.placeholder')"
          :aria-label="t('assistant.chat.placeholder')"
          :rows="3"
          autoresize
          :maxrows="10"
          :disabled="pending"
          class="w-full"
          @keydown="onKeydown"
        />
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1">
            <UTooltip
              v-if="speech.supported.value"
              :text="t('assistant.chat.voiceHint')"
            >
              <UButton
                :icon="speech.listening.value ? 'i-fadl-microphone-slash' : 'i-fadl-microphone'"
                :color="speech.listening.value ? 'error' : 'neutral'"
                :variant="speech.listening.value ? 'soft' : 'ghost'"
                :aria-label="speech.listening.value ? t('assistant.chat.voiceStop') : t('assistant.chat.voiceStart')"
                :aria-pressed="speech.listening.value"
                :class="speech.listening.value ? 'animate-pulse' : ''"
                :disabled="pending"
                @click="speech.toggle()"
              />
            </UTooltip>
            <UButton
              v-if="messages.length"
              icon="i-fadl-arrow-rotate-left"
              variant="ghost"
              color="neutral"
              :label="t('assistant.chat.reset')"
              :disabled="pending"
              @click="emit('reset')"
            />
          </div>
          <UButton
            type="submit"
            icon="i-fadl-paper-plane-top"
            :label="t('assistant.chat.send')"
            :disabled="!input.trim()"
            :loading="pending"
          />
        </div>
        <p
          v-if="speech.error.value"
          class="text-xs text-error"
        >
          {{ t('assistant.chat.voiceError', { error: speech.error.value }) }}
        </p>
      </form>
    </div>
  </div>
</template>
