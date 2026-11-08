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
  <USlideover
    v-model:open="open"
    :title="task?.title ?? t('task.loading')"
    :description="task ? `${task.todo.milestone.title} · ${task.todo.title}` : undefined"
    :ui="{ content: 'sm:max-w-xl' }"
  >
    <template #body>
      <USkeleton
        v-if="loading && !task"
        class="h-40"
      />
      <UEmpty
        v-else-if="!task"
        icon="i-fadl-square-check"
        :title="t('task.notFound')"
      />
      <div
        v-else
        class="space-y-6"
      >
        <div class="flex items-center gap-3">
          <ItemIcon
            :name="task.icon"
            fallback="square-check"
            class="text-3xl text-primary"
          />
          <DueDate
            :date="task.dueDate"
            :done="task.status === 'DONE'"
          />
          <div class="ms-auto flex gap-1">
            <UButton
              v-if="can.update"
              icon="i-fadl-pen"
              :label="t('common.edit')"
              color="neutral"
              variant="outline"
              size="sm"
              @click="emit('edit', task)"
            />
            <UButton
              v-if="can.delete"
              icon="i-fadl-trash"
              color="error"
              variant="ghost"
              size="sm"
              :aria-label="t('common.delete')"
              @click="deleting = true"
            />
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField :label="t('task.status')">
            <USelect
              v-if="can.move"
              :model-value="task.status"
              :items="statusItems"
              class="w-full"
              @update:model-value="changeStatus"
            />
            <StatusBadge
              v-else
              :status="task.status"
            />
          </UFormField>
          <UFormField :label="t('task.assignee')">
            <USelect
              v-if="can.assign"
              :model-value="task.assignee?.id ?? 'none'"
              :items="memberItems"
              class="w-full"
              @update:model-value="changeAssignee"
            />
            <span
              v-else
              class="text-sm"
            >{{ task.assignee?.name ?? t('task.nobody') }}</span>
          </UFormField>
        </div>

        <section>
          <h3 class="mb-2 font-semibold text-highlighted">
            {{ t('item.fields.description') }}
          </h3>
          <RichTextView
            :content="task.description"
            class="text-sm"
          />
          <p
            v-if="!task.description"
            class="text-sm text-muted"
          >
            {{ t('task.noDescription') }}
          </p>
        </section>

        <USeparator />

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
    </template>
  </USlideover>
</template>
