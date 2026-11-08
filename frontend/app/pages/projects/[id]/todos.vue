<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { DropdownMenuItem } from '@nuxt/ui'
import type { ItemFormTarget } from '~/components/item/FormModal.vue'
import { deleteTodoMutation, projectContentQueries, projectOverviewQuery, projectTodosQuery } from '~/graphql/project'
import { withoutNulls } from '~/utils/api'

const { t } = useI18n()
const { projectId, can } = useProject()

/** "all" stands for "no filter" (a select cannot hold null). */
const milestoneFilter = ref('all')
const statusFilter = ref<'all' | 'OPEN' | 'DONE'>('all')

const { result, loading } = useQuery(projectTodosQuery, () => ({
  projectId: projectId.value,
  ...withoutNulls({
    milestoneId: milestoneFilter.value === 'all' ? null : milestoneFilter.value,
    status: statusFilter.value === 'all' ? null : statusFilter.value
  })
}), { fetchPolicy: 'cache-and-network' })

const { result: overview } = useQuery(projectOverviewQuery, () => ({ id: projectId.value }))
const milestones = computed(() => (overview.value?.project?.milestones ?? []).map(milestone => ({ id: milestone.id, title: milestone.title })))

const milestoneItems = computed(() => [
  { label: t('todos.allMilestones'), value: 'all' },
  ...milestones.value.map(milestone => ({ label: milestone.title, value: milestone.id }))
])
const statusItems = computed(() => [
  { label: t('todos.allStatuses'), value: 'all' },
  { label: t('status.OPEN'), value: 'OPEN' },
  { label: t('status.DONE'), value: 'DONE' }
])

/** By due date (without date last), then in creation order. */
const todos = computed(() => [...(result.value?.todos ?? [])]
  .sort((a, b) => (a.dueDate ?? '9999').localeCompare(b.dueDate ?? '9999') || Number(a.id) - Number(b.id)))

type Item = NonNullable<ItemFormTarget['item']>

const editing = ref<ItemFormTarget | null>(null)
const deleting = ref<Item | null>(null)

const { mutate: deleteTodo } = useMutation(deleteTodoMutation, { refetchQueries: projectContentQueries, awaitRefetchQueries: true })

function startEditing(todo: Item): void {
  editing.value = { kind: 'todo', item: todo }
}

function startDeleting(todo: Item): void {
  deleting.value = todo
}

async function confirmDelete(): Promise<void> {
  if (deleting.value) {
    await deleteTodo({ id: deleting.value.id })
  }
}

function actions(todo: Item): DropdownMenuItem[] {
  const items: DropdownMenuItem[] = []

  if (can('TODO_UPDATE')) {
    items.push({ label: t('common.edit'), icon: 'i-fadl-pen', onSelect: () => startEditing(todo) })
  }
  if (can('TODO_DELETE')) {
    items.push({ label: t('common.delete'), icon: 'i-fadl-trash', color: 'error', onSelect: () => startDeleting(todo) })
  }

  return items
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
      <UFormField :label="t('todos.milestone')">
        <USelect
          v-model="milestoneFilter"
          :items="milestoneItems"
          class="w-56"
        />
      </UFormField>
      <UFormField :label="t('todos.status')">
        <USelect
          v-model="statusFilter"
          :items="statusItems"
          class="w-40"
        />
      </UFormField>
      <UButton
        v-if="can('TODO_CREATE') && milestones.length"
        icon="i-fadl-plus"
        :label="t('item.todo.create')"
        class="ms-auto"
        @click="editing = { kind: 'todo' }"
      />
    </div>

    <USkeleton
      v-if="loading && !result"
      class="h-40"
    />
    <UEmpty
      v-else-if="!todos.length"
      icon="i-fadl-list-check"
      :title="t('todos.none')"
    />
    <ul
      v-else
      class="divide-y divide-default rounded-lg border border-default"
    >
      <li
        v-for="todo in todos"
        :key="todo.id"
        class="flex flex-wrap items-center gap-3 px-4 py-2"
      >
        <ItemIcon
          :name="todo.icon"
          fallback="list-check"
          class="text-lg text-primary"
        />
        <span class="min-w-0 flex-1">
          <span class="block truncate">{{ todo.title }}</span>
          <span class="block truncate text-sm text-muted">{{ todo.milestone.title }}</span>
        </span>
        <span class="text-sm text-muted">{{ t('project.taskCount', { done: todo.progress.done, total: todo.progress.total }) }}</span>
        <StatusBadge :status="todo.status" />
        <DueDate
          :date="todo.dueDate"
          :done="todo.status === 'DONE'"
        />
        <UDropdownMenu
          v-if="actions(todo).length"
          :items="actions(todo)"
        >
          <UButton
            icon="i-fadl-ellipsis"
            color="neutral"
            variant="ghost"
            size="sm"
            :aria-label="t('common.actions')"
          />
        </UDropdownMenu>
      </li>
    </ul>

    <ItemFormModal
      :target="editing"
      :milestones="milestones"
      @close="editing = null"
    />
    <ConfirmModal
      :open="deleting !== null"
      :title="t('item.todo.deleteTitle')"
      :description="t('item.todo.deleteDescription', { title: deleting?.title ?? '' })"
      :confirm-label="t('common.delete')"
      :action="confirmDelete"
      @close="deleting = null"
    />
  </div>
</template>
