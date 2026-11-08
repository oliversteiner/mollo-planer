<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { myTasksQuery } from '~/graphql/profile'
import { TASK_STATUSES } from '~/graphql/task'
import { withoutNulls } from '~/utils/api'

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
</script>

<template>
  <UDashboardPanel id="my-tasks">
    <template #header>
      <UDashboardNavbar :title="t('myTasks.title')">
        <template #right>
          <USelect
            v-model="filter"
            :items="filterItems"
            :aria-label="t('todos.status')"
            class="w-44"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <USkeleton
        v-if="loading && !result"
        class="h-40"
      />
      <UEmpty
        v-else-if="!tasks.length"
        icon="i-fadl-square-check"
        :title="t('myTasks.none')"
      />
      <ul
        v-else
        class="divide-y divide-default rounded-lg border border-default"
      >
        <li
          v-for="task in tasks"
          :key="task.id"
        >
          <NuxtLink
            :to="`/projects/${task.todo.milestone.project.id}/kanban?task=${task.id}`"
            class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-elevated/50"
          >
            <ItemIcon
              :name="task.icon"
              fallback="square-check"
              class="text-lg text-primary"
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium text-highlighted">{{ task.title }}</span>
              <span class="block truncate text-sm text-muted">{{ task.todo.milestone.project.name }} · {{ task.todo.milestone.title }} · {{ task.todo.title }}</span>
            </span>
            <span
              v-if="task.commentCount"
              class="inline-flex items-center gap-1 text-sm text-muted"
            >
              <UIcon
                name="i-fadl-comments"
                class="size-4"
              />
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
    </template>
  </UDashboardPanel>
</template>
