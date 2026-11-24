<script setup lang="ts">
import { importPlanMutation } from '~/graphql/planer'
import type { ImportPlanMutation } from '~/graphql/generated/graphql'
import { readPlanerFile, type PlanerDocument } from '~/utils/planer'

/**
 * Reads a planer file into a project (docs/auftrag-m4.md §7): choose the file,
 * a dry run shows what would happen (the tree with marks, the changes, the
 * errors with their place), then "Import". Without a project id the file
 * becomes a new project. Everything the server rejects is shown with its path;
 * nothing is written before the person confirms, and a deletion is named.
 */
const props = defineProps<{
  /** The project the file goes into; without: a new project. */
  projectId?: string
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ done: [projectId: string] }>()

type Result = ImportPlanMutation['importPlan']

const { t } = useI18n()
const toast = useToast()
const refreshProject = useProjectRefresh()
const input = ref<HTMLInputElement | null>(null)

const document = ref<PlanerDocument | null>(null)
const text = ref('')
const fileName = ref('')
const problem = ref<string | null>(null)
const mode = ref<'FULL' | 'PARTIAL'>('FULL')
const result = ref<Result | null>(null)
const checking = ref(false)
const confirmingDeletion = ref(false)

const { mutate } = useProjectMutation(importPlanMutation)

const modes = computed(() => [
  { label: t('planer.import.modeFull'), value: 'FULL' },
  { label: t('planer.import.modePartial'), value: 'PARTIAL' }
])

const deletions = computed(() => (result.value?.changes ?? []).filter(change => change.kind === 'DELETE'))
const canImport = computed(() => !!result.value?.ok && (result.value.changes.length > 0 || !props.projectId))

function reset(): void {
  document.value = null
  text.value = ''
  fileName.value = ''
  problem.value = null
  mode.value = 'FULL'
  result.value = null
  confirmingDeletion.value = false
  if (input.value) {
    input.value.value = ''
  }
}

watch(open, (isOpen) => {
  if (isOpen) {
    reset()
  }
})

async function send(dryRun: boolean): Promise<Result | null> {
  const response = await mutate({ projectId: props.projectId ?? null, document: text.value, mode: mode.value, dryRun })

  return response?.data?.importPlan ?? null
}

async function check(): Promise<void> {
  checking.value = true

  try {
    result.value = await send(true)
  } catch {
    result.value = null
  } finally {
    checking.value = false
  }
}

async function onFile(event: Event): Promise<void> {
  const file = (event.target as HTMLInputElement).files?.[0]

  if (!file) {
    return
  }

  problem.value = null
  result.value = null
  fileName.value = file.name
  text.value = await file.text()

  const read = readPlanerFile(text.value)

  if ('problem' in read) {
    document.value = null
    problem.value = t(`planer.import.${read.problem}`)

    return
  }

  document.value = read.document
  // A file with a revision: the dry run checks it; a new project ignores it.
  await check()
}

watch(mode, () => {
  if (document.value) {
    void check()
  }
})

async function run(): Promise<void> {
  const imported = await send(false)

  if (!imported?.ok) {
    result.value = imported

    return
  }

  open.value = false
  // The warnings of the dry run stay true for the real run: say what was left out, the dialog is gone.
  toast.add(imported.warnings.length
    ? { title: t('planer.import.done'), description: t('planer.import.warningsDone', imported.warnings.length), color: 'warning' }
    : { title: t('planer.import.done'), color: 'success' })

  if (props.projectId) {
    await refreshProject(props.projectId)
  }

  emit('done', imported.projectId ?? props.projectId ?? '')
}

function submit(): Promise<void> | void {
  if (deletions.value.length) {
    confirmingDeletion.value = true

    return
  }

  return run()
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="projectId ? t('planer.import.title') : t('planer.import.titleNew')"
    :description="projectId ? t('planer.import.intro') : t('planer.import.introNew')"
  >
    <div class="wa-stack wa-gap-m">
      <div class="wa-cluster wa-gap-s">
        <wa-button
          appearance="outlined"
          variant="neutral"
          @click="input?.click()"
        >
          <wa-icon
            slot="start"
            name="file-import"
          ></wa-icon>
          {{ fileName ? t('planer.import.chooseOther') : t('planer.import.choose') }}
        </wa-button>
        <span
          v-if="fileName"
          class="import-file"
        >{{ fileName }}</span>
        <input
          ref="input"
          type="file"
          accept=".json,application/json"
          class="wa-visually-hidden"
          tabindex="-1"
          @change="onFile"
        >
      </div>

      <AppCallout
        v-if="problem"
        variant="danger"
        :description="problem"
      />

      <FormSelect
        v-if="projectId && document"
        v-model="mode"
        name="mode"
        :items="modes"
        :label="t('planer.import.mode')"
        :hint="mode === 'FULL' ? t('planer.import.modeFullHint') : t('planer.import.modePartialHint')"
      />

      <wa-skeleton
        v-if="checking"
        effect="sheen"
        class="import-skeleton"
      ></wa-skeleton>

      <template v-else-if="document && result">
        <AppCallout
          v-if="result.errors.length"
          variant="danger"
          :title="t('planer.import.errors', result.errors.length)"
        >
          <ul class="import-errors">
            <li
              v-for="(error, index) in result.errors"
              :key="index"
            >
              <code v-if="error.path">{{ error.path }}</code>
              {{ error.message }}
            </li>
          </ul>
        </AppCallout>
        <AppCallout
          v-else-if="!result.changes.length && projectId"
          variant="neutral"
          :description="t('planer.import.nothing')"
        />
        <AppCallout
          v-else
          variant="success"
          :description="t('planer.import.changes', result.changes.length)"
        />

        <AppCallout
          v-if="result.warnings.length"
          variant="warning"
          :title="t('planer.import.warnings', result.warnings.length)"
        >
          <ul class="import-errors">
            <li
              v-for="(warning, index) in result.warnings"
              :key="index"
            >
              <code v-if="warning.path">{{ warning.path }}</code>
              {{ warning.message }}
            </li>
          </ul>
        </AppCallout>

        <PlanerTree
          v-if="!result.errors.length"
          :document="document"
          :changes="result.changes"
        />
      </template>
    </div>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="open = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        variant="brand"
        :disabled="!canImport || checking"
        @click="submit"
      >
        {{ t('planer.import.submit') }}
      </wa-button>
    </template>
  </AppDialog>

  <ConfirmModal
    :open="confirmingDeletion"
    :title="t('planer.import.confirmTitle')"
    :description="t('planer.import.confirmDescription', deletions.length)"
    :confirm-label="t('planer.import.submit')"
    :action="run"
    @close="confirmingDeletion = false"
  />
</template>

<style scoped>
.import-file {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.import-skeleton {
  block-size: 6rem;
}

.import-errors {
  margin: var(--wa-space-2xs) 0 0;
  padding-inline-start: var(--wa-space-l);
}
</style>
