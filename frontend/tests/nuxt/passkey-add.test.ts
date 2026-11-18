import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import PasskeyAdd from '~/components/security/PasskeyAdd.vue'

const { addPasskey, toast } = vi.hoisted(() => ({ addPasskey: vi.fn(), toast: vi.fn() }))

mockNuxtImport('useSecurity', () => () => ({ addPasskey }))
mockNuxtImport('useToast', () => () => ({ add: toast }))
vi.mock('@simplewebauthn/browser', () => ({ browserSupportsWebAuthn: () => true }))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  addPasskey.mockReset()
  toast.mockReset()
})

describe('add a passkey', () => {
  it('suggests a name, which is no duty', async () => {
    const page = await mountSuspended(PasskeyAdd)
    const input = page.find('wa-input').element as unknown as { value: string, required: boolean }

    expect(input.value).not.toBe('')
    expect(input.required).toBeFalsy()
  })

  it('says what a cancelled system dialog means and what to do next', async () => {
    addPasskey.mockRejectedValue(Object.assign(new Error('cancelled'), { name: 'NotAllowedError' }))
    const page = await mountSuspended(PasskeyAdd)

    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(toast).toHaveBeenCalled())

    expect(toast).toHaveBeenCalledWith({ title: 'Kein Passkey angelegt. Versuch es nochmals oder nimm die Authenticator-App.', color: 'warning' })
  })

  it('uses the suggested name when the field is emptied', async () => {
    addPasskey.mockResolvedValue(true)
    const page = await mountSuspended(PasskeyAdd)
    const input = page.find('wa-input').element as HTMLElement & { value: string }
    Object.defineProperty(input, 'value', { value: '', writable: true, configurable: true })
    input.dispatchEvent(new Event('input'))

    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(addPasskey).toHaveBeenCalled())

    expect(addPasskey.mock.calls[0]![0]).not.toBe('')
    expect(page.emitted('added')).toHaveLength(1)
  })
})
