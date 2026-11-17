<script setup lang="ts">
import { updateProjectLabelsMutation } from '~/graphql/labels'
import type { LabelRow, LabelLocale, LabelSource } from '~/utils/labels'
import { labelInputs, removedLabels, rowFromLabel } from '~/utils/labels'

/**
 * The labels of a project in the editor (project.update). Labels that are
 * taken out are deleted from the tasks too; that is asked first and names
 * how many labels and tasks it concerns. One change for the planer file.
 */
const props = defineProps<{
  open: boolean
  projectId: string
  /** The language of the project: its names are required. */
  locale: string
  labels: readonly (LabelSource & { id: string })[]
}>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()
const { mutate } = useProjectMutation(updateProjectLabelsMutation)

const rows = ref<LabelRow[]>([])
const confirming = ref(false)

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    errors.value = {}
    rows.value = props.labels.map(rowFromLabel)
  }
}, { immediate: true })

const open = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const removed = computed(() => removedLabels(props.labels, rows.value))

function submit(): void {
  if (removed.value.count > 0) {
    confirming.value = true
  } else {
    void save()
  }
}

async function save(): Promise<void> {
  const result = await run<unknown>(() => mutate({ projectId: props.projectId, labels: labelInputs(rows.value) }))

  if (result) {
    toast.add({ title: t('labels.saved'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="t('labels.editTitle')"
    class="labels-dialog"
  >
    <form
      id="project-labels-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <LabelEditor
        v-model="rows"
        :required="locale.toUpperCase() as LabelLocale"
        :errors="errors"
      />
    </form>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="open = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        type="submit"
        form="project-labels-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>

  <ConfirmModal
    :open="confirming"
    :title="t('labels.removeTitle')"
    :description="t('labels.removeDescription', { count: removed.count, tasks: removed.tasks }, removed.count)"
    :confirm-label="t('labels.removeConfirm')"
    :action="save"
    @close="confirming = false"
  />
</template>

<style scoped>
.labels-dialog {
  --width: 56rem;
}
</style>
