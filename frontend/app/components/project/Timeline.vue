<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { VueDraggable, type SortableEvent } from 'vue-draggable-plus'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { TimelineEntry } from '~/components/StepTimeline.vue'
import type { ItemFormTarget } from '~/components/item/FormModal.vue'
import { projectTimelineQuery } from '~/graphql/project'
import { deleteTaskMutation, moveTaskInTodoMutation, moveTaskToTodoMutation } from '~/graphql/task'
import type { ProjectTimelineQuery } from '~/graphql/generated/graphql'
import { formatMoney } from '~/utils/money'
import { priceSums } from '~/utils/milestone-price'
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

const { result, loading, error, refetch } = useQuery(projectTimelineQuery, () => ({ id: props.projectId }), { fetchPolicy: 'cache-and-network' })

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
    // Milestones before the current one are reached.
    completed: index < currentIndex,
    class: 'plan-in',
    milestone,
    number: index + 1
  }))
})

/** Open todos by id. */
const openTodos = ref(new Set<string>())

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
 * Open milestones (docs/ux-projektseite.md §4.3): one set for the full and the calm view, remembered per project in this
 * browser. At the first visit only the current milestone is open (E1). The calm view opens the current one besides.
 */
const route = useRoute()
const router = useRouter()
const { calm } = useViewMode()
const milestoneState = useOpenMilestones(() => props.projectId)
const { isOpen, reveal } = milestoneState
const milestoneIds = computed(() => milestones.value.map(milestone => milestone.id))
const allMilestonesOpen = computed(() => milestoneIds.value.length > 0 && milestoneIds.value.every(isOpen))
const allMilestonesClosed = computed(() => milestoneIds.value.every(id => !isOpen(id)))

function toggleMilestone(milestone: Milestone, all: boolean): void {
  const opening = !isOpen(milestone.id)

  milestoneState.set(milestone.id, opening)

  // With the Option key the todos follow (Finder).
  if (all) {
    const ids = milestone.todos.map(todo => todo.id)

    openTodos.value = opening ? new Set([...openTodos.value, ...ids]) : new Set([...openTodos.value].filter(id => !ids.includes(id)))
  }
}

function expandAll(): void {
  milestoneState.openAll(milestoneIds.value)
}

/** Closes every milestone and every todo; a focus inside a milestone that closes goes to its arrow. */
function collapseAll(): void {
  const content = document.activeElement?.closest('[id^="milestone-"][id$="-content"]')

  milestoneState.closeAll()
  openTodos.value = new Set()

  if (content) {
    const toggle = content.id.replace(/-content$/, '-toggle')

    void nextTick(() => document.getElementById(toggle)?.focus())
  }
}

function scrollToMilestone(id: string): void {
  document.getElementById(`milestone-${id}`)?.scrollIntoView({ block: 'nearest' })
}

/** The first time the milestones are there: the remembered state; an old link ?milestones=only closes everything. */
watch(milestones, (list) => {
  milestoneState.start(list.map(milestone => milestone.id), current.value)

  if (list.length > 0 && route.query.milestones === 'only') {
    const { milestones: _only, ...query } = route.query

    milestoneState.closeAll()
    openTodos.value = new Set()
    void router.replace({ query })
  }
}, { immediate: true })

/** A milestone that appears after the first load (a new one) opens and comes into view; the focus only moves if nothing has it. */
let knownIds: Set<string> | null = null

watch(milestones, (list) => {
  const ids = list.map(milestone => milestone.id)
  const fresh = knownIds && knownIds.size > 0 ? ids.filter(id => !knownIds!.has(id)) : []

  knownIds = new Set(ids)

  if (fresh.length) {
    reveal(fresh)
    void nextTick(() => {
      const last = fresh[fresh.length - 1]!

      scrollToMilestone(last)

      if (document.activeElement === document.body) {
        document.getElementById(`milestone-${last}-toggle`)?.focus()
      }
    })
  }
}, { immediate: true })

/** A link into the page opens what it points at: #milestone-<id>, ?task=<id> (the todo of the task too). */
function revealTarget(): void {
  if (milestones.value.length === 0) {
    return
  }

  const taskId = typeof route.query.task === 'string' ? route.query.task : null

  if (taskId) {
    for (const milestone of milestones.value) {
      for (const todo of milestone.todos) {
        if (todo.tasks.some(task => task.id === taskId)) {
          reveal([milestone.id])
          setOpen(todo.id, true)

          return
        }
      }
    }
  }

  const anchor = /^#milestone-(\d+)$/.exec(route.hash)?.[1]

  if (anchor && milestoneIds.value.includes(anchor)) {
    reveal([anchor])
    void nextTick(() => scrollToMilestone(anchor))
  }
}

watch([() => route.query.task, () => route.hash, milestones], revealTarget, { immediate: true })

watch([calm, current, milestones], () => {
  if (calm.value) {
    const currentMilestone = milestones.value.find(milestone => milestone.id === current.value)

    if (currentMilestone) {
      milestoneState.showInCalm(currentMilestone.id)
      openTodos.value = new Set([...openTodos.value, ...currentMilestone.todos.map(todo => todo.id)])
    }
  }
}, { immediate: true })

/** Dragging a task over a closed milestone and holding it 600 ms opens the milestone (spring-loading); it stays open after the drop. */
const springTimers = new Map<string, ReturnType<typeof setTimeout>>()

function springEnter(id: string): void {
  if (!dragging.value || isOpen(id) || springTimers.has(id)) {
    return
  }

  springTimers.set(id, setTimeout(() => {
    springTimers.delete(id)

    if (dragging.value) {
      reveal([id])
    }
  }, 600))
}

function springLeave(id: string, event: DragEvent): void {
  if ((event.currentTarget as Node | null)?.contains(event.relatedTarget as Node | null)) {
    return
  }

  clearTimeout(springTimers.get(id))
  springTimers.delete(id)
}

onBeforeUnmount(() => springTimers.forEach(timer => clearTimeout(timer)))

/** Ideas are not in the progress (B12): how many a milestone holds. */
function ideaCount(milestone: Milestone): number {
  return milestone.todos.reduce((sum, todo) => sum + todo.tasks.filter(task => task.status === 'IDEA').length, 0)
}

/** In the calm view a todo that has only done tasks says so, instead of an empty box (B9). */
function calmAllDone(todo: Milestone['todos'][number]): boolean {
  return calm.value && todo.tasks.length > 0 && todo.tasks.every(task => task.status === 'DONE')
}

const { can: canSystem } = useViewer()
const canBill = computed(() => canSystem('BILLING_MANAGE'))

/** The sums of the prices (E3): only with billing.manage the API gives any price. */
const sums = computed(() => priceSums(milestones.value))
const money = (cents: number) => formatMoney(cents, language.value)

useCommands(() => [
  { id: 'milestones-expand', label: t('project.timeline.expandAllMilestones'), icon: 'chevrons-down', group: 'action', visible: () => !allMilestonesOpen.value, run: expandAll },
  { id: 'milestones-collapse', label: t('project.timeline.collapseAllMilestones'), icon: 'chevrons-up', group: 'action', visible: () => !allMilestonesClosed.value, run: collapseAll }
])

function menu(kind: 'milestone' | 'todo', item: NonNullable<ItemFormTarget['item']>): ActionMenuItem[] {
  const place = kind === 'milestone'
    ? { index: milestones.value.findIndex(milestone => milestone.id === item.id), count: milestones.value.length }
    : undefined

  return props.actions?.(kind, item, place) ?? []
}
</script>

<template>
  <div class="wa-stack wa-gap-m">
    <!-- First load: three rows in the height of a closed milestone, not one block (docs/ux-projektseite.md §4.6). -->
    <div
      v-if="loading && !result"
      class="timeline-skeletons"
      aria-hidden="true"
    >
      <div
        v-for="row in 3"
        :key="row"
        class="timeline-skeleton-row"
      >
        <wa-skeleton
          effect="sheen"
          class="timeline-skeleton-dot"
        ></wa-skeleton>
        <div class="timeline-skeleton-bars">
          <wa-skeleton
            effect="sheen"
            class="timeline-skeleton-title"
          ></wa-skeleton>
          <wa-skeleton
            effect="sheen"
            class="timeline-skeleton-line"
          ></wa-skeleton>
        </div>
      </div>
    </div>
    <!-- A failed load is not an empty project (B4). -->
    <wa-callout
      v-else-if="error && !result"
      variant="danger"
      class="timeline-error"
    >
      <wa-icon
        slot="icon"
        name="triangle-exclamation"
      ></wa-icon>
      <div class="timeline-error-body">
        <span>{{ t('project.timeline.loadError') }}</span>
        <wa-button
          size="s"
          variant="danger"
          appearance="outlined"
          @click="refetch()"
        >
          {{ t('project.timeline.retry') }}
        </wa-button>
      </div>
    </wa-callout>
    <EmptyState
      v-else-if="!milestones.length"
      icon="flag-checkered"
      :title="t('project.noMilestones')"
      :description="emptyHint"
    />

    <template v-else>
      <div
        class="timeline-toolbar"
        data-zen="hide"
      >
        <p
          v-if="sums"
          class="timeline-sum wa-caption-m"
        >
          {{ t('project.timeline.priceSum', { total: `CHF ${money(sums.total)}`, billed: money(sums.billed), draft: money(sums.draft) }) }}
        </p>
        <div class="wa-cluster wa-gap-s timeline-toolbar-actions">
          <wa-button
            id="timeline-expand-all"
            appearance="plain"
            variant="neutral"
            size="s"
            :disabled="allMilestonesOpen"
            :aria-label="t('project.timeline.expandAllMilestones')"
            @click="expandAll"
          >
            <wa-icon
              slot="start"
              name="chevrons-down"
            ></wa-icon>
            <span class="timeline-toolbar-label">{{ t('project.timeline.expandAllMilestones') }}</span>
          </wa-button>
          <wa-tooltip for="timeline-expand-all">
            {{ t('project.timeline.expandAllMilestones') }}
          </wa-tooltip>
          <wa-button
            id="timeline-collapse-all"
            appearance="plain"
            variant="neutral"
            size="s"
            :disabled="allMilestonesClosed"
            :aria-label="t('project.timeline.collapseAllMilestones')"
            @click="collapseAll"
          >
            <wa-icon
              slot="start"
              name="chevrons-up"
            ></wa-icon>
            <span class="timeline-toolbar-label">{{ t('project.timeline.collapseAllMilestones') }}</span>
          </wa-button>
          <wa-tooltip for="timeline-collapse-all">
            {{ t('project.timeline.collapseAllMilestones') }}
          </wa-tooltip>
        </div>
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
          <div
            class="milestone-drop"
            @dragover="springEnter(item.milestone.id)"
            @dragleave="springLeave(item.milestone.id, $event)"
          >
            <ProjectTimelineMilestoneHead
              :milestone="item.milestone"
              :number="item.number"
              :open="isOpen(item.milestone.id)"
              :menu="menu('milestone', item.milestone)"
              :can-bill="canBill"
              @toggle="toggleMilestone(item.milestone, $event)"
              @set="milestoneState.set(item.milestone.id, $event)"
            />
          </div>
        </template>

        <template #description="{ item }">
          <ProjectTimelineMilestone
            :id="item.milestone.id"
            :description="item.milestone.description"
            :progress="item.milestone.progress"
            :ideas="ideaCount(item.milestone)"
            :open="isOpen(item.milestone.id)"
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

                <p
                  v-if="openTodos.has(todo.id) && calmAllDone(todo)"
                  class="todo-all-done"
                >
                  {{ t('project.timeline.allTasksDone') }}
                </p>
                <div
                  v-else-if="openTodos.has(todo.id)"
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
                      handle=".task-handle"
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
                        :movable="canMoveTask"
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
          </ProjectTimelineMilestone>
        </template>
      </StepTimeline>
    </template>
  </div>
</template>

<style scoped>
.timeline-skeletons {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-l);
}

.timeline-skeleton-row {
  display: flex;
  align-items: center;
  gap: var(--wa-space-m);
}

.timeline-skeleton-dot {
  flex: none;
  inline-size: 2rem;
  block-size: 2rem;
  --border-radius: var(--wa-border-radius-circle);
}

.timeline-skeleton-bars {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: var(--wa-space-xs);
}

.timeline-skeleton-title {
  inline-size: 40%;
  block-size: 1rem;
}

.timeline-skeleton-line {
  inline-size: 60%;
  block-size: 0.5rem;
}

.timeline-error-body {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.timeline-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-xs) var(--wa-space-m);
}

.timeline-sum {
  margin: 0;
  color: var(--wa-color-text-quiet);
}

.timeline-toolbar-actions {
  margin-inline-start: auto;
}

/* The head of a milestone uses the whole width of the label of its step. */
.plan-timeline :deep(.timeline-heading) {
  display: flex;
  inline-size: 100%;
}

.plan-timeline :deep(.timeline-title) {
  flex: 1;
  min-inline-size: 0;
}

.milestone-drop {
  inline-size: 100%;
  min-inline-size: 0;
}

.todo-all-done {
  margin: 0;
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

/* Narrow window: the toolbar buttons are icons with a tooltip, the sum has a row of its own. */
@media (max-width: 40em) {
  .timeline-sum {
    flex-basis: 100%;
  }

  .timeline-toolbar-label {
    display: none;
  }
}

@media (min-width: 40.01em) {
  wa-tooltip {
    display: none;
  }
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
