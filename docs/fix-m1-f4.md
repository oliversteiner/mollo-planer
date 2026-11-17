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
