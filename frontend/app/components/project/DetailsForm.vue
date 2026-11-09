<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import type { Locale } from '~/graphql/generated/graphql'
import { updateProjectMutation } from '~/graphql/settings'

/** Name, description, icon and mail language of the project. */
const props = defineProps<{
  project: { id: string, name: string, description?: string | null, icon?: string | null, locale: Locale }
}>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const state = reactive<{ name: string, description: string, icon: string | null, locale: Locale }>({ name: '', description: '', icon: null, locale: 'DE' })

watch(() => props.project, (project) => {
  Object.assign(state, { name: project.name, description: project.description ?? '', icon: project.icon ?? null, locale: project.locale })
}, { immediate: true })

const locales = computed(() => [
  { label: t('locale.DE'), value: 'DE' },
  { label: t('locale.EN'), value: 'EN' }
])

const { mutate } = useMutation(updateProjectMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

async function submit(): Promise<void> {
  const result = await run(() => mutate({
    id: props.project.id,
    input: { name: state.name.trim(), description: state.description.trim() || null, icon: state.icon, locale: state.locale }
  }))

  if (result) {
    toast.add({ title: t('settings.project.saved'), color: 'success' })
  }
}
</script>

<template>
  <wa-card>
    <h2
      slot="header"
      class="wa-heading-m"
    >
      {{ t('settings.project.title') }}
    </h2>

    <form
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('projects.fields.name')"
        :error="errors.name"
        required
      />
      <FormTextarea
        v-model="state.description"
        name="description"
        :label="t('projects.fields.description')"
        :error="errors.description"
        :rows="3"
      />
      <FormField
        :label="t('item.fields.icon')"
        :hint="t('projects.fields.iconHint')"
        :error="errors.icon"
      >
        <IconPicker
          v-model="state.icon"
          fallback="folder"
        />
      </FormField>
      <FormSelect
        v-model="state.locale"
        name="locale"
        :items="locales"
        :label="t('projects.fields.locale')"
        :hint="t('projects.fields.localeHint')"
        :error="errors.locale"
      />
      <div>
        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
        >
          {{ t('common.save') }}
        </wa-button>
      </div>
    </form>
  </wa-card>
</template>
