import { describe, expect, it } from 'vitest'
import { currentVersion, formatReleaseDate, groupChanges, paragraphs, sortVersions, versionNumber, type AppVersion } from '../../app/utils/versions'

function version(major: number, minor: number, status: string, releasedOn: string | null = null): AppVersion {
  return { major, minor, number: `${major}.${minor}`, title: `Version ${major}.${minor}`, description: null, status, releasedOn, changes: [] }
}

describe('versions', () => {
  it('builds the number from major and minor', () => {
    expect(versionNumber(version(0, 7, 'veröffentlicht'))).toBe('0.7')
  })

  it('sorts oldest first, by major and then minor, without changing the input', () => {
    const input = [version(0, 10, 'geplant'), version(0, 2, 'geplant'), version(0, 9, 'geplant')]

    expect(sortVersions(input).map(versionNumber)).toEqual(['0.2', '0.9', '0.10'])
    expect(input[0]!.minor).toBe(10)
  })

  it('takes the highest released version as current, not the highest planned one', () => {
    const versions = [version(0, 5, 'veröffentlicht'), version(0, 7, 'veröffentlicht'), version(0, 8, 'geplant')]

    expect(versionNumber(currentVersion(versions)!)).toBe('0.7')
  })

  it('has no current version while nothing is released', () => {
    expect(currentVersion([version(0, 1, 'geplant')])).toBeNull()
  })

  it('groups release notes by kind in a fixed order and drops unknown kinds', () => {
    const groups = groupChanges([
      { kind: 'behoben', text: 'Fehler' },
      { kind: 'neu', text: 'Neu A' },
      { kind: 'neu', text: 'Neu B' },
      { kind: 'unbekannt' as 'neu', text: 'egal' }
    ])

    expect(groups).toEqual({ neu: ['Neu A', 'Neu B'], verbessert: [], behoben: ['Fehler'] })
  })

  it('splits a description into paragraphs and drops empty lines', () => {
    expect(paragraphs('Eins\n\n  Zwei  \n')).toEqual(['Eins', 'Zwei'])
    expect(paragraphs(null)).toEqual([])
  })

  it('formats the release date in the locale, without a time zone shift', () => {
    // The wording depends on the ICU data of the runtime, so the expected text is built the same way.
    expect(formatReleaseDate('2026-11-10', 'de-CH')).toBe(new Intl.DateTimeFormat('de-CH', { dateStyle: 'medium' }).format(new Date(2026, 10, 10)))
    expect(formatReleaseDate('2026-01-01', 'en-GB')).toBe(new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' }).format(new Date(2026, 0, 1)))
    expect(formatReleaseDate(null, 'de-CH')).toBeNull()
  })
})
