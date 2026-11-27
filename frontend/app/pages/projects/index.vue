<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { projectsQuery } from '~/graphql/projects'

/**
 * The projects the viewer sees, then the archived ones in a section of their
 * own, closed at first (docs/auftrag-m2.md §1.6).
 */
const { t } = useI18n()
const { can } = useViewer()

useHead({ title: () => t('projects.title') })

const { result, loading } = useQuery(projectsQuery, null, { fetchPolicy: 'cache-and-network' })

const projects = computed(() => result.value?.projects ?? [])

const { result: archivedResult } = useQuery(projectsQuery, { archived: true }, { fetchPolicy: 'cache-and-network' })
const archived = computed(() => archivedResult.value?.projects ?? [])
const creating = ref(false)

// The button is in the page actions, which the calm view hides.
useCommands(() => [{
  id: 'projects-create',
  label: t('projects.create.open'),
  icon: 'folder-plus',
  right: 'PROJECTS_CREATE',
  group: 'action',
  run: () => {
    creating.value = true
  }
}])
</script>

<template>
  <PageFrame
    :title="t('projects.title')"
    :breadcrumbs="[{ label: t('nav.workspace'), to: '/' }, { label: t('projects.title') }]"
  >
    <template
      v-if="can('PROJECTS_CREATE')"
      #actions
    >
      <wa-button
        variant="brand"
        @click="creating = true"
      >
        <wa-icon
          slot="start"
          name="folder-plus"
        ></wa-icon>
        {{ t('projects.create.open') }}
      </wa-button>
    </template>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="projects-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!projects.length"
      icon="folder-open"
      :title="t('projects.none')"
      :description="can('PROJECTS_CREATE') ? t('projects.noneAdmin') : t('projects.noneMember')"
    />
    <ProjectGrid
      v-else
      :projects="projects"
    />

    <wa-details
      v-if="archived.length"
      class="projects-archive"
    >
      <span slot="summary">
        <wa-icon name="box-archive"></wa-icon>
        <TabLabel
          :label="t('projects.archive.title')"
          :count="archived.length"
        />
      </span>
      <ProjectGrid :projects="archived" />
    </wa-details>

    <ProjectCreateModal v-model:open="creating" />
  </PageFrame>
</template>

<style scoped>
.projects-skeleton {
  block-size: 8rem;
}

.projects-archive {
  margin-block-start: var(--wa-space-xl);
}

.projects-archive [slot='summary'] {
  display: inline-flex;
  gap: var(--wa-space-xs);
  align-items: center;
  font-weight: var(--wa-font-weight-semibold);
}
</style>
