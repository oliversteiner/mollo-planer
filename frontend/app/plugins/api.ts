import type { ApolloLink } from '@apollo/client/core'
import { ApolloClient, InMemoryCache, from } from '@apollo/client/core'
import { setContext } from '@apollo/client/link/context'
import { onError } from '@apollo/client/link/error'
import { DefaultApolloClient } from '@vue/apollo-composable'
import createUploadLink from 'apollo-upload-client/createUploadLink.mjs'
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
    const backend = $fetch.create({
      baseURL: apiBase,
      credentials: 'include',
      async onRequest({ options }) {
        await ensureCsrfCookie()
        for (const [key, value] of Object.entries(headers())) {
          options.headers.set(key, value)
        }
      },
      onResponseError({ response }) {
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
    const errorLink = onError(({ graphQLErrors, networkError, operation }) => {
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
        backend,
        ensureCsrfCookie,
        apiErrors
      }
    }
  }
})
