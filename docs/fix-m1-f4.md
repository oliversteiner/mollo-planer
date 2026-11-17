# Fix F4 aus der Abnahme M1 (Olivers Punkte)

Stand: 2026-11-17, Planung. Code bis `47e0c74`.
Sammelt, was Oliver bei seinen offenen Punkten der Abnahme M1 findet. Wird laufend ergänzt; Bob setzt um, sobald Oliver „F4 abschließen“ sagt oder einen Punkt als dringend markiert.

---

## F4.1 Projektrolle in „Person ändern“ gesucht (entschieden 2026-11-17: nur klarer beschriften)

- Gefunden: Oliver legt die Projektrolle „redaktor - test“ an und sucht sie in `/admin/users` › „Person ändern“ › „Rolle“. Dort stehen nur Systemrollen; Projektrollen werden im Projekt unter „Mitglieder“ vergeben. Technisch korrekt, aber nicht erkennbar.
- Fix (nur Frontend, Texte DE/EN):
  - `components/admin/UserFormModal.vue`: Feld „Rolle“ heißt „Systemrolle“ (EN „System role“), darunter Hinweis: „Gilt in der ganzen App. Projektrollen vergibst du im Projekt unter ‚Mitglieder‘.“
  - `/admin/users` Liste: Badge der Rolle mit Titel/Tooltip „Systemrolle“.
  - `/admin/roles`, Reiter „Projekt“, Ansicht „Rollen“: Hinweis über der Liste: „Projektrollen vergibst du in einem Projekt unter ‚Mitglieder‘.“ Reiter „System“: „Systemrollen vergibst du unter ‚Benutzer‘.“ (mit Link).
  - Projekt › Mitglieder: Spalte bzw. Feld „Projektrolle“ statt „Rolle“.
- Test: Vitest/Snapshot der Texte ist nicht nötig; im Browser DE und EN ansehen.

Nicht gewählt (später möglich): Projekte der Person mit Projektrolle direkt in „Person ändern“ bearbeiten.

---

## F4.2 Chrome füllt das Suchfeld in `/admin/users` weiterhin aus (erledigt 2026-11-17)

- Gefunden (2026-11-17, Bob bei der Browser-Prüfung von F4.1): Nach dem Laden von `/admin/users` steht die E-Mail-Adresse des angemeldeten Kontos im Suchfeld, die Liste ist dadurch leer. Der Fix aus F3.10 (`name="people-filter"`, `autocomplete="off"`, `data-1p-ignore`, `data-lpignore`, inneres Feld `type="search"`) verhindert es nicht; Chrome ignoriert `autocomplete="off"`.
- Vermutung: Chrome hält das Feld (erstes Textfeld der Seite, gespeicherte Anmeldedaten für `localhost:3000`) für ein Benutzernamen-Feld.
- Ursache: Die Layouts halten den Dialog „Passwort bestätigen“ (`PasswordPromptModal`) auf jeder Seite bereit, auch geschlossen mit seinem Feld `current-password`. Chrome bildet aus allen Feldern ohne Formular ein Formular und nimmt das erste Textfeld davor, die Suche, als Benutzernamen.
- Fix: Das Passwortfeld gibt es nur, solange der Dialog offen ist (bis er ausgeblendet ist), und dann in einem eigenen Formular mit verstecktem Feld `autocomplete="username"` (E-Mail der angemeldeten Person). Test `tests/nuxt/password-prompt.test.ts`. Im Browser: Suchfeld bleibt leer, auch bei offenem Dialog.

## F4.3 Seitenleiste: Systemrolle unter der E-Mail-Adresse (offen)

- Gewünscht (Oliver, 2026-11-17): In der Seitenleiste unten (Benutzerknopf, `layouts/default.vue`, `UserLine`) unter Name und E-Mail-Adresse auch die Systemrolle der angemeldeten Person anzeigen.
- Fix (nur Frontend): Rollenname über `useRoleName(viewer.role)` (der Viewer liefert `role { key names }` bereits), DE/EN aus dem Backend. Form (eigene Zeile oder Badge wie in `/admin/users`) bei der Umsetzung im Browser festlegen.
