import { mountSuspended } from '@nuxt/test-utils/runtime'
import { FetchError } from 'ofetch'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, nextTick, ref } from 'vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import PasswordConfirmField from '~/components/PasswordConfirmField.vue'
import { stubBackend } from './support/backend'
import { installElementInternals } from './support/element-internals'

/**
 * The password confirmation inside a dialog (Auftrag 55, rule R11): usePasswordStep and its field. The backend is a
 * stand-in; what counts is the order: ask the status, show the field when it is old, confirm, then the action goes on.
 */
const backend = vi.fn()

// As in the other tests that put a stand-in in place of $backend: the Apollo composables are not needed here.
vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref(null), loading: ref(false), refetch: vi.fn() }),
  useMutation: () => ({ mutate: vi.fn() })
}))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

function oldConfirmation(): void {
  backend.mockImplementation(async (url: string) => (url === '/user/confirmed-password-status' ? { confirmed: false } : {}))
}

beforeEach(() => {
  backend.mockReset().mockImplementation(async (url: string) => (url === '/user/confirmed-password-status' ? { confirmed: true } : {}))
  stubBackend(backend)
})

async function step() {
  let value!: ReturnType<typeof usePasswordStep>
  await mountSuspended(defineComponent({ setup() {
    value = usePasswordStep()

    return () => null
  } }))

  return value
}

describe('usePasswordStep', () => {
  it('runs the action at once when the password was confirmed a moment ago', async () => {
    const action = vi.fn().mockResolvedValue(undefined)
    const password = await step()

    await password.run(action)

    expect(action).toHaveBeenCalledOnce()
    expect(password.waiting).toBe(false)
    expect(backend).not.toHaveBeenCalledWith('/user/confirm-password', expect.anything())
  })

  it('waits for the password when the confirmation is old, then confirms and runs the action without another click', async () => {
    oldConfirmation()
    const action = vi.fn().mockResolvedValue(undefined)
    const password = await step()

    await password.run(action)

    expect(password.waiting).toBe(true)
    expect(action).not.toHaveBeenCalled()

    password.setPassword('geheim')
    await password.submit()

    expect(backend).toHaveBeenCalledWith('/user/confirm-password', { method: 'POST', body: { password: 'geheim' } })
    expect(action).toHaveBeenCalledOnce()
    expect(password.waiting).toBe(false)
    expect(password.password).toBe('')
  })

  it('keeps the field and shows the message at it when the password is wrong, and runs nothing', async () => {
    oldConfirmation()
    backend.mockImplementation(async (url: string) => {
      if (url === '/user/confirmed-password-status') {
        return { confirmed: false }
      }

      throw Object.assign(new FetchError('422'), { status: 422, data: { message: 'invalid', errors: { password: ['Das Passwort stimmt nicht.'] } } })
    })
    const action = vi.fn()
    const password = await step()

    await password.run(action)
    password.setPassword('falsch')
    await password.submit()

    expect(password.waiting).toBe(true)
    expect(password.error).toBe('Das Passwort stimmt nicht.')
    expect(action).not.toHaveBeenCalled()
  })

  it('closes the field on cancel and runs nothing', async () => {
    oldConfirmation()
    const action = vi.fn()
    const password = await step()

    await password.run(action)
    password.setPassword('halb')
    password.cancel()

    expect(password.waiting).toBe(false)
    expect(password.password).toBe('')

    await password.submit()

    expect(action).not.toHaveBeenCalled()
  })

  it('asks again, keeping the action, when the action says the confirmation ran out meanwhile', async () => {
    const action = vi.fn().mockResolvedValueOnce(NEEDS_PASSWORD).mockResolvedValue(undefined)
    const password = await step()

    await password.run(action)

    expect(password.waiting).toBe(true)
    expect(action).toHaveBeenCalledOnce()

    password.setPassword('geheim')
    await password.submit()

    expect(action).toHaveBeenCalledTimes(2)
    expect(password.waiting).toBe(false)
  })

  it('runs one action at a time', async () => {
    let finish!: () => void
    const action = vi.fn().mockImplementation(() => new Promise<void>((resolve) => {
      finish = resolve
    }))
    const password = await step()

    const first = password.run(action)
    await vi.waitFor(() => expect(action).toHaveBeenCalledOnce())
    await password.run(action)

    expect(action).toHaveBeenCalledOnce()

    finish()
    await first
  })
})

describe('PasswordConfirmField', () => {
  it('shows only while the step waits, with a field that is no form of its own', async () => {
    oldConfirmation()
    const password = await step()
    const field = await mountSuspended(PasswordConfirmField, { props: { step: password } })

    expect(field.find('.password-confirm').exists()).toBe(false)

    await password.run(vi.fn())
    await nextTick()

    expect(field.find('.password-confirm').exists()).toBe(true)
    expect(field.find('form').exists()).toBe(false)
    expect(field.text()).toContain('Bitte bestätige dein Passwort')
  })

  it('confirms with the button and closes with «Abbrechen»', async () => {
    oldConfirmation()
    const action = vi.fn().mockResolvedValue(undefined)
    const password = await step()
    const field = await mountSuspended(PasswordConfirmField, { props: { step: password } })

    await password.run(action)
    await nextTick()
    password.setPassword('geheim')
    await nextTick()
    await field.findAll('wa-button').find(button => button.text() === 'Bestätigen')!.trigger('click')

    await vi.waitFor(() => expect(action).toHaveBeenCalledOnce())

    await password.run(action)
    await nextTick()
    await field.findAll('wa-button').find(button => button.text() === 'Abbrechen')!.trigger('click')

    expect(password.waiting).toBe(false)
  })
})

describe('ConfirmModal with a password', () => {
  const props = { open: true, title: 'Zugang widerrufen?', description: 'Danach geht er nicht mehr.', confirmLabel: 'Widerrufen' }

  it('asks for the password inside the dialog, then runs the action and closes', async () => {
    oldConfirmation()
    const action = vi.fn().mockResolvedValue(undefined)
    const modal = await mountSuspended(ConfirmModal, { props: { ...props, requiresPassword: true, action } })

    await modal.findAll('wa-button').find(button => button.text() === 'Widerrufen')!.trigger('click')
    await vi.waitFor(() => expect(modal.find('.password-confirm').exists()).toBe(true))

    expect(action).not.toHaveBeenCalled()
    expect(modal.findAll('wa-dialog')).toHaveLength(1)

    modal.findComponent(PasswordConfirmField).vm.$props.step.setPassword('geheim')
    await modal.findComponent(PasswordConfirmField).vm.$props.step.submit()

    expect(action).toHaveBeenCalledOnce()
    await vi.waitFor(() => expect(modal.emitted('close')).toHaveLength(1))
  })

  it('does not ask without the flag', async () => {
    oldConfirmation()
    const action = vi.fn().mockResolvedValue(undefined)
    const modal = await mountSuspended(ConfirmModal, { props: { ...props, action } })

    await modal.findAll('wa-button').find(button => button.text() === 'Widerrufen')!.trigger('click')

    await vi.waitFor(() => expect(action).toHaveBeenCalledOnce())
    expect(modal.find('.password-confirm').exists()).toBe(false)
  })

  it('shows the field again when the server says the confirmation ran out', async () => {
    const action = vi.fn().mockRejectedValue({ graphQLErrors: [{ message: 'x', extensions: { category: 'validation', validation: { password: ['Bitte bestätige zuerst dein Passwort.'] } } }] })
    const modal = await mountSuspended(ConfirmModal, { props: { ...props, requiresPassword: true, action } })

    await modal.findAll('wa-button').find(button => button.text() === 'Widerrufen')!.trigger('click')

    await vi.waitFor(() => expect(modal.find('.password-confirm').exists()).toBe(true))
    expect(modal.emitted('close')).toBeUndefined()
  })
})
