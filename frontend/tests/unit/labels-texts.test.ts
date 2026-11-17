import { createI18n } from 'vue-i18n'
import { describe, expect, it } from 'vitest'
import de from '../../i18n/locales/de.json'
import en from '../../i18n/locales/en.json'
import { removalText } from '../../app/utils/labels'

/** One and many in the label texts (F6.2). */
function composer(locale: 'de' | 'en') {
  return createI18n({ legacy: false, locale, messages: { de, en } }).global
}

describe('label texts in the singular and the plural', () => {
  it.each([
    ['de', 1, 'Bei 1 Label gibt es keinen Namen in Englisch: Dort gilt der Name in der Sprache der Vorlage.'],
    ['de', 2, 'Bei 2 Labels gibt es keinen Namen in Englisch: Dort gilt der Name in der Sprache der Vorlage.'],
    ['en', 1, '1 label has no name in English: the name in the language of the template is used there.'],
    ['en', 2, '2 labels have no name in English: the name in the language of the template is used there.']
  ] as const)('templateFallback %s with %i', (locale, count, text) => {
    expect(composer(locale).t('projects.create.templateFallback', { count, language: locale === 'de' ? 'Englisch' : 'English' }, count)).toBe(text)
  })

  it.each([
    ['de', 1, 'Bei 1 Label der Vorlage gibt es keinen Namen in Englisch: Dort gilt der Name in der Sprache der Vorlage.'],
    ['de', 3, 'Bei 3 Labels der Vorlage gibt es keinen Namen in Englisch: Dort gilt der Name in der Sprache der Vorlage.'],
    ['en', 1, '1 label of the template has no name in English: the name in the language of the template is used there.']
  ] as const)('fallbackTemplate %s with %i', (locale, count, text) => {
    expect(composer(locale).t('labels.fallbackTemplate', { count, language: locale === 'de' ? 'Englisch' : 'English' }, count)).toBe(text)
  })

  it.each([
    ['de', { count: 1, tasks: 1 }, '1 Label wird gelöscht und von 1 Task entfernt.'],
    ['de', { count: 2, tasks: 3 }, '2 Labels werden gelöscht und von 3 Tasks entfernt.'],
    ['de', { count: 1, tasks: 0 }, '1 Label wird gelöscht.'],
    ['de', { count: 0, tasks: 0 }, 'Keine Labels werden gelöscht.'],
    ['en', { count: 1, tasks: 1 }, '1 label is deleted and removed from 1 task.'],
    ['en', { count: 2, tasks: 3 }, '2 labels are deleted and removed from 3 tasks.']
  ] as const)('removalText %s %j', (locale, removed, text) => {
    const { t } = composer(locale)

    expect(removalText((key, count) => t(key, { count }, count), removed)).toBe(text)
  })
})
