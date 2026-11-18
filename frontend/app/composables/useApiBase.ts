import { resolveApiBase } from '~/utils/api-base'

/** The address of the backend without a trailing slash (see utils/api-base.ts). */
export function useApiBase(): string {
  return resolveApiBase(useRuntimeConfig().public.apiBase, import.meta.dev, typeof window === 'undefined' ? undefined : window.location)
}
