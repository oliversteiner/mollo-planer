/**
 * The planer-v2 file in the browser (docs/auftrag-m4.md §7): the types of a
 * document as spec/planer-v2.schema.json describes it, reading a file, names
 * of downloads. The Markdown view comes from tools/planer (renderPlaner).
 */
import { renderPlaner } from '#planer-core/render-planer.mjs'

export interface PlanerLabel {
  key: string
  names: Record<string, string>
  category?: string | null
  color: string
}

export interface PlanerTask {
  id: string
  title: string
  description?: string | null
  dueDate?: string | null
  status: 'idea' | 'planned' | 'open' | 'in_progress' | 'review' | 'done'
  assigneeEmail?: string | null
  labels?: string[]
  commit?: string | null
  userStory?: { role: string, goal: string, benefit: string } | null
}

export interface PlanerTodo {
  id: string
  title: string
  description?: string | null
  dueDate?: string | null
  tasks: PlanerTask[]
}

export interface PlanerMilestone {
  id: string
  title: string
  description?: string | null
  dueDate?: string | null
  release?: { version: string, status: 'planned' | 'released' } | null
  todos: PlanerTodo[]
}

export interface PlanerDocument {
  format: 'planer-v2'
  revision: number
  changed?: boolean
  project: {
    name: string
    description?: string | null
    locale: 'de' | 'en'
    members: { email: string, role: string }[]
    labels?: PlanerLabel[]
  }
  milestones: PlanerMilestone[]
}

/** A change from an import (ImportResult.changes), as the preview marks it. */
export interface PlanerChange {
  kind: 'CREATE' | 'UPDATE' | 'MOVE' | 'DELETE'
  path: string
  id?: string | null
  fields: string[]
  /** The title of what is deleted (the file does not know it). */
  title?: string | null
}

export type PlanerFileProblem = 'notJson' | 'notPlaner'

/**
 * Reads the text of a file. Only the shape is checked here (JSON, format
 * planer-v2, milestones as a list); the server checks everything else
 * and names the places.
 */
export function readPlanerFile(text: string): { document: PlanerDocument } | { problem: PlanerFileProblem } {
  let data: unknown

  try {
    data = JSON.parse(text)
  } catch {
    return { problem: 'notJson' }
  }

  const document = data as Partial<PlanerDocument> | null

  if (!document || typeof document !== 'object' || document.format !== 'planer-v2' || !Array.isArray(document.milestones) || !document.project) {
    return { problem: 'notPlaner' }
  }

  return { document: document as PlanerDocument }
}

/** The name of a download: the project name as a slug, e.g. "mollo-planer.planer.json". */
export function downloadName(projectName: string, extension: 'planer.json' | 'md'): string {
  const slug = projectName
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

  return `${slug || 'projekt'}.${extension}`
}

/** The file as Markdown, in the chosen language, with the legend in the other languages on request. */
export function planerMarkdown(document: PlanerDocument, language: 'de' | 'en', legendTranslations: boolean): string {
  return renderPlaner(document, { lang: language, legendTranslations })
}

/** Offers a text as a file to download. */
export function downloadText(name: string, text: string, type: string): void {
  const url = URL.createObjectURL(new Blob([text], { type }))
  const link = document.createElement('a')

  link.href = url
  link.download = name
  link.click()
  URL.revokeObjectURL(url)
}
