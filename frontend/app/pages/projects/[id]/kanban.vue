<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { VueDraggable } from 'vue-draggable-plus'
import type { SortableEvent } from 'sortablejs'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { TaskFormTarget } from '~/components/task/FormModal.vue'
import type { KanbanQuery } from '~/graphql/generated/graphql'
import { projectTodosQuery } from '~/graphql/project'
import { kanbanQuery, moveTaskMutation, projectMembersQuery, TASK_STATUSES, taskQuery } from '~/graphql/task'
import { locate, moveInColumns } from '~/utils/kanban'

type Column = KanbanQuery['kanban'][number]
type Task = Column['tasks'][number]
type Status = (typeof TASK_STATUSES)[number]

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { projectId, project, can } = useProject()

/**
 * Filter by label ("all": none). While filtering, cards cannot be dragged:
 * hidden cards would make the positions wrong. The card menu still moves.
 */
const labelFilter = ref('all')
const filterItems = computed(() => [
  { label: t('labels.allLabels'), value: 'all' },
  ...[...(project.value?.labelSet?.labels ?? [])]
    .sort((a, b) => a.position - b.position)
    .map(label => ({ label: label.name, value: label.id }))
])
const filtering = computed(() => labelFilter.value !== 'all')

function shown(task: Task): boolean {
  return !filtering.value || task.labels.some(label => label.id === labelFilter.value)
}

const { result, loading, refetch } = useQuery(kanbanQuery, () => ({ projectId: projectId.value }), { fetchPolicy: 'cache-and-network' })
const { result: membersResult } = useQuery(projectMembersQuery, () => ({ id: projectId.value }))
const { result: todosResult } = useQuery(projectTodosQuery, () => ({ projectId: projectId.value }))

const members = computed(() => (membersResult.value?.project?.members ?? [])
  .map(member => member.user)
  .sort((a, b) => a.name.localeCompare(b.name)))
const todos = computed(() => todosResult.value?.todos ?? [])

/**
 * Local copy of the board, so a drag shows at once; it is replaced by the
 * server's answer after every change.
 */
const columns = ref<Column[]>([])

/** Ideas and planned tasks have their own columns, shown on request (planer-abgleich §8.2 Nr. 3). */
const SHOW_IDEAS_KEY = 'mollo.kanban.showIdeas'
const HIDDEN_BY_DEFAULT = ['IDEA', 'PLANNED']

function readShowIdeas(): boolean {
  try {
    return localStorage.getItem(SHOW_IDEAS_KEY) === '1'
  } catch {
    return false
  }
}

const showIdeas = ref(readShowIdeas())
watch(showIdeas, (value) => {
  try {
    localStorage.setItem(SHOW_IDEAS_KEY, value ? '1' : '0')
  } catch {
    // Private window or blocked storage: the switch still works for this visit.
  }
})

const visibleColumns = computed(() => columns.value.filter(column => showIdeas.value || !HIDDEN_BY_DEFAULT.includes(column.status)))

watch(() => result.value?.kanban, (kanban) => {
  columns.value = (kanban ?? []).map(column => ({ ...column, tasks: [...column.tasks] }))
}, { immediate: true })

const { mutate: moveTask } = useMutation(moveTaskMutation)

async function move(taskId: string, status: Status, position: number): Promise<void> {
  columns.value = moveInColumns(columns.value, taskId, status, position)

  try {
    await moveTask({ id: taskId, status, position })
  } finally {
    await refetch()
  }
}

/** After a drop: SortableJS has moved the card in the local lists already. */
function onDrop(event: SortableEvent): void {
  const taskId = (event.item as HTMLElement).dataset.id
  const status = (event.to as HTMLElement).dataset.status as Status | undefined

  if (!taskId || !status || event.newIndex === undefined) {
    return
  }
  if (event.from === event.to && event.oldIndex === event.newIndex) {
    return
  }
  void move(taskId, status, event.newIndex)
}

/** Card menu: edit (if allowed), then moving without dragging. */
function menuItems(task: Task): ActionMenuItem[] {
  const edit: ActionMenuItem[] = can('TASK_UPDATE')
    ? [{ label: t('common.edit'), icon: 'pen', onSelect: () => editTask(task.id) }]
    : []

  return [...edit, ...moveItems(task)]
}

function moveItems(task: Task): ActionMenuItem[] {
  if (!can('TASK_MOVE')) {
    return []
  }
  const here = locate(columns.value, task.id)
  const columnLength = columns.value.find(column => column.status === here?.status)?.tasks.length ?? 0
  const items: ActionMenuItem[] = []

  if (here && here.position > 0) {
    items.push({ label: t('kanban.moveUp'), icon: 'arrow-up', onSelect: () => move(task.id, here.status as Status, here.position - 1) })
  }
  if (here && here.position < columnLength - 1) {
    items.push({ label: t('kanban.moveDown'), icon: 'arrow-down', onSelect: () => move(task.id, here.status as Status, here.position + 1) })
  }

  return [
    ...items,
    ...TASK_STATUSES
      .filter(status => status !== here?.status)
      .map(status => ({ label: t('kanban.moveTo', { column: t(`status.${status}`) }), icon: 'arrow-right', onSelect: () => move(task.id, status, 100000) }))
  ]
}

/** ?task=… opens the details (link in the mails). */
const editing = ref<TaskFormTarget | null>(null)

const openTaskId = computed(() => (typeof route.query.task === 'string' ? route.query.task : null))

/**
 * Only one overlay at a time: the details (side panel) or the edit
 * dialog. Editing from the details closes them and returns afterwards.
 */
const returnToTaskId = ref<string | null>(null)

function openTask(taskId: string): void {
  editing.value = null
  void router.replace({ query: { ...route.query, task: taskId } })
}

function closeTask(): void {
  const { task: _task, ...query } = route.query
  void router.replace({ query })
}

function startCreating(): void {
  closeTask()
  editing.value = {}
}

function startEditing(task: NonNullable<TaskFormTarget['task']>): void {
  editing.value = { task }
}

/** "Edit" in the details: close them, edit, then show them again. */
function editFromDetails(task: NonNullable<TaskFormTarget['task']>): void {
  returnToTaskId.value = task.id
  closeTask()
  startEditing(task)
}

function closeEditing(): void {
  editing.value = null

  if (returnToTaskId.value) {
    openTask(returnToTaskId.value)
    returnToTaskId.value = null
  }
}

const { $apollo } = useNuxtApp()

/**
 * Double-click or menu: edit the task. The board does not load the
 * description, so the task is fetched first. Without the right to edit,
 * the details open instead.
 */
async function editTask(taskId: string): Promise<void> {
  if (!can('TASK_UPDATE')) {
    openTask(taskId)

    return
  }
  if (openTaskId.value) {
    closeTask()
  }
  const { data } = await $apollo.query({ query: taskQuery, variables: { id: taskId }, fetchPolicy: 'network-only' })

  if (data.task) {
    startEditing(data.task)
  }
}

const abilities = computed(() => ({
  move: can('TASK_MOVE'),
  assign: can('TASK_ASSIGN'),
  update: can('TASK_UPDATE'),
  delete: can('TASK_DELETE'),
  comment: can('COMMENT_CREATE')
}))
</script>

<template>
  <div class="kanban">
    <Teleport
      v-if="can('TASK_CREATE')"
      to="#page-actions"
      defer
    >
      <wa-button
        variant="brand"
        :disabled="!todos.length"
        @click="startCreating"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('task.create') }}
      </wa-button>
    </Teleport>

    <FormSwitch
      v-model="showIdeas"
      :label="t('kanban.showIdeas')"
      :hint="t('kanban.showIdeasHint')"
      class="kanban-filter"
    />

    <FormSelect
      v-if="filterItems.length > 1"
      v-model="labelFilter"
      :items="filterItems"
      :label="t('labels.filter')"
      hide-label
      class="kanban-filter"
    />

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="kanban-skeleton"
    ></wa-skeleton>
    <div
      v-else
      class="kanban-board"
    >
      <section
        v-for="column in visibleColumns"
        :key="column.status"
        class="kanban-column"
        :aria-label="t(`status.${column.status}`)"
      >
        <header class="kanban-column-head">
          <h2 class="kanban-column-title">
            {{ t(`status.${column.status}`) }}
          </h2>
          <wa-badge
            variant="neutral"
            appearance="filled"
          >
            {{ filtering ? column.tasks.filter(shown).length : column.tasks.length }}
          </wa-badge>
        </header>

        <VueDraggable
          v-model="column.tasks"
          group="kanban"
          :disabled="!can('TASK_MOVE') || filtering"
          :animation="150"
          ghost-class="kanban-ghost"
          :data-status="column.status"
          class="kanban-list"
          @end="onDrop"
        >
          <TaskCard
            v-for="task in column.tasks"
            v-show="shown(task)"
            :key="task.id"
            :task="task"
            :menu-items="menuItems(task)"
            :editable="can('TASK_UPDATE')"
            @open="openTask(task.id)"
            @edit="editTask(task.id)"
          />
        </VueDraggable>
      </section>
    </div>

    <TaskDetail
      :task-id="openTaskId"
      :members="members"
      :can="abilities"
      @close="closeTask"
      @edit="editFromDetails"
    />
    <TaskFormModal
      :target="editing"
      :todos="todos"
      :members="members"
      @close="closeEditing"
    />
  </div>
</template>

<style scoped>
.kanban {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-m);
  block-size: 100%;
}

.kanban-filter {
  max-inline-size: 16rem;
}

.kanban-skeleton {
  block-size: 16rem;
}

.kanban-board {
  display: grid;
  flex: 1;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr));
  gap: var(--wa-space-m);
  min-block-size: 0;
}

.kanban-column {
  display: flex;
  flex-direction: column;
  min-block-size: 12rem;
  padding: var(--wa-space-xs);
  border-radius: var(--wa-border-radius-l);
  background-color: var(--wa-color-surface-raised);
}

.kanban-column-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--wa-space-3xs) var(--wa-space-xs);
}

.kanban-column-title {
  margin: 0;
  font-size: var(--wa-font-size-m);
  font-weight: var(--wa-font-weight-semibold);
}

.kanban-list {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: var(--wa-space-xs);
  min-block-size: 4rem;
}

.kanban-ghost {
  opacity: 0.4;
}
</style>
