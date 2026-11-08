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
  <UModal
    v-model:open="open"
    :title="t('projects.create.title')"
  >
    <template #body>
      <UForm
        id="create-project"
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
            autofocus
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
            class="w-full"
          />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          :label="t('common.cancel')"
          color="neutral"
          variant="ghost"
          @click="open = false"
        />
        <UButton
          type="submit"
          form="create-project"
          :label="t('projects.create.submit')"
          :loading="pending"
        />
      </div>
    </template>
  </UModal>
</template>
