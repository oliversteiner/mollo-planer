<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { NavItem } from '~/components/NavList.vue'
import { sidebarProjectsQuery } from '~/graphql/projects'

/**
 * Sidebar category "Projects": choose a project, then its pages. The
 * project of the current page is chosen automatically; otherwise the last
 * one chosen (remembered in the browser), otherwise the first.
 */
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

const links = computed<NavItem[]>(() => {
  const id = projectId.value

  if (!id) {
    return []
  }

  return [
    { label: t('project.tabs.overview'), icon: 'flag-checkered', to: `/projects/${id}`, exact: true },
    { label: t('project.tabs.todos'), icon: 'list-check', to: `/projects/${id}/todos` },
    { label: t('project.tabs.kanban'), icon: 'table-columns', to: `/projects/${id}/kanban` },
    { label: t('project.tabs.settings'), icon: 'gear', to: `/projects/${id}/settings` }
  ]
})
</script>

<template>
  <section
    class="sidebar-projects"
    :aria-label="t('nav.projects')"
  >
    <h2 class="sidebar-heading">
      {{ t('nav.projects') }}
    </h2>

    <FormSelect
      v-if="projects.length"
      :model-value="projectId"
      :items="projectItems"
      :searchable="projects.length > 5"
      :placeholder="t('nav.searchProject')"
      :aria-label="t('nav.chooseProject')"
      @update:model-value="value => value && choose(value)"
    />
    <p
      v-else
      class="sidebar-empty"
    >
      {{ t('projects.none') }}
    </p>

    <NavList :items="links" />
  </section>
</template>

<style scoped>
.sidebar-projects {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
}

.sidebar-heading {
  margin: 0;
  padding: var(--wa-space-s) var(--wa-space-s) 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
}

.sidebar-empty {
  margin: 0;
  padding-inline: var(--wa-space-s);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
