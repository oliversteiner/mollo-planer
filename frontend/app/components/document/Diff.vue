<script setup lang="ts">
import { compareDocuments, hasChanges } from '~/utils/document-diff'

/**
 * The difference between two versions of a document as text, line by line and word by word. An added line starts
 * with "+", a removed one with "−", and each has its colour: the sign carries the meaning, the colour only helps.
 * Everything is shown as text (never as HTML).
 */
const props = defineProps<{ older: string, newer: string }>()

const { t } = useI18n()
const rows = computed(() => compareDocuments(props.older, props.newer))
const SIGNS = { same: ' ', added: '+', removed: '−' } as const
</script>

<template>
  <p
    v-if="!hasChanges(rows)"
    class="document-diff-none"
  >
    {{ t('documents.versions.noChanges') }}
  </p>
  <div
    v-else
    class="document-diff"
    role="group"
    :aria-label="t('documents.versions.diff')"
    tabindex="0"
  >
    <div
      v-for="(row, index) in rows"
      :key="index"
      class="document-diff-line"
      :data-kind="row.kind"
    >
      <span
        class="document-diff-sign"
        :title="row.kind === 'added' ? t('documents.versions.added') : row.kind === 'removed' ? t('documents.versions.removed') : undefined"
      >{{ SIGNS[row.kind] }}</span>
      <span class="document-diff-text"><template
        v-for="(segment, position) in row.segments"
        :key="position"
      ><mark
        v-if="segment.changed && row.kind !== 'same' && row.segments.length > 1"
        class="document-diff-word"
      >{{ segment.text }}</mark><template v-else>{{ segment.text }}</template></template></span>
    </div>
  </div>
</template>

<style scoped>
.document-diff {
  overflow-x: auto;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
  font-family: var(--wa-font-family-code);
  font-size: var(--wa-font-size-s);
}

.document-diff:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.document-diff-none {
  margin: 0;
  color: var(--wa-color-text-quiet);
}

.document-diff-line {
  display: flex;
  gap: var(--wa-space-xs);
  padding-inline: var(--wa-space-xs);
  white-space: pre-wrap;
}

.document-diff-line[data-kind='added'] {
  background-color: var(--wa-color-success-fill-quiet);
  color: var(--wa-color-success-on-quiet);
}

.document-diff-line[data-kind='removed'] {
  background-color: var(--wa-color-danger-fill-quiet);
  color: var(--wa-color-danger-on-quiet);
}

.document-diff-sign {
  flex: none;
  inline-size: 1ch;
  font-weight: var(--wa-font-weight-semibold);
}

.document-diff-text {
  flex: 1;
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.document-diff-word {
  background-color: transparent;
  color: inherit;
  font-weight: var(--wa-font-weight-semibold);
  text-decoration: underline;
}
</style>
