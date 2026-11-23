/**
 * The area Zen starts with (docs/zen-modus.md §4.4). Content areas are the pages with work in them: the dashboard,
 * my tasks, ideas, the assistant, and in a project the timeline, the kanban, the todos and the documents (list, one document, its history). Zen starts with the page
 * the person is on if it is a content area (an open task or todo stays, it is the same page); on any other page
 * (settings, members, templates, trash, admin, profile, system) with the content area last visited on this device
 * (cookie `zen_area`), and without one with "Meine Tasks".
 */
const CONTENT = [/^\/$/, /^\/my-tasks$/, /^\/ideas$/, /^\/assistant$/, /^\/projects\/[^/]+$/, /^\/projects\/[^/]+\/(kanban|todos|documents)$/, /^\/projects\/[^/]+\/documents\/[^/]+(\/history)?$/]

export const FALLBACK_AREA = '/my-tasks'

/** A path without query and trailing slash. */
export function cleanPath(path: string): string {
  const bare = (path.split(/[?#]/)[0] ?? '').replace(/\/+$/, '')

  return bare === '' ? '/' : bare
}

export function isContentArea(path: string): boolean {
  const clean = cleanPath(path)

  return CONTENT.some(pattern => pattern.test(clean))
}

/**
 * @param current  The path of the page now
 * @param remembered  The path in the cookie `zen_area`, if any
 * @param allowed  Whether the person may see a path (the right of the page, the project exists for them)
 */
export function chooseZenArea(current: string, remembered: string | null | undefined, allowed: (path: string) => boolean): string {
  if (isContentArea(current)) {
    return cleanPath(current)
  }

  if (remembered && isContentArea(remembered) && allowed(cleanPath(remembered))) {
    return cleanPath(remembered)
  }

  return FALLBACK_AREA
}

/**
 * What is remembered of a content area (cookie `zen_area`): one document and its history are remembered as the list
 * of documents, because the document may be gone the next day.
 */
export function rememberedArea(path: string): string {
  return cleanPath(path).replace(/^(\/projects\/[^/]+\/documents)\/[^/]+(?:\/history)?$/, '$1')
}

/** The project id in a project path, or null. */
export function projectIdOf(path: string): string | null {
  return /^\/projects\/([^/]+)/.exec(cleanPath(path))?.[1] ?? null
}

/** The part of the path after the project: '' (timeline), 'kanban', 'todos' or 'documents' (also one document and its history). */
export function projectPageOf(path: string): '' | 'kanban' | 'todos' | 'documents' {
  const page = /^\/projects\/[^/]+\/(kanban|todos|documents)(?:\/|$)/.exec(cleanPath(path))?.[1]

  return page === 'kanban' || page === 'todos' || page === 'documents' ? page : ''
}
