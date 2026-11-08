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
  <UDashboardPanel id="dashboard">
    <template #header>
      <UDashboardNavbar :title="t('dashboard.title')" />
    </template>

    <template #body>
      <p class="text-lg text-highlighted">
        {{ t('dashboard.greeting', { name: viewer?.name ?? '' }) }}
      </p>

      <div class="grid gap-6 lg:grid-cols-2">
        <UCard>
          <template #header>
            <h2 class="font-semibold text-highlighted">
              {{ t('dashboard.myOpenTasks') }}
            </h2>
          </template>

          <USkeleton
            v-if="loading && !dashboard"
            class="h-24"
          />
          <UEmpty
            v-else-if="!dashboard?.myOpenTasks.length"
            icon="i-fadl-square-check"
            :description="t('dashboard.noOpenTasks')"
            size="sm"
          />
          <ul
            v-else
            class="divide-y divide-default"
          >
            <li
              v-for="task in dashboard.myOpenTasks"
              :key="task.id"
            >
              <NuxtLink
                :to="`/projects/${task.todo.milestone.project.id}/kanban?task=${task.id}`"
                class="flex items-start gap-3 py-2 hover:text-primary"
              >
                <ItemIcon
                  :name="task.icon"
                  fallback="square-check"
                  class="mt-0.5 text-lg text-primary"
                />
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-medium">{{ task.title }}</span>
                  <span class="block truncate text-sm text-muted">{{ task.todo.milestone.project.name }} · {{ task.todo.title }}</span>
                </span>
                <span class="flex flex-col items-end gap-1">
                  <StatusBadge :status="task.status" />
                  <DueDate :date="task.dueDate" />
                </span>
              </NuxtLink>
            </li>
          </ul>
        </UCard>

        <UCard>
          <template #header>
            <h2 class="font-semibold text-highlighted">
              {{ t('dashboard.upcomingTodos') }}
            </h2>
          </template>

          <USkeleton
            v-if="loading && !dashboard"
            class="h-24"
          />
          <UEmpty
            v-else-if="!dashboard?.upcomingTodos.length"
            icon="i-fadl-list-check"
            :description="t('dashboard.noUpcomingTodos')"
            size="sm"
          />
          <ul
            v-else
            class="divide-y divide-default"
          >
            <li
              v-for="todo in dashboard.upcomingTodos"
              :key="todo.id"
            >
              <NuxtLink
                :to="`/projects/${todo.milestone.project.id}`"
                class="flex items-start gap-3 py-2 hover:text-primary"
              >
                <ItemIcon
                  :name="todo.icon"
                  fallback="list-check"
                  class="mt-0.5 text-lg text-primary"
                />
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-medium">{{ todo.title }}</span>
                  <span class="block truncate text-sm text-muted">{{ todo.milestone.project.name }} · {{ todo.milestone.title }}</span>
                </span>
                <DueDate :date="todo.dueDate" />
              </NuxtLink>
            </li>
          </ul>
        </UCard>
      </div>

      <UCard>
        <template #header>
          <div class="flex items-center justify-between gap-2">
            <h2 class="font-semibold text-highlighted">
              {{ t('dashboard.projects') }}
            </h2>
            <UButton
              to="/projects"
              :label="t('dashboard.allProjects')"
              variant="link"
              trailing-icon="i-fadl-arrow-right"
            />
          </div>
        </template>

        <USkeleton
          v-if="loading && !dashboard"
          class="h-16"
        />
        <UEmpty
          v-else-if="!dashboard?.projects.length"
          icon="i-fadl-folder-open"
          :description="t('projects.none')"
          size="sm"
        />
        <ul
          v-else
          class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
          <li
            v-for="project in dashboard.projects"
            :key="project.id"
          >
            <NuxtLink
              :to="`/projects/${project.id}`"
              class="block rounded-md border border-default p-3 hover:border-primary"
            >
              <span class="block truncate font-medium text-highlighted">{{ project.name }}</span>
              <UProgress
                :model-value="project.progress.percent"
                size="sm"
                class="mt-2"
              />
              <span class="mt-1 block text-sm text-muted">
                {{ t('projects.progress', { done: project.progress.done, total: project.progress.total }) }}
              </span>
            </NuxtLink>
          </li>
        </ul>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
