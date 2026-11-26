import de from '~/data/manual/de.json'
import en from '~/data/manual/en.json'

/**
 * The chapters of the manual for the help in the app (Auftrag 54), made by scripts/manual.mjs from handbuch/. One list
 * per language; the app shows the one of its language.
 */
export interface ManualStep {
  doc: Record<string, unknown>
  images: { src: string, alt: string }[]
}

export interface ManualChapter {
  id: string
  title: string
  /** An appendix (Auftrag 66 B) has free headings in `body` and none of the fixed sections. */
  type: 'chapter' | 'appendix'
  body: Record<string, unknown> | null
  area: string
  scope: 'core' | 'app'
  right: string
  pages: string[]
  tools: string[]
  commands: string[]
  since: string
  purpose: Record<string, unknown> | null
  steps: ManualStep[]
  notes: Record<string, unknown> | null
  prompts: string[]
}

/** Areas in the order of the overview (the same list as scripts/manual.mjs). */
export const MANUAL_AREAS = ['first-steps', 'projects', 'plan', 'kanban', 'my-tasks', 'comments', 'documents', 'ideas', 'assistant', 'templates', 'trash', 'statistics', 'calm', 'planer-file', 'agents', 'admin', 'appendix'] as const

export function manualChapters(language: string): ManualChapter[] {
  return (language.startsWith('en') ? en : de) as ManualChapter[]
}

/** "task.updateOwn" → "TASK_UPDATE_OWN", the name of the right in GraphQL and in rights.names. */
export function rightEnum(right: string): string {
  return right.replace(/([a-z])([A-Z])/g, '$1_$2').replace(/\./g, '_').toUpperCase()
}

/** Rights of the whole app (the others hold within a project, by the role there). */
export const SYSTEM_RIGHT_PREFIXES = ['projects.', 'users.', 'roles.', 'templates.', 'assistant.', 'ideas.', 'settings.', 'system.', 'database.', 'billing.']

export function isSystemRight(right: string): boolean {
  return SYSTEM_RIGHT_PREFIXES.some(prefix => right.startsWith(prefix))
}

/** Rights that people with user administration hold: the next step is to ask them. */
export function isUserRight(right: string): boolean {
  return right.startsWith('users.') || right.startsWith('roles.')
}

export interface ManualGroup {
  area: string
  chapters: ManualChapter[]
}

/** The chapters by area in the order of the overview: the areas in order (the appendix last), the chapters by title. */
export function manualGroups(language: string): ManualGroup[] {
  const chapters = manualChapters(language)

  return MANUAL_AREAS
    .map(area => ({ area, chapters: chapters.filter(chapter => chapter.area === area).sort((a, b) => a.title.localeCompare(b.title)) }))
    .filter(group => group.chapters.length > 0)
}

/** All chapters in reading order: the order of the overview, over the borders of the areas. A chapter without the right for it stays in. */
export function manualOrder(language: string): ManualChapter[] {
  return manualGroups(language).flatMap(group => group.chapters)
}
