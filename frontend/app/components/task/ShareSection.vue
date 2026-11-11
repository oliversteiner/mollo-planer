<script setup lang="ts">
import type { ShareLabels, ShareTask } from '~/utils/share'
import {
  copyToClipboard,
  downloadIcs,
  generateIcsEvents,
  generateIcsTodos,
  generateMarkdown,
  generateMarkdownList,
  generatePlainText,
  generatePlainTextList
} from '~/utils/share'

/**
 * Share buttons for external apps: one task (detail panel) or the whole
 * list currently shown (My Tasks, after filtering). `name` is the base of
 * the file name for calendar exports.
 */
const props = withDefaults(defineProps<{
  tasks: ShareTask[]
  name: string
  heading?: boolean
}>(), { heading: true })

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

const hasTasks = computed(() => props.tasks.length > 0)

/** Calendar file name, e.g. "Mockup-Desktop.ics". */
const fileName = computed(() => `${sanitizeFilename(props.name)}.ics`)

/** A single task keeps its own format (frontmatter for Obsidian); several tasks become a list. */
function textFor(single: () => string, list: () => string): string {
  return props.tasks.length === 1 ? single() : list()
}

async function shareAppleCalendar(): Promise<void> {
  downloadIcs(generateIcsEvents(props.tasks, labels.value), fileName.value)
}

async function shareAppleReminders(): Promise<void> {
  downloadIcs(generateIcsTodos(props.tasks, labels.value), fileName.value)
}

async function shareAppleNotes(): Promise<void> {
  const content = textFor(
    () => generatePlainText(props.tasks[0]!, labels.value),
    () => generatePlainTextList(props.tasks, labels.value)
  )
  await copyToClipboard(content)
  toast.add({ title: t('task.share.copied'), color: 'success' })
}

async function shareObsidian(): Promise<void> {
  const content = textFor(
    () => generateMarkdown(props.tasks[0]!, labels.value),
    () => generateMarkdownList(props.tasks, labels.value)
  )
  await copyToClipboard(content)
  toast.add({ title: t('task.share.copied'), color: 'success' })
}

async function shareOutlook(): Promise<void> {
  downloadIcs(generateIcsEvents(props.tasks, labels.value), fileName.value)
}

/** Sanitize a name for use in a filename. */
function sanitizeFilename(name: string): string {
  return name.replace(/[^a-zA-Z0-9äöüÄÖÜß\s-]/g, '').replace(/\s+/g, '-').slice(0, 50) || 'tasks'
}
</script>

<template>
  <section class="share-section wa-stack wa-gap-xs">
    <h3
      v-if="heading"
      class="wa-heading-s share-title"
    >
      {{ t('task.share.shareTitle') }}
    </h3>
    <div class="share-buttons">
      <wa-button
        appearance="outlined"
        variant="neutral"
        size="s"
        :disabled="!hasTasks"
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
        :disabled="!hasTasks"
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
        :disabled="!hasTasks"
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
        :disabled="!hasTasks"
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
        :disabled="!hasTasks"
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
