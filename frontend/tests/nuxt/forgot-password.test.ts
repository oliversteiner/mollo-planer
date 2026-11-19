import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import ForgotPage from '~/pages/forgot-password.vue'

const { backend } = vi.hoisted(() => ({ backend: vi.fn() }))

mockNuxtImport('useNuxtApp', original => () => {
  const nuxtApp = original()

  return new Proxy(nuxtApp, { get: (target, key) => (key === '$backend' ? backend : Reflect.get(target, key)) })
})

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  backend.mockReset()
})

describe('forgot password page', () => {
  it('shows the same neutral text after sending, for any address', async () => {
    backend.mockResolvedValue({ message: 'Wenn die Adresse bei uns bekannt ist, haben wir dir einen Link geschickt.' })
    const page = await mountSuspended(ForgotPage)
    const input = page.findAll('wa-input').find((candidate: { element: Element }) => (candidate.element as unknown as { name: string }).name === 'email')!.element as HTMLElement & { value: string }
    Object.defineProperty(input, 'value', { value: 'nobody@example.org', writable: true, configurable: true })
    input.dispatchEvent(new Event('input'))

    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.text()).toContain('Wenn die Adresse bei uns bekannt ist, ist der Link unterwegs.'))

    expect(backend).toHaveBeenCalledWith('/forgot-password', { method: 'POST', body: { email: 'nobody@example.org' } })
    expect(page.find('form').exists()).toBe(false)
  })
})
