import { describe, expect, it } from 'vitest'
import { isZenQuery, withFirst } from '../../app/utils/commands'
import { FALLBACK_AREA, chooseZenArea, cleanPath, isContentArea } from '../../app/utils/zen-area'

const everything = () => true

describe('zen area', () => {
  it('knows the content areas', () => {
    for (const path of ['/', '/my-tasks', '/ideas', '/assistant', '/projects/3', '/projects/3/kanban', '/projects/3/todos']) {
      expect(isContentArea(path), path).toBe(true)
    }

    for (const path of ['/profile', '/templates', '/trash', '/admin/users', '/projects/3/settings', '/projects/3/members', '/projects/3/trash', '/system/status']) {
      expect(isContentArea(path), path).toBe(false)
    }
  })

  it('takes the page the person is on when it is a content area, also with an open task', () => {
    expect(chooseZenArea('/projects/3/kanban', '/ideas', everything)).toBe('/projects/3/kanban')
    expect(chooseZenArea('/projects/3?task=7', '/ideas', everything)).toBe('/projects/3')
  })

  it('takes the area last visited on any other page', () => {
    expect(chooseZenArea('/projects/3/settings', '/projects/3/todos', everything)).toBe('/projects/3/todos')
    expect(chooseZenArea('/profile', '/ideas', everything)).toBe('/ideas')
  })

  it('takes "Meine Tasks" without a remembered area', () => {
    expect(chooseZenArea('/profile', null, everything)).toBe(FALLBACK_AREA)
    expect(chooseZenArea('/admin/users', undefined, everything)).toBe('/my-tasks')
  })

  it('drops a remembered area that is not valid or not allowed any more', () => {
    expect(chooseZenArea('/profile', '/projects/9', path => !path.startsWith('/projects/9'))).toBe(FALLBACK_AREA)
    expect(chooseZenArea('/profile', '/admin/users', everything)).toBe(FALLBACK_AREA)
    expect(chooseZenArea('/profile', 'nonsense', everything)).toBe(FALLBACK_AREA)
  })

  it('cleans query and trailing slash', () => {
    expect(cleanPath('/ideas/?x=1#y')).toBe('/ideas')
    expect(cleanPath('')).toBe('/')
  })
})

describe('zen in the search', () => {
  it('"ze", "zen", "ruhig", "calm" and "stimmung" bring the zen entries first, "z" and "ergänzen" do not', () => {
    for (const query of ['ze', 'zen', 'ZEN', 'ru', 'ruhig', 'calm', 'stimm', 'stimmung', 'mood']) {
      expect(isZenQuery(query), query).toBe(true)
    }

    for (const query of ['z', '', 'ergänzen', 'projekt']) {
      expect(isZenQuery(query), query).toBe(false)
    }
  })

  it('puts the entries by id first, in the given order', () => {
    const items = [{ id: 'assistant' }, { id: 'zen-start' }, { id: 'mood' }, { id: 'ideas' }]

    expect(withFirst(items, ['zen-start', 'mood']).map(item => item.id)).toEqual(['zen-start', 'mood', 'assistant', 'ideas'])
    expect(withFirst(items, ['missing']).map(item => item.id)).toEqual(['assistant', 'zen-start', 'mood', 'ideas'])
  })
})
