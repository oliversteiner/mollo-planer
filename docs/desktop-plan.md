# Plan: Desktop-App mit Tauri

Stand: 2026-11-11. Status: **geplant, nicht ausgeführt.** Die Entscheidungen sind getroffen (siehe unten). GitHub-Milestone „Desktop-App (Tauri)“ ist angelegt. Mit der Umsetzung (Schritt 1, Spike) wird erst begonnen, wenn es ausdrücklich freigegeben ist.

## Ziel

Die Projektverwaltung läuft auch als eigenständige Desktop-App auf dem Mac (später Windows). Die App zeigt dieselbe Oberfläche wie der Browser. Die Daten bleiben auf dem Laravel-Backend, die App hat keine eigene Datenbank.

## Ausgangslage

- **Frontend:** Nuxt 4 als reine SPA (`ssr: false`), `npm run build` erzeugt statische Dateien in `frontend/.output/public`. Genau das lädt Tauri in die Webview.
- **Backend:** Laravel mit GraphQL unter `/api`. Die App spricht es über die Adresse aus `NUXT_PUBLIC_API_BASE` an (lokal `http://localhost:8000`, produktiv später der API-Server bei Hostpoint).
- **Anmeldung:** Sanctum-Cookie-Session, Sitzungen in der Datenbank, plus Pflicht zur 2FA oder zum Passkey (`StrongAuthentication`).
- **Werkzeuge auf dem Mac:** Rust und Cargo fehlen. Vorhanden sind nur die Xcode-Kommandozeilenwerkzeuge (`xcode-select` zeigt auf `CommandLineTools`). Für Signierung und Notarisierung braucht es ein Apple-Developer-Konto.

## Die wichtigste Frage: Anmeldung in der App

Die App läuft in einer Webview mit dem Ursprung `tauri://localhost`. Die API liegt auf einer anderen Adresse. Für den Browser ist das eine fremde Seite, und Sanctum-Cookies kommen dort nicht ohne Weiteres an:

- Cookies brauchen `SameSite=None; Secure`, also HTTPS auch für die API. Lokal auf `localhost` kann das klappen, das muss im Spike getestet werden.
- WebAuthn (Passkeys) in der Webview von macOS ist nicht zuverlässig. Passkeys sind bei uns aber Teil der Anmeldung.
- Die Sitzung läuft über Cookies im Webview-Speicher. Ein Zugriff von außen ist dort nicht vorgesehen, aber auch nicht sicher verwaltet.

**Vorschlag: eigene Desktop-Anmeldung mit Token.** Die App bekommt nach der Anmeldung (Passwort plus 2FA oder Passkey im Browser) ein persönliches Zugriffstoken (Sanctum, Tabelle `personal_access_tokens` gibt es schon). Das Token wird im Schlüsselbund des Betriebssystems gespeichert. Die App schickt es bei jeder Anfrage mit. So hängt die App nicht von Cookies der Webview ab. Die starke Anmeldung bleibt erhalten, weil das Token erst nach 2FA oder Passkey ausgestellt wird. Das braucht Backend-Arbeit (Token-Anmeldung als Route, Tests) und einen Widerruf in der App (Abmelden).

## Umsetzung in Schritten

1. **Spike:** Tauri-Hülle, die die lokale Build lädt. Test, ob die Token-Anmeldung gegen `localhost:8000` und der Zugriff auf den Schlüsselbund funktionieren.
2. **Anmeldung:** gewählte Lösung umsetzen, mit Tests (Backend) und Komponententests (Frontend).
3. **Bundle für macOS und Windows:** Icon, Fenstergröße, Menü (Abmelden, Neu laden, Hilfe), Adresse der API über Build-Variable. Erst unsigniert, für die Entwicklung.
4. **Signierung und Notarisierung:** Apple-Developer-Konto, Zertifikat, Notarisierung im Build.
5. **Auto-Update:** `tauri-plugin-updater` mit Signaturschlüssel. Die Update-Dateien liegen als statische Dateien auf dem Webspace (https). Jede Version wird signiert, die App prüft die Signatur vor dem Installieren.
6. **Release-Prozess:** GitHub Actions auf einem macOS-Runner baut, signiert und veröffentlicht. Doku in `README`.
7. **Windows:** Build auf einem Windows-Runner in der Release-Pipeline, gleiche Anmeldung. Wird ab Schritt 3 mitgeprüft.

## Was nicht Teil des Milestones ist

- Offline-Betrieb und eine lokale Datenbank.
- Native Benachrichtigungen (kann später nachgerüstet werden).
- Eigene Oberfläche. Die App zeigt die bestehende Webseite.

## Risiken

- **Anmeldung in der Webview** (siehe oben). Der Spike entscheidet frühzeitig.
- **Signierung:** ohne Konto und Notarisierung meldet macOS die App als nicht vertrauenswürdig. Für die Verteilung an andere ist das ein Muss.
- **Hostpoint:** nur PHP und MariaDB. Für Updates reichen statische Dateien, eine Tauri-Build-Pipeline läuft auf GitHub.
- **Doppelte Pflege:** Änderungen an der Anmeldung betreffen Browser und App gleichermaßen. Tests für beide Wege.

## Entscheidungen

1. **Plattformen:** macOS und Windows, beide gleichzeitig.
2. **Anmeldung:** persönliches Token, im Schlüsselbund des Betriebssystems gespeichert. Die Cookie-Session in der Webview wird nicht verwendet.
3. **Apple-Developer-Konto:** vorhanden. Signierung und Notarisierung sind also möglich.
4. **Produktive API-Adresse:** noch offen. Bis dahin baut die App mit `NUXT_PUBLIC_API_BASE` aus der Build-Umgebung.
5. **Auto-Update:** ja, mit `tauri-plugin-updater` ab Milestone 5.

Folgen für die Schritte: Windows wird in Schritt 3 mitgebaut, nicht erst in Schritt 7. Schritt 7 entfällt damit. Die Windows-Signierung (Code-Signing-Zertifikat) muss in Schritt 4 mit geplant werden.

## Milestone

GitHub-Milestone „Desktop-App (Tauri)“ im Repository. Die Schritte unten sind die Arbeitspakete dafür.

## Stand

- [ ] 1. Spike: Tauri-Hülle und Token-Anmeldung gegen die lokale API
- [ ] 2. Anmeldung umsetzen
- [ ] 3. Bundle macOS und Windows (unsigniert)
- [ ] 4. Signierung und Notarisierung (macOS), Code-Signing (Windows)
- [ ] 5. Auto-Update
- [ ] 6. Release-Prozess (macOS und Windows)
