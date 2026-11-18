<script setup lang="ts">
import { createTaskMutation, updateTaskMutation } from '~/graphql/task'
import { isEmptyDocument } from '~/utils/rich-text'

export interface TaskFormTarget {
  /** Todo preselected for a new task; without it the first one. */
  todoId?: string
  /** Existing task to change; without it a new one is created. */
  task?: {
    id: string
    title: string
    dueDate?: string | null
    icon?: string | null
    description?: Record<string, unknown> | null
    /** Only present when the caller loaded the story; without it the story is left as it is. */
    userStory?: { role: string, goal: string, benefit: string } | null
    /** Only present when the caller loaded the commit; without it the commit is left as it is. */
    commit?: string | null
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
  assigneeId: string | null
  title: string
  dueDate: string
  icon: string | null
  description: Record<string, unknown> | null
  labelIds: string[]
  story: { role: string, goal: string, benefit: string }
  commit: string
}>({ todoId: '', assigneeId: null, title: '', dueDate: '', icon: null, description: null, labelIds: [], story: { role: '', goal: '', benefit: '' }, commit: '' })

/** Labels of the project, for a new task (an existing one gets its labels in the details). */
const { project } = useProject()
const setLabels = computed(() => project.value?.labels ?? [])

watch(() => props.target, (target) => {
  errors.value = {}
  state.todoId = target?.todoId ?? props.todos[0]?.id ?? ''
  state.assigneeId = null
  state.title = target?.task?.title ?? ''
  state.dueDate = target?.task?.dueDate ?? ''
  state.icon = target?.task?.icon ?? null
  state.description = target?.task?.description ?? null
  state.commit = target?.task?.commit ?? ''
  state.story = { role: target?.task?.userStory?.role ?? '', goal: target?.task?.userStory?.goal ?? '', benefit: target?.task?.userStory?.benefit ?? '' }
  state.labelIds = []
}, { immediate: true })

const isNew = computed(() => !props.target?.task)
/** The story is edited only where the caller passed the story along (see TaskFormTarget). */
const storyEditable = computed(() => !props.target?.task || 'userStory' in props.target.task)
const commitEditable = computed(() => !props.target?.task || 'commit' in props.target.task)

/** All three parts, trimmed; null when all are empty. A partial story is refused by the server. */
function storyInput(): { role: string, goal: string, benefit: string } | null {
  const parts = { role: state.story.role.trim(), goal: state.story.goal.trim(), benefit: state.story.benefit.trim() }
  return parts.role || parts.goal || parts.benefit ? parts : null
}
const todoItems = computed(() => props.todos.map(todo => ({ label: `${todo.milestone.title} · ${todo.title}`, value: todo.id })))
/** Members first; people who may manage members can also take somebody in. */
const assignee = useAssigneeChoice(() => props.members)

const { mutate: createTask } = useProjectMutation(createTaskMutation)
const { mutate: updateTask } = useProjectMutation(updateTaskMutation)

async function submit(): Promise<void> {
  const fields = {
    title: state.title.trim(),
    dueDate: state.dueDate || null,
    icon: state.icon,
    description: isEmptyDocument(state.description) ? null : state.description,
    ...(storyEditable.value ? { userStory: storyInput() } : {}),
    ...(commitEditable.value ? { commit: state.commit.trim() || null } : {})
  }
  const task = props.target?.task
  const joining = !task && assignee.willJoin(state.assigneeId)
  const result = await run<unknown>(() => task
    ? updateTask({ id: task.id, input: fields })
    : createTask({ input: { ...fields, todoId: state.todoId, assigneeId: state.assigneeId, labelIds: state.labelIds } }))

  if (result) {
    toast.add({ title: t('task.saved'), color: 'success' })
    if (joining && state.assigneeId) {
      assignee.announceJoined(state.assigneeId)
    }
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="isNew ? t('task.create') : t('task.edit')"
    class="task-dialog"
  >
    <form
      id="task-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormSelect
        v-if="isNew"
        v-model="state.todoId"
        name="todoId"
        :items="todoItems"
        :label="t('task.todo')"
        :error="errors.todoId"
        required
      />

      <FormInput
        v-model="state.title"
        name="title"
        :label="t('item.fields.title')"
        :error="errors.title"
        autofocus
        required
      />

      <div class="wa-cluster wa-gap-m wa-align-items-start">
        <FormField
          :label="t('item.fields.icon')"
          :error="errors.icon"
        >
          <IconPicker
            v-model="state.icon"
            fallback="square-check"
          />
        </FormField>
        <FormInput
          v-model="state.dueDate"
          type="date"
          name="dueDate"
          :label="t('item.fields.dueDate')"
          :error="errors.dueDate"
        />
        <FormPersonSelect
          v-if="isNew"
          v-model="state.assigneeId"
          :groups="assignee.groups.value"
          :label="t('task.assignee')"
          :hint="assignee.hint.value"
          :error="errors.assigneeId"
          clearable
        />
      </div>

      <LabelPicker
        v-if="isNew && setLabels.length"
        v-model="state.labelIds"
        :labels="setLabels"
        :label="t('labels.field')"
      />
      <p
        v-if="errors.labelIds"
        data-form-error
        class="wa-body-s"
      >
        {{ errors.labelIds }}
      </p>

      <section
        v-if="storyEditable"
        class="wa-stack wa-gap-xs"
      >
        <h3 class="wa-heading-s">
          {{ t('task.story.title') }}
        </h3>
        <FormInput
          v-model="state.story.role"
          name="userStoryRole"
          :label="t('task.story.role')"
          :error="errors['userStory.role']"
        />
        <FormInput
          v-model="state.story.goal"
          name="userStoryGoal"
          :label="t('task.story.goal')"
          :error="errors['userStory.goal']"
        />
        <FormInput
          v-model="state.story.benefit"
          name="userStoryBenefit"
          :label="t('task.story.benefit')"
          :error="errors['userStory.benefit']"
        />
      </section>

      <FormInput
        v-if="commitEditable"
        v-model="state.commit"
        name="commit"
        :label="t('task.commit.label')"
        :hint="t('task.commit.hint')"
        :error="errors.commit"
      />

      <FormField
        :label="t('item.fields.description')"
        :error="errors.description"
      >
        <RichTextEditor
          v-model="state.description"
          :placeholder="t('item.fields.descriptionPlaceholder')"
        />
      </FormField>
    </form>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="open = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        type="submit"
        form="task-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.task-dialog {
  --width: 42rem;
}
</style>
