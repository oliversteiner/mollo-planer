import type { AccountOutcome, Failure, Occasion } from '~/utils/account-outcome'
import { accountOutcome } from '~/utils/account-outcome'

const FAILURES: Record<Failure, { key: string, color: 'error' | 'warning' }> = {
  unauthorized: { key: 'apiError.unauthorized', color: 'error' },
  csrf: { key: 'apiError.csrf', color: 'warning' },
  network: { key: 'apiError.network', color: 'error' },
  unknown: { key: 'apiError.unknown', color: 'error' }
}

export interface AccountFlowHandlers {
  /** No session or not a strong one. Without a handler the person is sent to the login page. */
  onSignedOut?: () => void | Promise<void>
  /** After a password, for an account protected by a passkey. */
  onPasskeyNeeded?: () => void | Promise<void>
}

/**
 * Asks the API who is logged in and carries out what the account pages do with the answer
 * (see utils/account-outcome.ts): go on, say what went wrong, or hand the case to the page.
 * The same messages as plugins/api-errors.ts, because the viewer check is silent.
 */
export function useAccountFlow() {
  const { t } = useI18n()
  const route = useRoute()
  const toast = useToast()
  const { load } = useViewer()

  async function proceed(occasion: Occasion, handlers: AccountFlowHandlers = {}): Promise<AccountOutcome> {
    const outcome = accountOutcome(await load(), { occasion, fullPath: route.fullPath, redirect: route.query.redirect })

    switch (outcome.kind) {
      case 'go':
        await (outcome.replace ? navigateTo(outcome.to, { replace: true }) : navigateTo(outcome.to))
        break
      case 'signed-out':
        await (handlers.onSignedOut ?? (() => navigateTo('/login')))()
        break
      case 'passkey-needed':
        await handlers.onPasskeyNeeded?.()
        break
      case 'failed':
        toast.add({ title: t(FAILURES[outcome.failure].key), color: FAILURES[outcome.failure].color })
        break
    }

    return outcome
  }

  return { proceed }
}
