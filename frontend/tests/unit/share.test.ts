import { describe, expect, it } from 'vitest'
import {
  extractPlainTextFromRichText,
  generateIcsEvent,
  generateIcsTodo,
  generateMarkdown,
  generatePlainText,
  type ShareTask
} from '../../app/utils/share'

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
    const ics = generateIcsEvent(sampleTask)

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
    const ics = generateIcsEvent(taskWithoutDate)
    expect(ics).not.toContain('DTSTART')
  })

  it('includes assignee in description', () => {
    const ics = generateIcsEvent(sampleTask)
    expect(ics).toContain('Max Mustermann')
  })

  it('works without assignee', () => {
    const ics = generateIcsEvent(taskWithoutAssignee)
    expect(ics).toContain('BEGIN:VEVENT')
    expect(ics).not.toContain('Zugewiesen')
  })

  it('works without description', () => {
    const ics = generateIcsEvent(taskWithoutDescription)
    expect(ics).toContain('BEGIN:VEVENT')
    expect(ics).toContain('SUMMARY:Test Task')
  })
})

describe('generateIcsTodo', () => {
  it('generates a valid VTODO with all fields', () => {
    const ics = generateIcsTodo(sampleTask)

    expect(ics).toContain('BEGIN:VCALENDAR')
    expect(ics).toContain('BEGIN:VTODO')
    expect(ics).toContain('END:VTODO')
    expect(ics).toContain('END:VCALENDAR')
    expect(ics).toContain('SUMMARY:Test Task')
    expect(ics).toContain('DUE;VALUE=DATE:20240615')
  })

  it('omits DUE when there is no due date', () => {
    const ics = generateIcsTodo(taskWithoutDate)
    expect(ics).not.toContain('DUE')
  })

  it('includes project context in description', () => {
    const ics = generateIcsTodo(sampleTask)
    expect(ics).toContain('Project Name')
    expect(ics).toContain('Milestone Title')
    expect(ics).toContain('Todo Title')
  })
})

describe('generatePlainText', () => {
  it('generates plain text with all fields', () => {
    const text = generatePlainText(sampleTask)

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
    const text = generatePlainText(taskWithoutDate)
    expect(text).not.toContain('Fällig')
  })

  it('omits assignee when not set', () => {
    const text = generatePlainText(taskWithoutAssignee)
    expect(text).not.toContain('Zugewiesen')
  })

  it('works without description', () => {
    const text = generatePlainText(taskWithoutDescription)
    expect(text).toContain('Test Task')
    expect(text).toContain('Projekt: Project Name')
  })
})

describe('generateMarkdown', () => {
  it('generates Markdown with YAML frontmatter', () => {
    const md = generateMarkdown(sampleTask)

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
    const md = generateMarkdown(taskWithoutDate)
    expect(md).not.toContain('date:')
  })

  it('omits assignee in frontmatter when not set', () => {
    const md = generateMarkdown(taskWithoutAssignee)
    expect(md).not.toContain('assignee:')
  })

  it('escapes double quotes in title', () => {
    const task = { ...sampleTask, title: 'Test "quoted"' }
    const md = generateMarkdown(task)
    expect(md).toContain('title: "Test \\"quoted\\""')
  })

  it('works without description', () => {
    const md = generateMarkdown(taskWithoutDescription)
    expect(md).toContain('# Test Task')
    expect(md).toContain('**Projekt:** Project Name')
  })
})
