<script setup lang="ts">
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
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
 * Share menu for external apps: one task (detail panel) or the whole list
 * currently shown (My Tasks, after filtering). `name` is the base of the file
 * name for calendar exports.
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

/** The entries of the menu. Without tasks they are shown but disabled. */
const shareItems = computed<ActionMenuItem[]>(() => [
  { label: t('task.share.appleCalendar'), icon: 'calendar', disabled: !hasTasks.value, onSelect: shareAppleCalendar },
  { label: t('task.share.appleReminders'), icon: 'clipboard-check', disabled: !hasTasks.value, onSelect: shareAppleReminders },
  { label: t('task.share.appleNotes'), icon: 'note-sticky', disabled: !hasTasks.value, onSelect: shareAppleNotes },
  { label: t('task.share.obsidian'), icon: 'book-atlas', disabled: !hasTasks.value, onSelect: shareObsidian },
  { label: t('task.share.outlook'), icon: 'envelope', disabled: !hasTasks.value, onSelect: shareOutlook }
])

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
    <ActionMenu
      :items="shareItems"
      :label="t('task.share.shareTitle')"
      :text="t('task.share.menu')"
      icon="share-from"
      :disabled="!hasTasks"
      placement="bottom-start"
    />
  </section>
</template>

<style scoped>
.share-title {
  margin-block-end: var(--wa-space-2xs);
}
</style>
