import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { installElementInternals } from './support/element-internals'

/**
 * Failed requests (plugins/api-errors.ts): a message of the server that is no text for people never reaches the
 * screen (Auftrag 55 D). It goes to the console.
 */
installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

afterEach(() => {
  vi.restoreAllMocks()
  document.querySelectorAll('wa-toast').forEach(element => element.remove())
})

const toastTexts = () => [...document.querySelectorAll('wa-toast-item')].map(item => item.textContent)

describe('unknown failures', () => {
  it('show a plain sentence, not the wording of the server, and keep the wording for the console', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined)

    useNuxtApp().$apiErrors.trigger({ kind: 'unknown', message: 'Variable "$name" of non-null type "String!" must not be null.', fields: {}, email: null })

    expect(toastTexts()).toEqual(['Das hat nicht geklappt. Bitte nochmals versuchen.'])
    expect(warn).toHaveBeenCalledWith('[api]', 'Variable "$name" of non-null type "String!" must not be null.')
  })

  it('also for an unknown failure without a message', () => {
    vi.spyOn(console, 'warn').mockImplementation(() => undefined)

    useNuxtApp().$apiErrors.trigger({ kind: 'unknown', message: null, fields: {}, email: null })

    expect(toastTexts()).toEqual(['Das hat nicht geklappt. Bitte nochmals versuchen.'])
  })

  it('leaves the other kinds as they were', () => {
    useNuxtApp().$apiErrors.trigger({ kind: 'network', message: null, fields: {}, email: null })

    expect(toastTexts()).toEqual(['Der Server ist nicht erreichbar.'])
  })
})
