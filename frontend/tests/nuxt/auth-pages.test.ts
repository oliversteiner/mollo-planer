import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import type { VueWrapper } from '@vue/test-utils'
import { FetchError } from 'ofetch'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import SetPasswordForm from '~/components/auth/SetPasswordForm.vue'
import ForgotPage from '~/pages/forgot-password.vue'
import LoginPage from '~/pages/login.vue'

const { backend } = vi.hoisted(() => ({ backend: vi.fn() }))

// The pages talk to the backend with $backend (REST); the tests answer for it.
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

type Field = HTMLElement & { value: string, name: string }
type Wrapper = VueWrapper

/** The wa-input / wa-otp-input with this name (Vue sets `name` as property, not as attribute). */
function field(wrapper: Wrapper, name: string) {
  return wrapper.findAll('wa-input, wa-otp-input').find(candidate => (candidate.element as Field).name === name)!
}

/** Types into the field with the given name. */
function type(wrapper: Wrapper, name: string, value: string) {
  const element = field(wrapper, name).element as Field

  // happy-dom has no form internals, so the setter of wa-otp-input fails: shadow it on this element.
  Object.defineProperty(element, 'value', { value, writable: true, configurable: true })
  element.dispatchEvent(new Event('input'))
}

function validationError(errors: Record<string, string[]>) {
  return Object.assign(new FetchError('422'), { status: 422, data: { message: 'invalid', errors } })
}

describe('login page', () => {
  it('sends e-mail and password and then asks for the app code', async () => {
    backend.mockResolvedValueOnce({ two_factor: true })
    const page = await mountSuspended(LoginPage)

    type(page, 'email', 'ada@example.org')
    type(page, 'password', 'geheim')
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.find('wa-otp-input').exists()).toBe(true))

    expect(backend).toHaveBeenCalledWith('/login', { method: 'POST', body: { email: 'ada@example.org', password: 'geheim' } })
    expect(page.text()).toContain('Zweiter Faktor')
  })

  it('sends the six digits as one code, or a recovery code', async () => {
    backend.mockResolvedValueOnce({ two_factor: true })
    const page = await mountSuspended(LoginPage)

    type(page, 'email', 'ada@example.org')
    type(page, 'password', 'geheim')
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.find('wa-otp-input').exists()).toBe(true))

    backend.mockResolvedValue({})
    type(page, 'code', '123456')
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(backend).toHaveBeenCalledWith('/two-factor-challenge', { method: 'POST', body: { code: '123456' } }))

    const toggle = page.findAll('wa-button').find(button => button.text() === 'Wiederherstellungscode verwenden')!
    await toggle.trigger('click')

    expect(page.find('wa-otp-input').exists()).toBe(false)

    type(page, 'recovery_code', ' abcd-efgh ')
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(backend).toHaveBeenCalledWith('/two-factor-challenge', { method: 'POST', body: { recovery_code: 'abcd-efgh' } }))
  })

  it('shows the message of the server at the field', async () => {
    backend.mockRejectedValueOnce(validationError({ email: ['Diese Zugangsdaten sind ungültig.'] }))
    const page = await mountSuspended(LoginPage)

    type(page, 'email', 'ada@example.org')
    type(page, 'password', 'falsch')
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(field(page, 'email').find('[data-form-error]').exists()).toBe(true))

    expect(field(page, 'email').find('[data-form-error]').text()).toBe('Diese Zugangsdaten sind ungültig.')
    expect(field(page, 'email').attributes('aria-invalid')).toBe('true')
  })
})

describe('forgot-password page', () => {
  it('sends the e-mail and then says so', async () => {
    backend.mockResolvedValueOnce({})
    const page = await mountSuspended(ForgotPage)

    type(page, 'email', 'ada@example.org')
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.find('wa-callout').exists()).toBe(true))

    expect(backend).toHaveBeenCalledWith('/forgot-password', { method: 'POST', body: { email: 'ada@example.org' } })
    expect(page.find('form').exists()).toBe(false)
  })
})

describe('set-password form', () => {
  const props = { endpoint: '/reset-password', title: 'Neues Passwort', description: 'Wähle eins.', submitLabel: 'Speichern' }

  it('says the link is invalid without token and e-mail', async () => {
    const form = await mountSuspended(SetPasswordForm, { props, route: '/reset-password' })

    expect(form.find('wa-callout').exists()).toBe(true)
    expect(form.find('form').exists()).toBe(false)
  })

  it('sends token, e-mail and both passwords and reports success', async () => {
    backend.mockResolvedValueOnce({})
    const form = await mountSuspended(SetPasswordForm, { props, route: '/reset-password?token=abc&email=ada%40example.org' })

    expect((field(form, 'email').element as Field).value).toBe('ada@example.org')

    type(form, 'password', 'neues-passwort')
    type(form, 'password_confirmation', 'neues-passwort')
    await form.find('form').trigger('submit')
    await vi.waitFor(() => expect(form.emitted('done')).toHaveLength(1))

    expect(backend).toHaveBeenCalledWith('/reset-password', {
      method: 'POST',
      body: { token: 'abc', email: 'ada@example.org', password: 'neues-passwort', password_confirmation: 'neues-passwort' }
    })
  })
})
