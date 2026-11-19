/**
 * A name suggestion for a new passkey, from the user agent: "Mac · Safari". The browser does not tell the
 * name of the device, so this is only a guess to recognise the passkey later; the person may change it.
 * Returns null when nothing is recognised.
 */
export function deviceName(userAgent: string): string | null {
  const system = [
    [/iPhone/, 'iPhone'],
    [/iPad/, 'iPad'],
    [/Android/, 'Android'],
    [/Windows/, 'Windows'],
    [/Mac OS X|Macintosh/, 'Mac'],
    [/CrOS/, 'Chromebook'],
    [/Linux/, 'Linux']
  ] as const
  const browser = [
    [/Edg\//, 'Edge'],
    [/OPR\/|Opera/, 'Opera'],
    [/Firefox\/|FxiOS/, 'Firefox'],
    [/Chrome\/|CriOS/, 'Chrome'],
    [/Safari\//, 'Safari']
  ] as const

  const os: string | undefined = system.find(([pattern]) => pattern.test(userAgent))?.[1]
  const name: string | undefined = browser.find(([pattern]) => pattern.test(userAgent))?.[1]
  const parts = [os, name].filter((part): part is string => part !== undefined)

  return parts.length > 0 ? parts.join(' · ') : null
}
