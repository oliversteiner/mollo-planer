/**
 * Labels der mitgelieferten Vorlage „Softwareentwicklung“. Werte wie
 * backend/app/Support/BuiltinLabelSets.php (Schlüssel, Kategorie, Farbe) und
 * backend/lang/de|en/labels.php (Namen). Der Konverter kopiert sie in die Datei.
 */
export const SOFTWARE_DEV_LABELS = [
  { key: 'research', names: { de: 'Recherche', en: 'research' }, category: 'phase', color: 'blue' },
  { key: 'design', names: { de: 'Design', en: 'design' }, category: 'phase', color: 'pink' },
  { key: 'feature', names: { de: 'Feature', en: 'feature' }, category: 'type', color: 'green' },
  { key: 'bug', names: { de: 'Fehler', en: 'bug' }, category: 'type', color: 'red' },
  { key: 'planning', names: { de: 'Planung', en: 'planning' }, category: 'type', color: 'indigo' },
  { key: 'blocked', names: { de: 'Blockiert', en: 'blocked' }, category: 'status', color: 'yellow' },
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
