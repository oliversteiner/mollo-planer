<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
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

function actions(todo: Item): ActionMenuItem[] {
  const items: ActionMenuItem[] = []

  if (can('TODO_UPDATE')) {
    items.push({ label: t('common.edit'), icon: 'pen', onSelect: () => startEditing(todo) })
  }
  if (can('TODO_DELETE')) {
    items.push({ label: t('common.delete'), icon: 'trash', color: 'error', onSelect: () => startDeleting(todo) })
  }

  return items
}
</script>

<template>
  <div class="wa-stack wa-gap-m">
    <div class="wa-cluster wa-gap-s wa-align-items-end">
      <FormSelect
        v-model="milestoneFilter"
        :items="milestoneItems"
        :label="t('todos.milestone')"
        class="todos-filter-wide"
      />
      <FormSelect
        v-model="statusFilter as string"
        :items="statusItems"
        :label="t('todos.status')"
        class="todos-filter"
      />
      <wa-button
        v-if="can('TODO_CREATE') && milestones.length"
        variant="brand"
        style="margin-inline-start: auto"
        @click="editing = { kind: 'todo' }"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('item.todo.create') }}
      </wa-button>
    </div>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="todos-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!todos.length"
      icon="list-check"
      :title="t('todos.none')"
    />
    <ul
      v-else
      class="todos-list wa-list-plain"
    >
      <li
        v-for="todo in todos"
        :key="todo.id"
        class="todos-row"
      >
        <ItemIcon
          :name="todo.icon"
          fallback="list-check"
          class="todos-icon"
        />
        <span class="todos-text">
          <span class="todos-title">{{ todo.title }}</span>
          <span class="todos-sub">{{ todo.milestone.title }}</span>
        </span>
        <span class="todos-sub">{{ t('project.taskCount', { done: todo.progress.done, total: todo.progress.total }) }}</span>
        <StatusBadge :status="todo.status" />
        <DueDate
          :date="todo.dueDate"
          :done="todo.status === 'DONE'"
        />
        <ActionMenu
          v-if="actions(todo).length"
          :items="actions(todo)"
          :label="t('common.actions')"
        />
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

<style scoped>
.todos-filter-wide {
  inline-size: 14rem;
}

.todos-filter {
  inline-size: 10rem;
}

.todos-skeleton {
  block-size: 10rem;
}

.todos-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.todos-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-m);
}

.todos-row + .todos-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.todos-icon {
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.todos-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.todos-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.todos-sub {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
