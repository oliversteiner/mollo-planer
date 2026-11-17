<script setup lang="ts">
import type { PlanerChange, PlanerDocument, PlanerLabel } from '~/utils/planer'

/**
 * A planer-v2 document as a tree: milestones, their todos and the tasks in
 * them with status, due date, assignee, labels, story and release. With the
 * changes of an import (dry run) every changed place is marked: a text and an
 * icon besides the colour (new, changed, moved). What the import would delete
 * is listed below, by id.
 */
const props = defineProps<{
  document: PlanerDocument
  /** ImportResult.changes; without them the tree only shows the plan. */
  changes?: readonly PlanerChange[]
}>()

const { t, locale } = useI18n()

const labels = computed(() => new Map((props.document.project.labels ?? []).map(label => [label.key, label])))

function labelName(key: string): string {
  const label: PlanerLabel | undefined = labels.value.get(key)

  return label?.names[locale.value] ?? label?.names[props.document.project.locale] ?? key
}

/** The mark of a place: moved wins over changed only when nothing else changed. */
const marks = computed(() => {
  const byPath = new Map<string, { kind: 'create' | 'update' | 'move', fields: string[] }>()

  for (const change of props.changes ?? []) {
    if (change.kind === 'DELETE') {
      continue
    }

    const kind = change.kind.toLowerCase() as 'create' | 'update' | 'move'
    const known = byPath.get(change.path)

    // A place both changed and moved shows "changed" with all its fields.
    byPath.set(change.path, known
      ? { kind: known.kind === 'update' || kind === 'update' ? 'update' : known.kind, fields: [...known.fields, ...change.fields] }
      : { kind, fields: [...change.fields] })
  }

  return byPath
})

const removed = computed(() => (props.changes ?? []).filter(change => change.kind === 'DELETE'))

function mark(path: string) {
  return marks.value.get(path) ?? null
}

function removedLabel(id: string | null | undefined): string {
  const kind = id?.startsWith('m-') ? 'milestone' : id?.startsWith('t-') ? 'todo' : id?.startsWith('k-') ? 'task' : 'member'

  return t(`planer.removed.${kind}`, { id: id ?? '' })
}

function statusKey(status: string) {
  return status.toUpperCase() as 'IDEA' | 'PLANNED' | 'OPEN' | 'IN_PROGRESS' | 'REVIEW' | 'DONE'
}
</script>

<template>
  <div class="wa-stack wa-gap-s">
    <wa-tree
      class="planer-tree"
      :aria-label="t('planer.tree')"
    >
      <wa-tree-item
        v-for="(milestone, m) in document.milestones"
        :key="milestone.id"
        expanded
      >
        <wa-icon name="flag"></wa-icon>
        {{ milestone.title }}
        <wa-badge
          v-if="milestone.release"
          variant="neutral"
          appearance="outlined"
        >
          {{ milestone.release.version }} · {{ t(`release.${milestone.release.status}`) }}
        </wa-badge>
        <DueDate :date="milestone.dueDate" />
        <PlanerMark
          v-if="mark(`milestones[${m}]`)"
          :mark="mark(`milestones[${m}]`)!"
        />
        <wa-tree-item
          v-for="(todo, d) in milestone.todos"
          :key="todo.id"
          expanded
        >
          <wa-icon name="list-check"></wa-icon>
          {{ todo.title }}
          <DueDate :date="todo.dueDate" />
          <PlanerMark
            v-if="mark(`milestones[${m}].todos[${d}]`)"
            :mark="mark(`milestones[${m}].todos[${d}]`)!"
          />
          <wa-tree-item
            v-for="(task, k) in todo.tasks"
            :key="task.id"
          >
            <wa-icon name="square-check"></wa-icon>
            {{ task.title }}
            <StatusBadge :status="statusKey(task.status)" />
            <DueDate
              :date="task.dueDate"
              :done="task.status === 'done'"
            />
            <span
              v-if="task.assigneeEmail"
              class="planer-tree-quiet"
            >{{ task.assigneeEmail }}</span>
            <span
              v-if="task.labels?.length"
              class="planer-tree-quiet"
            >{{ task.labels.map(labelName).join(', ') }}</span>
            <PlanerMark
              v-if="mark(`milestones[${m}].todos[${d}].tasks[${k}]`)"
              :mark="mark(`milestones[${m}].todos[${d}].tasks[${k}]`)!"
            />
          </wa-tree-item>
        </wa-tree-item>
      </wa-tree-item>
    </wa-tree>

    <p
      v-if="!document.milestones.length"
      class="planer-tree-quiet"
    >
      {{ t('planer.empty') }}
    </p>

    <section
      v-if="removed.length"
      class="wa-stack wa-gap-2xs"
    >
      <h3 class="wa-heading-s">
        {{ t('planer.willDelete', removed.length) }}
      </h3>
      <ul class="planer-removed wa-list-plain">
        <li
          v-for="change in removed"
          :key="change.path"
        >
          <wa-badge variant="danger">
            <wa-icon
              slot="start"
              name="trash"
            ></wa-icon>
            {{ t('planer.mark.delete') }}
          </wa-badge>
          {{ removedLabel(change.id) }}
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.planer-tree {
  --indent-guide-width: 1px;
}

.planer-tree-quiet {
  margin-inline-start: var(--wa-space-xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.planer-removed {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-2xs);
  margin: 0;
  padding: 0;
  font-size: var(--wa-font-size-s);
}
</style>
