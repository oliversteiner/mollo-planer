<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { myTasksQuery } from '~/graphql/profile'
import { TASK_STATUSES } from '~/graphql/task'
import { withoutNulls } from '~/utils/api'
import type { ShareTask } from '~/utils/share'

const { t } = useI18n()

useHead({ title: () => t('myTasks.title') })

/** "open" = everything not done yet (the default view). */
const filter = ref<'open' | 'all' | (typeof TASK_STATUSES)[number]>('open')
const filterItems = computed(() => [
  { label: t('myTasks.notDone'), value: 'open' },
  { label: t('todos.allStatuses'), value: 'all' },
  ...TASK_STATUSES.map(status => ({ label: t(`status.${status}`), value: status }))
])

const { result, loading } = useQuery(myTasksQuery, () => withoutNulls({
  status: filter.value === 'open' || filter.value === 'all' ? null : filter.value
}), { fetchPolicy: 'cache-and-network' })

/** By due date (without date last), then by title. */
const tasks = computed(() => [...(result.value?.myTasks ?? [])]
  .filter(task => filter.value !== 'open' || task.status !== 'DONE')
  .sort((a, b) => (a.dueDate ?? '9999').localeCompare(b.dueDate ?? '9999') || a.title.localeCompare(b.title)))

/** The shown (filtered) tasks in the form the share functions expect. */
const shareTasks = computed<ShareTask[]>(() => tasks.value.map(task => ({
  id: task.id,
  title: task.title,
  description: task.description,
  dueDate: task.dueDate,
  status: task.status,
  assignee: task.assignee,
  todo: { title: task.todo.title },
  milestone: { title: task.todo.milestone.title },
  project: { name: task.todo.milestone.project.name }
})))
</script>

<template>
  <PageFrame
    :title="t('myTasks.title')"
    :breadcrumbs="[{ label: t('nav.workspace'), to: '/' }, { label: t('myTasks.title') }]"
  >
    <template #actions>
      <TaskShareSection
        :tasks="shareTasks"
        :name="t('myTasks.title')"
        :heading="false"
      />
      <FormSelect
        :model-value="filter"
        :items="filterItems"
        :label="t('todos.status')"
        hide-label
        class="my-tasks-filter"
        @update:model-value="filter = $event as typeof filter"
      />
    </template>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="my-tasks-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!tasks.length"
      icon="square-check"
      :title="t('myTasks.none')"
    />
    <ul
      v-else
      class="my-tasks-list wa-list-plain"
    >
      <li
        v-for="task in tasks"
        :key="task.id"
      >
        <NuxtLink
          :to="`/projects/${task.todo.milestone.project.id}/kanban?task=${task.id}`"
          class="my-tasks-row"
        >
          <ItemIcon
            :name="task.icon"
            fallback="square-check"
            class="my-tasks-icon"
          />
          <span class="my-tasks-text">
            <span class="my-tasks-title">{{ task.title }}</span>
            <span class="my-tasks-sub">{{ task.todo.milestone.project.name }} · {{ task.todo.milestone.title }} · {{ task.todo.title }}</span>
          </span>
          <TaskLabels :labels="task.labels" />
          <span
            v-if="task.commentCount"
            class="my-tasks-comments"
          >
            <wa-icon name="comments"></wa-icon>
            {{ task.commentCount }}
          </span>
          <StatusBadge :status="task.status" />
          <DueDate
            :date="task.dueDate"
            :done="task.status === 'DONE'"
          />
        </NuxtLink>
      </li>
    </ul>
  </PageFrame>
</template>

<style scoped>
.my-tasks-filter {
  inline-size: 12rem;
}

.my-tasks-skeleton {
  block-size: 10rem;
}

.my-tasks-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.my-tasks-list > li + li {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.my-tasks-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-s) var(--wa-space-m);
  color: inherit;
  text-decoration: none;
}

.my-tasks-row:hover {
  background-color: var(--wa-color-neutral-fill-quiet);
}

.my-tasks-icon {
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.my-tasks-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.my-tasks-title {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.my-tasks-sub {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.my-tasks-comments {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
