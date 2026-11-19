# 19 · „Konto absichern“: nach dem Einrichten weiterleiten

Status: erledigt (0806fdb auf `fix/setup-security-redirect`), Prüfung durch unabhängigen Agenten bestanden (eingebaute Abweichung gefunden), wartet auf Oliver
Priorität: HOCH (Oliver, 2026-11-18) – sofort, vor Auftrag 16. Auftrag 18 ist erledigt.
Reihenfolge: zuerst dieser Auftrag. Eigener Branch `fix/setup-security-redirect` von `dev`, ein Commit. Nichts auf `dev` pushen, bis Oliver den Tag `v1.0.0` bestätigt hat.
Prozess (ui-consistency): kleine Änderung an einer Seite → `adjusting` (ist unten als Checkliste geschrieben) → `implementing` → `verifying` durch einen Agenten, der die Änderung nicht geschrieben hat. Die Prüfung entfällt nicht.

## Problem (Oliver, 2026-11-18, Produktion)

Auf `/setup-security` wird ein Passkey erzeugt, ein Info-Toast erscheint („Passkey hinzugefügt“), aber die Seite bleibt stehen. Der Nutzer muss nach dem Einrichten weitergeleitet werden.

Ursache im Code: `pages/setup-security.vue`, Funktion `done()`: Sie fragt `load()` ab und leitet nur weiter, wenn das Ergebnis `null` (alles gut) oder ein wartendes Konto (`unverified`, `awaiting-approval`) ist. Jedes andere Ergebnis (z. B. `reauth-required`, `unauthenticated`, `network`, `unknown`) wird **still ignoriert**. `load()` läuft mit `silentErrors`, der globale Handler (`plugins/api-errors.ts`) greift hier also nicht. Der Nutzer sieht den Erfolgs-Toast und bleibt auf der Seite, ohne Hinweis.

## Checkliste für eine Region (nur `done()` auf `setup-security.vue`, nicht für die ganze Seite)

Gelesen: die Seite, `login.vue` (`finish`), `account-pending.vue` (`check`), `verify-email.vue`, `SecurityCard.vue` (`refresh`), `plugins/api-errors.ts`, `composables/useViewer.ts`, `utils/api.ts`. Nicht gelesen: Abstände, Typografie, Farben (die Änderung bewegt keinen Text und keine Abstände), `SecurityPasskeyAdd`/`SecurityTwoFactorSetup` im Innern.

| Position | Was die Familie schreibt | Entscheidung (Stufe der Ordnung) |
|---|---|---|
| Ergebnis `null` | weiter nach `safeRedirect(route.query.redirect)` (login, account-pending, verify-email) | bleibt wie heute |
| `setup-required` | `/setup-security` (login, account-pending, verify-email, SecurityCard). Auf dieser Seite selbst nicht nötig, sonst Schleife | bleibt: **kein** Navigieren (Seite ist schon da); Toast „Einrichtung noch nicht abgeschlossen“ ist nicht nötig |
| wartendes Konto | `pendingAccountPath(problem)` (4 von 4) | bleibt wie heute |
| `unauthenticated`, `reauth-required` | `/login` (account-pending: „sonst → /login“; `api-errors.ts`: `/login?redirect=<aktuelle Adresse>`) | **neu auf dieser Seite**: `navigateTo({ path: '/login', query: { redirect } })` mit dem bisherigen `redirect` der Seite (sonst `/`), dazu ein Info-Toast „Dein Faktor ist eingerichtet. Melde dich jetzt damit an.“ |
| `network`, `unknown`, `unauthorized`, `csrf`, `validation` | `api-errors.ts` zeigt je einen Toast (Farbe `error`/`warning`) | **neu auf dieser Seite**: Fehler-Toast mit `apiError.*` (wie dort), Seite bleibt, der Nutzer kann es erneut versuchen |

Neue Zeichenketten (`i18n/locales/de.json`, `en.json`, unter `auth.setup`): ein Schlüssel für den Hinweis beim Weiterleiten auf `/login`. Sonst nichts Neues, keine neue Komponente, kein neuer Baustein.

**Außerhalb der Seite, nicht Teil dieses Auftrags (nur gemeldet):** `login.vue` (`finish()`) hat dieselbe Lücke (Ergebnisse außer `setup-required`, wartend und `null` werden ignoriert). Eine gemeinsame Funktion für „Ergebnis von `load()` → Ziel“ wäre der saubere Weg, liegt aber im gemeinsamen Teil und ist eine eigene Entscheidung von Oliver.

## Zusätzlich prüfen (Backend, Notiz im Commit oder in `docs/umsetzung.md`)

- **Reproduzieren** (lokal, Nutzer ohne Faktor): Passkey auf `/setup-security` einrichten, in der Browser-Konsole bzw. im Netzwerk-Tab den Wert festhalten, den `load()` danach liefert. Erwartet nach der Beschreibung im Code: Der erste Faktor gilt als „stark“ für diese Sitzung (`MarkSessionAsStrong`, `StrongAuthentication`), `load()` liefert `null`. Liefert es etwas anderes, nenne Wert und Grund (z. B. `reauth-required` nach dem Zurücksetzen des zweiten Faktors, weil die Sitzung nicht als stark markiert wurde). Das ist ein Fehler im Backend und kommt als eigener Punkt zu Stuart.
- Ergebnis bei einem Nutzer, der vorher einen **alten Passkey auf einer anderen Domain** hatte und über `resetUserTwoFactor` zurückgesetzt wurde: Wird die Sitzung nach dem ersten neuen Faktor stark? (Genau das ist Olivers Fall in Produktion.)

## Abnahme

- Test der Seite, soweit das Muster der Seitentests es erlaubt (Vitest): Ergebnis `null` → Weiterleitung; `reauth-required` → `/login` mit `redirect`; `network` → bleibt, Toast.
- Im Browser angesehen: Passkey einrichten → Weiterleitung auf die App (`/` oder `redirect`); Abbruch des Passkey-Dialogs bleibt auf der Seite wie heute.
- `lint`, `typecheck`, Vitest grün.
- `verifying` durch einen unabhängigen Agenten (Prüfung gegen die Checkliste oben, mit der einen Probe an dieser Stelle).
- Oben `Status: erledigt (<Commit>)`, anhalten.
