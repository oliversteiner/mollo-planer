<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { DropdownMenuItem } from '@nuxt/ui'
import { VueDraggable } from 'vue-draggable-plus'
import type { SortableEvent } from 'sortablejs'
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
const { projectId, can } = useProject()

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
function menuItems(task: Task): DropdownMenuItem[] {
  const edit: DropdownMenuItem[] = can('TASK_UPDATE')
    ? [{ label: t('common.edit'), icon: 'i-fadl-pen', onSelect: () => editTask(task.id) }]
    : []

  return [...edit, ...moveItems(task)]
}

function moveItems(task: Task): DropdownMenuItem[] {
  if (!can('TASK_MOVE')) {
    return []
  }
  const here = locate(columns.value, task.id)
  const columnLength = columns.value.find(column => column.status === here?.status)?.tasks.length ?? 0
  const items: DropdownMenuItem[] = []

  if (here && here.position > 0) {
    items.push({ label: t('kanban.moveUp'), icon: 'i-fadl-arrow-up', onSelect: () => move(task.id, here.status as Status, here.position - 1) })
  }
  if (here && here.position < columnLength - 1) {
    items.push({ label: t('kanban.moveDown'), icon: 'i-fadl-arrow-down', onSelect: () => move(task.id, here.status as Status, here.position + 1) })
  }

  return [
    ...items,
    ...TASK_STATUSES
      .filter(status => status !== here?.status)
      .map(status => ({ label: t('kanban.moveTo', { column: t(`status.${status}`) }), icon: 'i-fadl-arrow-right', onSelect: () => move(task.id, status, 100000) }))
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
  <div class="flex h-full flex-col gap-4">
    <div
      v-if="can('TASK_CREATE')"
      class="flex justify-end"
    >
      <UButton
        icon="i-fadl-plus"
        :label="t('task.create')"
        :disabled="!todos.length"
        @click="startCreating"
      />
    </div>

    <USkeleton
      v-if="loading && !result"
      class="h-64"
    />
    <div
      v-else
      class="grid min-h-0 flex-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
    >
      <section
        v-for="column in columns"
        :key="column.status"
        class="flex min-h-48 flex-col rounded-lg bg-elevated/50 p-2"
        :aria-label="t(`status.${column.status}`)"
      >
        <header class="flex items-center justify-between px-1 pb-2">
          <h2 class="font-semibold text-highlighted">
            {{ t(`status.${column.status}`) }}
          </h2>
          <UBadge
            color="neutral"
            variant="subtle"
            size="sm"
          >
            {{ column.tasks.length }}
          </UBadge>
        </header>

        <VueDraggable
          v-model="column.tasks"
          group="kanban"
          :disabled="!can('TASK_MOVE')"
          :animation="150"
          ghost-class="opacity-40"
          :data-status="column.status"
          class="flex min-h-16 flex-1 flex-col gap-2"
          @end="onDrop"
        >
          <TaskCard
            v-for="task in column.tasks"
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
