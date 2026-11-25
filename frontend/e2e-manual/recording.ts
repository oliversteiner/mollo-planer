import type { Locator, Page } from '@playwright/test'

/**
 * A recording file of the manual (handbuch/aufnahmen/<chapter>.mts, Auftrag 54 F): the pictures of one chapter. Each
 * shot brings the page into the state the step of the chapter describes and returns the part to take (or null for the
 * whole window). It runs once in German and once in English; `t` gives the label of a key of the language file, so a
 * shot clicks what the person reads.
 */
export interface ShotContext {
  lang: 'de' | 'en'
  t: (key: string) => string
  /** A name that is new in this run (the browser tests share one database). */
  unique: (label: string) => string
}

export interface Shot {
  /** The name of the picture, as in {{bild:<name>|…}} of the chapter. */
  name: string
  take: (page: Page, context: ShotContext) => Promise<Locator | null>
}

export interface Recording {
  chapter: string
  shots: Shot[]
}

/**
 * Replaces visible texts of the throwaway database by neutral, speaking ones before a picture is taken (texts in the
 * page and in the shadow roots of the Web Awesome elements; values of text fields too). Only for the picture: nothing
 * is written to the database.
 */
export async function relabel(page: Page, pairs: Record<string, string>): Promise<void> {
  await page.evaluate((replacements) => {
    const apply = (text: string): string => Object.entries(replacements).reduce((result, [from, to]) => result.split(from).join(to), text)
    const walk = (root: Node): void => {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)

      for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        node.nodeValue = apply(node.nodeValue ?? '')
      }

      for (const element of (root as Element | Document).querySelectorAll?.('*') ?? []) {
        if (element.shadowRoot) {
          walk(element.shadowRoot)
        }

        // The text of a select is shown in an input inside the shadow root.
        if (element instanceof HTMLInputElement && element.type === 'text' && element.value) {
          element.value = apply(element.value)
        }

        const field = element as HTMLElement & { value?: unknown }

        if (typeof field.value === 'string' && element.tagName.startsWith('WA-') && field.value && !element.hasAttribute('readonly')) {
          const next = apply(field.value)

          if (next !== field.value) {
            field.value = next
          }
        }
      }
    }

    walk(document.body)
  }, pairs)
}
