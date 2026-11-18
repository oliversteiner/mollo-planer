import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const css = readFileSync(fileURLToPath(new URL('../../app/assets/css/zen.css', import.meta.url)), 'utf8')

/** The mapping of the mood to the tokens (docs/zen-modus.md §4.6): what must stay in zen.css. */
describe('zen.css mapping', () => {
  it('maps the palette to the Web Awesome tokens', () => {
    for (const rule of [
      '--wa-color-surface-default: var(--zen-surface)',
      '--wa-color-surface-lowered: var(--zen-bg)',
      '--wa-color-text-normal: var(--zen-text)',
      '--wa-color-brand-fill-loud: var(--zen-accent)',
      '--wa-color-brand-on-loud: var(--zen-on-accent)',
      '--wa-color-focus: var(--zen-accent)',
      '--wa-color-danger-fill-loud: var(--zen-danger)',
      '--wa-color-warning-fill-loud: var(--zen-warn)'
    ]) {
      expect(css, rule).toContain(rule)
    }
  })

  it('colours the status badges: done moss, in progress and review ochre, the rest neutral', () => {
    expect(css).toMatch(/\.status-badge\[data-status='DONE'\][\s\S]*--zen-accent/)
    expect(css).toMatch(/\[data-status='IN_PROGRESS'\][\s\S]*\[data-status='REVIEW'\][\s\S]*--zen-warn/)
    expect(css).toMatch(/\.status-badge,[\s\S]*--zen-text\) 12%/)
  })

  it('colours the scroll bars and keeps the drawer at 96 % without dimming or blur', () => {
    expect(css).toContain('scrollbar-color: var(--zen-border) var(--zen-surface)')
    expect(css).toContain('var(--zen-surface, var(--wa-color-surface-default)) 96%')
    expect(css).toContain('--wa-color-overlay-modal: transparent')
    expect(css).toContain('backdrop-filter: none')
  })
})
