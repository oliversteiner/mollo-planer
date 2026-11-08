import type { PublicKeyCredentialCreationOptionsJSON, PublicKeyCredentialRequestOptionsJSON } from '@simplewebauthn/browser'
import { startAuthentication, startRegistration } from '@simplewebauthn/browser'
import { FetchError } from 'ofetch'
import { classifyRestError } from '~/utils/api'

/**
 * Second factor and passkeys through the Fortify and passkey routes
 * (REST). Every change first makes sure the password was confirmed
 * recently, asking for it if needed.
 */
export function useSecurity() {
  const { $backend } = useNuxtApp()
  const { ask } = usePasswordPrompt()

  /** True when confirmed (now or recently), false when cancelled. */
  async function ensurePasswordConfirmed(): Promise<boolean> {
    const status = await $backend<{ confirmed: boolean }>('/user/confirmed-password-status')

    if (status.confirmed) {
      return true
    }

    let previousError = ''

    for (;;) {
      const password = await ask(previousError)

      if (password === null) {
        return false
      }
      try {
        await $backend('/user/confirm-password', { method: 'POST', body: { password } })

        return true
      } catch (error) {
        const apiError = error instanceof FetchError ? classifyRestError(error.status ?? 0, error.data) : null

        if (apiError?.kind !== 'validation') {
          throw error
        }
        previousError = apiError.fields.password?.[0] ?? apiError.message ?? ''
      }
    }
  }

  /** Starts the setup of an authenticator app; returns the QR code and key. */
  async function startTwoFactor(): Promise<{ qrCode: string, secretKey: string } | null> {
    if (!await ensurePasswordConfirmed()) {
      return null
    }
    await $backend('/user/two-factor-authentication', { method: 'POST' })
    const [{ svg }, { secretKey }] = await Promise.all([
      $backend<{ svg: string }>('/user/two-factor-qr-code'),
      $backend<{ secretKey: string }>('/user/two-factor-secret-key')
    ])

    // An image, not inserted HTML: the SVG cannot run anything here.
    return { qrCode: `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`, secretKey }
  }

  /** Confirms the app with a code from it (validation error on "code"). */
  async function confirmTwoFactor(code: string): Promise<void> {
    await $backend('/user/confirmed-two-factor-authentication', { method: 'POST', body: { code } })
  }

  async function recoveryCodes(): Promise<string[] | null> {
    if (!await ensurePasswordConfirmed()) {
      return null
    }

    return $backend<string[]>('/user/two-factor-recovery-codes')
  }

  async function regenerateRecoveryCodes(): Promise<string[] | null> {
    if (!await ensurePasswordConfirmed()) {
      return null
    }
    await $backend('/user/two-factor-recovery-codes', { method: 'POST' })

    return $backend<string[]>('/user/two-factor-recovery-codes')
  }

  async function disableTwoFactor(): Promise<boolean> {
    if (!await ensurePasswordConfirmed()) {
      return false
    }
    await $backend('/user/two-factor-authentication', { method: 'DELETE' })

    return true
  }

  /** Registers a passkey on this device (the browser asks for PIN or biometrics). */
  async function addPasskey(name: string): Promise<boolean> {
    if (!await ensurePasswordConfirmed()) {
      return false
    }
    const { options } = await $backend<{ options: PublicKeyCredentialCreationOptionsJSON }>('/user/passkeys/options')
    const credential = await startRegistration({ optionsJSON: options })
    await $backend('/user/passkeys', { method: 'POST', body: { name, credential } })

    return true
  }

  async function removePasskey(id: string): Promise<boolean> {
    if (!await ensurePasswordConfirmed()) {
      return false
    }
    await $backend(`/user/passkeys/${encodeURIComponent(id)}`, { method: 'DELETE' })

    return true
  }

  /** Login with a passkey (one step, no code needed). */
  async function loginWithPasskey(): Promise<void> {
    const { options } = await $backend<{ options: PublicKeyCredentialRequestOptionsJSON }>('/passkeys/login/options')
    const credential = await startAuthentication({ optionsJSON: options })
    await $backend('/passkeys/login', { method: 'POST', body: { credential } })
  }

  return {
    startTwoFactor,
    confirmTwoFactor,
    recoveryCodes,
    regenerateRecoveryCodes,
    disableTwoFactor,
    addPasskey,
    removePasskey,
    loginWithPasskey
  }
}
