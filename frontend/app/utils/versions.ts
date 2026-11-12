/**
 * Versions of the app: sorting, current version, release notes and dates. Ported from
 * mollo-version (frontend/src/versions.mjs); the data is app/data/versions.json, built by
 * scripts/build-versions.mjs.
 */

export type ChangeKind = 'neu' | 'verbessert' | 'behoben'

export const CHANGE_KINDS: readonly ChangeKind[] = ['neu', 'verbessert', 'behoben']

export const STATUS_RELEASED = 'veröffentlicht'

export interface VersionChange {
  kind: ChangeKind
  text: string
}

export interface AppVersion {
  major: number
  minor: number
  /** "0.7" */
  number: string
  title: string
  description: string | null
  status: string
  /** Release date (YYYY-MM-DD) from the git tag, null without a tag. */
  releasedOn: string | null
  changes: VersionChange[]
}

export interface VersionFile {
  versions: AppVersion[]
}

/** "0.7" from major and minor. */
export function versionNumber(version: Pick<AppVersion, 'major' | 'minor'>): string {
  return `${version.major}.${version.minor}`
}

export function isReleased(version: Pick<AppVersion, 'status'>): boolean {
  return version.status === STATUS_RELEASED
}

/** Oldest first, by major and minor. Returns a new array. */
export function sortVersions<T extends Pick<AppVersion, 'major' | 'minor'>>(versions: readonly T[]): T[] {
  return [...versions].sort((a, b) => a.major - b.major || a.minor - b.minor)
}

/** The highest released version: the current one. */
export function currentVersion(versions: readonly AppVersion[]): AppVersion | null {
  return sortVersions(versions.filter(isReleased)).at(-1) ?? null
}

/** Release notes grouped by kind, in the order of CHANGE_KINDS. Unknown kinds are ignored. */
export function groupChanges(changes: readonly VersionChange[]): Record<ChangeKind, string[]> {
  const groups: Record<ChangeKind, string[]> = { neu: [], verbessert: [], behoben: [] }
  for (const change of changes) {
    if (change.kind in groups) {
      groups[change.kind].push(change.text)
    }
  }
  return groups
}

/** A description as paragraphs: one per line, empty lines dropped. */
export function paragraphs(text: string | null): string[] {
  if (!text) {
    return []
  }
  return text.split('\n').map(line => line.trim()).filter(line => line !== '')
}

/**
 * "2026-11-10" → "10. Nov. 2026" (de-CH) or "Nov 10, 2026" (en-GB). Null without a date.
 * The date is built from its parts, so no time zone shifts it.
 */
export function formatReleaseDate(isoDate: string | null, locale: string): string | null {
  if (!isoDate) {
    return null
  }
  const [year, month, day] = isoDate.split('-').map(Number)
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(year!, month! - 1, day))
}
