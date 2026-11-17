<script setup lang="ts">
import type { TrashQuery } from '~/graphql/generated/graphql'
import { formatDateTime } from '~/utils/dates'
import { trashSummary } from '~/utils/trash'

/**
 * The entries of a trash, oldest first (docs/auftrag-m2.md §4.2): type,
 * title, what went with it („mit 3 ToDos, 12 Tasks“), who deleted it and
 * when, and the buttons the viewer may use. The page does the actions.
 */
export type TrashEntry = TrashQuery['trash'][number]

defineProps<{
  items: readonly TrashEntry[]
  /** Show the project of each entry (the page of deleted projects does not need it). */
  withProject?: boolean
}>()

const emit = defineEmits<{
  restore: [entry: TrashEntry]
  purge: [entry: TrashEntry]
}>()

const { t, localeProperties } = useI18n()
const language = computed(() => localeProperties.value.language ?? 'de-CH')

const icons: Record<TrashEntry['type'], string> = {
  PROJECT: 'folder',
  MILESTONE: 'flag',
  TODO: 'list-check',
  TASK: 'square-check',
  COMMENT: 'comment'
}

function meta(entry: TrashEntry): string {
  const parts = [t(`trash.types.${entry.type}`)]
  const children = trashSummary(entry.childCounts, (type, count) => t(`trash.count.${type}`, { count }, count))

  if (children) {
    parts.push(t('trash.with', { list: children }))
  }

  return parts.join(' · ')
}

function deleted(entry: TrashEntry): string {
  const at = formatDateTime(entry.deletedAt, language.value)

  return entry.deletedBy ? t('trash.deletedBy', { name: entry.deletedBy.name, at }) : t('trash.deletedAt', { at })
}
</script>

<template>
  <ul class="trash-list wa-list-plain">
    <li
      v-for="entry in items"
      :key="entry.deletionId"
      class="trash-entry"
    >
      <ItemIcon
        :fallback="icons[entry.type]"
        class="trash-icon"
      />
      <div class="trash-text">
        <span class="trash-title">{{ entry.title }}</span>
        <span class="trash-meta">{{ meta(entry) }}</span>
        <span class="trash-meta">
          <template v-if="withProject && entry.project && entry.type !== 'PROJECT'">{{ entry.project.name }} · </template>{{ deleted(entry) }}
        </span>
      </div>
      <div class="wa-cluster wa-gap-xs trash-actions">
        <wa-button
          v-if="entry.viewerCanRestore"
          appearance="outlined"
          variant="neutral"
          size="s"
          @click="emit('restore', entry)"
        >
          <wa-icon
            slot="start"
            name="rotate-left"
          ></wa-icon>
          {{ t('trash.restore') }}
        </wa-button>
        <wa-button
          v-if="entry.viewerCanPurge"
          appearance="plain"
          variant="danger"
          size="s"
          @click="emit('purge', entry)"
        >
          <wa-icon
            slot="start"
            name="trash"
          ></wa-icon>
          {{ t('trash.purge') }}
        </wa-button>
      </div>
    </li>
  </ul>
</template>

<style scoped>
.trash-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.trash-entry {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wa-space-s) var(--wa-space-m);
  align-items: center;
  padding: var(--wa-space-s) var(--wa-space-m);
}

.trash-entry + .trash-entry {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.trash-icon {
  flex: none;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-l);
}

.trash-text {
  display: flex;
  flex: 1 1 14rem;
  flex-direction: column;
  min-inline-size: 0;
}

.trash-title {
  overflow-wrap: anywhere;
  font-weight: var(--wa-font-weight-semibold);
}

.trash-meta {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.trash-actions {
  margin-inline-start: auto;
}
</style>
