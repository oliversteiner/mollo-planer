<script setup lang="ts">
import { useApolloClient, useMutation, useQuery } from '@vue/apollo-composable'
import { archiveProjectMutation, deleteProjectMutation, projectSettingsQuery, unarchiveProjectMutation } from '~/graphql/settings'

/**
 * Settings of the project: details and labels (project.update), archive
 * and bring back (project.archive, asks before archiving), delete into the
 * trash (project.delete, asks with the name). An archived project shows only
 * what still holds there (Rights in the backend).
 */
const { t } = useI18n()
const { resolveClient } = useApolloClient()
const toast = useToast()
const { projectId, project: header, can } = useProject()
// Billing is a system right, independent of the role in the project (Auftrag 39).
const { can: canSystem } = useViewer()

const { result } = useQuery(projectSettingsQuery, () => ({ id: projectId.value }), { fetchPolicy: 'cache-and-network' })
const project = computed(() => result.value?.project ?? null)

const deleting = ref(false)
// 'active' includes the project choice in the sidebar.
const { mutate: deleteProject } = useMutation(deleteProjectMutation, { refetchQueries: 'active' })

async function confirmDelete(): Promise<void> {
  await deleteProject({ id: projectId.value })
  evictProjectLists(resolveClient().cache)
  toast.add({ title: t('settings.danger.deleted', { name: header.value?.name ?? '' }), color: 'success' })
  await navigateTo('/projects')
}

const archived = computed(() => header.value?.archivedAt != null)
const archiving = ref(false)
const { mutate: archiveProject } = useMutation(archiveProjectMutation, { refetchQueries: 'active' })
const { mutate: unarchiveProject, loading: unarchiving } = useMutation(unarchiveProjectMutation, { refetchQueries: 'active' })

async function confirmArchive(): Promise<void> {
  await archiveProject({ id: projectId.value })
  evictProjectLists(resolveClient().cache)
  toast.add({ title: t('settings.archive.done', { name: header.value?.name ?? '' }), color: 'success' })
}

async function bringBack(): Promise<void> {
  const result = await unarchiveProject({ id: projectId.value }).catch(() => null)

  if (result) {
    evictProjectLists(resolveClient().cache)
    toast.add({ title: t('settings.archive.back', { name: header.value?.name ?? '' }), color: 'success' })
  }
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
    <ProjectLabelsCard
      v-if="can('PROJECT_UPDATE')"
      :project-id="project.id"
    />
    <ProjectBillingCard
      v-if="canSystem('BILLING_MANAGE')"
      :project-id="project.id"
      :customer="project.customer ?? null"
    />
    <PlanerFileCard
      v-if="can('PLANER_EXPORT') || can('PLANER_IMPORT')"
      :project-id="project.id"
      :project-name="project.name"
      :can-export="can('PLANER_EXPORT')"
      :can-import="can('PLANER_IMPORT')"
    />
    <PlanerWebhookCard
      v-if="can('PROJECT_UPDATE')"
      :project-id="project.id"
    />
    <ProjectAiContextCard
      v-if="can('AI_CONTEXT_UPDATE')"
      :project-id="project.id"
    />

    <wa-card v-if="can('PROJECT_ARCHIVE')">
      <h2
        slot="header"
        class="wa-heading-m"
      >
        {{ t('settings.archive.title') }}
      </h2>
      <div class="wa-split wa-gap-m">
        <p class="settings-danger-text">
          {{ archived ? t('settings.archive.archivedText') : t('settings.archive.description') }}
        </p>
        <wa-button
          v-if="archived"
          variant="brand"
          :loading="unarchiving"
          @click="bringBack"
        >
          <wa-icon
            slot="start"
            name="box-open"
          ></wa-icon>
          {{ t('settings.archive.unarchive') }}
        </wa-button>
        <wa-button
          v-else
          variant="neutral"
          appearance="outlined"
          @click="archiving = true"
        >
          <wa-icon
            slot="start"
            name="box-archive"
          ></wa-icon>
          {{ t('settings.archive.archive') }}
        </wa-button>
      </div>
    </wa-card>

    <wa-card
      v-if="can('PROJECT_DELETE')"
      class="settings-danger"
    >
      <h2
        slot="header"
        class="wa-heading-m settings-danger-title"
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
      :open="archiving"
      :title="t('settings.archive.confirmTitle', { name: project.name })"
      :description="t('settings.archive.confirmDescription')"
      :confirm-label="t('settings.archive.archive')"
      :action="confirmArchive"
      @close="archiving = false"
    />
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
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-l);
  max-inline-size: 48rem;
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
