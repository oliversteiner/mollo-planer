<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

/**
 * Frame of every project page: name, tabs and the page of the tab. The
 * tabs follow the frontend plan (Kanban, settings in later steps).
 */
const { t } = useI18n()
const { projectId, project, loaded } = useProject()

useHead({ title: () => project.value?.name ?? t('projects.title') })

const tabs = computed<NavigationMenuItem[]>(() => [
  { label: t('project.tabs.overview'), icon: 'i-fadl-flag-checkered', to: `/projects/${projectId.value}`, exact: true },
  { label: t('project.tabs.todos'), icon: 'i-fadl-list-check', to: `/projects/${projectId.value}/todos` },
  { label: t('project.tabs.kanban'), icon: 'i-fadl-table-columns', to: `/projects/${projectId.value}/kanban` },
  // Everyone sees the members there; owners/managers also change things.
  { label: t('project.tabs.settings'), icon: 'i-fadl-gear', to: `/projects/${projectId.value}/settings` }
])
</script>

<template>
  <UDashboardPanel id="project">
    <template #header>
      <UDashboardNavbar :title="project?.name ?? ''">
        <template #leading>
          <UButton
            to="/projects"
            icon="i-fadl-arrow-left"
            color="neutral"
            variant="ghost"
            :aria-label="t('projects.title')"
          />
        </template>
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

      <UDashboardToolbar v-if="project">
        <UNavigationMenu
          :items="tabs"
          highlight
          class="-mx-1 flex-1"
        />
      </UDashboardToolbar>
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
