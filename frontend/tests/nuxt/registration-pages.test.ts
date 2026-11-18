import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import type { VueWrapper } from '@vue/test-utils'
import { FetchError } from 'ofetch'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import RegisterPage from '~/pages/register.vue'

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

type Field = HTMLElement & { value: string, name: string }

function field(wrapper: VueWrapper, name: string) {
  return wrapper.findAll('wa-input').find(candidate => (candidate.element as Field).name === name)!
}

function type(wrapper: VueWrapper, name: string, value: string) {
  const element = field(wrapper, name).element as Field

  Object.defineProperty(element, 'value', { value, writable: true, configurable: true })
  element.dispatchEvent(new Event('input'))
}

describe('register page', () => {
  it('sends name, e-mail and the password twice to the registration route', async () => {
    backend.mockResolvedValueOnce({})
    const page = await mountSuspended(RegisterPage)

    type(page, 'name', 'Eva Neu')
    type(page, 'email', 'eva@example.org')
    type(page, 'password', 'Correct-Horse-9')
    type(page, 'password_confirmation', 'Correct-Horse-9')
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(backend).toHaveBeenCalledTimes(1))

    expect(backend).toHaveBeenCalledWith('/register', {
      method: 'POST',
      body: { name: 'Eva Neu', email: 'eva@example.org', password: 'Correct-Horse-9', password_confirmation: 'Correct-Horse-9' }
    })
  })

  it('shows the message of the server at the field', async () => {
    backend.mockRejectedValueOnce(Object.assign(new FetchError('422'), {
      status: 422,
      data: { message: 'invalid', errors: { email: ['Diese E-Mail-Adresse ist bereits registriert.'] } }
    }))
    const page = await mountSuspended(RegisterPage)

    type(page, 'email', 'taken@example.org')
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(field(page, 'email').find('[data-form-error]').exists()).toBe(true))

    expect(field(page, 'email').find('[data-form-error]').text()).toBe('Diese E-Mail-Adresse ist bereits registriert.')
  })
})
