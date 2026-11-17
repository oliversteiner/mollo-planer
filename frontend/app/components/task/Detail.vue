<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { ShareTask } from '~/utils/share'
import { setTaskLabelsMutation } from '~/graphql/labels'
import { assignTaskMutation, deleteTaskMutation, moveTaskMutation, TASK_STATUSES, taskQuery, updateTaskMutation } from '~/graphql/task'

/**
 * Details of a task in a side panel: status, assignee, due date,
 * description and comments. Moving and commenting follow the project's
 * abilities; changing, assigning and deleting follow the task's own answers
 * (Task.viewerCan*), since task.updateOwn holds per task (docs/fix-m1-f4.md F4.5).
 */
const props = defineProps<{
  taskId: string | null
  members: { id: string, name: string }[]
  can: {
    move: boolean
    comment: boolean
  }
}>()

const emit = defineEmits<{
  close: []
  edit: [task: { id: string, title: string, dueDate?: string | null, icon?: string | null, description?: Record<string, unknown> | null }]
}>()

const { t } = useI18n()
const toast = useToast()

/**
 * Set when the task is deleted here: the panel closes at once, before the
 * page has changed the route, so useDelete waits for it (F5.1).
 */
const closing = ref(false)
watch(() => props.taskId, () => {
  closing.value = false
})

const open = computed({
  get: () => props.taskId !== null && !closing.value,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const { result, loading } = useQuery(taskQuery, () => ({ id: props.taskId ?? '' }), () => ({
  enabled: props.taskId !== null,
  fetchPolicy: 'cache-and-network'
}))
const task = computed(() => (props.taskId ? result.value?.task ?? null : null))
const may = computed(() => ({
  update: task.value?.viewerCanUpdate ?? false,
  assign: task.value?.viewerCanAssignSelf ?? false,
  delete: task.value?.viewerCanDelete ?? false
}))

const statusItems = computed(() => TASK_STATUSES.map(status => ({ label: t(`status.${status}`), value: status })))
/** Members first; people who may manage members can also take somebody in. */
const assignee = useAssigneeChoice(() => props.members, () => task.value?.viewerCanAssign ?? false)

const { mutate: moveTask } = useProjectMutation(moveTaskMutation)
const { mutate: assignTask } = useProjectMutation(assignTaskMutation)
const { mutate: deleteTask } = useProjectMutation(deleteTaskMutation)
const { mutate: setTaskLabels } = useProjectMutation(setTaskLabelsMutation)
const { mutate: updateTask } = useProjectMutation(updateTaskMutation)
const { errors: commitErrors, run: runCommit } = useGraphqlForm()

/** Labels of the project's set; the task's labels outside it can only be removed. */
const setLabels = computed(() => task.value?.todo.milestone.project.labelSet?.labels ?? [])
const setLabelIds = computed(() => setLabels.value.map(label => label.id))
const extraLabels = computed(() => (task.value?.labels ?? []).filter(label => !setLabelIds.value.includes(label.id)))

/** Share task data derived from the GraphQL result. */
const shareTask = computed<ShareTask | null>(() => {
  const t = task.value
  if (!t) return null
  return {
    id: t.id,
    title: t.title,
    description: t.description,
    dueDate: t.dueDate,
    status: t.status,
    assignee: t.assignee,
    todo: { title: t.todo.title },
    milestone: { title: t.todo.milestone.title },
    project: { name: t.todo.milestone.project.name }
  }
})

async function changeLabels(ids: string[]): Promise<void> {
  if (task.value) {
    await setTaskLabels({ taskId: task.value.id, labelIds: ids }).catch(() => null)
  }
}

/** A new status puts the task at the end of that column. */
async function changeStatus(status: (typeof TASK_STATUSES)[number]): Promise<void> {
  if (task.value && status !== task.value.status) {
    await moveTask({ id: task.value.id, status, position: 100000 }).catch(() => null)
  }
}

/**
 * The commit of a done task: a hash, or none. Saved when the field loses focus;
 * an empty field clears it. The backend checks the format and the release rules.
 */
async function changeCommit(value: string): Promise<void> {
  if (!task.value) {
    return
  }
  const commit = value.trim() || null
  if (commit === (task.value.commit ?? null)) {
    return
  }
  await runCommit(() => updateTask({ id: task.value!.id, input: { commit } }))
}

async function changeAssignee(value: string | null): Promise<void> {
  if (task.value && value !== (task.value.assignee?.id ?? null)) {
    const joining = assignee.willJoin(value)
    const result = await assignTask({ id: task.value.id, assigneeId: value }).catch(() => null)

    if (result && value) {
      toast.add({ title: t('task.assigned'), description: t('task.assignedMail'), color: 'success' })
      if (joining) {
        assignee.announceJoined(value)
      }
    }
  }
}

const deletion = useDelete<string>(id => deleteTask({ id }), {
  onDeleted: () => {
    closing.value = true
    emit('close')
  }
})
</script>

<template>
  <AppDrawer
    v-model:open="open"
    :label="task?.title ?? t('task.loading')"
    :description="task ? `${task.todo.milestone.title} · ${task.todo.title}` : undefined"
    class="task-drawer"
  >
    <wa-skeleton
      v-if="loading && !task"
      effect="sheen"
      class="task-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!task"
      icon="square-check"
      :title="t('task.notFound')"
    />
    <div
      v-else
      class="wa-stack wa-gap-l"
    >
      <div class="wa-cluster wa-gap-s wa-align-items-center">
        <ItemIcon
          :name="task.icon"
          fallback="square-check"
          class="task-big-icon"
        />
        <DueDate
          :date="task.dueDate"
          :done="task.status === 'DONE'"
        />
        <div class="wa-cluster wa-gap-3xs task-tools">
          <wa-button
            v-if="may.update"
            appearance="outlined"
            variant="neutral"
            size="s"
            @click="emit('edit', task)"
          >
            <wa-icon
              slot="start"
              name="pen"
            ></wa-icon>
            {{ t('common.edit') }}
          </wa-button>
          <wa-button
            v-if="may.delete"
            appearance="plain"
            variant="danger"
            size="s"
            :aria-label="t('common.delete')"
            @click="task && deletion.remove(task.id)"
          >
            <wa-icon name="trash"></wa-icon>
          </wa-button>
        </div>
      </div>

      <TaskShareSection
        v-if="shareTask"
        :tasks="[shareTask]"
        :name="shareTask.title"
      />

      <div class="task-fields">
        <FormSelect
          v-if="can.move"
          :model-value="task.status"
          :items="statusItems"
          :label="t('task.status')"
          @update:model-value="value => value && changeStatus(value as (typeof TASK_STATUSES)[number])"
        />
        <FormField
          v-else
          :label="t('task.status')"
        >
          <StatusBadge :status="task.status" />
        </FormField>

        <FormInput
          v-if="may.update"
          :model-value="task.commit ?? ''"
          name="commit"
          :label="t('task.commit.label')"
          :hint="t('task.commit.hint')"
          :error="commitErrors.commit"
          @change="(event: Event) => changeCommit((event.target as HTMLInputElement).value)"
        />

        <FormPersonSelect
          v-if="may.assign"
          :model-value="task.assignee?.id ?? null"
          :groups="assignee.groups.value"
          :label="t('task.assignee')"
          :hint="assignee.hint.value"
          clearable
          @update:model-value="changeAssignee"
        />
        <FormField
          v-else
          :label="t('task.assignee')"
        >
          <span class="wa-body-s">{{ task.assignee?.name ?? t('task.nobody') }}</span>
        </FormField>
      </div>

      <LabelPicker
        v-if="may.update && (setLabels.length || extraLabels.length)"
        :model-value="task.labels.map(label => label.id)"
        :labels="setLabels"
        :extra="extraLabels"
        :label="t('labels.field')"
        @update:model-value="changeLabels"
      />
      <FormField
        v-else-if="task.labels.length"
        :label="t('labels.field')"
      >
        <TaskLabels
          :labels="task.labels"
          :set-label-ids="setLabelIds"
        />
      </FormField>

      <section class="wa-stack wa-gap-xs">
        <p
          v-if="task.userStory"
          class="wa-body-s"
        >
          {{ t('task.story.sentence', { role: task.userStory.role, goal: task.userStory.goal, benefit: task.userStory.benefit }) }}
        </p>
        <h3 class="wa-heading-s">
          {{ t('item.fields.description') }}
        </h3>
        <RichTextView
          :content="task.description"
          class="wa-body-s"
        />
        <p
          v-if="!task.description"
          class="wa-body-s wa-color-text-quiet"
        >
          {{ t('task.noDescription') }}
        </p>
      </section>

      <wa-divider></wa-divider>

      <TaskComments
        :task-id="task.id"
        :comments="task.comments"
        :can-comment="can.comment"
      />
    </div>
  </AppDrawer>
</template>

<style scoped>
.task-drawer {
  --size: 36rem;
}

.task-skeleton {
  block-size: 10rem;
}

.task-big-icon {
  font-size: var(--wa-font-size-2xl);
  color: var(--wa-color-brand-on-quiet);
}

.task-tools {
  margin-inline-start: auto;
}

.task-fields {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: var(--wa-space-m);
}
</style>
