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
export const MANUAL_AREAS = ['first-steps', 'projects', 'plan', 'kanban', 'my-tasks', 'comments', 'documents', 'ideas', 'assistant', 'templates', 'trash', 'statistics', 'calm', 'planer-file', 'agents', 'admin'] as const

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
