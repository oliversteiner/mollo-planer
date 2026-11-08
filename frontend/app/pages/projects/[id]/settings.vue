<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { deleteProjectMutation, projectSettingsQuery } from '~/graphql/settings'

const { t } = useI18n()
const toast = useToast()
const { projectId, project: header, can } = useProject()

const { result } = useQuery(projectSettingsQuery, () => ({ id: projectId.value }), { fetchPolicy: 'cache-and-network' })
const project = computed(() => result.value?.project ?? null)

const deleting = ref(false)
// 'active' includes the project choice in the sidebar.
const { mutate: deleteProject } = useMutation(deleteProjectMutation, { refetchQueries: 'active' })

async function confirmDelete(): Promise<void> {
  await deleteProject({ id: projectId.value })
  toast.add({ title: t('settings.danger.deleted', { name: header.value?.name ?? '' }), color: 'success' })
  await navigateTo('/projects')
}
</script>

<template>
  <USkeleton
    v-if="!project"
    class="h-40"
  />
  <div
    v-else
    class="grid max-w-5xl gap-6 lg:grid-cols-2"
  >
    <ProjectDetailsForm
      v-if="can('PROJECT_UPDATE')"
      :project="project"
    />
    <ProjectMembersCard
      :project-id="project.id"
      :members="project.members"
      :can-manage="can('MEMBERS_MANAGE')"
    />

    <UCard
      v-if="can('PROJECT_DELETE')"
      class="border-error/40 lg:col-span-2"
    >
      <template #header>
        <h2 class="font-semibold text-error">
          {{ t('settings.danger.title') }}
        </h2>
      </template>
      <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="max-w-prose text-sm text-muted">
          {{ t('settings.danger.description') }}
        </p>
        <UButton
          icon="i-fadl-trash"
          :label="t('settings.danger.delete')"
          color="error"
          @click="deleting = true"
        />
      </div>
    </UCard>

    <ConfirmModal
      :open="deleting"
      :title="t('settings.danger.confirmTitle', { name: project.name })"
      :description="t('settings.danger.confirmDescription')"
      :confirm-label="t('settings.danger.delete')"
      :action="confirmDelete"
      @close="deleting = false"
    />
  </div>
</template>
