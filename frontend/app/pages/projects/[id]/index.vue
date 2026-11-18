<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { ItemFormTarget } from '~/components/item/FormModal.vue'
import type { TaskFormTarget } from '~/components/task/FormModal.vue'
import type { MilestonePlace } from '~/components/project/Timeline.vue'
import { deleteMilestoneMutation, deleteTodoMutation, moveMilestoneMutation, projectTodosQuery } from '~/graphql/project'
import { projectMembersQuery } from '~/graphql/task'

/**
 * Overview of a project: the timeline of milestones, todos and tasks,
 * with creating, changing and deleting by the rights of the viewer.
 */
const { t } = useI18n()
const route = useRoute()

// Someone else's changes show up within 30 seconds (S3f).
useProjectRevisionPolling()
const router = useRouter()
const { projectId, project, can } = useProject()
const { can: canSystem } = useViewer()

/** Only the milestones (?milestones=only). */
const milestonesOnly = computed({
  get: () => route.query.milestones === 'only',
  set: (value: boolean) => {
    router.replace({ query: { ...route.query, milestones: value ? 'only' : undefined } })
  }
})

/** Todos for the task dialog, members for it and the task details. */
const taskFormEnabled = computed(() => can('TASK_CREATE') || can('TASK_UPDATE') || can('TASK_UPDATE_OWN'))
const { result: todosResult } = useQuery(projectTodosQuery, () => ({ projectId: projectId.value }), () => ({ enabled: taskFormEnabled.value }))
const { result: membersResult } = useQuery(projectMembersQuery, () => ({ id: projectId.value }))
const todos = computed(() => todosResult.value?.todos ?? [])
const members = computed(() => (membersResult.value?.project?.members ?? [])
  .map(member => member.user)
  .sort((a, b) => a.name.localeCompare(b.name)))

/**
 * Tasks as in the Kanban: ?task=… opens the details (side panel), the
 * dialog creates or changes one. Only one overlay at a time: editing from
 * the details closes them and returns afterwards.
 */
const taskForm = ref<TaskFormTarget | null>(null)
const openTaskId = computed(() => (typeof route.query.task === 'string' ? route.query.task : null))
const returnToTaskId = ref<string | null>(null)

const taskAbilities = computed(() => ({
  move: can('TASK_MOVE'),
  comment: can('COMMENT_CREATE')
}))

function openTask(taskId: string): void {
  taskForm.value = null
  void router.replace({ query: { ...route.query, task: taskId } })
}

function closeTask(): void {
  const { task: _task, ...query } = route.query
  void router.replace({ query })
}

function createTask(todoId: string): void {
  closeTask()
  taskForm.value = { todoId }
}

function editTask(task: NonNullable<TaskFormTarget['task']>): void {
  closeTask()
  taskForm.value = { task }
}

function editFromDetails(task: NonNullable<TaskFormTarget['task']>): void {
  returnToTaskId.value = task.id
  editTask(task)
}

function closeTaskForm(): void {
  taskForm.value = null

  if (returnToTaskId.value) {
    openTask(returnToTaskId.value)
    returnToTaskId.value = null
  }
}

type Item = NonNullable<ItemFormTarget['item']>

const editing = ref<ItemFormTarget | null>(null)

const { mutate: deleteMilestone } = useProjectMutation(deleteMilestoneMutation)
const { mutate: deleteTodo } = useProjectMutation(deleteTodoMutation)
const { mutate: moveMilestone } = useProjectMutation(moveMilestoneMutation)

const deletion = useDelete<{ kind: 'milestone' | 'todo', item: Item }>(target =>
  target.kind === 'milestone' ? deleteMilestone({ id: target.item.id }) : deleteTodo({ id: target.item.id })
)

function startEditing(target: ItemFormTarget): void {
  editing.value = target
}

function startDeleting(target: { kind: 'milestone' | 'todo', item: Item }): void {
  void deletion.remove(target)
}

/** Menu "Ändern / Nach oben / Nach unten / Löschen" of a milestone or todo, by the viewer's rights. */
function actions(kind: 'milestone' | 'todo', item: Item, place?: MilestonePlace): ActionMenuItem[] {
  const prefix = kind === 'milestone' ? 'MILESTONE' : 'TODO'
  const items: ActionMenuItem[] = []

  if (can(`${prefix}_UPDATE`)) {
    items.push({ label: t('common.edit'), icon: 'pen', onSelect: () => startEditing({ kind, item }) })
  }
  if (kind === 'milestone' && place && can('MILESTONE_UPDATE')) {
    if (place.index > 0) {
      items.push({ label: t('kanban.moveUp'), icon: 'arrow-up', onSelect: () => void moveMilestone({ id: item.id, position: place.index - 1 }) })
    }
    if (place.index < place.count - 1) {
      items.push({ label: t('kanban.moveDown'), icon: 'arrow-down', onSelect: () => void moveMilestone({ id: item.id, position: place.index + 1 }) })
    }
  }
  if (can(`${prefix}_DELETE`)) {
    items.push({ label: t('common.delete'), icon: 'trash', color: 'error', onSelect: () => startDeleting({ kind, item }) })
  }

  return items
}
</script>

<template>
  <div class="wa-stack wa-gap-l">
    <Teleport
      v-if="canSystem('ASSISTANT_USE') && !project?.archivedAt"
      to="#page-actions"
      defer
    >
      <wa-button
        appearance="outlined"
        variant="neutral"
        @click="navigateTo({ path: '/assistant', query: { project: projectId } })"
      >
        <wa-icon
          slot="start"
          name="wand-magic-sparkles"
        ></wa-icon>
        {{ t('assistant.addWith') }}
      </wa-button>
    </Teleport>

    <Teleport
      v-if="can('MILESTONE_CREATE')"
      to="#page-actions"
      defer
    >
      <wa-button
        variant="brand"
        @click="editing = { kind: 'milestone', parentId: projectId }"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('item.milestone.create') }}
      </wa-button>
    </Teleport>

    <ProjectTimeline
      v-model:milestones-only="milestonesOnly"
      :project-id="projectId"
      :actions="actions"
      :empty-hint="can('MILESTONE_CREATE') ? t('project.noMilestonesHint') : undefined"
      :can-create-todo="can('TODO_CREATE')"
      :can-create-task="can('TASK_CREATE')"
      :can-move-task="can('TASK_UPDATE')"
      :can-reorder-task="can('TASK_MOVE')"
      @create-todo="editing = { kind: 'todo', parentId: $event }"
      @create-task="createTask"
      @open-task="openTask"
      @edit-task="editTask"
    />

    <ItemFormModal
      :target="editing"
      @close="editing = null"
    />
    <TaskDetail
      :task-id="openTaskId"
      :members="members"
      :can="taskAbilities"
      @close="closeTask"
      @edit="editFromDetails"
    />
    <TaskFormModal
      :target="taskForm"
      :todos="todos"
      :members="members"
      @close="closeTaskForm"
    />
  </div>
</template>
