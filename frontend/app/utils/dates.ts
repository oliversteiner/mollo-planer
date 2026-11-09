/**
 * Due dates come from the API as "YYYY-MM-DD" (scalar Date, no time and
 * no time zone). They are compared and shown as calendar days.
 */

/** Today as "YYYY-MM-DD" in the local time zone. */
export function today(now: Date = new Date()): string {
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${day}`
}

/** Due before today (due today is not overdue). */
export function isOverdue(dueDate: string | null | undefined, now: Date = new Date()): boolean {
  return Boolean(dueDate) && (dueDate as string) < today(now)
}

/**
 * Calendar day in the given language, for example "24.12.2026" (de-CH),
 * or with the month written out: "24. Dezember 2026".
 */
export function formatDate(dueDate: string, language: string, monthName = false): string {
  const [year, month, day] = dueDate.split('-').map(Number) as [number, number, number]
  const options: Intl.DateTimeFormatOptions = monthName
    ? { day: 'numeric', month: 'long', year: 'numeric' }
    : { day: '2-digit', month: '2-digit', year: 'numeric' }

  return new Intl.DateTimeFormat(language, options).format(new Date(year, month - 1, day))
}

/** Point in time (ISO 8601 from the API) with date and time, local zone. */
export function formatDateTime(value: string, language: string): string {
  return new Intl.DateTimeFormat(language, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}
