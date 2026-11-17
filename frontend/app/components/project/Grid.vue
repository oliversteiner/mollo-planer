<script setup lang="ts">
import type { ProjectsQuery } from '~/graphql/generated/graphql'
import { documentText } from '~/utils/rich-text'

/**
 * The projects as cards (name, role, description, progress, members): the
 * list of the projects and its section „Archiv“.
 */
defineProps<{
  projects: ProjectsQuery['projects']
}>()

const { t } = useI18n()
const roleName = useRoleName()
</script>

<template>
  <ul class="projects-grid wa-list-plain">
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
            {{ roleName(project.viewerRole) }}
          </wa-badge>
        </span>
        <span
          v-if="documentText(project.description)"
          class="projects-description"
        >{{ documentText(project.description) }}</span>
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
</template>

<style scoped>
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
