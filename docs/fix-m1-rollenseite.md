# Fix F1 vor der Abnahme M1: Rollenseite übersichtlich machen

Stand: 2026-11-16, Planung. Code bis `8e2bf87`.
Anlass: Abnahme M1 (Oliver): `/admin/roles` ist sehr unübersichtlich (vier lange Abschnitte mit Karten, Rechte als lange Kommaliste, Übersicht auf eigener Seite).
Entschieden (Oliver, 2026-11-16): Reiter System | Projekt, darin Rollen / Funktionen / Übersicht; kompakte Zeilen; Filter Textsuche, Mitgeliefert/Eigene, „Hat Recht …“, In Gebrauch/ungenutzt; **jetzt, vor der Abnahme M1**.

Nur Frontend, kein Backend-Umbau nötig (alle Daten liefern `roles(scope)`, `roleFunctions(scope)`, `rights(scope)`, `permissionMatrix`). Verhalten der Mutationen bleibt.

---

## 1. Aufbau

```
/admin/roles?scope=project&view=roles
┌ Rollen und Rechte ─────────────────────────────── [+ Neue Rolle] ┐
│ [ System | Projekt ]                      ← wa-tab-group          │
│ ( Rollen ) Funktionen  Übersicht          ← wa-radio-group Knöpfe │
│ 🔍 Suche…  [Alle ▾] [Hat Recht ▾] [Gebrauch ▾]   Filter zurücksetzen│
│ ──────────────────────────────────────────────────────────────── │
│ ▸ Owner      Standard·Vorgabe  Planen, Mitarbeiten +2  3 Pers.  ⋯ │
│ ▸ Manager    Standard          Planen, Mitarbeiten +1  5 Pers.  ⋯ │
│ ▸ Redaktion  Eigene            Planen, Mitarbeiten     1 Pers.  ⋯ │
└──────────────────────────────────────────────────────────────────┘
```

- **Reiter** `System` | `Projekt` (`wa-tab-group`). Standard: `Projekt` (wird am häufigsten angepasst).
- **Ansicht** je Reiter: `Rollen` | `Funktionen` | `Übersicht` (`wa-radio-group` als Knopfgruppe).
- **Zustand in der URL** (`?scope=system|project&view=roles|functions|matrix&q=…&origin=…&right=…&usage=…`, über `router.replace`): Neuladen, Zurück-Knopf und Links behalten Reiter, Ansicht und Filter.
- **Knopf oben rechts** passt sich der Ansicht an: „Neue Rolle“ bzw. „Neue Funktion“; der Bereich (System/Projekt) ist im Formular vorbelegt. In der Übersicht kein Knopf.
- **Übersicht** = die heutige Tabelle aus `/system/rechte`, nur für den gewählten Bereich: Zeilen Rechte, Spalten Rollen. `/system/rechte` leitet auf `/admin/roles?view=matrix` um; der Menüpunkt „Rechte“ entfällt (`route-rights.ts` nachführen). Damit ist auch der offene R1-Punkt „Rechte-Übersicht in `/admin/roles`“ erledigt.

## 2. Zeilen statt Karten

**Rolle (eine Zeile):** Aufklapp-Pfeil · Name · Badges (`Standard` bzw. `Eigene`, `Vorgabe` für die Standardrolle, `Ersteller` für die Ersteller-Rolle im Projekt-Reiter) · Funktionen als Tags (höchstens 3, dann „+n“) · bei Systemrollen die Obergrenze kurz („alle“, „keine“, „7 Projektrechte“) · Anzahl Personen · Aktionsmenü.

**Funktion (eine Zeile):** Aufklapp-Pfeil · Name · Badge · Anzahl Rechte („12 Rechte“) · verwendet von „Owner, Manager“ (höchstens 3, dann „+n“) · Aktionsmenü.

**Aufgeklappt:** alle Rechte, gruppiert nach Bereich (Präfix des Rechts: Projekt, Mitglieder, Milestones, ToDos, Tasks, Kommentare, Papierkorb, Planer …), jedes Recht mit seinem Kurztext (`lang/*/rights.php`). Bei Rollen zusätzlich: über welche Funktion ein Recht kommt (als Hinweis hinter dem Recht).

- Liste als `ul` mit Zeilen (`wa-details` oder eigenes Aufklappen mit `aria-expanded`), keine Karten.
- Telefonbreite: Zeile wird zweizeilig (Name und Badges oben, Tags und Anzahl darunter), keine seitliche Scrollleiste der Seite. Die Übersicht (Tabelle) scrollt seitlich **in ihrem Rahmen**, die erste Spalte (Recht) bleibt stehen.

## 3. Filter

Gelten für die aktuelle Ansicht im aktuellen Reiter, rein im Frontend (die Daten sind klein).

| Filter | Rollen | Funktionen | Übersicht |
|---|---|---|---|
| Textsuche | Name der Rolle (DE/EN), Namen ihrer Funktionen, Kurztexte ihrer Rechte | Name, Kurztexte ihrer Rechte | Zeilen: Kurztext oder Schlüssel des Rechts |
| Mitgeliefert / Eigene | Rollen | Funktionen | Spalten (Rollen) |
| Hat Recht … (Auswahl, mehrfach) | Rollen, die alle gewählten Rechte haben | Funktionen, die sie enthalten | nur diese Zeilen |
| In Gebrauch / ungenutzt | Rollen mit / ohne Personen | Funktionen mit / ohne Rolle | Spalten: Rollen mit / ohne Personen |

- Suche ohne Groß-/Kleinschreibung und ohne Akzente, mit kurzer Verzögerung (ca. 200 ms).
- Treffer im Namen oder Recht leicht hervorheben; bei einem Treffer in den Rechten einer Rolle die Zeile aufgeklappt zeigen.
- Zähler „3 von 9“ neben den Filtern, „Filter zurücksetzen“ sobald einer aktiv ist.
- Leerer Zustand: „Keine Rolle passt zu den Filtern.“ mit Knopf „Filter zurücksetzen“.
- Die Auswahl „Hat Recht …“ zeigt die Rechte des Reiters, gruppiert wie oben, mit Suche (`wa-select` mit `multiple` oder eigener Combobox).

## 4. Komponenten (Vorschlag)

- `pages/admin/roles.vue`: Reiter, Ansicht, Filter, URL-Zustand. Hält nur Auswahl und Modals.
- `components/role/Filters.vue`: Suchfeld und die drei Auswahlen, gibt ein Filter-Objekt aus.
- `components/role/RoleList.vue`, `components/role/FunctionList.vue`: Zeilen mit Aufklappen.
- `components/role/RightsMatrix.vue`: aus `pages/system/rechte.vue` herausgelöst, nimmt Bereich und Filter.
- `components/role/RightGroups.vue`: Rechte gruppiert nach Bereich (für Aufklappen, Formulare und „Hat Recht“).
- `composables/useRoleFilters.ts`: Filterlogik als reine Funktionen (testbar mit Vitest).
- `RoleFormModal` und `RoleFunctionFormModal` bekommen den Bereich vorbelegt und nutzen `RightGroups` für die Auswahl der Rechte (heute vermutlich eine lange Liste; **prüfen**, wenn ja, gleich gruppieren).

## 5. Tests

- Vitest für `useRoleFilters`: jede Filterart je Ansicht, Kombinationen, Suche ohne Akzente.
- Vitest/Komponententest: URL-Zustand wird gelesen und geschrieben; `/system/rechte` leitet um.
- Bestehende Tests zu `/admin/roles` und `/system/rechte` nachführen.

## 6. Abschluss

Ein Commit (oder zwei: Umbau, dann Filter). Prüfungen grün. Im Browser ansehen: hell/dunkel, DE/EN, Tastatur (Reiter mit Pfeiltasten, Aufklappen mit Enter/Leertaste, Fokus sichtbar), Telefonbreite. Stand in `umsetzung.md` nachführen, dann melden: Oliver setzt die Abnahme M1 fort (Abschnitt 1 der Checkliste ist angepasst).
