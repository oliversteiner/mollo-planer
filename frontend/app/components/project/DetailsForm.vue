<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import type { Locale } from '~/graphql/generated/graphql'
import { updateProjectMutation } from '~/graphql/settings'

/** Name, description and mail language of the project. */
const props = defineProps<{
  project: { id: string, name: string, description?: string | null, locale: Locale }
}>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const state = reactive<{ name: string, description: string, locale: Locale }>({ name: '', description: '', locale: 'DE' })

watch(() => props.project, (project) => {
  Object.assign(state, { name: project.name, description: project.description ?? '', locale: project.locale })
}, { immediate: true })

const locales = computed(() => [
  { label: t('locale.DE'), value: 'DE' },
  { label: t('locale.EN'), value: 'EN' }
])

const { mutate } = useMutation(updateProjectMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

async function submit(): Promise<void> {
  const result = await run(() => mutate({
    id: props.project.id,
    input: { name: state.name.trim(), description: state.description.trim() || null, locale: state.locale }
  }))

  if (result) {
    toast.add({ title: t('settings.project.saved'), color: 'success' })
  }
}
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ t('settings.project.title') }}
      </h2>
    </template>

    <UForm
      :state="state"
      class="space-y-4"
      @submit="submit"
    >
      <UFormField
        :label="t('projects.fields.name')"
        name="name"
        :error="errors.name"
        required
      >
        <UInput
          v-model="state.name"
          class="w-full"
          required
        />
      </UFormField>
      <UFormField
        :label="t('projects.fields.description')"
        name="description"
        :error="errors.description"
      >
        <UTextarea
          v-model="state.description"
          :rows="3"
          class="w-full"
        />
      </UFormField>
      <UFormField
        :label="t('projects.fields.locale')"
        :hint="t('projects.fields.localeHint')"
        name="locale"
        :error="errors.locale"
      >
        <USelect
          v-model="state.locale"
          :items="locales"
          class="w-48"
        />
      </UFormField>
      <UButton
        type="submit"
        :label="t('common.save')"
        :loading="pending"
      />
    </UForm>
  </UCard>
</template>
