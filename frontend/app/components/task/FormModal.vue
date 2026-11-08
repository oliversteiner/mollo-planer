<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { projectContentQueries } from '~/graphql/project'
import { createTaskMutation, updateTaskMutation } from '~/graphql/task'
import { isEmptyDocument } from '~/utils/rich-text'

export interface TaskFormTarget {
  /** Existing task to change; without it a new one is created. */
  task?: {
    id: string
    title: string
    dueDate?: string | null
    icon?: string | null
    description?: Record<string, unknown> | null
  }
}

/**
 * Create a task (with todo and assignee) or change one (title, icon, due
 * date, description). Assignment of an existing task is in the details.
 */
const props = defineProps<{
  target: TaskFormTarget | null
  todos: { id: string, title: string, milestone: { title: string } }[]
  members: { id: string, name: string }[]
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const open = computed({
  get: () => props.target !== null,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const state = reactive<{
  todoId: string
  assigneeId: string
  title: string
  dueDate: string
  icon: string | null
  description: Record<string, unknown> | null
}>({ todoId: '', assigneeId: 'none', title: '', dueDate: '', icon: null, description: null })

watch(() => props.target, (target) => {
  errors.value = {}
  state.todoId = props.todos[0]?.id ?? ''
  state.assigneeId = 'none'
  state.title = target?.task?.title ?? ''
  state.dueDate = target?.task?.dueDate ?? ''
  state.icon = target?.task?.icon ?? null
  state.description = target?.task?.description ?? null
}, { immediate: true })

const isNew = computed(() => !props.target?.task)
const todoItems = computed(() => props.todos.map(todo => ({ label: `${todo.milestone.title} · ${todo.title}`, value: todo.id })))
const memberItems = computed(() => [
  { label: t('task.nobody'), value: 'none' },
  ...props.members.map(member => ({ label: member.name, value: member.id }))
])

const options = { refetchQueries: projectContentQueries, awaitRefetchQueries: true }
const { mutate: createTask } = useMutation(createTaskMutation, options)
const { mutate: updateTask } = useMutation(updateTaskMutation, options)

async function submit(): Promise<void> {
  const fields = {
    title: state.title.trim(),
    dueDate: state.dueDate || null,
    icon: state.icon,
    description: isEmptyDocument(state.description) ? null : state.description
  }
  const task = props.target?.task
  const result = await run<unknown>(() => task
    ? updateTask({ id: task.id, input: fields })
    : createTask({ input: { ...fields, todoId: state.todoId, assigneeId: state.assigneeId === 'none' ? null : state.assigneeId } }))

  if (result) {
    toast.add({ title: t('task.saved'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="isNew ? t('task.create') : t('task.edit')"
    :ui="{ content: 'sm:max-w-2xl' }"
  >
    <template #body>
      <UForm
        id="task-form"
        :state="state"
        class="space-y-4"
        @submit="submit"
      >
        <UFormField
          v-if="isNew"
          :label="t('task.todo')"
          name="todoId"
          :error="errors.todoId"
          required
        >
          <USelect
            v-model="state.todoId"
            :items="todoItems"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="t('item.fields.title')"
          name="title"
          :error="errors.title"
          required
        >
          <UInput
            v-model="state.title"
            class="w-full"
            autofocus
            required
          />
        </UFormField>

        <div class="flex flex-wrap gap-4">
          <UFormField
            :label="t('item.fields.icon')"
            name="icon"
            :error="errors.icon"
          >
            <IconPicker
              v-model="state.icon"
              fallback="square-check"
            />
          </UFormField>
          <UFormField
            :label="t('item.fields.dueDate')"
            name="dueDate"
            :error="errors.dueDate"
          >
            <UInput
              v-model="state.dueDate"
              type="date"
            />
          </UFormField>
          <UFormField
            v-if="isNew"
            :label="t('task.assignee')"
            name="assigneeId"
            :error="errors.assigneeId"
          >
            <USelect
              v-model="state.assigneeId"
              :items="memberItems"
              class="w-48"
            />
          </UFormField>
        </div>

        <UFormField
          :label="t('item.fields.description')"
          name="description"
          :error="errors.description"
        >
          <RichTextEditor
            v-model="state.description"
            :placeholder="t('item.fields.descriptionPlaceholder')"
          />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          :label="t('common.cancel')"
          color="neutral"
          variant="ghost"
          @click="open = false"
        />
        <UButton
          type="submit"
          form="task-form"
          :label="t('common.save')"
          :loading="pending"
        />
      </div>
    </template>
  </UModal>
</template>
