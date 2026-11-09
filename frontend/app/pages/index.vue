<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { dashboardQuery } from '~/graphql/dashboard'

const { t } = useI18n()
const { viewer } = useViewer()

useHead({ title: () => t('dashboard.title') })

const { result, loading } = useQuery(dashboardQuery, { limit: 8 }, { fetchPolicy: 'cache-and-network' })

const dashboard = computed(() => result.value?.dashboard)
</script>

<template>
  <PageFrame :title="t('dashboard.title')">
    <p class="wa-heading-s">
      {{ t('dashboard.greeting', { name: viewer?.name ?? '' }) }}
    </p>

    <div class="dashboard-columns">
      <wa-card>
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('dashboard.myOpenTasks') }}
        </h2>

        <wa-skeleton
          v-if="loading && !dashboard"
          effect="sheen"
          class="dashboard-skeleton"
        ></wa-skeleton>
        <EmptyState
          v-else-if="!dashboard?.myOpenTasks.length"
          icon="square-check"
          :description="t('dashboard.noOpenTasks')"
          size="sm"
          bare
        />
        <ul
          v-else
          class="dashboard-list wa-list-plain"
        >
          <li
            v-for="task in dashboard.myOpenTasks"
            :key="task.id"
          >
            <NuxtLink
              :to="`/projects/${task.todo.milestone.project.id}/kanban?task=${task.id}`"
              class="dashboard-row"
            >
              <ItemIcon
                :name="task.icon"
                fallback="square-check"
                class="dashboard-icon"
              />
              <span class="dashboard-text">
                <span class="dashboard-title">{{ task.title }}</span>
                <span class="dashboard-sub">{{ task.todo.milestone.project.name }} · {{ task.todo.title }}</span>
              </span>
              <span class="dashboard-meta">
                <StatusBadge :status="task.status" />
                <DueDate :date="task.dueDate" />
              </span>
            </NuxtLink>
          </li>
        </ul>
      </wa-card>

      <wa-card>
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('dashboard.upcomingTodos') }}
        </h2>

        <wa-skeleton
          v-if="loading && !dashboard"
          effect="sheen"
          class="dashboard-skeleton"
        ></wa-skeleton>
        <EmptyState
          v-else-if="!dashboard?.upcomingTodos.length"
          icon="list-check"
          :description="t('dashboard.noUpcomingTodos')"
          size="sm"
          bare
        />
        <ul
          v-else
          class="dashboard-list wa-list-plain"
        >
          <li
            v-for="todo in dashboard.upcomingTodos"
            :key="todo.id"
          >
            <NuxtLink
              :to="`/projects/${todo.milestone.project.id}`"
              class="dashboard-row"
            >
              <ItemIcon
                :name="todo.icon"
                fallback="list-check"
                class="dashboard-icon"
              />
              <span class="dashboard-text">
                <span class="dashboard-title">{{ todo.title }}</span>
                <span class="dashboard-sub">{{ todo.milestone.project.name }} · {{ todo.milestone.title }}</span>
              </span>
              <DueDate :date="todo.dueDate" />
            </NuxtLink>
          </li>
        </ul>
      </wa-card>
    </div>

    <wa-card>
      <div
        slot="header"
        class="wa-split wa-gap-s"
      >
        <h2 class="wa-heading-m">
          {{ t('dashboard.projects') }}
        </h2>
        <wa-button
          href="/projects"
          appearance="plain"
          variant="brand"
          @click.prevent="navigateTo('/projects')"
        >
          {{ t('dashboard.allProjects') }}
          <wa-icon
            slot="end"
            name="arrow-right"
          ></wa-icon>
        </wa-button>
      </div>

      <wa-skeleton
        v-if="loading && !dashboard"
        effect="sheen"
        class="dashboard-skeleton"
      ></wa-skeleton>
      <EmptyState
        v-else-if="!dashboard?.projects.length"
        icon="folder-open"
        :description="t('projects.none')"
        size="sm"
        bare
      />
      <ul
        v-else
        class="dashboard-projects wa-list-plain"
      >
        <li
          v-for="project in dashboard.projects"
          :key="project.id"
        >
          <NuxtLink
            :to="`/projects/${project.id}`"
            class="dashboard-project"
          >
            <span class="dashboard-title">{{ project.name }}</span>
            <wa-progress-bar
              :value="project.progress.percent"
              :label="t('projects.progress', { done: project.progress.done, total: project.progress.total })"
            ></wa-progress-bar>
            <span class="dashboard-sub">
              {{ t('projects.progress', { done: project.progress.done, total: project.progress.total }) }}
            </span>
          </NuxtLink>
        </li>
      </ul>
    </wa-card>
  </PageFrame>
</template>

<style scoped>
.dashboard-columns {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 24rem), 1fr));
  gap: var(--wa-space-l);
}

.dashboard-skeleton {
  block-size: 6rem;
}

.dashboard-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
}

.dashboard-list > li + li {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.dashboard-row {
  display: flex;
  align-items: flex-start;
  gap: var(--wa-space-s);
  padding-block: var(--wa-space-xs);
  color: inherit;
  text-decoration: none;
}

.dashboard-row:hover .dashboard-title {
  color: var(--wa-color-brand-on-quiet);
}

.dashboard-icon {
  margin-block-start: var(--wa-space-3xs);
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.dashboard-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.dashboard-title {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-sub {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--wa-space-3xs);
}

.dashboard-projects {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
  gap: var(--wa-space-m);
  margin: 0;
  padding: 0;
}

.dashboard-project {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  padding: var(--wa-space-s);
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
  color: inherit;
  text-decoration: none;
}

.dashboard-project:hover {
  border-color: var(--wa-color-brand-border-loud);
}

.dashboard-project wa-progress-bar {
  --track-height: 0.5rem;
}
</style>
