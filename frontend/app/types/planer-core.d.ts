/** The Markdown view of a planer file, from tools/planer/core (plain JavaScript, no dependencies). */
declare module '#planer-core/render-planer.mjs' {
  export function renderPlaner(plan: unknown, options?: { lang?: string, legendTranslations?: boolean }): string
}

declare module '#planer-core/planer-words.mjs' {
  export const LANGUAGES: readonly string[]
}
