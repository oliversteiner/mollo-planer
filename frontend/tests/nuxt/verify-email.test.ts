import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { FetchError } from 'ofetch'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import ResendVerification from '~/components/auth/ResendVerification.vue'
import AccountPendingPage from '~/pages/account-pending.vue'
import VerifyEmailPage from '~/pages/verify-email.vue'

const { backend, raw, load, redirect, toast } = vi.hoisted(() => ({
  backend: vi.fn(),
  raw: vi.fn(),
  load: vi.fn(),
  redirect: vi.fn(),
  toast: vi.fn()
}))

// The pages talk to the backend with $backend (REST, also $backend.raw); the tests answer for it.
mockNuxtImport('useNuxtApp', original => () => {
  const nuxtApp = original()
  const $backend = Object.assign(backend, { raw })

  return new Proxy(nuxtApp, { get: (target, key) => (key === '$backend' ? $backend : Reflect.get(target, key)) })
})
mockNuxtImport('useViewer', () => () => ({ viewer: ref(null), load, logout: vi.fn(), can: () => false }))
mockNuxtImport('navigateTo', () => redirect)
mockNuxtImport('useToast', () => () => ({ add: toast }))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  for (const mock of [backend, raw, load, redirect, toast]) {
    mock.mockReset()
  }
})

afterEach(() => {
  vi.useRealTimers()
})

const LINK = '/verify-email?id=7&hash=abc123&expires=1795031799&signature=sig456'

type Page = Awaited<ReturnType<typeof mountSuspended>>

/** The wa-input with this name (Vue sets `name` as a property, not as an attribute). */
function field(page: Page, name: string) {
  return page.findAll('wa-input').find((candidate: { element: Element }) => (candidate.element as unknown as { name: string }).name === name)
}

function button(page: Page, text: string) {
  return page.findAll('wa-button').find((candidate: { text: () => string }) => candidate.text() === text)!
}

describe('verify email page', () => {
  it('shows the button and sends nothing when it is only opened', async () => {
    const page = await mountSuspended(VerifyEmailPage, { route: LINK })

    expect(page.text()).toContain('Bestätige, dass diese Adresse dir gehört.')
    expect(button(page, 'E-Mail-Adresse bestätigen').exists()).toBe(true)
    expect(backend).not.toHaveBeenCalled()
    expect(load).not.toHaveBeenCalled()
  })

  it('confirms with the click, as a POST to the address of the link, and goes on into the app when logged in', async () => {
    backend.mockResolvedValue({ status: 'ok' })
    load.mockResolvedValue(null)
    const page = await mountSuspended(VerifyEmailPage, { route: LINK })

    await button(page, 'E-Mail-Adresse bestätigen').trigger('click')
    await vi.waitFor(() => expect(redirect).toHaveBeenCalledWith('/'))

    expect(backend).toHaveBeenCalledWith('/email/verify/7/abc123', { method: 'POST', query: { expires: '1795031799', signature: 'sig456' } })
    expect(page.text()).toContain('Deine E-Mail-Adresse ist bestätigt.')
  })

  it('leads a logged-in account on to the setup or to the approval', async () => {
    backend.mockResolvedValue({ status: 'ok' })
    load.mockResolvedValueOnce('setup-required')
    const setup = await mountSuspended(VerifyEmailPage, { route: LINK })
    await button(setup, 'E-Mail-Adresse bestätigen').trigger('click')
    await vi.waitFor(() => expect(redirect).toHaveBeenCalledWith('/setup-security'))

    redirect.mockReset()
    load.mockResolvedValueOnce('awaiting-approval')
    const approval = await mountSuspended(VerifyEmailPage, { route: LINK })
    await button(approval, 'E-Mail-Adresse bestätigen').trigger('click')
    await vi.waitFor(() => expect(redirect).toHaveBeenCalledWith('/account-pending?reason=approval'))
  })

  it('tells a person without a session what comes next, and the sign-in button works', async () => {
    backend.mockResolvedValue({ status: 'ok' })
    load.mockResolvedValue('unauthenticated')
    const page = await mountSuspended(VerifyEmailPage, { route: LINK })

    await button(page, 'E-Mail-Adresse bestätigen').trigger('click')
    await vi.waitFor(() => expect(page.text()).toContain('Melde dich jetzt an und richte deinen zweiten Faktor ein.'))

    expect(page.text()).toContain('Ein Admin gibt dein Konto frei; du bekommst eine Mail.')
    expect(page.text()).not.toContain('sobald ein Admin')
    await button(page, 'Anmelden').trigger('click')
    expect(redirect).toHaveBeenCalledWith('/login')
  })

  it('says so when the address was already confirmed', async () => {
    backend.mockResolvedValue({ status: 'already' })
    load.mockResolvedValue('unauthenticated')
    const page = await mountSuspended(VerifyEmailPage, { route: LINK })

    await button(page, 'E-Mail-Adresse bestätigen').trigger('click')
    await vi.waitFor(() => expect(page.text()).toContain('Diese Adresse ist schon bestätigt.'))

    expect(page.text()).not.toContain('Deine E-Mail-Adresse ist bestätigt.')
  })

  it('an invalid answer leads to the error with the two ways on (the address form without a session)', async () => {
    backend.mockResolvedValue({ status: 'invalid' })
    load.mockResolvedValue('unauthenticated')
    const page = await mountSuspended(VerifyEmailPage, { route: LINK })

    await button(page, 'E-Mail-Adresse bestätigen').trigger('click')
    await vi.waitFor(() => expect(page.text()).toContain('Der Link ist ungültig oder abgelaufen.'))

    expect(page.text()).not.toContain('Deine E-Mail-Adresse ist bestätigt.')
    expect(field(page, 'email')).toBeDefined()
    expect(page.text()).toContain('Bestätigungsmail erneut senden')
    expect(page.text()).toContain('Zur Anmeldung')
  })

  it('an invalid answer with a session offers the button without a form', async () => {
    backend.mockResolvedValue({ status: 'invalid' })
    load.mockResolvedValue('unverified')
    const page = await mountSuspended(VerifyEmailPage, { route: LINK })

    await button(page, 'E-Mail-Adresse bestätigen').trigger('click')
    await vi.waitFor(() => expect(field(page, 'email')).toBeUndefined())

    expect(button(page, 'Bestätigungsmail erneut senden').exists()).toBe(true)
  })

  it('status=resend (from the mail "registration not confirmed") is no error: the form, without a session', async () => {
    load.mockResolvedValue('unauthenticated')
    const page = await mountSuspended(VerifyEmailPage, { route: '/verify-email?status=resend' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    expect(page.text()).toContain('Gib deine Adresse ein. Wir schicken dir die Bestätigungsmail nochmals.')
    expect(page.text()).not.toContain('ungültig oder abgelaufen')
    expect(page.find('wa-callout').exists()).toBe(false)
    expect(field(page, 'email')).toBeDefined()
    expect(page.text()).toContain('Zur Anmeldung')
    expect(backend).not.toHaveBeenCalled()
  })

  it('status=resend with a session offers the button without the error', async () => {
    load.mockResolvedValue('unverified')
    const page = await mountSuspended(VerifyEmailPage, { route: '/verify-email?status=resend' })
    await vi.waitFor(() => expect(field(page, 'email')).toBeUndefined())

    expect(button(page, 'Bestätigungsmail erneut senden')).toBeDefined()
    expect(page.text()).not.toContain('ungültig oder abgelaufen')
  })

  it('status=invalid still shows the error with the same form', async () => {
    load.mockResolvedValue('unauthenticated')
    const page = await mountSuspended(VerifyEmailPage, { route: '/verify-email?status=invalid' })

    expect(page.text()).toContain('Der Link ist ungültig oder abgelaufen.')
    expect(page.text()).not.toContain('Gib deine Adresse ein.')
  })

  it('shows the error at once for status=invalid or an incomplete link, and asks the API for nothing', async () => {
    load.mockResolvedValue('unauthenticated')

    const marked = await mountSuspended(VerifyEmailPage, { route: '/verify-email?status=invalid' })
    expect(marked.text()).toContain('Der Link ist ungültig oder abgelaufen.')

    const incomplete = await mountSuspended(VerifyEmailPage, { route: '/verify-email?id=7&hash=abc' })
    expect(incomplete.text()).toContain('Der Link ist ungültig oder abgelaufen.')

    const none = await mountSuspended(VerifyEmailPage, { route: '/verify-email' })
    expect(none.text()).toContain('Der Link ist ungültig oder abgelaufen.')
    expect(backend).not.toHaveBeenCalled()
  })

  it('keeps the button and says so when the request fails', async () => {
    backend.mockRejectedValue(new FetchError('offline'))
    const page = await mountSuspended(VerifyEmailPage, { route: LINK })

    await button(page, 'E-Mail-Adresse bestätigen').trigger('click')
    await vi.waitFor(() => expect(page.text()).toContain('Die Bestätigung hat nicht geklappt.'))

    expect(button(page, 'E-Mail-Adresse bestätigen').exists()).toBe(true)
    expect(page.text()).not.toContain('Deine E-Mail-Adresse ist bestätigt.')
  })
})

describe('resend the confirmation mail', () => {
  it('logged in: sends, says so, and locks the button for a minute', async () => {
    raw.mockResolvedValue({ status: 202 })
    const page = await mountSuspended(ResendVerification, { props: { mode: 'session' } })

    await button(page, 'Bestätigungsmail erneut senden').trigger('click')
    await vi.waitFor(() => expect(toast).toHaveBeenCalledWith({ title: 'Wir haben dir eine neue Mail geschickt. Sie kann eine Minute brauchen.', color: 'success' }))

    expect(raw).toHaveBeenCalledWith('/email/verification-notification', { method: 'POST' })
    const locked = button(page, 'Erneut senden in 60 s')
    expect(locked.exists()).toBe(true)
    expect((locked.element as unknown as { disabled: boolean }).disabled).toBe(true)
  })

  it('opens the button again after a minute', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
    raw.mockResolvedValue({ status: 202 })
    const page = await mountSuspended(ResendVerification, { props: { mode: 'session' } })

    await button(page, 'Bestätigungsmail erneut senden').trigger('click')
    await vi.waitFor(() => expect(toast).toHaveBeenCalled())
    await vi.advanceTimersByTimeAsync(60_000)

    expect(button(page, 'Bestätigungsmail erneut senden').exists()).toBe(true)
  })

  it('logged in, an answer 204 means the address is confirmed meanwhile: no message, the page checks the account', async () => {
    raw.mockResolvedValue({ status: 204 })
    const page = await mountSuspended(ResendVerification, { props: { mode: 'session' } })

    await button(page, 'Bestätigungsmail erneut senden').trigger('click')
    await vi.waitFor(() => expect(page.emitted('confirmed')).toHaveLength(1))

    expect(toast).not.toHaveBeenCalled()
  })

  it('without a session: asks for the address and answers the same way for every address', async () => {
    backend.mockResolvedValue({ message: 'ok' })
    const page = await mountSuspended(ResendVerification, { props: { mode: 'address' } })
    const input = field(page, 'email')!.element as HTMLElement & { value: string }
    Object.defineProperty(input, 'value', { value: 'eva@example.com', writable: true, configurable: true })
    input.dispatchEvent(new Event('input'))

    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.text()).toContain('Wenn ein Konto mit dieser Adresse auf Bestätigung wartet, haben wir eine Mail geschickt.'))

    expect(backend).toHaveBeenCalledWith('/email/verification-notification/guest', { method: 'POST', body: { email: 'eva@example.com' } })
    expect(button(page, 'Erneut senden in 60 s').exists()).toBe(true)
  })

  it('without a session, a field error from the server stays at the field and nothing is said as sent', async () => {
    backend.mockRejectedValue(Object.assign(new FetchError('422'), { status: 422, data: { message: 'invalid', errors: { email: ['Die E-Mail-Adresse ist ungültig.'] } } }))
    const page = await mountSuspended(ResendVerification, { props: { mode: 'address' } })

    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.text()).toContain('Die E-Mail-Adresse ist ungültig.'))

    expect(page.text()).not.toContain('haben wir eine Mail geschickt')
  })
})

describe('pending account page', () => {
  it('offers to send the mail again while the address is not confirmed, and checks the account again on 204', async () => {
    load.mockResolvedValue('unverified')
    raw.mockResolvedValue({ status: 204 })
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=email' })
    await vi.waitFor(() => expect(load).toHaveBeenCalledTimes(1))

    await button(page, 'Bestätigungsmail erneut senden').trigger('click')
    await vi.waitFor(() => expect(load).toHaveBeenCalledTimes(2))
  })

  it('has no such button once the address is confirmed and the approval is awaited', async () => {
    load.mockResolvedValue('awaiting-approval')
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=approval' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    expect(page.text()).not.toContain('Bestätigungsmail erneut senden')
  })
})
