/**
 * Feste Wörter der Markdown-Ansicht in den unterstützten Sprachen (planer-abgleich §5.3).
 * Die Datei selbst kennt nur die Werte in Englisch (`planned`, `none`, `de`), die Ansicht
 * zeigt sie übersetzt. `md2json` liest beide Sprachen.
 */
export const LANGUAGES = Object.freeze(['de', 'en']);

export const WORDS = Object.freeze({
  de: {
    language: 'Sprache',
    members: 'Mitglieder',
    status: { planned: 'geplant', released: 'veröffentlicht' },
    due: 'Fällig',
    assignee: 'Zuständig',
    labels: 'Labels',
    commit: 'Commit',
    noCommit: 'Ohne Commit',
    story: (role, goal, benefit) => `Als ${role} möchte ich ${goal}, damit ${benefit}.`,
    legendKey: 'Schlüssel',
    legendName: 'Name',
    legendCategory: 'Kategorie',
    legendColor: 'Farbe',
  },
  en: {
    language: 'Language',
    members: 'Members',
    status: { planned: 'planned', released: 'released' },
    due: 'Due',
    assignee: 'Assignee',
    labels: 'Labels',
    commit: 'Commit',
    noCommit: 'None',
    story: (role, goal, benefit) => `As ${role} I want ${goal}, so that ${benefit}.`,
    legendKey: 'Key',
    legendName: 'Name',
    legendCategory: 'Category',
    legendColor: 'Colour',
  },
});

/** Die Sprache eines Namens: nur de und en, sonst die Sprache des Projekts. */
export function languageOrDefault(lang, fallback) {
  return LANGUAGES.includes(lang) ? lang : fallback;
}
