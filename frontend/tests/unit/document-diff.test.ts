import { describe, expect, it } from 'vitest'
import { compareDocuments, hasChanges } from '../../app/utils/document-diff'

const text = (rows: ReturnType<typeof compareDocuments>) => rows.map(row => `${row.kind}:${row.segments.map(segment => segment.text).join('')}`)

describe('compareDocuments', () => {
  it('finds no change between equal texts', () => {
    expect(hasChanges(compareDocuments('a\nb\n', 'a\nb\n'))).toBe(false)
  })

  it('marks added and removed lines', () => {
    expect(text(compareDocuments('eins\nzwei\n', 'eins\nzwei\ndrei\n'))).toEqual(['same:eins', 'same:zwei', 'added:drei'])
    expect(text(compareDocuments('eins\nzwei\n', 'eins\n'))).toEqual(['same:eins', 'removed:zwei'])
  })

  it('compares a changed line word by word', () => {
    const rows = compareDocuments('Der Aufbau dauert zwei Stunden\n', 'Der Aufbau dauert drei Stunden\n')

    expect(rows.map(row => row.kind)).toEqual(['removed', 'added'])
    expect(rows[0]!.segments.filter(segment => segment.changed).map(segment => segment.text.trim())).toEqual(['zwei'])
    expect(rows[1]!.segments.filter(segment => segment.changed).map(segment => segment.text.trim())).toEqual(['drei'])
  })

  it('keeps markup in the text as text', () => {
    const rows = compareDocuments('a\n', '<b onclick="x()">a</b>\n')

    expect(rows.at(-1)!.segments.map(segment => segment.text).join('')).toBe('<b onclick="x()">a</b>')
  })
})
