<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { projectsQuery } from '~/graphql/projects'

const { t } = useI18n()
const { viewer } = useViewer()

useHead({ title: () => t('projects.title') })

const { result, loading } = useQuery(projectsQuery, null, { fetchPolicy: 'cache-and-network' })

const projects = computed(() => result.value?.projects ?? [])
const creating = ref(false)
</script>

<template>
  <PageFrame
    :title="t('projects.title')"
    :breadcrumbs="[{ label: t('nav.workspace'), to: '/' }, { label: t('projects.title') }]"
  >
    <template
      v-if="viewer?.isAdmin"
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
      :description="viewer?.isAdmin ? t('projects.noneAdmin') : t('projects.noneMember')"
    />
    <ul
      v-else
      class="projects-grid wa-list-plain"
    >
      <li
        v-for="project in projects"
        :key="project.id"
      >
        <NuxtLink
          :to="`/projects/${project.id}`"
          class="projects-card"
        >
          <span class="wa-split wa-gap-s wa-align-items-start">
            <span class="projects-name">{{ project.name }}</span>
            <wa-badge
              v-if="project.viewerRole"
              variant="neutral"
              appearance="outlined"
            >
              {{ t(`role.${project.viewerRole}`) }}
            </wa-badge>
          </span>
          <span
            v-if="project.description"
            class="projects-description"
          >{{ project.description }}</span>
          <span class="projects-footer">
            <wa-progress-bar
              :value="project.progress.percent"
              :label="t('projects.progress', { done: project.progress.done, total: project.progress.total })"
            ></wa-progress-bar>
            <span class="wa-split wa-gap-s">
              <span class="projects-progress">{{ t('projects.progress', { done: project.progress.done, total: project.progress.total }) }}</span>
              <UserAvatarGroup
                :people="project.members.map(member => member.user)"
                :max="5"
              />
            </span>
          </span>
        </NuxtLink>
      </li>
    </ul>

    <ProjectCreateModal v-model:open="creating" />
  </PageFrame>
</template>

<style scoped>
.projects-skeleton {
  block-size: 8rem;
}

.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 18rem), 1fr));
  gap: var(--wa-space-m);
  margin: 0;
  padding: 0;
}

.projects-card {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-s);
  block-size: 100%;
  padding: var(--wa-space-m);
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
  color: inherit;
  text-decoration: none;
}

.projects-card:hover {
  border-color: var(--wa-color-brand-border-loud);
}

.projects-name {
  font-weight: var(--wa-font-weight-semibold);
}

.projects-description {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.projects-footer {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-3xs);
  margin-block-start: auto;
}

.projects-footer wa-progress-bar {
  --track-height: 0.5rem;
}

.projects-progress {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
