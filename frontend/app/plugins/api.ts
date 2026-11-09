import type { ApolloLink } from '@apollo/client/core'
import { ApolloClient, InMemoryCache, from } from '@apollo/client/core'
import { setContext } from '@apollo/client/link/context'
import { onError } from '@apollo/client/link/error'
import { fromPromise } from '@apollo/client/link/utils'
import { DefaultApolloClient } from '@vue/apollo-composable'
import createUploadLink from 'apollo-upload-client/createUploadLink.mjs'
import type { $Fetch } from 'ofetch'
import type { ApiError } from '~/utils/api'
import { classifyApolloError, classifyRestError, readCookie } from '~/utils/api'

/**
 * Connection to the backend (separate origin, cookie session via Sanctum):
 * - $backend: REST requests to Fortify (login, 2FA, passwords, invitation)
 * - Apollo: GraphQL, including file uploads (multipart)
 * Both send the session cookie, the XSRF token and the UI language, and
 * report failures through $apiErrors.
 */
export default defineNuxtPlugin({
  name: 'api',
  setup(nuxtApp) {
    const apiBase = useRuntimeConfig().public.apiBase.replace(/\/$/, '')
    const i18n = nuxtApp.$i18n as { locale: { value: string } }

    const xsrfToken = () => readCookie(document.cookie, 'XSRF-TOKEN')

    /** Laravel sets XSRF-TOKEN on this route; needed before the first change. */
    async function ensureCsrfCookie(force = false): Promise<void> {
      if (force || !xsrfToken()) {
        await $fetch('/sanctum/csrf-cookie', { baseURL: apiBase, credentials: 'include' })
      }
    }

    function headers(): Record<string, string> {
      const token = xsrfToken()

      return {
        'Accept': 'application/json',
        'Accept-Language': i18n.locale.value,
        ...(token ? { 'X-XSRF-TOKEN': token } : {})
      }
    }

    const errorListeners = new Set<(error: ApiError) => void>()
    const apiErrors = {
      /** Called for every failed request; returns a function to stop listening. */
      on(listener: (error: ApiError) => void): () => void {
        errorListeners.add(listener)

        return () => errorListeners.delete(listener)
      },
      trigger(error: ApiError): void {
        errorListeners.forEach(listener => listener(error))
      }
    }

    // Validation errors (422) stay with the form that sent the request;
    // everything else goes to $apiErrors.
    //
    // 419 (CSRF token mismatch) happens when the XSRF-TOKEN cookie in the
    // browser belongs to a session that no longer exists (expired, or ended
    // by a logout elsewhere). Then a fresh cookie is fetched and the request
    // is sent once more; only if that fails too, the user sees a message.
    const backend = $fetch.create({
      baseURL: apiBase,
      credentials: 'include',
      retry: 1,
      retryDelay: 0,
      retryStatusCodes: [419],
      async onRequest({ options }) {
        await ensureCsrfCookie()
        for (const [key, value] of Object.entries(headers())) {
          options.headers.set(key, value)
        }
      },
      async onResponseError({ response, options }) {
        if (response.status === 419 && Number(options.retry ?? 0) > 0) {
          await ensureCsrfCookie(true)

          return
        }

        const error = classifyRestError(response.status, response._data)

        if (error.kind !== 'validation') {
          apiErrors.trigger(error)
        }
      }
    })

    const contextLink = setContext(async (_, { headers: current }) => {
      await ensureCsrfCookie()

      return { headers: { ...current, ...headers() } }
    })

    // Queries that handle their errors themselves pass
    // context: { silentErrors: true } (for example the viewer check of the
    // route middleware).
    const errorLink = onError(({ graphQLErrors, networkError, operation, forward }) => {
      const status = networkError && 'statusCode' in networkError ? networkError.statusCode : null

      // Same as for REST: a stale CSRF cookie gets replaced, then one retry.
      if (status === 419 && !operation.getContext().csrfRetried) {
        operation.setContext({ csrfRetried: true })

        return fromPromise(ensureCsrfCookie(true)).flatMap(() => forward(operation))
      }

      if (!operation.getContext().silentErrors) {
        apiErrors.trigger(classifyApolloError({ graphQLErrors, networkError }))
      }
    })

    const uploadLink = createUploadLink({
      uri: `${apiBase}/graphql`,
      credentials: 'include'
    }) as unknown as ApolloLink

    const apollo = new ApolloClient({
      link: from([errorLink, contextLink, uploadLink]),
      cache: new InMemoryCache()
    })

    nuxtApp.vueApp.provide(DefaultApolloClient, apollo)

    return {
      provide: {
        apollo,
        // Typed as plain ofetch: the backend is another origin, the routes of
        // this app (Nitro's typed requests) do not apply, and inferring them
        // is too deep for TypeScript.
        backend: backend as $Fetch,
        ensureCsrfCookie,
        apiErrors
      }
    }
  }
})
