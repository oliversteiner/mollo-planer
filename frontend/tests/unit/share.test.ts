import { describe, expect, it } from 'vitest'
import {
  extractPlainTextFromRichText,
  generateIcsEvent,
  generateIcsEvents,
  generateIcsTodos,
  generateMarkdownList,
  generatePlainTextList,
  generateIcsTodo,
  generateMarkdown,
  generatePlainText,
  type ShareLabels,
  type ShareTask
} from '../../app/utils/share'
import de from '../../i18n/locales/de.json'
import en from '../../i18n/locales/en.json'

const labels: ShareLabels = { locale: 'de', ...de.task.share.labels }

const sampleTask: ShareTask = {
  id: '1',
  title: 'Test Task',
  description: {
    type: 'doc',
    content: [
      {
        type: 'paragraph',
        content: [
          { type: 'text', text: 'Hello world' }
        ]
      }
    ]
  },
  dueDate: '2024-06-15',
  status: 'OPEN',
  assignee: { id: '1', name: 'Max Mustermann' },
  todo: { title: 'Todo Title' },
  milestone: { title: 'Milestone Title' },
  project: { name: 'Project Name' }
}

const taskWithoutDate: ShareTask = {
  ...sampleTask,
  dueDate: null
}

const taskWithoutAssignee: ShareTask = {
  ...sampleTask,
  assignee: null
}

const taskWithoutDescription: ShareTask = {
  ...sampleTask,
  description: null
}

describe('extractPlainTextFromRichText', () => {
  it('joins inline text nodes of one paragraph without a separator', () => {
    const richText = {
      type: 'doc',
      content: [
        {
          type: 'paragraph',
          content: [
            { type: 'text', text: 'Hallo ' },
            { type: 'text', text: 'Welt', marks: [{ type: 'bold' }] },
            { type: 'text', text: '!' }
          ]
        }
      ]
    }

    expect(extractPlainTextFromRichText(richText)).toBe('Hallo Welt!')
  })

  it('extracts text from a simple paragraph', () => {
    const richText = {
      type: 'doc',
      content: [
        {
          type: 'paragraph',
          content: [{ type: 'text', text: 'Hello world' }]
        }
      ]
    }
    expect(extractPlainTextFromRichText(richText)).toBe('Hello world')
  })

  it('returns empty string for null content', () => {
    expect(extractPlainTextFromRichText(null)).toBe('')
  })

  it('returns empty string for empty document', () => {
    expect(extractPlainTextFromRichText({ type: 'doc', content: [] })).toBe('')
  })

  it('extracts multiple paragraphs', () => {
    const richText = {
      type: 'doc',
      content: [
        { type: 'paragraph', content: [{ type: 'text', text: 'First' }] },
        { type: 'paragraph', content: [{ type: 'text', text: 'Second' }] }
      ]
    }
    expect(extractPlainTextFromRichText(richText)).toBe('First\nSecond')
  })

  it('extracts bullet list items', () => {
    const richText = {
      type: 'doc',
      content: [
        {
          type: 'bulletList',
          content: [
            { type: 'listItem', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Item 1' }] }] },
            { type: 'listItem', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Item 2' }] }] }
          ]
        }
      ]
    }
    const result = extractPlainTextFromRichText(richText)
    expect(result).toContain('• Item 1')
    expect(result).toContain('• Item 2')
  })

  it('extracts ordered list items', () => {
    const richText = {
      type: 'doc',
      content: [
        {
          type: 'orderedList',
          content: [
            { type: 'listItem', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'First' }] }] },
            { type: 'listItem', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Second' }] }] }
          ]
        }
      ]
    }
    const result = extractPlainTextFromRichText(richText)
    expect(result).toContain('1. First')
    expect(result).toContain('2. Second')
  })

  it('appends link URL in brackets', () => {
    const richText = {
      type: 'doc',
      content: [
        {
          type: 'paragraph',
          content: [
            {
              type: 'text',
              text: 'Click here',
              marks: [{ type: 'link', attrs: { href: 'https://example.com' } }]
            }
          ]
        }
      ]
    }
    expect(extractPlainTextFromRichText(richText)).toBe('Click here (https://example.com)')
  })

  it('extracts blockquote', () => {
    const richText = {
      type: 'doc',
      content: [
        {
          type: 'blockquote',
          content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Quote' }] }]
        }
      ]
    }
    expect(extractPlainTextFromRichText(richText)).toBe('> Quote')
  })
})

describe('generateIcsEvent', () => {
  it('generates a valid VEVENT with all fields', () => {
    const ics = generateIcsEvent(sampleTask, labels)

    expect(ics).toContain('BEGIN:VCALENDAR')
    expect(ics).toContain('BEGIN:VEVENT')
    expect(ics).toContain('END:VEVENT')
    expect(ics).toContain('END:VCALENDAR')
    expect(ics).toContain('SUMMARY:Test Task')
    expect(ics).toContain('DTSTART;VALUE=DATE:20240615')
    expect(ics).toContain('LOCATION:Project Name')
    expect(ics).toContain('task-1@mollo-planer')
  })

  it('omits DTSTART when there is no due date', () => {
    const ics = generateIcsEvent(taskWithoutDate, labels)
    expect(ics).not.toContain('DTSTART')
  })

  it('includes assignee in description', () => {
    const ics = generateIcsEvent(sampleTask, labels)
    expect(ics).toContain('Max Mustermann')
  })

  it('works without assignee', () => {
    const ics = generateIcsEvent(taskWithoutAssignee, labels)
    expect(ics).toContain('BEGIN:VEVENT')
    expect(ics).not.toContain('Zugewiesen')
  })

  it('works without description', () => {
    const ics = generateIcsEvent(taskWithoutDescription, labels)
    expect(ics).toContain('BEGIN:VEVENT')
    expect(ics).toContain('SUMMARY:Test Task')
  })
})

describe('generateIcsTodo', () => {
  it('generates a valid VTODO with all fields', () => {
    const ics = generateIcsTodo(sampleTask, labels)

    expect(ics).toContain('BEGIN:VCALENDAR')
    expect(ics).toContain('BEGIN:VTODO')
    expect(ics).toContain('END:VTODO')
    expect(ics).toContain('END:VCALENDAR')
    expect(ics).toContain('SUMMARY:Test Task')
    expect(ics).toContain('DUE;VALUE=DATE:20240615')
  })

  it('omits DUE when there is no due date', () => {
    const ics = generateIcsTodo(taskWithoutDate, labels)
    expect(ics).not.toContain('DUE')
  })

  it('includes project context in description', () => {
    const ics = generateIcsTodo(sampleTask, labels)
    expect(ics).toContain('Project Name')
    expect(ics).toContain('Milestone Title')
    expect(ics).toContain('Todo Title')
  })
})

describe('generatePlainText', () => {
  it('generates plain text with all fields', () => {
    const text = generatePlainText(sampleTask, labels)

    expect(text).toContain('Test Task')
    expect(text).toContain('Hello world')
    expect(text).toContain('Projekt: Project Name')
    expect(text).toContain('Milestone: Milestone Title')
    expect(text).toContain('ToDo: Todo Title')
    expect(text).toContain('Fällig: 15.06.2024')
    expect(text).toContain('Zugewiesen: Max Mustermann')
    expect(text).toContain('Status: OPEN')
  })

  it('omits due date when not set', () => {
    const text = generatePlainText(taskWithoutDate, labels)
    expect(text).not.toContain('Fällig')
  })

  it('omits assignee when not set', () => {
    const text = generatePlainText(taskWithoutAssignee, labels)
    expect(text).not.toContain('Zugewiesen')
  })

  it('works without description', () => {
    const text = generatePlainText(taskWithoutDescription, labels)
    expect(text).toContain('Test Task')
    expect(text).toContain('Projekt: Project Name')
  })
})

describe('export labels', () => {
  const englishLabels: ShareLabels = { locale: 'en', ...en.task.share.labels }

  it('uses the given labels and locale instead of fixed German texts', () => {
    const text = generatePlainText(sampleTask, englishLabels)

    expect(text).toContain('Project: Project Name')
    expect(text).toContain('Due: Jun 15, 2024')
    expect(text).not.toContain('Projekt')
  })

  it('formats the due date for German labels as DD.MM.YYYY', () => {
    expect(generatePlainText(sampleTask, labels)).toContain('Fällig: 15.06.2024')
  })
})

describe('generateMarkdown', () => {
  it('generates Markdown with YAML frontmatter', () => {
    const md = generateMarkdown(sampleTask, labels)

    expect(md).toContain('---')
    expect(md).toContain('title: "Test Task"')
    expect(md).toContain('date: 2024-06-15')
    expect(md).toContain('status: "OPEN"')
    expect(md).toContain('project: "Project Name"')
    expect(md).toContain('milestone: "Milestone Title"')
    expect(md).toContain('todo: "Todo Title"')
    expect(md).toContain('assignee: "Max Mustermann"')
    expect(md).toContain('# Test Task')
    expect(md).toContain('Hello world')
  })

  it('omits date in frontmatter when not set', () => {
    const md = generateMarkdown(taskWithoutDate, labels)
    expect(md).not.toContain('date:')
  })

  it('omits assignee in frontmatter when not set', () => {
    const md = generateMarkdown(taskWithoutAssignee, labels)
    expect(md).not.toContain('assignee:')
  })

  it('escapes double quotes in title', () => {
    const task = { ...sampleTask, title: 'Test "quoted"' }
    const md = generateMarkdown(task, labels)
    expect(md).toContain('title: "Test \\"quoted\\""')
  })

  it('works without description', () => {
    const md = generateMarkdown(taskWithoutDescription, labels)
    expect(md).toContain('# Test Task')
    expect(md).toContain('**Projekt:** Project Name')
  })
})

/** Undo RFC 5545 folding: a line break followed by one space continues the line. */
function unfold(ics: string): string {
  return ics.replace(/\r\n /g, '')
}

describe('iCal line handling', () => {
  it('escapes line breaks inside DESCRIPTION instead of writing raw ones', () => {
    const ics = unfold(generateIcsEvent(sampleTask, labels))
    const description = ics.split('\r\n').find(line => line.startsWith('DESCRIPTION:')) ?? ''

    expect(description).toContain('Hello world\\n\\nZugewiesen: Max Mustermann\\nStatus: OPEN')
    expect(ics.split('\r\n').every(line => /^[A-Z]+[;:]/.test(line) || line === '')).toBe(true)
  })

  it('escapes line breaks in VTODO DESCRIPTION', () => {
    const ics = unfold(generateIcsTodo(sampleTask, labels))
    const description = ics.split('\r\n').find(line => line.startsWith('DESCRIPTION:')) ?? ''

    expect(description).toBe('DESCRIPTION:Hello world\\n\\nProject Name · Milestone Title · Todo Title\\nZugewiesen: Max Mustermann')
  })

  it('escapes commas and semicolons in SUMMARY', () => {
    const ics = generateIcsEvent({ ...sampleTask, title: 'Angebot, Entwurf; neu' }, labels)

    expect(ics).toContain('SUMMARY:Angebot\\, Entwurf\\; neu')
  })

  it('folds lines longer than 75 octets and never splits a character', () => {
    const longTitle = 'Ä'.repeat(80)
    const ics = generateIcsEvent({ ...sampleTask, title: longTitle }, labels)

    for (const line of ics.split('\r\n')) {
      expect(new TextEncoder().encode(line).length).toBeLessThanOrEqual(75)
    }
    expect(unfold(ics)).toContain(`SUMMARY:${longTitle}`)
  })
})

describe('list exports (several tasks)', () => {
  const second: ShareTask = { ...sampleTask, id: '2', title: 'Zweiter Task', status: 'DONE', dueDate: null }

  it('puts every task into one calendar file', () => {
    const ics = unfold(generateIcsEvents([sampleTask, second], labels))

    expect(ics.match(/BEGIN:VCALENDAR/g)).toHaveLength(1)
    expect(ics.match(/BEGIN:VEVENT/g)).toHaveLength(2)
    expect(ics).toContain('UID:task-2@mollo-planer')
  })

  it('puts every task into one reminder file', () => {
    const ics = unfold(generateIcsTodos([sampleTask, second], labels))

    expect(ics.match(/BEGIN:VTODO/g)).toHaveLength(2)
    expect(ics).toContain('SUMMARY:Zweiter Task')
  })

  it('joins plain text blocks of all tasks', () => {
    const text = generatePlainTextList([sampleTask, second], labels)

    expect(text).toContain('Test Task')
    expect(text).toContain('Zweiter Task')
    expect(text.indexOf('Test Task')).toBeLessThan(text.indexOf('Zweiter Task'))
  })

  it('writes a Markdown checklist with one line per task, checked when done', () => {
    const md = generateMarkdownList([sampleTask, second], labels)
    const lines = md.split('\n')

    expect(lines).toHaveLength(2)
    expect(lines[0]).toMatch(/^- \[ \] Test Task \(Project Name · Milestone Title · Todo Title, Fällig: 15.06.2024/)
    expect(lines[1]).toMatch(/^- \[x\] Zweiter Task/)
  })

  it('single-task functions still produce one calendar file', () => {
    expect(unfold(generateIcsEvent(sampleTask, labels)).match(/BEGIN:VEVENT/g)).toHaveLength(1)
  })
})
