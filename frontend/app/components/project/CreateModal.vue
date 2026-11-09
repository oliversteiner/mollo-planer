<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import type { Locale } from '~/graphql/generated/graphql'
import { createProjectMutation } from '~/graphql/projects'

/** New project (admins only; the backend checks it again). */
const open = defineModel<boolean>('open', { default: false })

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const state = reactive<{ name: string, description: string, locale: Locale }>({ name: '', description: '', locale: 'DE' })
const locales = computed(() => [
  { label: t('locale.DE'), value: 'DE' },
  { label: t('locale.EN'), value: 'EN' }
])

// 'active' includes the project choice in the sidebar.
const { mutate } = useMutation(createProjectMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

async function submit(): Promise<void> {
  const result = await run(() => mutate({
    input: { name: state.name.trim(), description: state.description.trim() || null, locale: state.locale }
  }))
  const project = result?.data?.createProject

  if (project) {
    open.value = false
    toast.add({ title: t('projects.create.done', { name: project.name }), color: 'success' })
    await navigateTo(`/projects/${project.id}`)
  }
}

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(state, { name: '', description: '', locale: 'DE' })
    errors.value = {}
  }
})
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="t('projects.create.title')"
  >
    <form
      id="create-project"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('projects.fields.name')"
        :error="errors.name"
        autofocus
        required
      />

      <FormTextarea
        v-model="state.description"
        name="description"
        :label="t('projects.fields.description')"
        :error="errors.description"
        :rows="3"
      />

      <FormSelect
        v-model="state.locale"
        name="locale"
        :items="locales"
        :label="t('projects.fields.locale')"
        :hint="t('projects.fields.localeHint')"
        :error="errors.locale"
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
        form="create-project"
        variant="brand"
        :loading="pending"
      >
        {{ t('projects.create.submit') }}
      </wa-button>
    </template>
  </AppDialog>
</template>
