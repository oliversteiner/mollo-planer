import { beforeEach, describe, expect, it } from 'vitest'
import { parseStoredMode } from '~/composables/useViewMode'
import { isChord, shortcutBlocked } from '~/utils/shortcuts'

describe('view mode', () => {
  beforeEach(async () => {
    useCookie('view_mode').value = null
    await navigateTo({ path: '/', query: {} })
  })

  it('reads only calm from the cookie, everything else is full', () => {
    expect(parseStoredMode('calm')).toBe('calm')
    expect(parseStoredMode('focus')).toBe('full')
    expect(parseStoredMode(undefined)).toBe('full')
  })

  it('starts as full, toggles to calm and back, and keeps calm in the cookie', async () => {
    const view = useViewMode()

    expect(view.mode.value).toBe('full')

    view.toggleCalm()
    expect(view.mode.value).toBe('calm')
    await new Promise(resolve => setTimeout(resolve, 20))
    expect(document.cookie).toContain('view_mode=calm')

    view.toggleCalm()
    expect(view.mode.value).toBe('full')
  })

  it('focus comes from the URL, wins over the stored mode, and leaving it restores the stored mode', async () => {
    const view = useViewMode()

    view.setMode('calm')
    await view.enterFocus({ kind: 'task', id: '7' })

    expect(useRoute().query).toMatchObject({ focus: '1', task: '7' })
    expect(view.mode.value).toBe('focus')

    await view.leaveFocus()

    expect(useRoute().query.focus).toBeUndefined()
    expect(view.mode.value).toBe('calm')
  })

  it('a todo focus carries the todo in the URL and leaving removes both', async () => {
    const view = useViewMode()

    await view.enterFocus({ kind: 'todo', id: '5' })
    expect(useRoute().query).toMatchObject({ focus: '1', todo: '5' })

    await view.leaveFocus()
    expect(useRoute().query.todo).toBeUndefined()
    expect(useRoute().query.focus).toBeUndefined()
  })

  it('focus ends without touching the cookie, also from full', async () => {
    const view = useViewMode()

    await view.enterFocus()
    expect(view.mode.value).toBe('focus')
    expect(document.cookie).not.toContain('view_mode=focus')

    await view.leaveFocus()
    expect(view.mode.value).toBe('full')
  })
})

describe('shortcuts', () => {
  const key = (init: KeyboardEventInit, target?: HTMLElement) => {
    const event = new KeyboardEvent('keydown', init)

    Object.defineProperty(event, 'target', { value: target ?? document.body })

    return event
  }

  it('knows the chord with Ctrl or Cmd', () => {
    expect(isChord(key({ key: '.', ctrlKey: true }), '.')).toBe(true)
    expect(isChord(key({ key: '.', metaKey: true }), '.')).toBe(true)
    expect(isChord(key({ key: '.' }), '.')).toBe(false)
    expect(isChord(key({ key: '.', ctrlKey: true, shiftKey: true }), '.')).toBe(false)
  })

  it('stays out of the way in text fields (plain keys only) and while a dialog is open', () => {
    const input = document.createElement('input')

    expect(shortcutBlocked(key({ key: 'Escape' }, input))).toBe(true)
    expect(shortcutBlocked(key({ key: '.', ctrlKey: true }, input))).toBe(false)
    expect(shortcutBlocked(key({ key: 'Escape' }))).toBe(false)

    const dialog = document.createElement('wa-dialog')

    dialog.setAttribute('open', '')
    document.body.append(dialog)
    expect(shortcutBlocked(key({ key: '.', ctrlKey: true }))).toBe(true)
    dialog.remove()
  })
})
