<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import type { Locale } from '~/graphql/generated/graphql'
import { createTemplateMutation, updateTemplateMutation } from '~/graphql/labels'
import type { LabelRow, LabelLocale, LabelSource } from '~/utils/labels'
import { labelInputs, rowFromLabel } from '~/utils/labels'

/**
 * Create an own template (name, description, language, labels, no outline) or
 * change one (name, description, labels). The labels of a template need a name
 * in its language; language and outline stay when it is changed.
 */
export interface TemplateFormTarget {
  template?: {
    id: string
    name: string
    description?: string | null
    locale: string
    labels: readonly LabelSource[]
  }
}

const props = defineProps<{ target: TemplateFormTarget | null }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const state = reactive<{ name: string, description: string, locale: Locale, rows: LabelRow[] }>({ name: '', description: '', locale: 'DE', rows: [] })

const open = computed({
  get: () => props.target !== null,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const isNew = computed(() => !props.target?.template)
const localeItems = computed(() => [
  { label: t('locale.DE'), value: 'DE' },
  { label: t('locale.EN'), value: 'EN' }
])

watch(() => props.target, (target) => {
  errors.value = {}
  const template = target?.template
  state.name = template?.name ?? ''
  state.description = template?.description ?? ''
  state.locale = (template?.locale.toUpperCase() as Locale | undefined) ?? 'DE'
  state.rows = (template?.labels ?? []).map(rowFromLabel)
}, { immediate: true })

const { mutate: createTemplate } = useMutation(createTemplateMutation, templateRefresh)
const { mutate: updateTemplate } = useMutation(updateTemplateMutation, templateRefresh)

async function submit(): Promise<void> {
  const base = { name: state.name.trim(), description: state.description.trim() || null, labels: labelInputs(state.rows) }
  const template = props.target?.template
  const result = await run<unknown>(() => (template
    ? updateTemplate({ id: template.id, input: base })
    : createTemplate({ input: { ...base, locale: state.locale } })))

  if (result) {
    toast.add({ title: t('templates.saved'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="isNew ? t('templates.create') : t('templates.edit')"
    class="template-dialog"
  >
    <form
      id="template-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('templates.name')"
        :error="errors.name"
        maxlength="60"
        autofocus
        required
      />
      <FormTextarea
        v-model="state.description"
        name="description"
        :label="t('templates.description')"
        :error="errors.description"
        maxlength="500"
        :rows="2"
      />
      <FormSelect
        v-if="isNew"
        v-model="state.locale"
        name="locale"
        :items="localeItems"
        :label="t('templates.locale')"
        :hint="t('templates.localeHint')"
        :error="errors.locale"
      />
      <LabelEditor
        v-model="state.rows"
        :required="state.locale as LabelLocale"
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
        form="template-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.template-dialog {
  --width: 56rem;
}
</style>
