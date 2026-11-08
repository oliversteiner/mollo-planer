import { describe, expect, it } from 'vitest'
import type { IconEntry } from '../../app/utils/icon-search'
import { searchIcons } from '../../app/utils/icon-search'

const icons: IconEntry[] = [
  ['rocket', 'Rocket', 'launch space nasa'],
  ['rocket-launch', 'Rocket Launch', 'space flight'],
  ['shuttle-space', 'Space Shuttle', 'rocket nasa'],
  ['futbol', 'Futbol Ball', 'soccer football sport'],
  ['users', 'Users', 'people team']
]

describe('searchIcons', () => {
  it('suggests project icons when nothing is typed', () => {
    expect(searchIcons(icons, '').map(icon => icon[0])).toEqual(['rocket', 'users', 'futbol'])
  })

  it('finds by name, label and terms, exact and prefix matches first', () => {
    expect(searchIcons(icons, 'rocket').map(icon => icon[0])).toEqual(['rocket', 'rocket-launch', 'shuttle-space'])
    expect(searchIcons(icons, 'soccer').map(icon => icon[0])).toEqual(['futbol'])
  })

  it('needs every word and respects the limit', () => {
    expect(searchIcons(icons, 'space nasa').map(icon => icon[0])).toEqual(['rocket', 'shuttle-space'])
    expect(searchIcons(icons, 'space', 1)).toHaveLength(1)
  })
})
