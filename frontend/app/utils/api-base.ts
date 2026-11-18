/**
 * The address of the Laravel backend. A configured address (NUXT_PUBLIC_API_BASE) always wins. Without one, the
 * development server takes the host the page was opened with (`http://<host>:8000`), so `localhost:3000` and
 * `<IP of the Mac>:3000` (a tablet in the local network) both reach the backend. A build without an address
 * falls back to http://localhost:8000.
 */
export function resolveApiBase(configured: string | undefined, dev: boolean, location?: { protocol: string, hostname: string }): string {
  const value = (configured ?? '').trim().replace(/\/$/, '')

  if (value !== '') {
    return value
  }

  if (dev && location) {
    return `${location.protocol}//${location.hostname}:8000`
  }

  return 'http://localhost:8000'
}
