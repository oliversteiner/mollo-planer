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
  <wa-skeleton
    v-if="!project"
    effect="sheen"
    class="settings-skeleton"
  ></wa-skeleton>
  <div
    v-else
    class="settings-grid"
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

    <wa-card
      v-if="can('PROJECT_DELETE')"
      class="settings-danger"
    >
      <h2
        slot="header"
        class="wa-heading-xs settings-danger-title"
      >
        {{ t('settings.danger.title') }}
      </h2>
      <div class="wa-split wa-gap-m">
        <p class="settings-danger-text">
          {{ t('settings.danger.description') }}
        </p>
        <wa-button
          variant="danger"
          @click="deleting = true"
        >
          <wa-icon
            slot="start"
            name="trash"
          ></wa-icon>
          {{ t('settings.danger.delete') }}
        </wa-button>
      </div>
    </wa-card>

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

<style scoped>
.settings-skeleton {
  block-size: 10rem;
}

.settings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 26rem), 1fr));
  gap: var(--wa-space-l);
  max-inline-size: 72rem;
}

.settings-danger {
  grid-column: 1 / -1;
  --border-color: var(--wa-color-danger-border-normal);
}

.settings-danger-title {
  color: var(--wa-color-danger-on-quiet);
}

.settings-danger-text {
  margin: 0;
  max-inline-size: 65ch;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
