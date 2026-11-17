<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { applyTemplateLabelsMutation, templatesQuery } from '~/graphql/labels'
import { MAX_LABELS, nameFallbacks, templateLabelsPlan } from '~/utils/labels'

/**
 * Take over the labels of a template in the project (project.update): replace
 * them or add the missing ones, with a preview of what happens to the labels
 * and their tasks. One change for the planer file.
 */
const props = defineProps<{
  open: boolean
  projectId: string
  /** The language of the project (a label without a name in it gets the name of the template). */
  locale: string
  labels: readonly { key: string, taskCount?: number | null }[]
}>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { pending, run } = useGraphqlForm()
const { mutate } = useProjectMutation(applyTemplateLabelsMutation)

const { result } = useQuery(templatesQuery, null, () => ({ enabled: props.open, fetchPolicy: 'cache-and-network' }))
const templates = computed(() => result.value?.templates ?? [])

const templateId = ref<string | null>(null)
const mode = ref<'REPLACE' | 'APPEND'>('APPEND')

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    templateId.value = null
    mode.value = 'APPEND'
  }
})

const open = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const items = computed(() => templates.value.map(template => ({ label: template.name, value: template.id })))
const template = computed(() => templates.value.find(entry => entry.id === templateId.value) ?? null)
const plan = computed(() => (template.value ? templateLabelsPlan(props.labels, template.value.labels, mode.value) : null))
const tooMany = computed(() => (plan.value?.total ?? 0) > MAX_LABELS)
const fallbacks = computed(() => (template.value ? nameFallbacks(template.value.labels, props.locale.toUpperCase()) : 0))

function onMode(event: Event): void {
  mode.value = (event.target as HTMLElement & { value: string }).value === 'REPLACE' ? 'REPLACE' : 'APPEND'
}

async function apply(): Promise<void> {
  if (!template.value) {
    return
  }

  const done = await run<unknown>(() => mutate({ projectId: props.projectId, templateId: template.value!.id, mode: mode.value }))

  if (done) {
    toast.add({ title: t('labels.applied'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="t('labels.applyTitle')"
  >
    <div class="wa-stack wa-gap-m">
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('labels.applyIntro') }}
      </p>

      <p
        v-if="!templates.length"
        class="wa-body-s"
      >
        {{ t('labels.noTemplates') }}
      </p>
      <FormSelect
        v-else
        v-model="templateId"
        :items="items"
        :label="t('labels.template')"
      />

      <wa-radio-group
        v-if="template"
        :value="mode"
        :label="t('labels.mode')"
        :hint="mode === 'REPLACE' ? t('labels.replaceHint') : t('labels.appendHint')"
        @change="onMode"
      >
        <wa-radio value="APPEND">
          {{ t('labels.append') }}
        </wa-radio>
        <wa-radio value="REPLACE">
          {{ t('labels.replace') }}
        </wa-radio>
      </wa-radio-group>

      <div
        v-if="template && plan"
        class="wa-stack wa-gap-xs"
      >
        <h3 class="wa-heading-s">
          {{ t('labels.preview') }}
        </h3>
        <TaskLabels
          :labels="template.labels.map((label, position) => ({ id: label.key, name: label.name, color: label.color, position }))"
        />
        <ul class="plan wa-list-plain">
          <li>{{ t('labels.previewAdded', { count: plan.added }) }}</li>
          <li v-if="mode === 'REPLACE'">
            {{ t('labels.previewUpdated', { count: plan.updated }) }}
          </li>
          <li v-if="mode === 'REPLACE' && plan.kept">
            {{ t('labels.previewKept', { count: plan.kept }) }}
          </li>
          <li v-if="mode === 'REPLACE'">
            {{ t('labels.previewRemoved', { count: plan.removed }) }}
          </li>
          <li>{{ t('labels.previewTotal', { count: plan.total }) }}</li>
        </ul>
        <p
          v-if="fallbacks"
          class="wa-body-s wa-color-text-quiet"
        >
          {{ t('labels.fallbackTemplate', { count: fallbacks, language: t(`labels.languages.${locale.toUpperCase()}`) }, fallbacks) }}
        </p>
        <p
          v-if="tooMany"
          class="wa-body-s plan-error"
          data-form-error
        >
          {{ t('labels.tooMany', { max: MAX_LABELS }) }}
        </p>
      </div>
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
        :disabled="!template || tooMany"
        :loading="pending"
        @click="apply"
      >
        {{ t('labels.apply') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.plan {
  margin: 0;
  padding: 0;
  font-size: var(--wa-font-size-s);
}

.plan-error {
  color: var(--wa-color-danger-on-quiet);
}
</style>
