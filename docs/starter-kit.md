# Konzept: Starter-Kit aus der Basis des Planers

Stand: 2026-11-23, Stuart mit Oliver. Status: Konzept, Umsetzung nach den wichtigsten Aufträgen (35 zuerst).

## Ziel

Die Basis des Mollo Planers wird ein wiederverwendbares **Starter-Kit** für neue Anwendungen. Dazu gehören Konten, Rechte, die Schale mit Haupt- und Benutzermenü, Einstellungen, Mails, Sprachen, Deploy und die Arbeitsweise der Agenten. Neben Laravel und Nuxt folgen native Kits: **Swift** (iOS/iPadOS) und **Kotlin** (Android und Windows).

## Entscheide (Oliver, 2026-11-23)

1. **Form:** Pakete plus Starter-Repo, keine Kopiervorlage. Verbesserungen erreichen alle Apps über Updates.
2. **Konten:** Beides ist möglich. Das **Grundpaket** ist standardmässig ein SSO-Client («Mit Mollo anmelden»). Das **Modul Identität** (eigene Kontoverwaltung, Anmeldedienst) wird bei Bedarf zugeschaltet.
3. **Öffentlich (Open Source).** Das Kit ist allgemein gehalten, Mollo ist nur eine Konfiguration davon (siehe Folgen).
4. **Web Awesome ist Pflicht, aber nur die freie Ausgabe** (`@awesome.me/webawesome`, dazu Font Awesome Free). Kein Pro im Kit (Entscheid 2026-11-23, siehe unten).
5. **Trennregel ab sofort (ab Auftrag 41, OAuth):** Neuer Code für die Basis kommt in eigene Bereiche (`App\Core\…`, Nuxt-Layer `layers/core`).

## Aufbau

| Teil | Laravel | Nuxt | Inhalt |
|---|---|---|---|
| Grundpaket | `core` (Composer) | Layer `core` | Schale (Layout, Haupt- und Benutzermenü), Profil, Einstellungen, Rechte und Rollen, SSO-Client, Agent-Konten und Tokens, MCP-Grundgerüst mit Werkzeug-Schicht, Mails, Sprachen, Fehlercodes, PDF-Rahmen (mPDF, Briefpapier, Satzspiegel; Auftrag 39 F) |
| Modul Identität | `identity` | Layer `identity` | Registrierung, Onboarding, Passwort, 2FA, Passkeys, Freigabe, Authorization Server und OpenID Provider (OAuth 2.1, OIDC), Verwaltung der Clients |
| Starter-Repo | bindet die Pakete ein | bindet die Layer ein | Deploy-Skript, CI, Vorlagen für `CLAUDE.md`/`AGENTS.md`, `docs/` (Aufträge in `next-work/` bleiben lokal, nicht im Git) |

**Optionale Module (später):** Abrechnung (Kunden, Rechnungen, QR-Rechnung; Auftrag 39) als eigenes Paket, das eine App zuschalten kann.

**Nicht in die Basis:** Projekte, Aufgaben, Meilensteine, Dokumente, Planer-Format, Statistik. Das bleibt Fachlogik des Planers.

Namen der Pakete legen wir beim Auslagern fest. Sie sollen neutral sein, weil das Kit öffentlich wird.

## Vertrag für die nativen Kits

Swift und Kotlin teilen keinen Code mit dem Web, aber einen **Vertrag**. Er wird ein eigenes Dokument, versioniert wie eine API:
- **Anmeldung:** OAuth 2.1 mit PKCE (iOS über `ASWebAuthenticationSession`, Android über Custom Tabs, Windows über den System-Browser mit Loopback-Redirect). Refresh mit Rotation, Abmelden, Widerruf.
- **GraphQL der Basis:** `viewer` (inkl. `approved`, `emailVerified`, Rechte), Benutzer, Einstellungen, Geräte und Zugänge.
- **Fehlercodes** (`STRONG_AUTHENTICATION_REQUIRED`, `ACCOUNT_NOT_READY`, …) und `Retry-After` bei 429.
- **Schale:** Einträge von Haupt- und Benutzermenü als Daten (Schlüssel, Bezeichnung, Symbol, Recht), damit Web und Apps dieselbe Navigation zeigen.
- **Sprachen:** gemeinsame Schlüssel für Texte der Basis.

Kits: **Swift** (SwiftUI, aus der iOS-App von Steve) und **Kotlin Multiplatform mit Compose Multiplatform** (Android, Windows als Desktop-App). Gegen den Vertrag gibt es je eine kleine Beispiel-App.

## Vorgehen

1. **Ab sofort, ohne Zusatzkosten:** Trennregel für Bob. Neuer Basis-Code kommt nach `App\Core\…` und `layers/core`, Fachcode bleibt, wo er ist. Die Basis darf nichts aus dem Fachcode importieren (prüfbar mit einer Architekturregel in den Tests, z. B. Pest `arch()`).
2. **Refactoring im Planer:** Die bestehende Basis wandert in diese Bereiche. Alle Tests bleiben grün, keine Änderung am Verhalten. Mehrere kleine Aufträge statt eines grossen.
3. **Auslagern:** in eigene Repos und Pakete, sobald die Grenze trägt. Der Planer bindet sie dann selbst ein. Damit ist er die erste Anwendung des Kits.
4. **Probe:** eine zweite, kleine App entsteht aus dem Starter-Repo als SSO-Client. Erst sie zeigt, ob die Grenze stimmt.
5. **Native:** zuerst den Vertrag schreiben. Dann zieht Steve die Basis der iOS-App in ein Swift-Kit, danach folgt Kotlin.

## Web Awesome: frei im Kit, Pro nur in der App

Der Planer nutzt heute **Web Awesome Pro** (`@web.awesome.me/webawesome-pro`) und **Font Awesome Pro**, unter anderem das Theme «Matter» mit Palette «Mild». Ein öffentliches Kit kann Pro nicht mitliefern: Lizenz und privates Paket-Register mit Token.

- **Kit:** nur freie Komponenten, freie Themes und freie Symbole. Komponenten-Importe und CSS laufen über eine Stelle im Layer `core`, damit eine App das Paket tauschen kann.
- **App (z. B. der Planer):** darf Pro zuschalten (Theme, Pro-Komponenten, Pro-Symbole) über ihren eigenen Layer bzw. ihre Konfiguration. Das Kit muss ohne Pro vollständig laufen und gut aussehen.
- **Bestandsaufnahme beim Refactoring:** Bob listet, was der Planer heute aus Pro nutzt (Theme, Palette, Komponenten, Symbole), und schlägt je Stelle den freien Ersatz für das Kit vor. Was es nur in Pro gibt, bleibt im Planer.
- **Symbole:** Das Kit nutzt nur Namen, die es in Font Awesome Free gibt (Stil `solid`/`regular`/`brands`). Die bestehende Zuordnung (`wa-icons.ts`) wird dafür geprüft.

## Folgen von «öffentlich»

- **Nichts Mollo-Spezifisches im Kit:** Name, Logo, Farben, Domains, Hostpoint-Pfade und Mail-Absender kommen aus der Konfiguration. Mollo ist eine Installation davon.
- **Deploy:** Das Skript ist allgemein, Hostpoint ist ein Beispiel. Pfade und PHP-Binary kommen aus `.env`, wie heute schon (`DEPLOY_*`).
- **Lizenz** wählen (Vorschlag MIT), dazu `SECURITY.md` (Meldeweg für Lücken), `CONTRIBUTING.md`, Changelog, SemVer.
- **Geheimnisse:** Vor dem ersten öffentlichen Push die ganze Git-Historie prüfen (z. B. gitleaks). Die Pakete entstehen deshalb **in neuen Repos ohne die Historie des Planers**.
- **Doku für Fremde:** Installation, Konfiguration, Erweitern (eigene Menüeinträge, eigene Rechte, eigene Werkzeuge für MCP).
- **Pflege:** Abhängigkeiten aktuell halten, CI auf den unterstützten Versionen von PHP und Node.

## Offen

- Paketnamen und Organisation auf GitHub.
- Ob der Anmeldedienst später eine eigene Installation wird (`konto.mollo.ch`).
- Zeitpunkt: nach 41 (OAuth), nach der Abnahme von 17 und 34 sowie nach dem iOS-Login.
