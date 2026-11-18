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
  /** The assistant's preview: new elements get a checkbox to strike them out (docs/auftrag-m6.md §4.4). */
  strikable?: boolean
  /** Ids of the struck elements. */
  struck?: ReadonlySet<string>
}>()

const emit = defineEmits<{ toggle: [id: string] }>()

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

/** "Task «Titel»"; without a title (a member) the id. */
function removedLabel(change: PlanerChange): string {
  const id = change.id
  const kind = id?.startsWith('m-') ? 'milestone' : id?.startsWith('t-') ? 'todo' : id?.startsWith('k-') ? 'task' : 'member'

  return t(`planer.removed.${kind}`, { name: change.title || (id ?? '') })
}

function strikes(id: string): boolean {
  return props.strikable === true && id.includes('-new-')
}

function isStruck(id: string): boolean {
  return props.struck?.has(id) ?? false
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
        <span class="planer-row">
          <span class="planer-head">
            <wa-checkbox
              v-if="strikes(milestone.id)"
              size="s"
              :checked="!isStruck(milestone.id)"
              :aria-label="t('planer.keep', { name: milestone.title })"
              @click.stop
              @change="emit('toggle', milestone.id)"
            ></wa-checkbox>
            <wa-icon name="flag"></wa-icon>
            <span
              class="planer-title"
              :data-struck="isStruck(milestone.id) ? '' : undefined"
            >{{ milestone.title }}</span>
            <PlanerMark
              v-if="mark(`milestones[${m}]`)"
              :mark="mark(`milestones[${m}]`)!"
            />
          </span>
          <span
            v-if="milestone.release || milestone.dueDate"
            class="planer-meta"
          >
            <wa-badge
              v-if="milestone.release"
              variant="neutral"
              appearance="outlined"
            >
              {{ milestone.release.version }} · {{ t(`release.${milestone.release.status}`) }}
            </wa-badge>
            <DueDate :date="milestone.dueDate" />
          </span>
        </span>
        <wa-tree-item
          v-for="(todo, d) in milestone.todos"
          :key="todo.id"
          expanded
        >
          <span class="planer-row">
            <span class="planer-head">
              <wa-checkbox
                v-if="strikes(todo.id)"
                size="s"
                :checked="!isStruck(todo.id)"
                :aria-label="t('planer.keep', { name: todo.title })"
                @click.stop
                @change="emit('toggle', todo.id)"
              ></wa-checkbox>
              <wa-icon name="list-check"></wa-icon>
              <span
                class="planer-title"
                :data-struck="isStruck(todo.id) ? '' : undefined"
              >{{ todo.title }}</span>
              <PlanerMark
                v-if="mark(`milestones[${m}].todos[${d}]`)"
                :mark="mark(`milestones[${m}].todos[${d}]`)!"
              />
            </span>
            <span
              v-if="todo.dueDate"
              class="planer-meta"
            >
              <DueDate :date="todo.dueDate" />
            </span>
          </span>
          <wa-tree-item
            v-for="(task, k) in todo.tasks"
            :key="task.id"
          >
            <span class="planer-row">
              <span class="planer-head">
                <wa-checkbox
                  v-if="strikes(task.id)"
                  size="s"
                  :checked="!isStruck(task.id)"
                  :aria-label="t('planer.keep', { name: task.title })"
                  @click.stop
                  @change="emit('toggle', task.id)"
                ></wa-checkbox>
                <wa-icon name="square-check"></wa-icon>
                <span
                  class="planer-title"
                  :data-struck="isStruck(task.id) ? '' : undefined"
                >{{ task.title }}</span>
                <PlanerMark
                  v-if="mark(`milestones[${m}].todos[${d}].tasks[${k}]`)"
                  :mark="mark(`milestones[${m}].todos[${d}].tasks[${k}]`)!"
                />
              </span>
              <span class="planer-meta">
                <StatusBadge :status="statusKey(task.status)" />
                <DueDate
                  :date="task.dueDate"
                  :done="task.status === 'done'"
                />
                <span v-if="task.assigneeEmail">{{ task.assigneeEmail }}</span>
                <span v-if="task.labels?.length">{{ task.labels.map(labelName).join(', ') }}</span>
              </span>
            </span>
          </wa-tree-item>
        </wa-tree-item>
      </wa-tree-item>
    </wa-tree>

    <p
      v-if="!document.milestones.length"
      class="planer-quiet"
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
          {{ removedLabel(change) }}
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.planer-tree {
  --indent-guide-width: 1px;
  max-inline-size: 100%;
}

/* A row wraps: the title and its mark on the first line, the details on the second. */
.planer-row {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-3xs);
  min-inline-size: 0;
  white-space: normal;
}

.planer-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
}

.planer-title {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.planer-title[data-struck] {
  color: var(--wa-color-text-quiet);
  text-decoration: line-through;
}

.planer-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs) var(--wa-space-s);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.planer-quiet {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
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
