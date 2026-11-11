<script setup lang="ts">
import type { ShareLabels, ShareTask } from '~/utils/share'
import {
  copyToClipboard,
  downloadIcs,
  generateIcsEvent,
  generateIcsTodo,
  generateMarkdown,
  generatePlainText
} from '~/utils/share'

/**
 * Share section with 5 export buttons for external apps.
 * Placed in the task detail panel below the title.
 */
const props = defineProps<{
  task: ShareTask
}>()

const { t, locale } = useI18n()
const toast = useToast()

/** Texts of the exported files, in the UI language. */
const labels = computed<ShareLabels>(() => ({
  locale: locale.value,
  assignee: t('task.share.labels.assignee'),
  status: t('task.share.labels.status'),
  project: t('task.share.labels.project'),
  milestone: t('task.share.labels.milestone'),
  todo: t('task.share.labels.todo'),
  due: t('task.share.labels.due')
}))

async function shareAppleCalendar(): Promise<void> {
  const content = generateIcsEvent(props.task, labels.value)
  downloadIcs(content, `${sanitizeFilename(props.task.title)}.ics`)
}

async function shareAppleReminders(): Promise<void> {
  const content = generateIcsTodo(props.task, labels.value)
  downloadIcs(content, `${sanitizeFilename(props.task.title)}.ics`)
}

async function shareAppleNotes(): Promise<void> {
  const content = generatePlainText(props.task, labels.value)
  await copyToClipboard(content)
  toast.add({ title: t('task.share.copied'), color: 'success' })
}

async function shareObsidian(): Promise<void> {
  const content = generateMarkdown(props.task, labels.value)
  await copyToClipboard(content)
  toast.add({ title: t('task.share.copied'), color: 'success' })
}

async function shareOutlook(): Promise<void> {
  const content = generateIcsEvent(props.task, labels.value)
  downloadIcs(content, `${sanitizeFilename(props.task.title)}.ics`)
}

/** Sanitize a title for use in a filename. */
function sanitizeFilename(title: string): string {
  return title.replace(/[^a-zA-Z0-9äöüÄÖÜß\s-]/g, '').replace(/\s+/g, '-').slice(0, 50) || 'task'
}
</script>

<template>
  <section class="share-section wa-stack wa-gap-xs">
    <h3 class="wa-heading-s share-title">
      {{ t('task.share.shareTitle') }}
    </h3>
    <div class="share-buttons">
      <wa-button
        appearance="outlined"
        variant="neutral"
        size="s"
        @click="shareAppleCalendar"
      >
        <wa-tooltip
          slot="end"
          :content="t('task.share.appleCalendarTooltip')"
          position="top"
        >
          <wa-icon
            name="calendar"
            class="share-icon"
          ></wa-icon>
        </wa-tooltip>
        {{ t('task.share.appleCalendar') }}
      </wa-button>

      <wa-button
        appearance="outlined"
        variant="neutral"
        size="s"
        @click="shareAppleReminders"
      >
        <wa-tooltip
          slot="end"
          :content="t('task.share.appleRemindersTooltip')"
          position="top"
        >
          <wa-icon
            name="clipboard-check"
            class="share-icon"
          ></wa-icon>
        </wa-tooltip>
        {{ t('task.share.appleReminders') }}
      </wa-button>

      <wa-button
        appearance="outlined"
        variant="neutral"
        size="s"
        @click="shareAppleNotes"
      >
        <wa-tooltip
          slot="end"
          :content="t('task.share.appleNotesTooltip')"
          position="top"
        >
          <wa-icon
            name="note-sticky"
            class="share-icon"
          ></wa-icon>
        </wa-tooltip>
        {{ t('task.share.appleNotes') }}
      </wa-button>

      <wa-button
        appearance="outlined"
        variant="neutral"
        size="s"
        @click="shareObsidian"
      >
        <wa-tooltip
          slot="end"
          :content="t('task.share.obsidianTooltip')"
          position="top"
        >
          <wa-icon
            name="book-atlas"
            class="share-icon"
          ></wa-icon>
        </wa-tooltip>
        {{ t('task.share.obsidian') }}
      </wa-button>

      <wa-button
        appearance="outlined"
        variant="neutral"
        size="s"
        @click="shareOutlook"
      >
        <wa-tooltip
          slot="end"
          :content="t('task.share.outlookTooltip')"
          position="top"
        >
          <wa-icon
            name="envelope"
            class="share-icon"
          ></wa-icon>
        </wa-tooltip>
        {{ t('task.share.outlook') }}
      </wa-button>
    </div>
  </section>
</template>

<style scoped>
.share-title {
  margin-block-end: var(--wa-space-2xs);
}

.share-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wa-space-xs);
}

.share-icon {
  margin-inline-end: var(--wa-space-3xs);
}
</style>
