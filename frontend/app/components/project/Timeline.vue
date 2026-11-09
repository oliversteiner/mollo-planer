<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { DropdownMenuItem, TimelineItem } from '@nuxt/ui'
import type { ItemFormTarget } from '~/components/item/FormModal.vue'
import { projectTimelineQuery } from '~/graphql/project'
import type { ProjectTimelineQuery } from '~/graphql/generated/graphql'
import { formatDate } from '~/utils/dates'
import { isEmptyDocument } from '~/utils/rich-text'

/**
 * Project as a timeline, like the preview of the AI assistant: milestones
 * by due date, their todos (collapsible) and in them the tasks. Milestones
 * before the first open one are shown as reached. Creating, changing and
 * deleting is left to the page (events and the actions menu).
 */
const props = defineProps<{
  projectId: string
  /** Menu entries (change, delete) of a milestone or todo; none: no menu */
  actions?: (kind: 'milestone' | 'todo', item: NonNullable<ItemFormTarget['item']>) => DropdownMenuItem[]
  /** Below "Noch keine Milestones" */
  emptyHint?: string
  /** Shows "ToDo anlegen" under the todos of each milestone */
  canCreateTodo?: boolean
  /** Shows "Task anlegen" under the tasks of each todo */
  canCreateTask?: boolean
  /** A double-click on a task opens the edit dialog */
  canUpdateTask?: boolean
}>()

const emit = defineEmits<{
  /** New todo in the milestone with this id */
  createTodo: [milestoneId: string]
  /** New task in the todo with this id */
  createTask: [todoId: string]
  /** Details of the task (side panel) */
  openTask: [taskId: string]
  /** Edit dialog of the task */
  editTask: [task: TimelineTask]
}>()

const { t, localeProperties } = useI18n()
const language = computed(() => localeProperties.value.language ?? 'de-CH')

const { result, loading } = useQuery(projectTimelineQuery, () => ({ id: props.projectId }), { fetchPolicy: 'cache-and-network' })

type Milestone = NonNullable<ProjectTimelineQuery['project']>['milestones'][number]
type MilestoneItem = TimelineItem & { milestone: Milestone, number: number }
type TimelineTask = Milestone['todos'][number]['tasks'][number]

/** Click: details; double-click: edit dialog, with the right to change. */
function editTask(task: TimelineTask) {
  if (props.canUpdateTask) {
    emit('editTask', task)
  }
}

function byDueDate<T extends { id: string, dueDate?: string | null }>(items: readonly T[]): T[] {
  return [...items].sort((a, b) => (a.dueDate ?? '9999').localeCompare(b.dueDate ?? '9999') || Number(a.id) - Number(b.id))
}

const milestones = computed(() => byDueDate(result.value?.project?.milestones ?? []))

const timeline = computed<MilestoneItem[]>(() => milestones.value.map((milestone, index) => ({
  value: milestone.id,
  title: milestone.title,
  date: milestone.dueDate ? formatDate(milestone.dueDate, language.value, true) : t('project.timeline.noDate'),
  class: 'plan-in',
  milestone,
  number: index + 1
})))

/** The first milestone not done is the current one; all done: the last. */
const current = computed(() => (milestones.value.find(milestone => milestone.status !== 'DONE') ?? milestones.value.at(-1))?.id)

/** Open todos by id. */
const openTodos = ref(new Set<string>())
const allTodoIds = computed(() => milestones.value.flatMap(milestone => milestone.todos.map(todo => todo.id)))
const allOpen = computed(() => allTodoIds.value.length > 0 && allTodoIds.value.every(id => openTodos.value.has(id)))

function setOpen(id: string, open: boolean) {
  const next = new Set(openTodos.value)

  if (open) {
    next.add(id)
  } else {
    next.delete(id)
  }

  openTodos.value = next
}

/** Only the milestones: without descriptions and todos, as an outline. */
const milestonesOnly = defineModel<boolean>('milestonesOnly', { default: false })

function menu(kind: 'milestone' | 'todo', item: NonNullable<ItemFormTarget['item']>): DropdownMenuItem[] {
  return props.actions?.(kind, item) ?? []
}

function toggleAll() {
  openTodos.value = allOpen.value ? new Set() : new Set(allTodoIds.value)
}
</script>

<template>
  <div class="space-y-4">
    <USkeleton
      v-if="loading && !result"
      class="h-40"
    />
    <UEmpty
      v-else-if="!milestones.length"
      icon="i-fadl-flag-checkered"
      :title="t('project.noMilestones')"
      :description="emptyHint"
    />

    <template v-else>
      <div class="flex flex-wrap justify-end gap-2">
        <UButton
          variant="ghost"
          color="neutral"
          size="sm"
          :icon="milestonesOnly ? 'i-fadl-list-tree' : 'i-fadl-flag-checkered'"
          :label="milestonesOnly ? t('project.timeline.showAll') : t('project.timeline.milestonesOnly')"
          :aria-pressed="milestonesOnly"
          @click="milestonesOnly = !milestonesOnly"
        />
        <UButton
          v-if="allTodoIds.length && !milestonesOnly"
          variant="ghost"
          color="neutral"
          size="sm"
          :icon="allOpen ? 'i-fadl-chevrons-up' : 'i-fadl-chevrons-down'"
          :label="allOpen ? t('project.timeline.collapseAll') : t('project.timeline.expandAll')"
          @click="toggleAll"
        />
      </div>

      <UTimeline
        :items="timeline"
        :default-value="current"
        color="primary"
        size="sm"
        class="plan-timeline"
        :ui="{ wrapper: 'w-full min-w-0', description: 'w-full' }"
      >
        <template #indicator="{ item }">
          <ItemIcon
            :name="item.milestone.icon"
            fallback="flag-checkered"
          />
        </template>

        <template #title="{ item }">
          <span class="inline-flex flex-wrap items-center gap-2">
            <MilestoneNumber :number="item.number" />
            {{ item.title }}
            <StatusBadge :status="item.milestone.status" />
            <UDropdownMenu
              v-if="menu('milestone', item.milestone).length"
              :items="menu('milestone', item.milestone)"
            >
              <UButton
                icon="i-fadl-ellipsis"
                color="neutral"
                variant="ghost"
                size="xs"
                :aria-label="t('project.timeline.milestoneActions', { title: item.milestone.title })"
              />
            </UDropdownMenu>
          </span>
        </template>

        <template #description="{ item }">
          <div
            class="space-y-3"
            :class="milestonesOnly ? 'pb-4' : 'pb-6'"
          >
            <RichTextView
              v-if="!milestonesOnly && !isEmptyDocument(item.milestone.description)"
              :content="item.milestone.description"
              class="text-sm"
            />

            <div class="flex max-w-md items-center gap-3">
              <UProgress
                :model-value="item.milestone.progress.percent"
                size="sm"
                class="flex-1"
              />
              <span class="text-xs text-muted">{{ t('projects.progress', { done: item.milestone.progress.done, total: item.milestone.progress.total }) }}</span>
            </div>

            <template v-if="!milestonesOnly">
              <ul
                v-if="item.milestone.todos.length"
                class="space-y-2"
              >
                <li
                  v-for="todo in byDueDate(item.milestone.todos)"
                  :key="todo.id"
                  class="rounded-lg border border-default"
                >
                  <div class="flex items-center gap-1 pe-1">
                    <UButton
                      variant="ghost"
                      color="neutral"
                      class="min-w-0 flex-1 justify-start gap-2 text-start"
                      :aria-label="t('project.timeline.toggleTodo', { title: todo.title })"
                      :aria-expanded="openTodos.has(todo.id)"
                      @click="setOpen(todo.id, !openTodos.has(todo.id))"
                    >
                      <UIcon
                        :name="openTodos.has(todo.id) ? 'i-fadl-chevron-down' : 'i-fadl-chevron-right'"
                        class="size-4 shrink-0 text-muted"
                      />
                      <ItemIcon
                        :name="todo.icon"
                        fallback="list-check"
                        class="shrink-0 text-primary"
                      />
                      <span class="min-w-0 flex-1 truncate font-medium text-highlighted">{{ todo.title }}</span>
                      <span class="hidden text-xs text-muted sm:inline">{{ t('project.taskCount', { done: todo.progress.done, total: todo.progress.total }) }}</span>
                      <StatusBadge :status="todo.status" />
                      <DueDate
                        :date="todo.dueDate"
                        :done="todo.status === 'DONE'"
                      />
                    </UButton>
                    <UDropdownMenu
                      v-if="menu('todo', todo).length"
                      :items="menu('todo', todo)"
                    >
                      <UButton
                        icon="i-fadl-ellipsis"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('project.timeline.todoActions', { title: todo.title })"
                      />
                    </UDropdownMenu>
                  </div>

                  <UCollapsible :open="openTodos.has(todo.id)">
                    <template #content>
                      <div class="space-y-3 border-t border-default px-3 py-3">
                        <RichTextView
                          v-if="!isEmptyDocument(todo.description)"
                          :content="todo.description"
                          class="text-sm"
                        />

                        <ul
                          v-if="todo.tasks.length"
                          class="divide-y divide-default rounded-md bg-elevated/50"
                        >
                          <li
                            v-for="task in byDueDate(todo.tasks)"
                            :key="task.id"
                            class="flex cursor-pointer flex-col gap-2 px-3 py-2 transition-colors first:rounded-t-md last:rounded-b-md hover:bg-accented/70 sm:flex-row sm:items-start"
                            @click="emit('openTask', task.id)"
                            @dblclick="editTask(task)"
                          >
                            <ItemIcon
                              :name="task.icon"
                              fallback="square-check"
                              class="mt-0.5 hidden shrink-0 text-muted sm:block"
                            />
                            <div class="min-w-0 flex-1 space-y-1">
                              <button
                                type="button"
                                class="block text-start text-sm font-medium focus-visible:underline focus-visible:outline-none"
                                :class="task.status === 'DONE' ? 'text-muted line-through' : 'text-highlighted'"
                                :aria-label="t('project.timeline.openTask', { title: task.title })"
                                @click.stop="emit('openTask', task.id)"
                                @dblclick.stop="editTask(task)"
                              >
                                {{ task.title }}
                              </button>
                              <RichTextView
                                v-if="!isEmptyDocument(task.description)"
                                :content="task.description"
                                class="text-sm text-muted"
                              />
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-3">
                              <StatusBadge :status="task.status" />
                              <DueDate
                                :date="task.dueDate"
                                :done="task.status === 'DONE'"
                              />
                              <span
                                v-if="task.commentCount"
                                class="inline-flex items-center gap-1 text-xs text-muted"
                              >
                                <UIcon
                                  name="i-fadl-comments"
                                  class="size-4"
                                />
                                {{ task.commentCount }}
                                <span class="sr-only">{{ t('kanban.comments') }}</span>
                              </span>
                              <UUser
                                v-if="task.assignee"
                                :name="task.assignee.name"
                                :avatar="{ src: task.assignee.avatarUrl ?? undefined, alt: task.assignee.name, icon: 'i-fadl-user' }"
                                size="xs"
                              />
                              <span
                                v-else
                                class="text-xs text-dimmed"
                              >{{ t('project.timeline.unassigned') }}</span>
                            </div>
                          </li>
                        </ul>
                        <p
                          v-else
                          class="text-sm text-muted"
                        >
                          {{ t('project.timeline.noTasks') }}
                        </p>

                        <UButton
                          v-if="canCreateTask"
                          icon="i-fadl-plus"
                          :label="t('task.create')"
                          :aria-label="t('project.timeline.createTask', { title: todo.title })"
                          color="neutral"
                          variant="ghost"
                          size="xs"
                          @click="emit('createTask', todo.id)"
                        />
                      </div>
                    </template>
                  </UCollapsible>
                </li>
              </ul>
              <p
                v-else
                class="text-sm text-muted"
              >
                {{ t('project.noTodos') }}
              </p>

              <UButton
                v-if="canCreateTodo"
                icon="i-fadl-plus"
                :label="t('item.todo.create')"
                :aria-label="t('project.timeline.createTodo', { title: item.milestone.title })"
                color="neutral"
                variant="ghost"
                size="xs"
                @click="emit('createTodo', item.milestone.id)"
              />
            </template>
          </div>
        </template>
      </UTimeline>
    </template>
  </div>
</template>
