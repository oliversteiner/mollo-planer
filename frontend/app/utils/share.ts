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

// ---------------------------------------------------------------------------
// iCal VEVENT (for Apple Calendar, Outlook)
// ---------------------------------------------------------------------------

/**
 * Generate an iCal VEVENT string for calendar apps.
 * Uses the task's dueDate as the event date.
 */
export function generateIcsEvent(task: ShareTask): string {
  const uid = generateUid(task.id)
  const timestamp = nowTimestamp()
  const summary = escapeIcalText(task.title)
  const location = escapeIcalText(`${task.project.name} · ${task.milestone.title} · ${task.todo.title}`)

  let description = summary
  const plainText = extractPlainTextFromRichText(task.description)
  if (plainText) {
    description += '\n\n' + escapeIcalText(plainText)
  }
  if (task.assignee) {
    description += '\n\nZugewiesen: ' + escapeIcalText(task.assignee.name)
  }
  description += '\n\nStatus: ' + escapeIcalText(task.status)

  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Mollo Planer//DE',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    `UID:${uid}`,
    `DTSTAMP:${timestamp}`,
    `SUMMARY:${summary}`,
    `DESCRIPTION:${description}`,
    `LOCATION:${location}`
  ]

  const icalDate = toIcalDate(task.dueDate)
  if (icalDate) {
    lines.push(`DTSTART;VALUE=DATE:${icalDate}`)
  }

  lines.push('END:VEVENT')
  lines.push('END:VCALENDAR')

  return lines.join('\r\n')
}

// ---------------------------------------------------------------------------
// iCal VTODO (for Apple Reminders)
// ---------------------------------------------------------------------------

/**
 * Generate an iCal VTODO string for reminder apps.
 * Apple Reminders imports VTODO components from .ics files.
 */
export function generateIcsTodo(task: ShareTask): string {
  const uid = generateUid(task.id)
  const timestamp = nowTimestamp()
  const summary = escapeIcalText(task.title)

  let description = ''
  const plainText = extractPlainTextFromRichText(task.description)
  if (plainText) {
    description += escapeIcalText(plainText)
  }
  description += `\n\n${escapeIcalText(task.project.name)} · ${escapeIcalText(task.milestone.title)} · ${escapeIcalText(task.todo.title)}`
  if (task.assignee) {
    description += `\nZugewiesen: ${escapeIcalText(task.assignee.name)}`
  }

  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Mollo Planer//DE',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VTODO',
    `UID:${uid}`,
    `DTSTAMP:${timestamp}`,
    `SUMMARY:${summary}`
  ]

  if (description) {
    lines.push(`DESCRIPTION:${description}`)
  }

  const icalDate = toIcalDate(task.dueDate)
  if (icalDate) {
    lines.push(`DUE;VALUE=DATE:${icalDate}`)
  }

  lines.push('END:VTODO')
  lines.push('END:VCALENDAR')

  return lines.join('\r\n')
}

// ---------------------------------------------------------------------------
// Plain text (for Apple Notes)
// ---------------------------------------------------------------------------

/**
 * Generate plain text for copying to clipboard.
 * Simple format suitable for pasting into Apple Notes.
 */
export function generatePlainText(task: ShareTask): string {
  const lines: string[] = []

  lines.push(task.title)
  lines.push(''.repeat(task.title.length) ? '─'.repeat(task.title.length) : '')

  const plainText = extractPlainTextFromRichText(task.description)
  if (plainText) {
    lines.push(plainText)
  }

  lines.push('')
  lines.push(`Projekt: ${task.project.name}`)
  lines.push(`Milestone: ${task.milestone.title}`)
  lines.push(`ToDo: ${task.todo.title}`)

  if (task.dueDate) {
    const dateMatch = task.dueDate.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (dateMatch) {
      lines.push(`Fällig: ${dateMatch[3]}.${dateMatch[2]}.${dateMatch[1]}`)
    }
  }

  if (task.assignee) {
    lines.push(`Zugewiesen: ${task.assignee.name}`)
  }

  lines.push(`Status: ${task.status}`)

  return lines.join('\n')
}

// ---------------------------------------------------------------------------
// Markdown with YAML frontmatter (for Obsidian)
// ---------------------------------------------------------------------------

/**
 * Generate Markdown with YAML frontmatter for Obsidian.
 * Frontmatter contains title, date, status, project, milestone, todo, assignee.
 */
export function generateMarkdown(task: ShareTask): string {
  const frontmatterLines: string[] = []
  frontmatterLines.push('---')
  frontmatterLines.push(`title: "${task.title.replace(/"/g, '\\"')}"`)

  if (task.dueDate) {
    const dateMatch = task.dueDate.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (dateMatch) {
      frontmatterLines.push(`date: ${dateMatch[0]}`)
    }
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
  body.push(`**Projekt:** ${task.project.name}`)
  body.push(`**Milestone:** ${task.milestone.title}`)
  body.push(`**ToDo:** ${task.todo.title}`)

  if (task.dueDate) {
    const dateMatch = task.dueDate.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (dateMatch) {
      body.push(`**Fällig:** ${dateMatch[3]}.${dateMatch[2]}.${dateMatch[1]}`)
    }
  }

  if (task.assignee) {
    body.push(`**Zugewiesen:** ${task.assignee.name}`)
  }

  body.push(`**Status:** ${task.status}`)

  return [...frontmatterLines, '', ...body].join('\n')
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
