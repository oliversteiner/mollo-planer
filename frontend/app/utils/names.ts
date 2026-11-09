/** One or two capital letters for an avatar without a picture: "Lindita Aliu" gives "LA". */
export function initials(name: string | null | undefined): string {
  const words = (name ?? '').trim().split(/\s+/).filter(Boolean)

  if (!words.length) {
    return ''
  }

  const first = Array.from(words[0]!)[0] ?? ''
  const last = words.length > 1 ? (Array.from(words.at(-1)!)[0] ?? '') : ''

  return (first + last).toLocaleUpperCase()
}
