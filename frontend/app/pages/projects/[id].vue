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
  <UDashboardPanel id="project">
    <template #header>
      <UDashboardNavbar :title="title">
        <template #right>
          <UBadge
            v-if="project?.viewerRole"
            color="neutral"
            variant="outline"
          >
            {{ t(`role.${project.viewerRole}`) }}
          </UBadge>
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <USkeleton
        v-if="!loaded"
        class="h-32"
      />
      <UEmpty
        v-else-if="!project"
        icon="i-fadl-folder-open"
        :title="t('projects.notFound')"
      />
      <NuxtPage v-else />
    </template>
  </UDashboardPanel>
</template>
