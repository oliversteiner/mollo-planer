<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { NavItem } from '~/components/NavList.vue'
import { sidebarProjectsQuery } from '~/graphql/projects'

/**
 * Sidebar category "Projects": every project of the viewer, alphabetical,
 * with its icon. The project of the current page is marked; its pages are
 * reached through the tabs below the page header.
 */
const { t } = useI18n()

const { result, loading } = useQuery(sidebarProjectsQuery, null, { fetchPolicy: 'cache-and-network' })

const items = computed<NavItem[]>(() => [...(result.value?.projects ?? [])]
  .sort((a, b) => a.name.localeCompare(b.name))
  .map(project => ({ label: project.name, icon: project.icon, fallbackIcon: 'folder', to: `/projects/${project.id}` })))
</script>

<template>
  <section
    class="sidebar-projects"
    :aria-label="t('nav.projects')"
  >
    <h2 class="sidebar-heading">
      {{ t('nav.projects') }}
    </h2>

    <NavList
      v-if="items.length"
      :items="items"
      class="sidebar-project-list"
    />
    <p
      v-else-if="!loading"
      class="sidebar-empty"
    >
      {{ t('projects.none') }}
    </p>
  </section>
</template>

<style scoped>
.sidebar-projects {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  min-block-size: 0;
}

.sidebar-heading {
  margin: 0;
  padding: 0 var(--wa-space-s);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* Many projects scroll inside the sidebar. */
.sidebar-project-list {
  max-block-size: 40vh;
  overflow-y: auto;
}

.sidebar-empty {
  margin: 0;
  padding-inline: var(--wa-space-s);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
