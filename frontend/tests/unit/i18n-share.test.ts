import { describe, expect, it } from 'vitest'
import de from '../../i18n/locales/de.json'
import en from '../../i18n/locales/en.json'

describe('share texts', () => {
  it('German has every task.share key that English has, including labels', () => {
    const englishKeys = Object.keys(en.task.share).sort()
    const germanKeys = Object.keys(de.task.share ?? {}).sort()

    expect(germanKeys).toEqual(englishKeys)
    expect(Object.keys(de.task.share.labels).sort()).toEqual(Object.keys(en.task.share.labels).sort())
  })
})
