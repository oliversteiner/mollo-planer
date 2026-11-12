/**
 * Export utilities for sharing tasks to external apps.
 * All generation happens client-side in the browser.
 */

// ---------------------------------------------------------------------------
// Type for the minimal task data needed for export
// ---------------------------------------------------------------------------

export interface ShareTask {
  id: string
  title: string
  description: Record<string, unknown> | null
  dueDate: string | null
  status: string
  assignee: { id: string, name: string } | null
  todo: { title: string }
  milestone: { title: string }
  project: { name: string }
}

/** UI texts for the export, taken from lang/de and lang/en (task.share.labels). */
export interface ShareLabels {
  /** Locale for dates, e.g. "de" or "en". */
  locale: string
  assignee: string
  status: string
  project: string
  milestone: string
  todo: string
  due: string
}

// ---------------------------------------------------------------------------
// RichText (TipTap JSON) → plain text
// ---------------------------------------------------------------------------

/**
 * Recursively extract plain text from TipTap JSON content.
 * Handles paragraphs, text, bold, italic, links, lists, list items, headings, blockquotes.
 */
export function extractPlainTextFromRichText(richText: Record<string, unknown> | null): string {
  if (!richText || !richText.content || !Array.isArray(richText.content)) {
    return ''
  }

  return extractContentText(richText.content).filter(Boolean).join('\n')
}

function extractContentText(nodes: unknown[]): string[] {
  const results: string[] = []

  for (const node of nodes) {
    if (!isNode(node)) continue

    const type = node.type as string

    switch (type) {
      case 'paragraph':
      case 'heading': {
        // Inline nodes (text, hardBreak) join without a separator: text nodes carry their own spaces.
        const text = extractContentText(node.content ?? []).join('')
        if (text.length > 0) {
          results.push(text)
        }
        break
      }

      case 'text': {
        const text = (node.text as string) ?? ''
        let result = text
        // Links: append URL in brackets
        if (node.marks && Array.isArray(node.marks)) {
          for (const mark of node.marks) {
            if (isMark(mark) && mark.type === 'link' && mark.attrs?.href) {
              result += ` (${mark.attrs.href as string})`
            }
          }
        }
        results.push(result)
        break
      }

      case 'hardBreak':
        results.push('\n')
        break

      case 'bulletList':
      case 'orderedList': {
        const items = node.content ?? []
        for (let i = 0; i < items.length; i++) {
          const item = items[i]
          if (isNode(item) && item.type === 'listItem' && item.content) {
            const itemText = extractContentText(item.content).filter(Boolean).join(' ')
            const prefix = type === 'orderedList' ? `${i + 1}. ` : '• '
            results.push(prefix + itemText)
          }
        }
        break
      }

      case 'blockquote': {
        const text = extractContentText(node.content ?? []).filter(Boolean).join(' ')
        if (text) {
          results.push('> ' + text)
        }
        break
      }

      case 'horizontalRule':
        results.push('---')
        break

      default:
        // Fallback: try to extract text from children
        if (node.content && Array.isArray(node.content)) {
          const text = extractContentText(node.content).filter(Boolean).join(' ')
          if (text) {
            results.push(text)
          }
        }
    }
  }

  return results
}

function isNode(value: unknown): value is { type: string, content?: unknown[], text?: string, marks?: unknown[], attrs?: Record<string, unknown> } {
  return typeof value === 'object' && value !== null && 'type' in value
}

function isMark(value: unknown): value is { type: string, attrs?: Record<string, unknown> } {
  return typeof value === 'object' && value !== null && 'type' in value
}

// ---------------------------------------------------------------------------
// iCal helpers
// ---------------------------------------------------------------------------

/** Format a date string (YYYY-MM-DD) to iCal DATE format (YYYYMMDD). */
function toIcalDate(dateStr: string | null | undefined): string | null {
  if (!dateStr) return null
  // Accept ISO date strings like "2024-01-15" or "2024-01-15T00:00:00.000000Z"
  const match = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!match) return null
  return `${match[1]}${match[2]}${match[3]}`
}

/** Generate a unique UID for iCal components. */
function generateUid(taskId: string): string {
  return `task-${taskId}@mollo-planer`
}

/** Get current timestamp in iCal format. */
function nowTimestamp(): string {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getUTCFullYear()}${pad(d.getUTCMonth() + 1)}${pad(d.getUTCDate())}T${pad(d.getUTCHours())}${pad(d.getUTCMinutes())}${pad(d.getUTCSeconds())}Z`
}

/** Escape special characters in iCal text values. */
function escapeIcalText(text: string): string {
  return text
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/\n/g, '\\n')
}

/**
 * Fold a content line to at most 75 octets (RFC 5545 §3.1): continuation
 * lines start with a space. Characters are never split.
 */
function foldIcalLine(line: string): string {
  const encoder = new TextEncoder()
  const parts: string[] = []
  let current = ''
  let size = 0
  let limit = 75

  for (const char of line) {
    const bytes = encoder.encode(char).length
    if (size + bytes > limit) {
      parts.push(current)
      current = ''
      size = 0
      limit = 74
    }
    current += char
    size += bytes
  }
  parts.push(current)

  return parts.join('\r\n ')
}

// ---------------------------------------------------------------------------
// iCal (VEVENT for calendar apps, VTODO for reminders)
// ---------------------------------------------------------------------------

/** Due date as a readable date in the given locale, e.g. "15.06.2024" for de. */
function formatDueDate(dateStr: string | null | undefined, locale: string): string | null {
  const match = dateStr?.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!match) return null
  const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])))
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeZone: 'UTC' }).format(date)
}

/** One calendar file with the given components (VEVENT or VTODO lines). */
function icsCalendar(components: string[][]): string {
  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Mollo Planer//DE',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    ...components.flat(),
    'END:VCALENDAR'
  ]

  return lines.map(foldIcalLine).join('\r\n')
}

/** VEVENT lines of one task: the due date is the event date. */
function eventComponent(task: ShareTask, labels: ShareLabels): string[] {
  const summary = escapeIcalText(task.title)
  const location = escapeIcalText(`${task.project.name} · ${task.milestone.title} · ${task.todo.title}`)

  // Build the text first, then escape it once: every line break becomes "\n".
  const blocks = [task.title]
  const plainText = extractPlainTextFromRichText(task.description)
  if (plainText) {
    blocks.push(plainText)
  }
  const details = []
  if (task.assignee) {
    details.push(`${labels.assignee}: ${task.assignee.name}`)
  }
  details.push(`${labels.status}: ${task.status}`)
  blocks.push(details.join('\n'))
  const description = escapeIcalText(blocks.join('\n\n'))

  const lines = [
    'BEGIN:VEVENT',
    `UID:${generateUid(task.id)}`,
    `DTSTAMP:${nowTimestamp()}`,
    `SUMMARY:${summary}`,
    `DESCRIPTION:${description}`,
    `LOCATION:${location}`
  ]

  const icalDate = toIcalDate(task.dueDate)
  if (icalDate) {
    lines.push(`DTSTART;VALUE=DATE:${icalDate}`)
  }

  lines.push('END:VEVENT')

  return lines
}

/** VTODO lines of one task for reminder apps. */
function todoComponent(task: ShareTask, labels: ShareLabels): string[] {
  const summary = escapeIcalText(task.title)

  // Build the text first, then escape it once: every line break becomes "\n".
  const blocks: string[] = []
  const plainText = extractPlainTextFromRichText(task.description)
  if (plainText) {
    blocks.push(plainText)
  }
  blocks.push(`${task.project.name} · ${task.milestone.title} · ${task.todo.title}`)
  let description = escapeIcalText(blocks.join('\n\n'))
  if (task.assignee) {
    description += escapeIcalText(`\n${labels.assignee}: ${task.assignee.name}`)
  }

  const lines = [
    'BEGIN:VTODO',
    `UID:${generateUid(task.id)}`,
    `DTSTAMP:${nowTimestamp()}`,
    `SUMMARY:${summary}`,
    `DESCRIPTION:${description}`
  ]

  const icalDate = toIcalDate(task.dueDate)
  if (icalDate) {
    lines.push(`DUE;VALUE=DATE:${icalDate}`)
  }

  lines.push('END:VTODO')

  return lines
}

/** One event per task in a calendar file (Apple Calendar, Outlook). */
export function generateIcsEvents(tasks: ShareTask[], labels: ShareLabels): string {
  return icsCalendar(tasks.map(task => eventComponent(task, labels)))
}

/** One reminder per task (Apple Reminders imports VTODO from .ics files). */
export function generateIcsTodos(tasks: ShareTask[], labels: ShareLabels): string {
  return icsCalendar(tasks.map(task => todoComponent(task, labels)))
}

export function generateIcsEvent(task: ShareTask, labels: ShareLabels): string {
  return generateIcsEvents([task], labels)
}

export function generateIcsTodo(task: ShareTask, labels: ShareLabels): string {
  return generateIcsTodos([task], labels)
}

// ---------------------------------------------------------------------------
// Plain text (for Apple Notes)
// ---------------------------------------------------------------------------

/**
 * Generate plain text for copying to clipboard.
 * Simple format suitable for pasting into Apple Notes.
 */
export function generatePlainText(task: ShareTask, labels: ShareLabels): string {
  const lines: string[] = []

  lines.push(task.title)
  lines.push('─'.repeat(task.title.length))

  const plainText = extractPlainTextFromRichText(task.description)
  if (plainText) {
    lines.push(plainText)
  }

  lines.push('')
  lines.push(`${labels.project}: ${task.project.name}`)
  lines.push(`${labels.milestone}: ${task.milestone.title}`)
  lines.push(`${labels.todo}: ${task.todo.title}`)

  const due = formatDueDate(task.dueDate, labels.locale)
  if (due) {
    lines.push(`${labels.due}: ${due}`)
  }

  if (task.assignee) {
    lines.push(`${labels.assignee}: ${task.assignee.name}`)
  }

  lines.push(`${labels.status}: ${task.status}`)

  return lines.join('\n')
}

/** Several tasks as plain text, one block after another. */
export function generatePlainTextList(tasks: ShareTask[], labels: ShareLabels): string {
  return tasks.map(task => generatePlainText(task, labels)).join('\n\n\n')
}

// ---------------------------------------------------------------------------
// Markdown with YAML frontmatter (for Obsidian)
// ---------------------------------------------------------------------------

/**
 * Generate Markdown with YAML frontmatter for Obsidian.
 * Frontmatter keys are fixed (machine-readable); the body uses the labels.
 */
export function generateMarkdown(task: ShareTask, labels: ShareLabels): string {
  const frontmatterLines: string[] = []
  frontmatterLines.push('---')
  frontmatterLines.push(`title: "${task.title.replace(/"/g, '\\"')}"`)

  const isoDate = task.dueDate?.match(/^\d{4}-\d{2}-\d{2}/)?.[0]
  if (isoDate) {
    frontmatterLines.push(`date: ${isoDate}`)
  }

  frontmatterLines.push(`status: "${task.status}"`)
  frontmatterLines.push(`project: "${task.project.name.replace(/"/g, '\\"')}"`)
  frontmatterLines.push(`milestone: "${task.milestone.title.replace(/"/g, '\\"')}"`)
  frontmatterLines.push(`todo: "${task.todo.title.replace(/"/g, '\\"')}"`)

  if (task.assignee) {
    frontmatterLines.push(`assignee: "${task.assignee.name.replace(/"/g, '\\"')}"`)
  }

  frontmatterLines.push('---')

  const body: string[] = []
  body.push(`# ${task.title}`)

  const plainText = extractPlainTextFromRichText(task.description)
  if (plainText) {
    body.push('')
    body.push(plainText)
  }

  body.push('')
  body.push(`**${labels.project}:** ${task.project.name}`)
  body.push(`**${labels.milestone}:** ${task.milestone.title}`)
  body.push(`**${labels.todo}:** ${task.todo.title}`)

  const due = formatDueDate(task.dueDate, labels.locale)
  if (due) {
    body.push(`**${labels.due}:** ${due}`)
  }

  if (task.assignee) {
    body.push(`**${labels.assignee}:** ${task.assignee.name}`)
  }

  body.push(`**${labels.status}:** ${task.status}`)

  return [...frontmatterLines, '', ...body].join('\n')
}

/** Several tasks as one Markdown note: a checklist, one line per task. */
export function generateMarkdownList(tasks: ShareTask[], labels: ShareLabels): string {
  const items = tasks.map((task) => {
    const parts = [`${task.project.name} · ${task.milestone.title} · ${task.todo.title}`]
    const due = formatDueDate(task.dueDate, labels.locale)
    if (due) {
      parts.push(`${labels.due}: ${due}`)
    }
    if (task.assignee) {
      parts.push(`${labels.assignee}: ${task.assignee.name}`)
    }
    const checked = task.status === 'DONE' ? 'x' : ' '

    return `- [${checked}] ${task.title} (${parts.join(', ')})`
  })

  return items.join('\n')
}

// ---------------------------------------------------------------------------
// Download & clipboard helpers
// ---------------------------------------------------------------------------

/**
 * Trigger a browser download of an .ics file.
 */
export function downloadIcs(content: string, filename: string): void {
  const blob = new Blob([content], { type: 'text/calendar;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

/**
 * Copy text to clipboard with fallback.
 * Returns a promise that resolves on success or rejects on failure.
 */
export async function copyToClipboard(text: string): Promise<void> {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    try {
      await navigator.clipboard.writeText(text)
      return
    } catch {
      // Fallback below
    }
  }

  // Fallback for non-secure contexts
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.style.position = 'fixed'
  textarea.style.left = '-9999px'
  document.body.appendChild(textarea)
  textarea.select()
  try {
    document.execCommand('copy')
  } finally {
    document.body.removeChild(textarea)
  }
}
