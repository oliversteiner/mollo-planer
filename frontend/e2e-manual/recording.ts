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
