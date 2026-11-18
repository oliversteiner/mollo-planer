import { afterEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'

describe('useStoredSwitch (Kanban, Meine Tasks: Ideen anzeigen)', () => {
  afterEach(() => localStorage.clear())

  it('starts off and remembers the position in the browser', async () => {
    const first = useStoredSwitch('test.switch')
    expect(first.value).toBe(false)

    first.value = true
    await nextTick()

    expect(localStorage.getItem('test.switch')).toBe('1')
    expect(useStoredSwitch('test.switch').value).toBe(true)
  })

  it('takes the fallback when nothing is stored', () => {
    expect(useStoredSwitch('test.other', true).value).toBe(true)
  })
})
