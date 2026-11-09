<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { assignTaskMutation, deleteTaskMutation, moveTaskMutation, TASK_STATUSES, taskQuery } from '~/graphql/task'

/**
 * Details of a task in a side panel: status, assignee, due date,
 * description and comments. What can be changed follows the abilities.
 */
const props = defineProps<{
  taskId: string | null
  members: { id: string, name: string }[]
  can: {
    move: boolean
    assign: boolean
    update: boolean
    delete: boolean
    comment: boolean
  }
}>()

const emit = defineEmits<{
  close: []
  edit: [task: { id: string, title: string, dueDate?: string | null, icon?: string | null, description?: Record<string, unknown> | null }]
}>()

const { t } = useI18n()
const toast = useToast()

const open = computed({
  get: () => props.taskId !== null,
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

const statusItems = computed(() => TASK_STATUSES.map(status => ({ label: t(`status.${status}`), value: status })))
const memberItems = computed(() => [
  { label: t('task.nobody'), value: 'none' },
  ...props.members.map(member => ({ label: member.name, value: member.id }))
])

const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: moveTask } = useMutation(moveTaskMutation, options)
const { mutate: assignTask } = useMutation(assignTaskMutation, options)
const { mutate: deleteTask } = useMutation(deleteTaskMutation, options)

/** A new status puts the task at the end of that column. */
async function changeStatus(status: (typeof TASK_STATUSES)[number]): Promise<void> {
  if (task.value && status !== task.value.status) {
    await moveTask({ id: task.value.id, status, position: 100000 }).catch(() => null)
  }
}

async function changeAssignee(value: string): Promise<void> {
  if (task.value) {
    const result = await assignTask({ id: task.value.id, assigneeId: value === 'none' ? null : value }).catch(() => null)

    if (result && value !== 'none') {
      toast.add({ title: t('task.assigned'), description: t('task.assignedMail'), color: 'success' })
    }
  }
}

const deleting = ref(false)

async function confirmDelete(): Promise<void> {
  if (task.value) {
    await deleteTask({ id: task.value.id })
    emit('close')
  }
}
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
            v-if="can.update"
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
            v-if="can.delete"
            appearance="plain"
            variant="danger"
            size="s"
            :aria-label="t('common.delete')"
            @click="deleting = true"
          >
            <wa-icon name="trash"></wa-icon>
          </wa-button>
        </div>
      </div>

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

        <FormSelect
          v-if="can.assign"
          :model-value="task.assignee?.id ?? 'none'"
          :items="memberItems"
          :label="t('task.assignee')"
          @update:model-value="value => value && changeAssignee(value)"
        />
        <FormField
          v-else
          :label="t('task.assignee')"
        >
          <span class="wa-body-s">{{ task.assignee?.name ?? t('task.nobody') }}</span>
        </FormField>
      </div>

      <section class="wa-stack wa-gap-xs">
        <h3 class="wa-heading-xs">
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

    <ConfirmModal
      :open="deleting"
      :title="t('task.deleteTitle')"
      :description="t('task.deleteDescription', { title: task?.title ?? '' })"
      :confirm-label="t('common.delete')"
      :action="confirmDelete"
      @close="deleting = false"
    />
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
