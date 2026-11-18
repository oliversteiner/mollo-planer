# 23 · Onboarding: Zustandsseiten, Anmeldung und Einrichtung (Frontend)

Status: offen
Reihenfolge: nach Auftrag 22 (neue API-Antworten) und nach 21 (Link, erneut senden). Vor Auftrag 20 (Browser-Tests). Ein Commit je Punkt (F1 bis F8).
Branch: `fix/onboarding-frontend` **von `fix/onboarding-backend`** (Auftrag 22; die Aufträge sind gestapelt, darin stecken auch 19 und 21). Nichts mergen und nichts auf `dev` oder `main` pushen, bis Oliver es sagt; der Tag `v1.0.0` wird heute nach der Umsetzung des Onboardings gesetzt (Oliver).
Grundlage: `docs/onboarding-gutachten.md` (Fable, 2026-11-18), Empfehlungen E2, E10, E15, E17 bis E20 und die Anpassungen aus den Entscheiden von Oliver (Registrierung neutral, Passkey-Konten: Admin-Reset + Hinweis, 12 Zeichen, Bestätigung der E-Mail per Klick in Auftrag 21). Für alles an der Oberfläche gilt `docs/frontend-webawesome-plan.md` vor `frontend-plan.md` (`CLAUDE.md`).
Prozess (ui-consistency): mehrere Seiten, neue Meldungen. **Zuerst `finding-patterns`** (Checkliste je Seite, Zustandsseiten und Auth-Seiten vergleichen), dann `planning` (ein Task je Seite), `implementing`, `verifying` durch einen Agenten, der nichts davon geschrieben hat. Die Prüfung entfällt nicht. Wo das Muster der Familie und dieser Auftrag sich widersprechen, gilt die Ordnung von `ui-consistency:decisions`; Bob meldet, was entschieden hat.
Nicht Teil: Barrierefreiheit im Browser durchprüfen (geht in Auftrag 20 und in eine eigene Prüfung), Backend.

## F1 · `login.vue`: jedes Ergebnis von `load()` behandeln (E2)

- Heute ignoriert `finish()` alle Ergebnisse ausser `setup-required`, wartendes Konto und `null` (Gutachten K8). Wer nach korrektem Passwort `reauth-required` bekommt (Konto nur mit Passkey, Sitzung nicht stark), sieht **nichts**.
- Eine gemeinsame Funktion «Ergebnis von `load()` → Ziel oder Meldung» in `utils` oder `composables` für `login`, `setup-security`, `account-pending`, `verify-email`. Auftrag 19 hat die Fälle in `setup-security.vue` bereits gelöst (`done()`); diese Logik wird **extrahiert**, nicht neu erfunden. Das Verhalten der vier Seiten bleibt, wo es heute stimmt. Vorher in der Checkliste festhalten, was jede Seite heute tut, danach Tests (vitest) je Seite, die das Verhalten festhalten.
- `reauth-required` auf `login.vue`, **zwei Fälle, die die Seite unterscheiden muss:**

  | Wann | Verhalten |
  |---|---|
  | beim Öffnen der Seite (`onMounted` ruft heute `finish()` auf) und ebenso `unauthenticated` | **still bleiben.** Eine halb angemeldete Sitzung ist hier der Normalfall; kein Hinweis, kein Toast. |
  | direkt **nach abgeschicktem Passwort** | Hinweis am Formular (kein Toast, er bleibt stehen): «Dein Konto ist mit einem Passkey geschützt. Melde dich mit dem Passkey an.» Der Passkey-Knopf wird zur Hauptaktion (`variant="brand"`), der Knopf «Anmelden» tritt zurück. |
  | wie oben, aber der Browser kann keine Passkeys (`browserSupportsWebAuthn()` falsch, der Knopf fehlt heute ganz) | «Dein Konto ist mit einem Passkey geschützt. Öffne die Seite auf einem Gerät mit deinem Passkey oder melde dich bei einem Admin.» |

  Die gemeinsame Funktion bekommt dafür den Anlass als Parameter (Seite geöffnet / Formular abgeschickt), nicht zwei Kopien der Logik.
- `network`, `csrf`, `unknown`, `unauthorized` nach abgeschicktem Formular: Meldung wie in `plugins/api-errors.ts`, Seite bleibt.
- Texte in `i18n` (de, en), unter `auth.login.*`.

## F2 · Zwei klar getrennte Zustandsseiten (E15)

- «E-Mail bestätigen» (`account-pending.vue`, Zustand unbestätigt, und die Seite nach der Registrierung): zeigt die **Adresse**, nennt die **Frist** (60 Minuten, aus der Konfiguration oder als feste Textstelle, die Bob mit Auftrag 21 abstimmt), den Spam-Hinweis, den Knopf «Mail erneut senden» (Auftrag 21, Teil C) mit Wartezeit, und «Adresse falsch? Nochmals registrieren».
- «Freigabe ausstehend»: erklärt, was passiert ist (Adresse bestätigt), was als Nächstes kommt (ein Admin schaltet frei, **du bekommst eine Mail**, Auftrag 22 O8), dass die Person nichts tun muss. Eigener Titel statt «Konto wartet».
- **Ohne Sitzung** (neutrale Registrierung, Auftrag 22 O3): Die Seite «E-Mail bestätigen» funktioniert nach `201` auch ohne Sitzung. Die Adresse kennt die Seite dann aus dem Registrierformular (nicht aus der API); wird die Seite neu geladen und die Adresse ist weg, zeigt sie dieselben Hinweise ohne Adresse und das Feld für «erneut senden».
- **Woher die Adresse kommt:** mit Sitzung aus `extensions.email` der Antwort `ACCOUNT_NOT_READY` (Auftrag 22 O9), auch nach einem Neuladen; direkt nach der Registrierung aus dem Formular (im Speicher der SPA weitergereicht, **nicht** in der URL); sonst keine Adresse. Liefert Auftrag 22 O9 die Adresse nicht (Oliver kann den Punkt streichen), zeigt die Seite nach dem Neuladen keine Adresse; der Rest bleibt gleich.
- **Wartezeit sagen:** «Die Mail kann eine Minute brauchen.» (Die Queue läuft auf dem Server jede Minute.) Gilt nach der Registrierung und nach «erneut senden».
- «Erneut senden» angemeldet: Bei Antwort `204` ist die Adresse inzwischen bestätigt; dann den Zustand neu prüfen und weitergehen (Auftrag 21, Ergänzungen).
- «Adresse falsch? Nochmals registrieren»: meldet ab (falls angemeldet) und führt zu `/register`. Das alte, unbestätigte Konto löscht der Zeitplan nach 7 Tagen; das muss die Seite nicht erklären.
- Der Text sagt in beiden Fällen (neue Adresse / schon vergeben) **dasselbe**. Kein Wort, das den Fall verrät.

## F3 · Registrierung (neutral)

- Nach `201` immer die Seite aus F2. Der Feldfehler «bereits vergeben» entfällt (die API liefert ihn nicht mehr). Validierungsfehler bleiben am Feld.
- Hinweistext zur Passwortlänge: «mindestens 12 Zeichen» (Auftrag 22 O4). Bob sucht alle Stellen mit dem Text «8» (Registrierung, Passwort zurücksetzen, Profil, Einladung) und passt sie an; Fundstellen in der Abschlussmeldung.
- Passwort-Wiederholfeld: bleibt, solange F7 (Passwort anzeigen) nicht umgesetzt ist.

## F4 · Passwort vergessen

- Erfolgstext erscheint **immer** nach dem Absenden (die API antwortet immer 200, Auftrag 22 O1). Der Feldfehler «Zu dieser Adresse gibt es kein Konto» kann nicht mehr vorkommen. Bei `429` eine verständliche Meldung («Zu viele Versuche. Versuch es in einer Minute nochmals.»).

## F5 · Passkey-Einstieg erklären (E17) und Hinweis auf zweiten Faktor

- Vor dem Knopf in `PasskeyAdd.vue` (Seite «Konto absichern»): zwei Sätze, was ein Passkey ist und wo er gespeichert wird (vorhandener Text `security.passkeys.intro` als Ausgangspunkt, ohne den Satz zu «zwei einrichten»). Das Namensfeld ist vorausgefüllt und keine Pflicht: Der Browser verrät keinen Gerätenamen; der Vorschlag wird aus Betriebssystem und Browser abgeleitet (z. B. «Mac · Safari»), sonst «Passkey».
- Nach Abbruch des Systemdialogs: «Kein Passkey angelegt. Versuch es nochmals oder nimm die Authenticator-App.» (statt «Abgebrochen.»).
- **Abschlussschritt nach dem ersten Passkey** (Entscheid Oliver, Frage 4; ersetzt die frühere Fassung mit Toast): Die Seite «Konto absichern» leitet nach dem Passkey **nicht sofort** weiter, sondern zeigt einen festen Schritt, wie der App-Weg seinen Schritt mit den Codes hat:
  - Erfolgsmeldung «Dein Passkey ist eingerichtet.»
  - Hinweis (kein Toast, er verschwindet nicht): «Richte einen zweiten Weg ein, zum Beispiel die Authenticator-App oder einen Passkey auf einem zweiten Gerät. So kommst du auch ohne dieses Gerät in dein Konto.»
  - Hauptknopf **«Weiter»**: löst die Weiterleitung aus Auftrag 19 aus (`done()` bzw. die gemeinsame Funktion aus F1).
  - Zweiter Knopf «Authenticator-App einrichten»: öffnet auf derselben Seite den App-Weg (`SecurityTwoFactorSetup`), danach ebenfalls «Weiter». Ohne Pflicht.
  - Grund für den festen Schritt: Selbst registrierte Konten landen nach der Einrichtung auf «Freigabe ausstehend», nicht auf der Startseite; ein Toast dort ginge verloren, und ein Hinweis dieser Tragweite darf nicht nach Sekunden verschwinden.
  - Im Profil bleibt der vorhandene Hinweis (`security.passkeys.secondHint`).

## F6 · Fortschritt und Codes bestätigen (E18)

- «Konto absichern» zeigt in beiden Wegen zwei Schritte: «Schritt 1 von 2: Faktor einrichten», dann «Schritt 2 von 2»: beim App-Weg «Codes sichern», beim Passkey-Weg der Abschlussschritt aus F5. Vor «Fertig» im App-Weg ein Kästchen «Ich habe die Codes gespeichert», solange die Seite Codes anzeigt. Für Screenreader ist der Schritt Text, nicht nur Gestaltung.
- Der Weg über den Passkey hat keine Codes (Entscheid: Admin-Reset und Hinweis aus F5).
- Hinweis auf der Abfrageseite des zweiten Faktors (E20): «Kein Zugriff mehr auf App und Codes? Melde dich bei einem Admin.»

## F7 · Passwort anzeigen (E19), niedrige Priorität

- Auf allen Passwortfeldern der Auth-Seiten ein Knopf «Passwort anzeigen» (`wa-input` hat dafür `password-toggle`). Danach kann das Wiederholfeld bei Registrierung und Reset entfallen, **aber nur nach Absprache** mit Oliver: Bob meldet den Vorschlag, ändert die Felder nicht von sich aus.
- Wenn der Ablauf mit F1 bis F6 schon gross ist, darf F7 in einen eigenen Auftrag wandern. Bob meldet das, bevor er F7 beginnt.

## F8 · Admin setzt den zweiten Faktor zurück: Rückfrage (E10)

- `admin/users.vue`: Die Rückfrage vor `resetUserTwoFactor` (vorhandenes `ConfirmModal`, kein neuer Baustein) nennt die Folgen und stellt die Frage zur Identität: «Authenticator-App und Passkeys von {name} werden entfernt, alle Sitzungen beendet. {name} bekommt eine Mail und richtet bei der nächsten Anmeldung einen neuen Faktor ein. **Hast du auf anderem Weg geprüft, dass die Anfrage wirklich von {name} kommt** (Anruf, persönlich)?» Knopf «Ja, zurücksetzen».
- Der Reset ist der einzige Weg um den zweiten Faktor herum; die Rückfrage ist der Schutz gegen eine vorgetäuschte Bitte per Mail.
- Texte unter `admin.users.*` (de, en). Wie die Rückfrage heute lautet, hält `finding-patterns` fest.

## Tests und Prüfung

- vitest je Seite: Zustände (`status`), `201` für beide Fälle ohne Sitzung, Passwort vergessen immer Erfolgstext, `reauth-required` auf `login.vue` in beiden Fällen (Seite geöffnet: still; Formular abgeschickt: Hinweis; ohne Passkey-Unterstützung: anderer Text), Abschlussschritt nach dem Passkey mit «Weiter», Kästchen «Codes gespeichert», Knopf «erneut senden» mit Wartezeit und Antwort `204`, Adresse aus `extensions.email`, Rückfrage beim Admin-Reset.
- Im Browser (Bob lokal, zwei Profile): Registrierung mit neuer und mit vergebener Adresse sehen **gleich** aus; Link im zweiten Profil; Konto nur mit Passkey meldet sich mit Passwort an und bekommt den Hinweis, beim blossen Öffnen von `/login` erscheint er nicht; Passkey einrichten → Abschlussschritt → «Weiter» führt in die App bzw. zu «Freigabe ausstehend».
- Lint, Typprüfung, `npm test` grün. i18n: de und en gleiche Schlüssel (der Test dafür besteht, falls nicht: melden).
- Unabhängige Prüfung (`ui-consistency:verifying`) gegen die Checklisten aus `finding-patterns`.

## Abnahme

- Commits je Punkt, `docs/umsetzung.md` (Stand), `docs/frontend-webawesome-plan.md` und `docs/frontend-plan.md` (Auth-Seiten) ergänzen.
- Bericht an die Planung: was `verifying` gefunden hat und was daraus wurde; Liste der Texte, die Oliver prüfen soll (alle neuen Schlüssel mit Wortlaut DE/EN).
- Oben `Status: erledigt (<Commit>)`, dann anhalten.
