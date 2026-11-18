<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import {
  clearProjectAiContextMutation,
  projectAiContextQuery,
  removeProjectAiContextFileMutation,
  setProjectAiContextMutation,
  uploadProjectAiContextFileMutation
} from '~/graphql/aicontext'
import { AI_CONTEXT_LIMIT, contextCharacters, fileProblem } from '~/utils/ai-context'

/**
 * Project settings: background knowledge for the assistant (aiContext.update, docs/auftrag-m6.md §3):
 * a text and a .txt/.md file, together up to 20 000 characters, with a counter. "Share with tools"
 * lets external tools (MCP get_project_ai_context) read it. It is background knowledge, never
 * instructions; the assistant reads it as such.
 */
const props = defineProps<{ projectId: string }>()

const { t, n } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const { result } = useQuery(projectAiContextQuery, () => ({ id: props.projectId }), { fetchPolicy: 'cache-and-network' })
const saved = computed(() => result.value?.project?.aiContext ?? null)

const text = ref('')
const shared = ref(false)
const input = ref<HTMLInputElement | null>(null)
const fileError = ref('')

watch(saved, (context) => {
  text.value = context?.text ?? ''
  shared.value = context?.sharedWithTools ?? false
}, { immediate: true })

const used = computed(() => contextCharacters(text.value, saved.value?.fileText))
const over = computed(() => used.value > AI_CONTEXT_LIMIT)
const dirty = computed(() => text.value !== (saved.value?.text ?? '') || shared.value !== (saved.value?.sharedWithTools ?? false))

const { mutate: setContext } = useMutation(setProjectAiContextMutation)
const { mutate: uploadFile } = useMutation(uploadProjectAiContextFileMutation)
const { mutate: removeFile } = useMutation(removeProjectAiContextFileMutation)
const { mutate: clearContext } = useMutation(clearProjectAiContextMutation)

async function save(): Promise<void> {
  const response = await run(() => setContext({ projectId: props.projectId, input: { text: text.value, sharedWithTools: shared.value } }))

  if (response) {
    toast.add({ title: t('aiContext.saved'), color: 'success' })
  }
}

async function onFile(event: Event): Promise<void> {
  const file = (event.target as HTMLInputElement).files?.[0]
  ;(event.target as HTMLInputElement).value = ''
  fileError.value = ''

  if (!file) {
    return
  }

  const problem = fileProblem(file)

  if (problem) {
    fileError.value = t(`aiContext.file.${problem}`)

    return
  }

  const response = await run(() => uploadFile({ projectId: props.projectId, file }))

  if (response) {
    toast.add({ title: t('aiContext.fileSaved'), color: 'success' })
  } else {
    fileError.value = errors.value.file ?? ''
  }
}

async function removeTheFile(): Promise<void> {
  await run(() => removeFile({ projectId: props.projectId }))
}

async function clearAll(): Promise<void> {
  const response = await run(() => clearContext({ projectId: props.projectId }))

  if (response) {
    text.value = ''
    shared.value = false
  }
}
</script>

<template>
  <wa-card>
    <div slot="header">
      <h2 class="wa-heading-m">
        {{ t('aiContext.title') }}
      </h2>
      <p
        class="wa-body-s wa-color-text-quiet"
        data-zen="hide"
      >
        {{ t('aiContext.intro') }}
      </p>
    </div>

    <form
      class="wa-stack wa-gap-m"
      @submit.prevent="save"
    >
      <FormTextarea
        v-model="text"
        :label="t('aiContext.text')"
        :hint="t('aiContext.textHint')"
        :error="errors.text"
        :rows="6"
      />

      <div class="wa-stack wa-gap-xs">
        <span class="wa-caption-m">{{ t('aiContext.fileLabel') }}</span>
        <div
          v-if="saved?.fileName"
          class="wa-split wa-gap-s"
        >
          <span><wa-icon name="file-lines"></wa-icon> {{ saved.fileName }}</span>
          <wa-button
            appearance="plain"
            variant="danger"
            size="small"
            @click="removeTheFile"
          >
            {{ t('aiContext.removeFile') }}
          </wa-button>
        </div>
        <div class="wa-cluster wa-gap-s">
          <wa-button
            appearance="outlined"
            variant="neutral"
            size="small"
            @click="input?.click()"
          >
            <wa-icon
              slot="start"
              name="upload"
            ></wa-icon>
            {{ saved?.fileName ? t('aiContext.replaceFile') : t('aiContext.chooseFile') }}
          </wa-button>
          <input
            ref="input"
            type="file"
            accept=".txt,.md,text/plain,text/markdown"
            class="wa-visually-hidden"
            tabindex="-1"
            @change="onFile"
          >
        </div>
        <p
          v-if="fileError"
          class="wa-caption-s"
          data-form-error
        >
          {{ fileError }}
        </p>
      </div>

      <p
        class="ai-counter"
        :data-over="over ? '' : undefined"
        aria-live="polite"
      >
        {{ t('aiContext.counter', { used: n(used), limit: n(AI_CONTEXT_LIMIT) }) }}
      </p>

      <FormSwitch
        v-model="shared"
        :label="t('aiContext.share')"
        :hint="t('aiContext.shareHint')"
      />

      <div class="wa-split wa-gap-s">
        <wa-button
          v-if="saved"
          appearance="plain"
          variant="danger"
          @click="clearAll"
        >
          {{ t('aiContext.clear') }}
        </wa-button>
        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
          :disabled="!dirty || over"
        >
          {{ t('common.save') }}
        </wa-button>
      </div>
    </form>
  </wa-card>
</template>

<style scoped>
.ai-counter {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.ai-counter[data-over] {
  color: var(--wa-color-danger-on-quiet);
  font-weight: var(--wa-font-weight-semibold);
}
</style>
