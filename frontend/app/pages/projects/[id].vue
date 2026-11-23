<script setup lang="ts">
import type { Crumb } from '~/components/PageFrame.vue'
import { documentText } from '~/utils/rich-text'

/**
 * Frame of every project page: header (breadcrumb, project name, role,
 * description, the actions of the page), the tabs of the project and the
 * page. The pages put their buttons into the header with
 * <Teleport to="#page-actions" defer>.
 */
const { t } = useI18n()
const roleName = useRoleName()
const route = useRoute()
const { project, loaded, can } = useProject()

/** Name of the page within the project (the tab), for breadcrumb and window title. */
const pageName = computed(() => {
  const page = route.path.split('/')[3] ?? ''
  const keys: Record<string, string> = { '': 'milestones', 'todos': 'todos', 'kanban': 'tasks', 'documents': 'documents', 'members': 'members', 'statistics': 'statistics', 'settings': 'settings', 'trash': 'trash' }

  return t(`project.tabs.${keys[page] ?? 'milestones'}`)
})

const breadcrumbs = computed<Crumb[]>(() => [
  { label: t('projects.title'), to: '/projects' },
  ...(project.value ? [{ label: project.value.name, to: `/projects/${project.value.id}` }] : []),
  { label: pageName.value }
])

/** "Settings" only when there is something to set: changing, archiving or deleting the project. */
const withSettings = computed(() => can('PROJECT_UPDATE') || can('PROJECT_ARCHIVE') || can('PROJECT_DELETE'))

/** "Trash" for people who may restore or delete for good (docs/auftrag-m2.md §4.4). */
const withTrash = computed(() => can('TRASH_RESTORE') || can('TRASH_PURGE'))

// The tabs are hidden in the calm view: the palette reaches the pages of the project.
useCommands(() => {
  const id = project.value?.id

  if (!id) {
    return []
  }

  const go = async (path: string): Promise<void> => {
    await navigateTo(path)
  }
  const tab = (key: string, path: string, icon: string, visible = true) => ({
    id: `project-tab-${key}`,
    label: `${project.value!.name}: ${t(`project.tabs.${key}`)}`,
    icon,
    group: 'action' as const,
    visible: () => visible,
    run: () => go(`/projects/${id}${path}`)
  })

  return [
    tab('milestones', '', 'timeline'),
    tab('todos', '/todos', 'list-check'),
    tab('tasks', '/kanban', 'table-columns'),
    tab('documents', '/documents', 'file-lines'),
    tab('members', '/members', 'users'),
    tab('statistics', '/statistics', 'chart-simple'),
    tab('settings', '/settings', 'gear', withSettings.value),
    tab('trash', '/trash', 'trash-can', withTrash.value),
    {
      id: 'project-assistant',
      label: t('assistant.addWith'),
      icon: 'wand-magic-sparkles',
      right: 'ASSISTANT_USE',
      group: 'action' as const,
      visible: () => !project.value?.archivedAt,
      run: () => go('/assistant?project=' + id)
    }
  ]
})

useHead({ title: () => (project.value ? `${project.value.name} · ${pageName.value}` : t('projects.title')) })
</script>

<template>
  <PageFrame
    :title="project?.name ?? t('projects.title')"
    :breadcrumbs="breadcrumbs"
    :description="documentText(project?.description)"
  >
    <template
      v-if="project?.viewerRole"
      #title-end
    >
      <wa-badge
        variant="neutral"
        appearance="outlined"
      >
        {{ roleName(project.viewerRole) }}
      </wa-badge>
    </template>

    <template
      v-if="project"
      #nav
    >
      <ProjectTabs
        :project-id="project.id"
        :with-settings="withSettings"
        :with-trash="withTrash"
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
    <template v-else>
      <AppCallout
        v-if="project.archivedAt"
        variant="neutral"
        :title="t('project.archived.title')"
        :description="t('project.archived.description')"
        class="project-archived"
      />
      <NuxtPage />
    </template>
  </PageFrame>
</template>

<style scoped>
.project-skeleton {
  block-size: 8rem;
}

.project-archived {
  margin-block-end: var(--wa-space-l);
}
</style>
