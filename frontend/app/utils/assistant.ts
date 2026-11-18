import type { PlanerDocument } from '~/utils/planer'

/**
 * The plan of the assistant in the browser (docs/auftrag-m6.md §4.4): the
 * canonical text of the server becomes a document; what the person struck out
 * is taken away (a struck milestone takes its todos, a struck todo its tasks)
 * before the new preview and before "Jetzt eintragen".
 */

/** Only what the assistant adds can be struck out: the ids with "-new-". */
export function isNewId(id: string): boolean {
  return id.includes('-new-')
}

export function readPlan(text: string | null): PlanerDocument | null {
  if (!text) {
    return null
  }

  try {
    const document = JSON.parse(text) as Partial<PlanerDocument> | null

    return document?.format === 'planer-v2' && Array.isArray(document.milestones) && document.project ? document as PlanerDocument : null
  } catch {
    return null
  }
}

/** The document without the struck elements. */
export function withoutStruck(document: PlanerDocument, struck: ReadonlySet<string>): PlanerDocument {
  return {
    ...document,
    milestones: document.milestones
      .filter(milestone => !struck.has(milestone.id))
      .map(milestone => ({
        ...milestone,
        todos: milestone.todos
          .filter(todo => !struck.has(todo.id))
          .map(todo => ({ ...todo, tasks: todo.tasks.filter(task => !struck.has(task.id)) }))
      }))
  }
}

/** How many new milestones, todos and tasks the plan adds (without the struck ones). */
export function newCounts(document: PlanerDocument, struck: ReadonlySet<string> = new Set()): { milestones: number, todos: number, tasks: number } {
  const kept = withoutStruck(document, struck)
  const todos = kept.milestones.flatMap(milestone => milestone.todos)

  return {
    milestones: kept.milestones.filter(milestone => isNewId(milestone.id)).length,
    todos: todos.filter(todo => isNewId(todo.id)).length,
    tasks: todos.flatMap(todo => todo.tasks).filter(task => isNewId(task.id)).length
  }
}

/** The text to send: the document as JSON (the server reads any JSON, it writes the canonical form itself). */
export function planText(document: PlanerDocument): string {
  return JSON.stringify(document)
}

/**
 * The title an idea gets from a draft (docs/fix-m6.md F9.5): the first new todo, else the first new task, else the
 * first new milestone; for a new project its name. Without a draft the start of the first message.
 */
export function ideaTitle(document: PlanerDocument | null, newProject: boolean, firstMessage: string): string {
  const fromMessage = firstMessage.trim().split('\n')[0]?.slice(0, 200) ?? ''

  if (!document) {
    return fromMessage
  }

  if (newProject && document.project.name) {
    return document.project.name.slice(0, 200)
  }

  const milestones = document.milestones
  const todo = milestones.flatMap(milestone => milestone.todos).find(item => isNewId(item.id))
  const task = milestones.flatMap(milestone => milestone.todos).flatMap(item => item.tasks).find(item => isNewId(item.id))
  const milestone = milestones.find(item => isNewId(item.id))

  return (todo?.title ?? task?.title ?? milestone?.title ?? fromMessage).slice(0, 200)
}

/** A text as a rich-text document: blank lines separate the paragraphs; null for no text. */
export function textDocument(text: string): Record<string, unknown> | null {
  const paragraphs = text.split(/\n\s*\n/).map(part => part.trim()).filter(Boolean)

  return paragraphs.length
    ? { type: 'doc', content: paragraphs.map(part => ({ type: 'paragraph', content: [{ type: 'text', text: part }] })) }
    : null
}
