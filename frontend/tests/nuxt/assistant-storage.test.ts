import { beforeEach, describe, expect, it } from 'vitest'
import { assistantStorageKey, clearAssistantStorage } from '../../app/utils/assistant-storage'

describe('assistant storage', () => {
  beforeEach(() => sessionStorage.clear())

  it('keeps the chat per person', () => {
    expect(assistantStorageKey('1')).not.toBe(assistantStorageKey('2'))
    expect(assistantStorageKey(null)).toBe('assistant-draft-v2:anonymous')
  })

  it('logging out deletes the chats of every person and leaves other keys alone', () => {
    sessionStorage.setItem(assistantStorageKey('1'), '{}')
    sessionStorage.setItem(assistantStorageKey('2'), '{}')
    sessionStorage.setItem('assistant-draft-v1', '{}')
    sessionStorage.setItem('other', 'x')

    clearAssistantStorage()

    expect(Object.keys(sessionStorage)).toEqual(['other'])
  })
})
