<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import {
  createMilestoneMutation,
  createTodoMutation,
  projectContentQueries,
  updateMilestoneMutation,
  updateTodoMutation
} from '~/graphql/project'
import { isEmptyDocument } from '~/utils/rich-text'

export interface ItemFormTarget {
  kind: 'milestone' | 'todo'
  /** Existing item to change; without it a new one is created. */
  item?: {
    id: string
    title: string
    dueDate?: string | null
    icon?: string | null
    description?: Record<string, unknown> | null
  }
  /** Project (new milestone) or milestone (new todo) the item belongs to. */
  parentId?: string
}

/** Create or change a milestone or todo: title, description, icon, due date. */
const props = defineProps<{
  target: ItemFormTarget | null
  /** Choice of milestone for a new todo without parentId. */
  milestones?: { id: string, title: string }[]
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
  title: string
  dueDate: string
  milestoneId: string
  icon: string | null
  description: Record<string, unknown> | null
}>({ title: '', dueDate: '', milestoneId: '', icon: null, description: null })

watch(() => props.target, (target) => {
  errors.value = {}
  state.title = target?.item?.title ?? ''
  state.dueDate = target?.item?.dueDate ?? ''
  state.icon = target?.item?.icon ?? null
  state.description = target?.item?.description ?? null
  state.milestoneId = target?.parentId ?? props.milestones?.[0]?.id ?? ''
}, { immediate: true })

const isNew = computed(() => !props.target?.item)
const kind = computed(() => props.target?.kind ?? 'milestone')
const needsMilestoneChoice = computed(() => kind.value === 'todo' && isNew.value && !props.target?.parentId)
const title = computed(() => t(`item.${kind.value}.${isNew.value ? 'create' : 'edit'}`))
const milestoneItems = computed(() => (props.milestones ?? []).map(milestone => ({ label: milestone.title, value: milestone.id })))

const options = { refetchQueries: projectContentQueries, awaitRefetchQueries: true }
const { mutate: createMilestone } = useMutation(createMilestoneMutation, options)
const { mutate: updateMilestone } = useMutation(updateMilestoneMutation, options)
const { mutate: createTodo } = useMutation(createTodoMutation, options)
const { mutate: updateTodo } = useMutation(updateTodoMutation, options)

async function submit(): Promise<void> {
  const target = props.target

  if (!target) {
    return
  }

  const fields = {
    title: state.title.trim(),
    dueDate: state.dueDate || null,
    icon: state.icon,
    description: isEmptyDocument(state.description) ? null : state.description
  }
  const result = await run<unknown>(() => {
    if (target.item) {
      return target.kind === 'milestone'
        ? updateMilestone({ id: target.item.id, input: fields })
        : updateTodo({ id: target.item.id, input: fields })
    }

    return target.kind === 'milestone'
      ? createMilestone({ input: { ...fields, projectId: target.parentId ?? '' } })
      : createTodo({ input: { ...fields, milestoneId: target.parentId ?? state.milestoneId } })
  })

  if (result) {
    toast.add({ title: t(`item.${target.kind}.saved`), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="title"
    class="item-dialog"
  >
    <form
      id="item-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormSelect
        v-if="needsMilestoneChoice"
        v-model="state.milestoneId"
        name="milestoneId"
        :items="milestoneItems"
        :label="t('item.todo.milestone')"
        :error="errors.milestoneId"
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
            :fallback="kind === 'milestone' ? 'flag-checkered' : 'list-check'"
          />
        </FormField>

        <FormInput
          v-model="state.dueDate"
          type="date"
          name="dueDate"
          :label="t('item.fields.dueDate')"
          :error="errors.dueDate"
        />
      </div>

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
        form="item-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.item-dialog {
  --width: 42rem;
}
</style>
