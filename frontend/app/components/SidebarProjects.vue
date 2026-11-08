<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { NavigationMenuItem } from '@nuxt/ui'
import { sidebarProjectsQuery } from '~/graphql/projects'

/**
 * Sidebar category "Projects": choose a project, then its pages. The
 * project of the current page is chosen automatically; otherwise the last
 * one chosen (remembered in the browser), otherwise the first.
 */
defineProps<{ collapsed?: boolean }>()

const { t } = useI18n()
const route = useRoute()

const STORAGE_KEY = 'sidebar.projectId'

const { result } = useQuery(sidebarProjectsQuery, null, { fetchPolicy: 'cache-and-network' })
const projects = computed(() => [...(result.value?.projects ?? [])].sort((a, b) => a.name.localeCompare(b.name)))

/** Project of the current page (/projects/:id/...), if any. */
const routeProjectId = computed(() => (route.path.startsWith('/projects/') && typeof route.params.id === 'string' ? route.params.id : null))

function remembered(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    return null
  }
}

function remember(id: string): void {
  try {
    localStorage.setItem(STORAGE_KEY, id)
  } catch {
    // Not stored (private mode); the choice is kept for this visit only.
  }
}

const chosenId = ref<string | null>(remembered())

const projectId = computed(() => {
  const known = (id: string | null) => (id && projects.value.some(project => project.id === id) ? id : null)

  return known(routeProjectId.value) ?? known(chosenId.value) ?? projects.value[0]?.id ?? null
})

watch(routeProjectId, (id) => {
  if (id) {
    chosenId.value = id
    remember(id)
  }
}, { immediate: true })

const projectItems = computed(() => projects.value.map(project => ({ label: project.name, value: project.id })))

/** Changing the project keeps the page: Kanban of one project, Kanban of the other. */
async function choose(id: string): Promise<void> {
  chosenId.value = id
  remember(id)
  const subPage = routeProjectId.value ? route.path.replace(/^\/projects\/[^/]+/, '') : ''
  await navigateTo(`/projects/${id}${subPage}`)
}

const links = computed<NavigationMenuItem[]>(() => {
  const id = projectId.value

  if (!id) {
    return []
  }

  return [
    { label: t('project.tabs.overview'), icon: 'i-fadl-flag-checkered', to: `/projects/${id}`, exact: true },
    { label: t('project.tabs.todos'), icon: 'i-fadl-list-check', to: `/projects/${id}/todos` },
    { label: t('project.tabs.kanban'), icon: 'i-fadl-table-columns', to: `/projects/${id}/kanban` },
    { label: t('project.tabs.settings'), icon: 'i-fadl-gear', to: `/projects/${id}/settings` }
  ]
})
</script>

<template>
  <nav
    class="flex flex-col gap-1"
    :aria-label="t('nav.projects')"
  >
    <span
      v-if="!collapsed"
      class="px-2.5 pt-2 text-xs font-semibold text-muted"
    >{{ t('nav.projects') }}</span>

    <USelectMenu
      v-if="!collapsed && projects.length"
      :model-value="projectId ?? undefined"
      :items="projectItems"
      value-key="value"
      :search-input="projects.length > 5 ? { placeholder: t('nav.searchProject') } : false"
      :aria-label="t('nav.chooseProject')"
      icon="i-fadl-folder-open"
      class="w-full"
      @update:model-value="choose"
    />
    <p
      v-else-if="!collapsed"
      class="px-2.5 text-sm text-muted"
    >
      {{ t('projects.none') }}
    </p>

    <UNavigationMenu
      v-if="links.length"
      :items="links"
      :collapsed="collapsed"
      orientation="vertical"
    />
  </nav>
</template>
