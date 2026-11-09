<script setup lang="ts">
/**
 * Frame of every project page: name, role and the page. The pages of a
 * project are linked in the sidebar (category "Projects").
 */
const { t } = useI18n()
const route = useRoute()
const { project, loaded } = useProject()

/** Name of the page within the project, shown next to the project name. */
const pageName = computed(() => {
  const page = route.path.split('/')[3] ?? ''
  const keys: Record<string, string> = { '': 'overview', 'todos': 'todos', 'kanban': 'kanban', 'settings': 'settings' }

  return t(`project.tabs.${keys[page] ?? 'overview'}`)
})

const title = computed(() => (project.value ? `${project.value.name} · ${pageName.value}` : ''))

useHead({ title: () => (project.value ? title.value : t('projects.title')) })
</script>

<template>
  <PageFrame :title="title">
    <template
      v-if="project?.viewerRole"
      #actions
    >
      <wa-badge
        variant="neutral"
        appearance="outlined"
      >
        {{ t(`role.${project.viewerRole}`) }}
      </wa-badge>
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
