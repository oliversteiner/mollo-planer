<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { VueDraggable, type SortableEvent } from 'vue-draggable-plus'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { TimelineEntry } from '~/components/StepTimeline.vue'
import type { ItemFormTarget } from '~/components/item/FormModal.vue'
import { projectTimelineQuery } from '~/graphql/project'
import { deleteTaskMutation, moveTaskInTodoMutation, moveTaskToTodoMutation } from '~/graphql/task'
import type { ProjectTimelineQuery } from '~/graphql/generated/graphql'
import { formatDate } from '~/utils/dates'
import { isEmptyDocument } from '~/utils/rich-text'

/**
 * Project as a timeline, like the preview of the AI assistant: milestones
 * in their order from the backend (position), their todos (collapsible)
 * and in them the tasks in their order (position). Milestones
 * before the first open one are shown as reached. Creating, changing and
 * deleting is left to the page (events and the actions menu); tasks are
 * moved to another todo here, by drag and drop.
 */
export interface MilestonePlace {
  index: number
  count: number
}

const props = defineProps<{
  projectId: string
  /**
   * Menu entries (change, move, delete) of a milestone or todo; none: no
   * menu. For a milestone, `place` is its index among the milestones.
   */
  actions?: (kind: 'milestone' | 'todo', item: NonNullable<ItemFormTarget['item']>, place?: MilestonePlace) => ActionMenuItem[]
  /** Below "Noch keine Milestones" */
  emptyHint?: string
  /** Shows "ToDo anlegen" under the todos of each milestone */
  canCreateTodo?: boolean
  /** Shows "Task anlegen" under the tasks of each todo */
  canCreateTask?: boolean
  /** Tasks can be dragged into another todo */
  canMoveTask?: boolean
  /** Tasks can be moved up and down inside their todo (task.move) */
  canReorderTask?: boolean
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

/** Click: details; double-click: edit dialog, with the right to change this task (task.updateOwn holds per task). */
function editTask(task: TimelineTask) {
  if (task.viewerCanUpdate) {
    emit('editTask', task)
  }
}

const milestones = computed(() => result.value?.project?.milestones ?? [])

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
 * the order is the server's (position, changed by "Move up/down"), so
 * dragging only moves between todos; the server's answer replaces the copy
 * after every move.
 */
const taskLists = ref<Record<string, TimelineTask[]>>({})
const dragging = ref(false)

function fillTaskLists() {
  taskLists.value = Object.fromEntries(milestones.value.flatMap(milestone => milestone.todos.map(todo => [todo.id, [...todo.tasks]])))
}

watch(milestones, fillTaskLists, { immediate: true })

const { mutate: moveTaskToTodo } = useProjectMutation(moveTaskToTodoMutation)
const { mutate: moveTaskInTodo } = useProjectMutation(moveTaskInTodoMutation)

const { mutate: deleteTask } = useProjectMutation(deleteTaskMutation)
const deletion = useDelete<string>(id => deleteTask({ id }))

/** Menu of a task like the Kanban card: edit (if allowed), then its place in the todo, then delete (if allowed). */
function taskMenu(task: TimelineTask, todoId: string): ActionMenuItem[] {
  const list = taskLists.value[todoId] ?? []
  const index = list.findIndex(entry => entry.id === task.id)
  const items: ActionMenuItem[] = []

  if (task.viewerCanUpdate) {
    items.push({ label: t('common.edit'), icon: 'pen', onSelect: () => emit('editTask', task) })
  }
  if (props.canReorderTask && index > 0) {
    items.push({ label: t('kanban.moveUp'), icon: 'arrow-up', onSelect: () => void moveTaskInTodo({ id: task.id, position: index - 1 }) })
  }
  if (props.canReorderTask && index >= 0 && index < list.length - 1) {
    items.push({ label: t('kanban.moveDown'), icon: 'arrow-down', onSelect: () => void moveTaskInTodo({ id: task.id, position: index + 1 }) })
  }
  if (task.viewerCanDelete) {
    items.push({ label: t('common.delete'), icon: 'trash', color: 'error', onSelect: () => void deletion.remove(task.id) })
  }

  return items
}

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

/**
 * Calm view (docs/zen-modus.md §1, §5): the current milestone shows its todos open, the other milestones stay closed
 * until "Aufklappen". This is state of the data; what is hidden is decided by zen.css.
 */
const { calm } = useViewMode()
const zenOpened = ref(new Set<string>())

function toggleZenOpen(id: string): void {
  const next = new Set(zenOpened.value)

  if (!next.delete(id)) {
    next.add(id)
  }

  zenOpened.value = next
}

/** Full view: every milestone shows its todos, "Zuklappen" hides them (until "Aufklappen" again). */
const collapsedMilestones = ref(new Set<string>())

function isMilestoneOpen(id: string): boolean {
  return calm.value ? id === current.value || zenOpened.value.has(id) : !collapsedMilestones.value.has(id)
}

function toggleMilestone(id: string): void {
  if (calm.value) {
    toggleZenOpen(id)
    return
  }

  const next = new Set(collapsedMilestones.value)

  if (!next.delete(id)) {
    next.add(id)
  }

  collapsedMilestones.value = next
}

watch([calm, current, milestones], () => {
  if (calm.value) {
    const todos = milestones.value.find(milestone => milestone.id === current.value)?.todos ?? []

    openTodos.value = new Set([...openTodos.value, ...todos.map(todo => todo.id)])
  }
}, { immediate: true })

/** Only the milestones: without descriptions and todos, as an outline. */
const milestonesOnly = defineModel<boolean>('milestonesOnly', { default: false })

/** "Nur Milestones anzeigen" collapses all todos, so they are closed when the full view comes back. */
function toggleMilestonesOnly() {
  if (!milestonesOnly.value) {
    openTodos.value = new Set()
  }

  milestonesOnly.value = !milestonesOnly.value
}

function menu(kind: 'milestone' | 'todo', item: NonNullable<ItemFormTarget['item']>): ActionMenuItem[] {
  const place = kind === 'milestone'
    ? { index: milestones.value.findIndex(milestone => milestone.id === item.id), count: milestones.value.length }
    : undefined

  return props.actions?.(kind, item, place) ?? []
}

/** "Alle ToDos zeigen" opens every todo and every milestone (also those closed in the full or calm view); the other way closes the todos. */
function toggleAll() {
  if (allOpen.value) {
    openTodos.value = new Set()
    return
  }

  openTodos.value = new Set(allTodoIds.value)
  collapsedMilestones.value = new Set()
  zenOpened.value = new Set(milestones.value.map(milestone => milestone.id))
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
      <div
        class="wa-cluster wa-gap-s wa-justify-content-end"
        data-zen="hide"
      >
        <wa-button
          appearance="plain"
          variant="neutral"
          size="s"
          :aria-pressed="milestonesOnly"
          @click="toggleMilestonesOnly"
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
          <span
            :id="`milestone-${item.milestone.id}`"
            class="timeline-title-row"
          >
            <MilestoneNumber :number="item.number" />
            {{ item.title }}
            <span
              v-if="item.milestone.release"
              class="timeline-release wa-caption-s"
              data-zen="hide"
            >
              {{ item.milestone.release.version }} · {{ t(`release.${item.milestone.release.status.toLowerCase()}`) }}{{ item.milestone.release.date ? ` · ${formatDate(item.milestone.release.date, language, true)}` : '' }}
            </span>
            <StatusBadge :status="item.milestone.status" />
            <ActionMenu
              v-if="menu('milestone', item.milestone).length"
              data-zen="hide"
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

            <!--
              Calm view: only the current milestone shows its todos; the others are closed, "Aufklappen" opens one.
              The marking is data (is it the current one?), the hiding is zen.css.
            -->
            <wa-button
              v-if="!milestonesOnly && (!calm || item.milestone.id !== current)"
              appearance="plain"
              variant="neutral"
              size="s"
              :aria-expanded="isMilestoneOpen(item.milestone.id)"
              @click="toggleMilestone(item.milestone.id)"
            >
              <wa-icon
                slot="start"
                :name="isMilestoneOpen(item.milestone.id) ? 'chevron-up' : 'chevron-down'"
              ></wa-icon>
              {{ isMilestoneOpen(item.milestone.id) ? t('zen.collapseMilestone') : t('zen.expandMilestone') }}
            </wa-button>
            <div
              v-if="!milestonesOnly && isMilestoneOpen(item.milestone.id)"
              class="wa-stack wa-gap-s"
            >
              <ul
                v-if="item.milestone.todos.length"
                class="todo-list wa-list-plain"
              >
                <li
                  v-for="todo in item.milestone.todos"
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
                      <span
                        class="todo-count"
                        data-zen="hide"
                      >{{ t('project.taskCount', { done: todo.progress.done, total: todo.progress.total }) }}</span>
                      <StatusBadge :status="todo.status" />
                      <DueDate
                        :date="todo.dueDate"
                        :done="todo.status === 'DONE'"
                        data-zen="hide"
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
                        <ProjectTimelineTask
                          v-for="task in taskLists[todo.id]"
                          :key="task.id"
                          :task="task"
                          :menu="taskMenu(task, todo.id)"
                          @open="emit('openTask', task.id)"
                          @edit="editTask(task)"
                        />
                      </VueDraggable>
                      <p
                        v-if="!taskLists[todo.id]?.length && !dragging"
                        class="task-none"
                        :class="{ 'task-none-overlay': canMoveTask }"
                      >
                        {{ t('project.timeline.noTasks') }}
                      </p>
                    </div>

                    <div
                      v-if="canCreateTask"
                      data-zen="hide"
                    >
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

              <div
                v-if="canCreateTodo"
                data-zen="hide"
              >
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
            </div>
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
