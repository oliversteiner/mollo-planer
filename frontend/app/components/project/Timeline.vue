<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { VueDraggable, type SortableEvent } from 'vue-draggable-plus'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { TimelineEntry } from '~/components/StepTimeline.vue'
import type { ItemFormTarget } from '~/components/item/FormModal.vue'
import { projectTimelineQuery } from '~/graphql/project'
import { moveTaskToTodoMutation } from '~/graphql/task'
import type { ProjectTimelineQuery } from '~/graphql/generated/graphql'
import { formatDate } from '~/utils/dates'
import { isEmptyDocument } from '~/utils/rich-text'

/**
 * Project as a timeline, like the preview of the AI assistant: milestones
 * by due date, their todos (collapsible) and in them the tasks. Milestones
 * before the first open one are shown as reached. Creating, changing and
 * deleting is left to the page (events and the actions menu); tasks are
 * moved to another todo here, by drag and drop.
 */
const props = defineProps<{
  projectId: string
  /** Menu entries (change, delete) of a milestone or todo; none: no menu */
  actions?: (kind: 'milestone' | 'todo', item: NonNullable<ItemFormTarget['item']>) => ActionMenuItem[]
  /** Below "Noch keine Milestones" */
  emptyHint?: string
  /** Shows "ToDo anlegen" under the todos of each milestone */
  canCreateTodo?: boolean
  /** Shows "Task anlegen" under the tasks of each todo */
  canCreateTask?: boolean
  /** A double-click on a task opens the edit dialog */
  canUpdateTask?: boolean
  /** Tasks can be dragged into another todo */
  canMoveTask?: boolean
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

const { result, loading, refetch } = useQuery(projectTimelineQuery, () => ({ id: props.projectId }), { fetchPolicy: 'cache-and-network' })

type Milestone = NonNullable<ProjectTimelineQuery['project']>['milestones'][number]
type MilestoneItem = TimelineEntry & { milestone: Milestone, number: number }
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

/** The first milestone not done is the current one; all done: the last. */
const current = computed(() => (milestones.value.find(milestone => milestone.status !== 'DONE') ?? milestones.value.at(-1))?.id)

const timeline = computed<MilestoneItem[]>(() => {
  const currentIndex = milestones.value.findIndex(milestone => milestone.id === current.value)

  return milestones.value.map((milestone, index) => ({
    value: milestone.id,
    title: milestone.title,
    date: milestone.dueDate ? formatDate(milestone.dueDate, language.value, true) : t('project.timeline.noDate'),
    // Milestones before the current one are reached.
    completed: index < currentIndex,
    class: 'plan-in',
    milestone,
    number: index + 1
  }))
})

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

/**
 * Tasks per todo id, a local copy so a drop shows at once. Within a todo
 * the order is by due date, so dragging only moves between todos; the
 * server's answer replaces the copy after every move.
 */
const taskLists = ref<Record<string, TimelineTask[]>>({})
const dragging = ref(false)

function fillTaskLists() {
  taskLists.value = Object.fromEntries(milestones.value.flatMap(milestone => milestone.todos.map(todo => [todo.id, byDueDate(todo.tasks)])))
}

watch(milestones, fillTaskLists, { immediate: true })

const { mutate: moveTaskToTodo } = useMutation(moveTaskToTodoMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

async function onDrop(event: SortableEvent) {
  dragging.value = false

  const taskId = (event.item as HTMLElement).dataset.id
  const todoId = (event.to as HTMLElement).dataset.todoId

  if (!taskId || !todoId || event.from === event.to) {
    return
  }

  try {
    await moveTaskToTodo({ id: taskId, todoId })
  } finally {
    await refetch()
    fillTaskLists()
  }
}

/** While dragging, a closed todo opens when the task is held over it. */
function openWhileDragging(todoId: string) {
  if (dragging.value && !openTodos.value.has(todoId)) {
    setOpen(todoId, true)
  }
}

/** Only the milestones: without descriptions and todos, as an outline. */
const milestonesOnly = defineModel<boolean>('milestonesOnly', { default: false })

function menu(kind: 'milestone' | 'todo', item: NonNullable<ItemFormTarget['item']>): ActionMenuItem[] {
  return props.actions?.(kind, item) ?? []
}

function toggleAll() {
  openTodos.value = allOpen.value ? new Set() : new Set(allTodoIds.value)
}
</script>

<template>
  <div class="wa-stack wa-gap-m">
    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="timeline-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!milestones.length"
      icon="flag-checkered"
      :title="t('project.noMilestones')"
      :description="emptyHint"
    />

    <template v-else>
      <div class="wa-cluster wa-gap-s wa-justify-content-end">
        <wa-button
          appearance="plain"
          variant="neutral"
          size="s"
          :aria-pressed="milestonesOnly"
          @click="milestonesOnly = !milestonesOnly"
        >
          <wa-icon
            slot="start"
            :name="milestonesOnly ? 'list-tree' : 'flag-checkered'"
          ></wa-icon>
          {{ milestonesOnly ? t('project.timeline.showAll') : t('project.timeline.milestonesOnly') }}
        </wa-button>
        <wa-button
          v-if="allTodoIds.length && !milestonesOnly"
          appearance="plain"
          variant="neutral"
          size="s"
          @click="toggleAll"
        >
          <wa-icon
            slot="start"
            :name="allOpen ? 'chevrons-up' : 'chevrons-down'"
          ></wa-icon>
          {{ allOpen ? t('project.timeline.collapseAll') : t('project.timeline.expandAll') }}
        </wa-button>
      </div>

      <StepTimeline
        :items="timeline"
        :active="current"
        :label="t('project.timeline.label')"
        class="plan-timeline"
      >
        <template #indicator="{ item }">
          <ItemIcon
            :name="item.milestone.icon"
            fallback="flag-checkered"
          />
        </template>

        <template #title="{ item }">
          <span class="timeline-title-row">
            <MilestoneNumber :number="item.number" />
            {{ item.title }}
            <StatusBadge :status="item.milestone.status" />
            <ActionMenu
              v-if="menu('milestone', item.milestone).length"
              :items="menu('milestone', item.milestone)"
              :label="t('project.timeline.milestoneActions', { title: item.milestone.title })"
            />
          </span>
        </template>

        <template #description="{ item }">
          <div
            class="wa-stack wa-gap-s"
            :class="milestonesOnly ? 'milestone-end-short' : 'milestone-end'"
          >
            <RichTextView
              v-if="!milestonesOnly && !isEmptyDocument(item.milestone.description)"
              :content="item.milestone.description"
              class="wa-body-s"
            />

            <div class="milestone-progress">
              <wa-progress-bar
                :value="item.milestone.progress.percent"
                :label="t('projects.progress', { done: item.milestone.progress.done, total: item.milestone.progress.total })"
              ></wa-progress-bar>
              <span class="milestone-progress-text">{{ t('projects.progress', { done: item.milestone.progress.done, total: item.milestone.progress.total }) }}</span>
            </div>

            <template v-if="!milestonesOnly">
              <ul
                v-if="item.milestone.todos.length"
                class="todo-list wa-list-plain"
              >
                <li
                  v-for="todo in byDueDate(item.milestone.todos)"
                  :key="todo.id"
                  class="todo"
                >
                  <div
                    class="todo-header"
                    @dragover="openWhileDragging(todo.id)"
                  >
                    <button
                      type="button"
                      class="todo-toggle plain-button"
                      :aria-label="t('project.timeline.toggleTodo', { title: todo.title })"
                      :aria-expanded="openTodos.has(todo.id)"
                      @click="setOpen(todo.id, !openTodos.has(todo.id))"
                    >
                      <wa-icon
                        :name="openTodos.has(todo.id) ? 'chevron-down' : 'chevron-right'"
                        class="todo-chevron"
                      ></wa-icon>
                      <ItemIcon
                        :name="todo.icon"
                        fallback="list-check"
                        class="todo-icon"
                      />
                      <span class="todo-title">{{ todo.title }}</span>
                      <span class="todo-count">{{ t('project.taskCount', { done: todo.progress.done, total: todo.progress.total }) }}</span>
                      <StatusBadge :status="todo.status" />
                      <DueDate
                        :date="todo.dueDate"
                        :done="todo.status === 'DONE'"
                      />
                    </button>
                    <ActionMenu
                      v-if="menu('todo', todo).length"
                      :items="menu('todo', todo)"
                      :label="t('project.timeline.todoActions', { title: todo.title })"
                    />
                  </div>

                  <div
                    v-if="openTodos.has(todo.id)"
                    class="todo-content wa-stack wa-gap-s"
                  >
                    <RichTextView
                      v-if="!isEmptyDocument(todo.description)"
                      :content="todo.description"
                      class="wa-body-s"
                    />

                    <div class="task-area">
                      <VueDraggable
                        v-model="taskLists[todo.id]!"
                        tag="ul"
                        group="timeline-tasks"
                        :sort="false"
                        :disabled="!canMoveTask"
                        :animation="150"
                        ghost-class="task-ghost"
                        :data-todo-id="todo.id"
                        class="task-list wa-list-plain"
                        :class="{ 'task-list-empty': !taskLists[todo.id]?.length, 'task-list-droppable': !taskLists[todo.id]?.length && canMoveTask }"
                        @start="dragging = true"
                        @end="onDrop"
                      >
                        <li
                          v-for="task in taskLists[todo.id]"
                          :key="task.id"
                          :data-id="task.id"
                          class="task"
                          @click="emit('openTask', task.id)"
                          @dblclick="editTask(task)"
                        >
                          <ItemIcon
                            :name="task.icon"
                            fallback="square-check"
                            class="task-icon"
                          />
                          <div class="task-text">
                            <button
                              type="button"
                              class="task-title plain-button"
                              :data-done="task.status === 'DONE' ? '' : undefined"
                              :aria-label="t('project.timeline.openTask', { title: task.title })"
                              @click.stop="emit('openTask', task.id)"
                              @dblclick.stop="editTask(task)"
                            >
                              {{ task.title }}
                            </button>
                            <RichTextView
                              v-if="!isEmptyDocument(task.description)"
                              :content="task.description"
                              class="wa-body-s task-description"
                            />
                          </div>
                          <div class="task-meta">
                            <StatusBadge :status="task.status" />
                            <DueDate
                              :date="task.dueDate"
                              :done="task.status === 'DONE'"
                            />
                            <span
                              v-if="task.commentCount"
                              class="task-comments"
                            >
                              <wa-icon name="comments"></wa-icon>
                              {{ task.commentCount }}
                              <span class="wa-visually-hidden">{{ t('kanban.comments') }}</span>
                            </span>
                            <UserLine
                              v-if="task.assignee"
                              :name="task.assignee.name"
                              :src="task.assignee.avatarUrl"
                              size="xs"
                            />
                            <span
                              v-else
                              class="task-unassigned"
                            >{{ t('project.timeline.unassigned') }}</span>
                          </div>
                        </li>
                      </VueDraggable>
                      <p
                        v-if="!taskLists[todo.id]?.length && !dragging"
                        class="task-none"
                        :class="{ 'task-none-overlay': canMoveTask }"
                      >
                        {{ t('project.timeline.noTasks') }}
                      </p>
                    </div>

                    <div v-if="canCreateTask">
                      <wa-button
                        appearance="plain"
                        variant="neutral"
                        size="s"
                        :aria-label="t('project.timeline.createTask', { title: todo.title })"
                        @click="emit('createTask', todo.id)"
                      >
                        <wa-icon
                          slot="start"
                          name="plus"
                        ></wa-icon>
                        {{ t('task.create') }}
                      </wa-button>
                    </div>
                  </div>
                </li>
              </ul>
              <p
                v-else
                class="task-none"
              >
                {{ t('project.noTodos') }}
              </p>

              <div v-if="canCreateTodo">
                <wa-button
                  appearance="plain"
                  variant="neutral"
                  size="s"
                  :aria-label="t('project.timeline.createTodo', { title: item.milestone.title })"
                  @click="emit('createTodo', item.milestone.id)"
                >
                  <wa-icon
                    slot="start"
                    name="plus"
                  ></wa-icon>
                  {{ t('item.todo.create') }}
                </wa-button>
              </div>
            </template>
          </div>
        </template>
      </StepTimeline>
    </template>
  </div>
</template>

<style scoped>
.timeline-skeleton {
  block-size: 10rem;
}

.timeline-title-row {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
}

.milestone-end {
  padding-block-end: var(--wa-space-l);
}

.milestone-end-short {
  padding-block-end: var(--wa-space-m);
}

.milestone-progress {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  max-inline-size: 28rem;
}

.milestone-progress wa-progress-bar {
  flex: 1;
  --track-height: 0.5rem;
}

.milestone-progress-text {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  white-space: nowrap;
}

.todo-list {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  margin: 0;
  padding: 0;
}

.todo {
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.todo-header {
  display: flex;
  align-items: center;
  gap: var(--wa-space-3xs);
  padding-inline-end: var(--wa-space-3xs);
}

.todo-toggle {
  display: flex;
  flex: 1;
  align-items: center;
  gap: var(--wa-space-xs);
  min-inline-size: 0;
  padding: var(--wa-space-xs) var(--wa-space-s);
  border: none;
  border-radius: var(--wa-border-radius-l);
  background: none;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.todo-toggle:hover {
  background-color: var(--wa-color-neutral-fill-quiet);
}

.todo-toggle:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.todo-chevron {
  color: var(--wa-color-text-quiet);
}

.todo-icon {
  color: var(--wa-color-brand-on-quiet);
}

.todo-title {
  flex: 1;
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.todo-count {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

@media (max-width: 40em) {
  .todo-count {
    display: none;
  }
}

.todo-content {
  padding: var(--wa-space-s);
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.task-area {
  position: relative;
}

.task-list {
  margin: 0;
  padding: 0;
  border-radius: var(--wa-border-radius-m);
  background-color: var(--wa-color-surface-raised);
}

.task-list-empty {
  background: none;
}

.task-list-droppable {
  min-block-size: 2.5rem;
  border: var(--wa-border-width-s) dashed var(--wa-color-surface-border);
}

.task {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-xs);
  padding: var(--wa-space-xs) var(--wa-space-s);
  cursor: pointer;
}

.task + .task {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.task:hover {
  background-color: var(--wa-color-neutral-fill-quiet);
}

.task-ghost {
  opacity: 0.4;
}

.task-icon {
  margin-block-start: var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
}

.task-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: var(--wa-space-3xs);
  min-inline-size: 12rem;
}

.task-title {
  align-self: flex-start;
  padding: 0;
  border: none;
  background: none;
  color: inherit;
  font: inherit;
  font-size: var(--wa-font-size-s);
  font-weight: var(--wa-font-weight-semibold);
  text-align: start;
  cursor: pointer;
}

.task-title:focus-visible {
  text-decoration: underline;
  outline: none;
}

.task-title[data-done] {
  color: var(--wa-color-text-quiet);
  text-decoration: line-through;
}

.task-description {
  color: var(--wa-color-text-quiet);
}

.task-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.task-comments {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.task-unassigned {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.task-none {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.task-none-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  padding-inline: var(--wa-space-s);
  pointer-events: none;
}
</style>
