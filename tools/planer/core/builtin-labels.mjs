/**
 * Labels der mitgelieferten Vorlage „Softwareentwicklung“, wie in
 * spec/templates/software-development.planer.json (Schlüssel, Namen,
 * Kategorie, Farbe). Der Konverter kopiert sie in die Datei.
 */
export const SOFTWARE_DEV_LABELS = [
  { key: 'research', names: { de: 'Recherche', en: 'Research' }, category: 'phase', color: 'blue' },
  { key: 'design', names: { de: 'Design', en: 'Design' }, category: 'phase', color: 'pink' },
  { key: 'feature', names: { de: 'Feature', en: 'Feature' }, category: 'type', color: 'green' },
  { key: 'bug', names: { de: 'Fehler', en: 'Bug' }, category: 'type', color: 'red' },
  { key: 'planning', names: { de: 'Planung', en: 'Planning' }, category: 'type', color: 'indigo' },
  { key: 'blocked', names: { de: 'Blockiert', en: 'Blocked' }, category: 'status', color: 'yellow' },
];

/** Die Labels der Vorlage mit Namen in beiden Sprachen, in der Reihenfolge der Vorlage. */
export function softwareDevLabels() {
  return SOFTWARE_DEV_LABELS.map((label) => ({
    key: label.key,
    names: { ...label.names },
    category: label.category,
    color: label.color,
  }));
}
