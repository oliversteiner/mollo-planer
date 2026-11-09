<script setup lang="ts">
import type { Crumb } from '~/components/PageFrame.vue'

/**
 * Frame of every project page: header (breadcrumb, project name, role,
 * description, the actions of the page), the tabs of the project and the
 * page. The pages put their buttons into the header with
 * <Teleport to="#page-actions" defer>.
 */
const { t } = useI18n()
const route = useRoute()
const { project, loaded, can } = useProject()

/** Name of the page within the project (the tab), for breadcrumb and window title. */
const pageName = computed(() => {
  const page = route.path.split('/')[3] ?? ''
  const keys: Record<string, string> = { '': 'milestones', 'todos': 'todos', 'kanban': 'tasks', 'members': 'members', 'settings': 'settings' }

  return t(`project.tabs.${keys[page] ?? 'milestones'}`)
})

const breadcrumbs = computed<Crumb[]>(() => [
  { label: t('projects.title'), to: '/projects' },
  ...(project.value ? [{ label: project.value.name, to: `/projects/${project.value.id}` }] : []),
  { label: pageName.value }
])

/** "Settings" only when there is something to set: changing or deleting the project. */
const withSettings = computed(() => can('PROJECT_UPDATE') || can('PROJECT_DELETE'))

useHead({ title: () => (project.value ? `${project.value.name} · ${pageName.value}` : t('projects.title')) })
</script>

<template>
  <PageFrame
    :title="project?.name ?? t('projects.title')"
    :breadcrumbs="breadcrumbs"
    :description="project?.description"
  >
    <template
      v-if="project?.viewerRole"
      #title-end
    >
      <wa-badge
        variant="neutral"
        appearance="outlined"
      >
        {{ t(`role.${project.viewerRole}`) }}
      </wa-badge>
    </template>

    <template
      v-if="project"
      #nav
    >
      <ProjectTabs
        :project-id="project.id"
        :with-settings="withSettings"
      />
    </template>

    <wa-skeleton
      v-if="!loaded"
      effect="sheen"
      class="project-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!project"
      icon="folder-open"
      :title="t('projects.notFound')"
    />
    <NuxtPage v-else />
  </PageFrame>
</template>

<style scoped>
.project-skeleton {
  block-size: 8rem;
}
</style>
