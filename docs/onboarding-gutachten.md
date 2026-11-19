# Gutachten: Onboarding im Mollo Planer

Stand: 18.11.2026 · Gutachter: Fable · Gelesen wurde der Branch `fix/setup-security-redirect` (HEAD `718016a`, enthält den Fix aus Auftrag 19, der noch nicht auf `dev` liegt). Am Repo wurde nichts geändert.

## 1. Kurzfazit

Das Fundament ist gut: Der zweite Faktor wird auf dem Server erzwungen und ist in Produktion nicht abschaltbar, Passkeys verlangen Benutzerverifikation, Anmeldung und Faktor-Abfrage sind gedrosselt, CSRF und 419 sind sauber gelöst, die Tests im Backend sind breit.
Der Weg dorthin ist aber an vier Stellen unterbrochen:

1. Der Bestätigungslink braucht eine Sitzung (bekannt, Auftrag 21).
2. Es gibt kein «erneut senden», und der dafür nötige API-Pfad fehlt in der CORS-Liste (neu).
3. Wer nur einen Passkey hat und sich mit dem Passwort anmeldet, bleibt ohne Meldung auf der Anmeldeseite stehen und hat keine Wiederherstellungscodes (neu).
4. Nach der Freigabe kommt keine Mail, obwohl die Seite «Du bekommst Bescheid» verspricht (neu).

Bei der Sicherheit verraten Registrierung und «Passwort vergessen», ob eine Adresse ein Konto hat. Die Bestätigungsmail läuft entgegen Plan und Kommentar nicht über die Queue. Texte und Zustandsseiten sind verständlich, aber knapp: Adresse, Frist, Spam-Hinweis und Fortschritt fehlen.

## 2. Soll-Ablauf (Phase 1, festgeschrieben vor dem Blick ins Repo)

Stand: 18.11.2026. Dieser Abschnitt wurde geschrieben, bevor eine Datei aus dem Repo gelesen wurde, und danach nicht mehr geändert. Was Phase 2 am Soll korrigieren würde, steht separat in Abschnitt 2.4.

Quellenkürzel in eckigen Klammern verweisen auf die Quellenliste (Abschnitt 6).

### 2.1 Leitentscheide

| Nr. | Entscheid | Begründung |
|---|---|---|
| L1 | Reihenfolge: Registrieren → E-Mail bestätigen → Freigabe durch Admin → erste Anmeldung → zweiten Faktor einrichten → Arbeitssitzung. | Admins sollen nur Konten mit bestätigter Adresse sehen. Der zweite Faktor kommt erst nach der Freigabe, damit niemand ein Konto absichert, das abgelehnt wird. |
| L2 | Der Bestätigungslink funktioniert ohne Sitzung, in jedem Browser und auf jedem Gerät. | Laravels Standardroute verlangt `auth` und `signed` [L-VERIFY]. Wer die Mail auf dem Handy öffnet, hat dort keine Sitzung. Die Signatur über ID und Hash der Adresse genügt als Nachweis. |
| L3 | Nach der Bestätigung wird niemand automatisch angemeldet. | Der Link beweist nur Zugriff aufs Postfach, nicht Kenntnis des Passworts. OWASP rät beim Passwort-Reset ausdrücklich vom automatischen Anmelden ab [O-FORGOT]; dieselbe Überlegung gilt hier. Zusätzlich rufen Mail-Scanner Links vorab auf [SCAN]. Ohne Auto-Login und mit wiederholbarer Bestätigung ist das harmlos. |
| L4 | Registrierung, «Passwort vergessen» und «erneut senden» antworten immer gleich, egal ob die Adresse existiert. | OWASP: Kontoerstellung soll nicht verraten, dass eine Adresse vergeben ist; gleiche Statuscodes und gleiche Antwortzeit [O-AUTH], [O-FORGOT]. |
| L5 | Zweiter Faktor ist für alle Pflicht, ohne Ausnahme für Admins. | OWASP: MFA für alle, zwingend für privilegierte Konten [O-MFA]. |
| L6 | Passkey ist die empfohlene Methode, Authenticator-App die Alternative, Wiederherstellungscodes sind immer dabei. | Passkeys sind phishing-resistent [O-MFA]. Mehrere Faktoren und Einmal-Codes verhindern, dass ein verlorenes Gerät aussperrt [O-PASSKEY]. |
| L7 | Der Server ist die einzige Quelle für den Onboarding-Zustand. Das Frontend leitet daraus die Zielseite ab. | Nur so funktionieren Abbruch und Wiederaufnahme auf einem anderen Gerät. |

**Widerspruch zwischen Quellen (L4):** Baymard empfiehlt, bei schon vergebener Adresse sofort im Formular darauf hinzuweisen und Anmelden oder Passwort-Reset anzubieten [B-SIGNUP]. OWASP verlangt das Gegenteil [O-AUTH]. Für ein geschlossenes Werkzeug mit Freigabe durch einen Admin wiegt der Schutz der Mitgliederliste schwerer. Den Komfort holt die Mail zurück: Wer schon ein Konto hat, bekommt statt des Bestätigungslinks eine Mail «Du hast schon ein Konto» mit Link zur Anmeldung und zum Passwort-Reset.

**Widerspruch (L1):** Die FIDO-Richtlinien empfehlen, Passkeys schon bei der Kontoerstellung anzubieten [F-UX]. Wegen der Freigabe durch einen Admin verschiebe ich das auf die erste Anmeldung. Das ist immer noch ein «Konto-Moment» im Sinn der Richtlinie.

### 2.2 Schrittliste

| Schritt | Auslöser | Zielseite | Meldung (sinngemäss, Du-Form) | Randfälle |
|---|---|---|---|---|
| **S1 Registrieren** | Formular: Name, E-Mail, Passwort | `/register` → nach Absenden `/verify-email` (Hinweisseite) | «Fast geschafft. Wir haben dir eine Mail an **x@y.ch** geschickt. Klick auf den Link darin.» | Adresse schon vergeben: gleiche Antwort, gleicher Statuscode; statt Bestätigungslink geht die Mail «Du hast schon ein Konto» raus. Validierungsfehler am Feld. Drosselung pro IP. |
| **S2 Hinweisseite «E-Mail bestätigen»** | nach S1 oder Anmeldeversuch mit unbestätigtem Konto | `/verify-email` | Adresse anzeigen, Frist nennen, Hinweis auf Spam-Ordner. Knopf «Mail erneut senden». Link «Adresse falsch? Nochmals registrieren». | Erneut senden: funktioniert mit und ohne Sitzung (ohne Sitzung mit Adressfeld), antwortet immer gleich, gedrosselt, Knopf mit Wartezeit. Falsch getippte Adresse: neu registrieren; das alte, unbestätigte Konto wird nach Frist automatisch gelöscht. Seite neu geladen oder später wieder geöffnet: Zustand bleibt verständlich. |
| **S3 Link in der Mail** | Klick, beliebiges Gerät | API prüft Signatur → Weiterleitung auf SPA-Seite mit Ergebnis | Erfolg: «Deine E-Mail-Adresse ist bestätigt. Ein Admin schaltet dein Konto jetzt frei. Du bekommst eine Mail.» | Ohne Sitzung: funktioniert. Schon benutzt: gleiche Erfolgsmeldung (wiederholbar). Abgelaufen oder verändert: «Der Link ist nicht mehr gültig» + Feld und Knopf für einen neuen Link. Keine automatische Anmeldung. |
| **S4 Freigabe** | Bestätigung löst Benachrichtigung an Admins aus | Nutzer: `/account-pending` (nach Anmeldung) | «Dein Konto wartet auf die Freigabe. Du musst nichts weiter tun.» Mail bei Freigabe mit Link zur Anmeldung; Mail bei Ablehnung. | Anmeldung vor der Freigabe: erst nach korrektem Passwort den Zustand zeigen, sonst verrät die Meldung das Konto. Admin lehnt ab: klare Mail, Konto gesperrt oder gelöscht. Freigabe im Hintergrund: Seite merkt es beim nächsten Laden. |
| **S5 Erste Anmeldung** | E-Mail + Passwort | `/login` → `/setup-security` | Fehler immer generisch: «E-Mail oder Passwort stimmen nicht.» | CSRF-Cookie vor dem POST holen. 419: Cookie neu holen, einmal wiederholen. Drosselung pro Adresse + IP. |
| **S6 Konto absichern** | Sitzung ohne zweiten Faktor | `/setup-security`, alle anderen Seiten und API-Aufrufe gesperrt | Fortschritt «Schritt 1 von 2». Passkey zuerst, mit kurzer Erklärung (was, wo gespeichert). Alternative: Authenticator-App mit QR-Code, Schlüssel als Text und Code-Bestätigung. | Passkey-Dialog abgebrochen: Meldung ohne Vorwurf, nochmals versuchen oder App wählen. Gerät kann keine Passkeys: Option ausblenden oder erklären. TOTP-Code falsch: Feldfehler, Uhrzeit-Hinweis. Abbruch: beim nächsten Anmelden wieder hier. |
| **S7 Wiederherstellungscodes** | direkt nach erfolgreichem Faktor | gleiche Seite, Schritt 2 von 2 | «Bewahre diese Codes sicher auf. Jeder Code gilt einmal.» Kopieren, herunterladen, drucken. Weiter erst nach «Ich habe die Codes gespeichert». | Seite vor dem Speichern geschlossen: Codes im Profil neu erzeugen (nach erneuter Bestätigung der Identität). |
| **S8 Fertig** | Bestätigung in S7 | automatische Weiterleitung zur Startseite | «Dein Konto ist geschützt.» | Kein Stehenbleiben: Nach jedem erfolgreichen Schritt lädt das Frontend den Zustand neu und leitet weiter [F-UX], [G-PASSKEY]. |
| **S9 Spätere Anmeldung** | Passwort + Code/Passkey, oder Passkey allein | `/login` → Abfrage zweiter Faktor → Start | — | Wiederherstellungscode als Ausweg, wird verbraucht; Hinweis, wenn wenige übrig sind. |
| **S10 Passwort vergessen** | Formular mit Adresse | `/forgot-password` → `/reset-password?token=…` → `/login` | «Wenn es ein Konto mit dieser Adresse gibt, haben wir dir einen Link geschickt.» | Token einmalig, läuft ab, keine automatische Anmeldung, andere Sitzungen beenden, Bestätigungsmail, `Referrer-Policy: no-referrer` [O-FORGOT]. Der zweite Faktor bleibt bestehen. |
| **S11 Zweiter Faktor verloren** | kein Gerät, keine Codes | Hilfetext auf der Abfrageseite | «Kein Zugriff mehr? Melde dich bei einem Admin.» | Admin setzt den Faktor erst nach Identitätsprüfung auf anderem Weg zurück [O-MFA]. Nutzer bekommt eine Mail. Nächste Anmeldung führt wieder zu S6. Das Zurücksetzen darf das Passwort nicht ersetzen. |
| **S12 Gerätewechsel / Domain** | neues Gerät, neue Domain | Profil → Sicherheit | Mehrere Passkeys möglich, benennbar; letzter Faktor nicht löschbar. | Passkeys sind an die RP-ID gebunden. Wechselt die Domain, sind sie unbrauchbar [W-RPID]. Die RP-ID muss zum Ursprung der SPA passen, nicht nur zur API. |

### 2.3 Prüfkriterien

**Ablauf**

- K1 Bestätigungslink funktioniert ohne Sitzung und auf fremdem Gerät.
- K2 Bestätigung ist wiederholbar; abgelaufener oder ungültiger Link führt zu einer Seite mit «neuen Link senden».
- K3 «Erneut senden» ist vorhanden, gedrosselt und ohne Sitzung nutzbar.
- K4 Nach der Bestätigung keine automatische Anmeldung; klare Folgeseite.
- K5 Admins werden über neue, bestätigte Konten informiert; Nutzer bekommt Mail bei Freigabe und Ablehnung.
- K6 Zustandsseiten «E-Mail bestätigen» und «Freigabe ausstehend» erklären, was passiert ist, was als Nächstes kommt und was man selbst tun kann.
- K7 Zweiter Faktor wird bei der ersten Anmeldung erzwungen; ohne ihn ist serverseitig nichts anderes erreichbar (nicht nur im Frontend).
- K8 Nach jedem Schritt in «Konto absichern» geht es automatisch weiter; kein Stehenbleiben.
- K9 Wiederherstellungscodes werden beim Einrichten gezeigt und müssen bestätigt werden; auch beim Weg über den Passkey.
- K10 Abbruch und Wiederaufnahme: Zustand kommt vom Server, jede Seite ist neu ladbar, Weiterleitung stimmt.
- K11 Unbestätigte Konten werden nach einer Frist gelöscht; die Adresse wird wieder frei.

**Sicherheit**

- K12 Registrierung verrät nicht, ob eine Adresse vergeben ist (Text, Statuscode, Antwortzeit).
- K13 «Passwort vergessen» und «erneut senden» antworten immer gleich.
- K14 Anmeldefehler generisch; Kontozustand (unbestätigt, nicht freigegeben) erst nach korrektem Passwort sichtbar.
- K15 Drosselung auf Anmeldung, Faktor-Abfrage, Registrierung, Reset-Anfrage, erneut senden, Passkey-Routen [L-FORTIFY].
- K16 Passwortregeln nach NIST: mindestens 8 Zeichen bei Pflicht-MFA (empfohlen 12 oder mehr), mindestens 64 erlaubt, keine Zusammensetzungsregeln, Abgleich mit Liste bekannter Passwörter, Einfügen erlaubt [N-63B].
- K17 Lebensdauer: Bestätigungslink und Reset-Token laufen ab, Reset-Token ist einmalig [O-FORGOT].
- K18 Nach Passwort-Reset keine automatische Anmeldung, andere Sitzungen enden, Nutzer bekommt eine Mail [O-FORGOT].
- K19 Wiederherstellungscodes: einmalig, neu erzeugbar, nicht im Klartext gespeichert [N-63B], [O-PASSKEY].
- K20 Faktoren ändern oder löschen nur nach erneuter Bestätigung der Identität; letzter Faktor nicht löschbar; Mail bei jeder Änderung [O-MFA], [O-PASSKEY].
- K21 Admin-Reset des zweiten Faktors ist protokolliert, auf Admins beschränkt und informiert den Nutzer.
- K22 Passkey: Benutzerverifikation verlangt, RP-ID und erlaubte Ursprünge fest konfiguriert, nicht aus Request-Headern [O-PASSKEY].
- K23 Links in Mails werden aus fester Konfiguration gebaut, nicht aus dem Host-Header [O-FORGOT].
- K24 Sitzung wird bei der Anmeldung erneuert; Cookies `Secure`, `HttpOnly`, `SameSite`.

**UX und Barrierefreiheit**

- K25 Fortschritt im mehrstufigen Teil sichtbar.
- K26 Fehler stehen am Feld, sagen was zu tun ist, ohne Vorwurf; keine Fehlermeldung vor der ersten Eingabe [NN-FORM], [NN-HOSTILE].
- K27 Texte in Du-Form, ohne Fachwörter («Zwei-Faktor» erklärt), in DE und EN vollständig.
- K28 WCAG 2.2 AA: Einfügen und Passwortmanager funktionieren in allen Feldern, auch im Code-Feld (3.3.8) [W-338]; Statusmeldungen werden vorgelesen (4.1.3); Fehler als Text benannt (3.3.1); Fokus sichtbar und nach Seitenwechsel sinnvoll gesetzt; Zielgrösse mind. 24 px (2.5.8).
- K29 Felder mit passenden `autocomplete`-Werten (`email`, `new-password`, `current-password`, `one-time-code`); Passwort anzeigen möglich [B-SHOW], [N-63B].
- K30 Passkey-Einstieg: kurze Erklärung vor dem Systemdialog, Ergebnis danach, verständliche Meldung bei Abbruch [F-UX], [G-PASSKEY].
- K31 Mobile: Formulare ohne horizontales Scrollen, Code-Feld mit Zifferntastatur.

**Technik**

- K32 SPA und API unter derselben registrierbaren Domain; `stateful`-Domains, Sitzungs-Domain und CORS mit Credentials konfiguriert [L-SANCTUM].
- K33 CSRF: Cookie vor schreibenden Aufrufen; 401 und 419 führen zu sauberem Verhalten (neu anmelden bzw. einmal wiederholen) [L-SANCTUM].
- K34 Mails laufen über die Queue; ein Fehlschlag geht nicht still verloren (Wiederholung, Protokoll).
- K35 Auth-Seiten ohne Hydration-Fehler: Web Components nur im Client rendern oder als Custom Elements deklarieren [N-HYDRA], [N-CE].
- K36 Der ganze Ablauf ist durch automatische Tests gedeckt (mindestens Backend-Feature-Tests je Schritt und Randfall).

### 2.4 Was Phase 2 am Soll ändert (offen ausgewiesen)

- **L1, Reihenfolge:** Im Repo ist am 12.11.2026 bewusst anders entschieden worden: E-Mail → zweiter Faktor → Freigabe (`docs/registrierung-plan.md:60`). Mein Soll setzt die Freigabe vor den Faktor. Beide Wege sind vertretbar; siehe offene Frage 2.
- **S1, Sitzung nach der Registrierung:** Fortify meldet nach `/register` sofort an (`vendor/laravel/fortify/src/Http/Controllers/RegisteredUserController.php:64`). Das ist in Ordnung, weil das Passwort eben eingegeben wurde. Das Soll verlangt nur, dass der Ablauf auch ohne diese Sitzung funktioniert.
- Sonst keine Änderung.

## 3. Vergleich Soll und Ist

Pfade ohne Präfix beziehen sich auf `backend/` bzw. `frontend/`, wie angegeben. «Abgeleitet» heisst: aus dem Code geschlossen, nicht im Browser nachgestellt.

| Kriterium | Soll | Ist (Fundstelle) | Bewertung | Schwere |
|---|---|---|---|---|
| K1 Link ohne Sitzung | funktioniert überall | Fortify-Route trägt `auth`, `signed`, `throttle` (`backend/vendor/laravel/fortify/routes/routes.php:94-96`); keine eigene Route (`backend/routes/web.php:1-16`). Ohne Sitzung vermutlich Fehler 500, weil die Route `login` bei `views => false` fehlt (abgeleitet; `backend/config/fortify.php:148`). | Lücke, bekannt (Auftrag 21) | hoch |
| K2 Wiederholbar, Fehlerseite | Seite je Zustand | `frontend/app/pages/verify-email.vue:46-49` zeigt immer «bestätigt», auch wenn nichts bestätigt wurde. Kein Zustand «ungültig». | Lücke, bekannt (Auftrag 21) | hoch |
| K3 Erneut senden | Knopf, gedrosselt, auch ohne Sitzung | Kein Knopf (`frontend/app/pages/account-pending.vue:53-76`). Die Fortify-Route existiert (`routes.php:98-100`), aber `email/*` fehlt in den CORS-Pfaden (`backend/config/cors.php:19-32`): Der Aufruf aus der SPA scheitert im Browser (abgeleitet). Auftrag 21 nennt CORS nicht. | Lücke, CORS-Teil neu | hoch |
| K4 Kein Auto-Login nach Bestätigung | keine Sitzung durch den Link | Der Link legt keine Sitzung an (`vendor/…/VerifyEmailController.php:18-29`). | ok | – |
| K5 Admin-Hinweis, Mail bei Freigabe/Ablehnung | beides | Admins bekommen eine Mail (`backend/app/Listeners/NotifyAdminsOfPendingUser.php:19-33`). Freigabe ohne Mail (`backend/app/GraphQL/Mutations/ApproveUser.php:19-24`, so entschieden in `docs/registrierung-plan.md:58`). Die Seite verspricht aber «Du bekommst Bescheid» (`frontend/i18n/locales/de.json:174`). Ablehnung löscht ohne Mail (`RejectUser.php:19-24`). | Lücke | hoch |
| K6 Zustandsseiten | Adresse, Frist, nächster Schritt, eigene Handlung | Eine Seite für beide Zustände, Titel «Konto wartet», ein Satz, «Erneut prüfen», «Abmelden» (`account-pending.vue:46-76`, `de.json:172-175`). Keine Adresse, keine Frist, kein Spam-Hinweis, kein Weg bei falscher Adresse. | Lücke | mittel |
| K7 Faktor serverseitig erzwungen | API gesperrt | Drei Sperren in fester Reihenfolge (`backend/config/lighthouse.php:75-82`), Schutz der Fortify-Routen (`backend/app/Http/Middleware/ProtectAccountSecurityRoutes.php:45-68`), in Produktion immer an (`backend/app/Support/AppSettings.php:28-43`). | ok, stark | – |
| K8 Weiter nach jedem Schritt | automatisch | `frontend/app/pages/setup-security.vue:25-47` behandelt jetzt jedes Ergebnis (Auftrag 19, noch nicht auf `dev`). Dieselbe Lücke besteht in `frontend/app/pages/login.vue:47-59`: `reauth-required`, `network`, `unknown` werden ignoriert. | Lücke in `login.vue` | hoch |
| K9 Wiederherstellungscodes | für alle, mit Bestätigung | Nur auf dem Weg über die App (`frontend/app/components/security/TwoFactorSetup.vue:41-43, 112-126`). Der Passkey-Weg endet ohne Codes (`setup-security.vue:97-100`); das Profil zeigt Codes nur mit App (`frontend/app/components/profile/SecurityCard.vue:108-151`). «Fertig» ohne Bestätigung, dass die Codes gespeichert sind. | Lücke | hoch |
| K10 Abbruch und Wiederaufnahme | Zustand vom Server | `frontend/app/composables/useViewer.ts:23-47`, `frontend/app/middleware/auth.global.ts:6-38`, `frontend/app/utils/api.ts:72-90`. Jede Seite ist neu ladbar. | ok | – |
| K11 Unbestätigte Konten löschen | Frist | 7 Tage, täglich (`backend/app/Console/Commands/PruneUnverifiedUsers.php:30-36`, `backend/routes/console.php:12`). | ok | – |
| K12 Registrierung ohne Auskunft | gleiche Antwort | `unique:users` liefert 422 am Feld (`backend/app/Actions/Fortify/CreateNewUser.php:25`, Test `backend/tests/Feature/SelfRegistrationTest.php:50-60`, Text `backend/lang/de/validation.php:36`). | Risiko | mittel |
| K13 Reset und erneut senden ohne Auskunft | gleiche Antwort | Unbekannte Adresse: 422 mit «Zu dieser E-Mail-Adresse gibt es kein Konto» (`vendor/…/FailedPasswordResetLinkRequestResponse.php:38-42`, nicht ersetzt: `backend/app/Providers/FortifyServiceProvider.php:23-26`; `backend/lang/de/passwords.php:8`). Das Frontend zeigt den Fehler am Feld (`frontend/app/pages/forgot-password.vue:49`) und widerspricht damit dem eigenen Erfolgstext «Wenn die Adresse bei uns bekannt ist …» (`de.json:133`). | Risiko | mittel |
| K14 Anmeldefehler generisch | ja | Fortify-Standard, «Diese Zugangsdaten stimmen nicht» (`backend/lang/de/auth.php:4`). Kontozustand erst nach der Anmeldung (`EnsureEmailVerified.php:22-26`). | ok | – |
| K15 Drosselung | alle Einstiege | Anmeldung, Faktor, Passkeys, Registrierung (`FortifyServiceProvider.php:39-62`). `/forgot-password` und `/reset-password` ohne Routen-Drossel (`routes.php:64-69`); nur 60 s je Adresse im Broker (`backend/config/auth.php:100`). | Lücke | niedrig |
| K16 Passwortregeln | 8+ mit MFA, Liste bekannter Passwörter | `Password::default()` ohne gesetzte Vorgaben, also nur 8 Zeichen (`backend/app/Actions/Fortify/PasswordValidationRules.php:17`; kein `Password::defaults` in `app/`). Kein Abgleich mit bekannten Passwörtern (bei NIST ein «SHALL»). Keine Zusammensetzungsregeln: gut. | Lücke | mittel |
| K17 Lebensdauer | begrenzt, Reset einmalig | Bestätigung 60 Min. (`vendor/…/Auth/Notifications/VerifyEmail.php:83-85`), Reset 60 Min., Einladung 7 Tage (`backend/config/auth.php:99, 108`). Die Bestätigungsmail nennt die Frist nicht (`backend/lang/de/mail.php:40-46`). | ok, Text fehlt | niedrig |
| K18 Nach Reset | kein Auto-Login, Sitzungen enden, Mail | Kein Auto-Login (Fortify). Andere Sitzungen bleiben, keine Info-Mail (`backend/app/Actions/Fortify/ResetUserPassword.php:28-30`; `vendor/…/CompletePasswordReset.php:18-25` erneuert nur das Remember-Token). Der zweite Faktor bleibt Pflicht, das mildert. | Lücke | niedrig |
| K19 Wiederherstellungscodes | einmalig, gehasht | Einmalig und neu erzeugbar. Verschlüsselt statt gehasht (`vendor/…/GenerateNewRecoveryCodes.php:21`), deshalb später wieder anzeigbar (`SecurityCard.vue:110-121`). Fortify-Standard, weicht von NIST ab. | Abweichung | niedrig |
| K20 Faktoren ändern | erneute Bestätigung, Mail | Passwortbestätigung plus starke Sitzung (`fortify.php:188-195`, `ProtectAccountSecurityRoutes.php:39-43`). Warnung beim letzten Faktor (`SecurityCard.vue:44`). Keine Mail bei Änderungen (in `backend/app/Listeners/` nur die drei Sitzungs- und Admin-Listener). | ok, Mail fehlt | niedrig |
| K21 Admin-Reset | protokolliert, Nutzer informiert | Entfernt Faktoren, Sitzungen und Tokens (`backend/app/GraphQL/Mutations/ResetUserTwoFactor.php:22-46`). Keine Mail an die Person, kein Protokolleintrag. Recht: `update` (`backend/graphql/mutations.graphql:17-19`). | Lücke | mittel |
| K22 Passkey | UV, RP-ID fest | UV verlangt (`vendor/laravel/passkeys/src/Actions/GenerateRegistrationOptions.php:83-87`, Test `AuthenticationFlowTest.php:166`). RP-ID und Ursprung aus `FRONTEND_URL` (`fortify.php:164-165`). | ok | – |
| K23 Links aus Konfiguration | ja | Reset und Einladung aus `app.frontend_url` (`ResetPasswordNotification.php:32`, `UserInvited.php:33`). Der Bestätigungslink entsteht über `route()` in der laufenden Anfrage (`VerifyRegistrationEmail.php:21`); vertrauenswürdige Hosts sind nicht gesetzt (`backend/bootstrap/app.php:18-23`). Die Signatur schützt vor Missbrauch. | ok, Hinweis | niedrig |
| K24 Sitzung | erneuern, sichere Cookies | Erneuert bei Anmeldung und Registrierung (`RegisteredUserController.php:66-68`). `HttpOnly`, `SameSite=lax` (`backend/config/session.php:185, 202`). | ok | – |
| K25 Fortschritt | sichtbar | Keiner (`setup-security.vue:51-125`). | Lücke | niedrig |
| K26 Fehler am Feld | ja, ohne Vorwurf | Am Feld, für Screenreader markiert (`frontend/app/components/form/Input.vue:40-50`). Prüfung nur durch den Server, keine Rückmeldung beim Verlassen des Felds. | ok, ausbaubar | niedrig |
| K27 Texte | Du, verständlich, DE/EN | Du-Form durchgehend, DE und EN deckungsgleich (alle Schlüssel verglichen). «Abgebrochen.» nach Passkey-Abbruch sagt nicht, wie es weitergeht (`de.json:487`). | ok | niedrig |
| K28 WCAG 2.2 AA | siehe Soll | Aus dem Code: Fehler als Text, `aria-invalid`. Nicht prüfbar ohne Browser: Kontrast, Fokus nach Seitenwechsel, Ansage der Toasts, Zielgrössen, Passwortmanager im Shadow DOM von `wa-input`. | offen | – |
| K29 `autocomplete`, Passwort zeigen | ja | `autocomplete` gesetzt (`frontend/app/pages/register.vue:43, 54, 64, 75`; `login.vue:108, 119, 208`). Kein «Passwort anzeigen» (im Frontend kein `password-toggle`). | Lücke | niedrig |
| K30 Passkey-Einstieg | Erklärung, Ergebnis, Abbruch | In der Einrichtung nur ein Namensfeld und ein Knopf (`frontend/app/components/security/PasskeyAdd.vue:43-66`). Die Erklärung steht nur im Profil (`SecurityCard.vue:158-160`). | Lücke | mittel |
| K31 Mobile | bedienbar | Eine Spalte, max. 24 rem (`frontend/app/layouts/auth.vue:57-60`). Im Browser nicht geprüft. | offen | – |
| K32 Domains | gleiche Hauptdomain | Vorlage sieht es so vor (`docs/deploy-template.md:64, 77-79`). Der echte Server wurde nicht geprüft. | ok laut Vorlage | – |
| K33 CSRF, 401, 419 | sauber | Cookie vor jedem Aufruf, 419 einmal wiederholen (`frontend/app/plugins/api.ts:29-33, 65-90, 101-109`). | ok | – |
| K34 Mails über die Queue | ja | Reset, Einladung, Admin-Hinweis: ja. Die Bestätigungsmail nicht: `VerifyRegistrationEmail` erbt von `VerifyEmail` ohne `ShouldQueue` (`backend/app/Notifications/VerifyRegistrationEmail.php:13`), der Kommentar sagt «queued» (`backend/app/Models/User.php:144-150`), der Plan verlangt es (`docs/registrierung-plan.md:22`). | Lücke | mittel |
| K35 Hydration | keine Fehler | `ssr: false`, `wa-*` als Custom Elements (`frontend/nuxt.config.ts:27, 52`). | ok | – |
| K36 Tests | ganzer Ablauf | Backend deckt Anmeldung, Faktor, Einladung, Registrierung ab. Es fehlen: Link ohne Sitzung, erneut senden, unbekannte Adresse bei «Passwort vergessen», Freigabe-Mail. Browser-Tests fehlen (Auftrag 20). | Lücke | mittel |

**Zusätzliche Befunde ausserhalb der Kriterien**

- **Passwort wird in der Einrichtung sofort wieder verlangt.** `addPasskey` und `startTwoFactor` prüfen zuerst die Passwortbestätigung (`frontend/app/composables/useSecurity.ts:16-44, 48, 94`). Die Anmeldung setzt diesen Zeitstempel nicht; nur `POST /user/confirm-password` tut es (`vendor/…/ConfirmablePasswordController.php:58`). Wer sich eben angemeldet hat, tippt das Passwort also ein zweites Mal. Schwere: mittel (Komfort).
- **Adresse von fremder Hand registriert.** Registriert jemand ein Konto mit der Adresse eines Mitglieds und das Mitglied klickt den Link, ist das Konto bestätigt, das Passwort kennt aber der Fremde. Schutz heute: der Satz in der Mail (`lang/de/mail.php:44`) und die Freigabe. Mit Link ohne Sitzung (Auftrag 21) wird dieser Klick einfacher. Schwere: niedrig bis mittel.
- **`app:user-*`-Befehle** stehen nicht in diesem Branch (Suche in `backend/app` ohne Treffer; es gibt einen Branch `feature/user-commands`, den ich nicht gelesen habe). Auftrag 20, Szenario 8 setzt `app:user-2fa reset` voraus.

**Nicht oder nur teilweise gelesen:** `docs/backend-plan.md` und `docs/frontend-plan.md` (nicht gelesen), `docs/release-1.0.md` (nur durchsucht, kein Bezug zum Onboarding gefunden), `lang/en` (nur `mail.php`), `components/admin/*`, `PasswordPromptModal.vue`, `usePasswordPrompt.ts`, `invitation.vue`. Nichts wurde ausgeführt, weder Tests noch Browser.

## 4. Empfehlungen

Aufwand: S bis ein halber Tag, M bis zwei Tage, L mehr.

### 4.1 Ablauf bricht ab

| Prio | Empfehlung | Begründung | Aufwand | Wo |
|---|---|---|---|---|
| hoch | **E1 Auftrag 21 umsetzen und um CORS ergänzen.** `email/*` in die CORS-Pfade aufnehmen, dazu ein Test, der den Aufruf mit `Origin` des Frontends prüft. | Ohne das scheitert «erneut senden» im Browser, auch wenn Route und Knopf stimmen (K1 bis K3). | M | beides |
| hoch | **E2 `login.vue`: jedes Ergebnis von `load()` behandeln.** Bei `reauth-required` eine Meldung zeigen: «Dieses Konto ist mit einem Passkey geschützt. Melde dich mit dem Passkey an.» und den Passkey-Knopf hervorheben. Am besten eine gemeinsame Funktion «Ergebnis → Ziel» für `login`, `setup-security`, `account-pending`, `verify-email` (in Auftrag 19 schon vermerkt). | Heute passiert nach korrektem Passwort nichts. Das trifft jedes Konto, das nur einen Passkey hat (K8). | S | Frontend |
| hoch | **E3 Wiederherstellungscodes für alle.** Siehe offene Frage 4. | Ein Konto nur mit Passkey hat heute keinen Ausweg ausser dem Admin (K9). | M | beides |
| hoch | **E4 Mail bei Freigabe und bei Ablehnung.** Queue, Sprache der Person, Link zur Anmeldung. Bis dahin den Text «Du bekommst Bescheid» ändern. | Die Seite verspricht etwas, das nicht passiert. Niemand weiss, wann er weitermachen kann (K5). | S | Backend, Text im Frontend |
| mittel | **E5 Bestätigungsmail in die Queue** (`ShouldQueue`, `afterCommit`, wie die anderen Mails). | Entspricht Plan und Kommentar. Ein Mailfehler bricht die Registrierung nicht ab, die Antwortzeit verrät nichts (K34). | S | Backend |
| mittel | **E6 Auftrag 20 (Browser-Tests) nach E1 bis E4** und um zwei Szenarien erweitern: Passwort-Anmeldung bei Konto nur mit Passkey; Link in zweitem Browserprofil. | Die Lücken oben wären mit einem Durchlauf im Browser aufgefallen (K36). | L | Frontend |

### 4.2 Sicherheitsrisiko

| Prio | Empfehlung | Begründung | Aufwand | Wo |
|---|---|---|---|---|
| hoch | **E7 «Passwort vergessen» neutral machen.** Eigene Antwort für den Fehlfall binden: immer 200 und derselbe Text, auch bei unbekannter Adresse und bei Drossel. | Klarer Widerspruch zum eigenen Text, einfach zu beheben, von OWASP ausdrücklich verlangt (K13). | S | Backend, Test |
| mittel | **E8 Registrierung neutral machen.** Siehe offene Frage 1 und Vertrag in 4.4. | Schützt die Mitgliederliste (K12). Steht im Widerspruch zu Auftrag 20, Szenario 1. | M | beides |
| mittel | **E9 Passwortregeln zentral setzen:** Mindestlänge 12, Abgleich mit bekannten Passwörtern, Obergrenze dokumentieren. Hinweistext im Frontend anpassen. | NIST verlangt den Abgleich; 8 Zeichen sind das Minimum, 12 die übliche Empfehlung (K16). Der Abgleich fragt einen externen Dienst an: siehe offene Frage 6. | S | Backend, Text |
| mittel | **E10 Admin-Reset des zweiten Faktors:** Mail an die Person, Protokolleintrag mit Admin und Zeit, Rückfrage im Dialog mit dem Hinweis «Identität auf anderem Weg geprüft?». | Der Reset ist der einzige Weg um den zweiten Faktor herum (K21). | S | beides |
| niedrig | **E11 Drossel auf `/forgot-password` und `/reset-password`** je IP. | Schliesst die letzte ungedrosselte Auth-Route (K15). | S | Backend |
| niedrig | **E12 Nach Passwort-Reset:** andere Sitzungen beenden, Mail «Dein Passwort wurde geändert». Gleiche Mail bei neuem oder entferntem Faktor. | OWASP-Empfehlung (K18, K20). | S | Backend |
| niedrig | **E13 Bestätigungsmail schärfen:** Name aus der Registrierung nennen, Frist nennen, Satz «Wenn du das nicht warst, klick nicht auf den Link». In der Freigabeliste das Registrierungsdatum zeigen. | Mildert die Registrierung von fremder Hand (K17). | S | Backend, Frontend |
| niedrig | **E14 Vertrauenswürdige Hosts setzen** oder den Bestätigungslink aus fester Konfiguration bauen. | Vorsorge (K23). | S | Backend |

Nicht empfohlen: die Wiederherstellungscodes auf gehashte Speicherung umbauen (K19). Das hiesse, Fortify zu ersetzen; der Gewinn ist klein, solange die Datenbank und der App-Schlüssel getrennt liegen.

### 4.3 Verständlichkeit und Komfort

| Prio | Empfehlung | Begründung | Aufwand | Wo |
|---|---|---|---|---|
| mittel | **E15 Zwei eigene Zustandsseiten oder zwei klar getrennte Zustände:** «E-Mail bestätigen» (Adresse, Frist, Spam-Hinweis, erneut senden, «Adresse falsch? Nochmals registrieren») und «Freigabe ausstehend» (was passiert, dass eine Mail kommt). Eigene Titel statt «Konto wartet». | K6. Der Nutzer sieht heute weder, wohin die Mail ging, noch was er tun kann. | S | Frontend |
| mittel | **E16 Passwortabfrage in der ersten Einrichtung weglassen.** Siehe offene Frage 5. | Zweimal dasselbe Passwort innert Sekunden wirkt wie ein Fehler. | S | Backend |
| mittel | **E17 Passkey-Einstieg erklären:** zwei Sätze vor dem Knopf (was ein Passkey ist, wo er gespeichert wird), das Namensfeld erst nach dem Systemdialog oder mit Vorschlag aus dem Gerät. Nach Abbruch: «Kein Passkey angelegt. Versuch es nochmals oder nimm die Authenticator-App.» | FIDO-Richtlinien: Erklärung vor und Ergebnis nach dem Systemdialog (K30). | S | Frontend |
| niedrig | **E18 Fortschritt in «Konto absichern»** («Schritt 1 von 2: Faktor wählen», «Schritt 2 von 2: Codes sichern») und Kästchen «Ich habe die Codes gespeichert» vor «Fertig». | K25, K9. | S | Frontend |
| niedrig | **E19 «Passwort anzeigen»** auf allen Passwortfeldern; danach ist das Feld «Passwort wiederholen» verzichtbar. | NIST und Baymard (K29). | S | Frontend |
| niedrig | **E20 Hinweis auf der Abfrageseite:** «Kein Zugriff mehr auf App und Codes? Melde dich bei einem Admin.» | K-Randfall «Faktor verloren». | S | Frontend |
| niedrig | **E21 Barrierefreiheit im Browser prüfen** (Skill `ui-consistency:accessibility` oder Auftrag 20): Fokus nach Seitenwechsel, Ansage der Toasts, Passwortmanager und Einfügen in `wa-input` und `wa-otp-input`, Zielgrössen, Kontrast in hell und dunkel. | K28 ist aus dem Code nicht entscheidbar. | M | Frontend |

### 4.4 Verträge für die wichtigsten Änderungen

**Registrierung, neutral (E8)**

| | |
|---|---|
| Request | `POST /register` mit `name`, `email`, `password` (und `password_confirmation`, solange das Feld bleibt) |
| Antwort, Adresse neu | `201`, Sitzung wie heute, Bestätigungsmail in der Queue |
| Antwort, Adresse vergeben | `201` mit gleichem Körper, **keine** Sitzung, Mail «Du hast schon ein Konto» an die Adresse (Links: Anmelden, Passwort vergessen) |
| Antwort, Eingabe ungültig | `422` mit Feldfehlern (Name, Format der Adresse, Passwort), nie «vergeben» |
| Frontend | nach `201` immer die Seite «E-Mail bestätigen»; ohne Sitzung zeigt sie denselben Text und «erneut senden» mit Adressfeld |

Ein Rest bleibt: Ob eine Sitzung entstand, sieht der Aufrufer am Cookie. Wer das schliessen will, meldet nach der Registrierung gar nicht an (Alternative B in Frage 1).

**Bestätigungslink und erneut senden (E1):** wie in `next-work/21-bestaetigungslink-ohne-sitzung.md` beschrieben (`status=ok|already|invalid`, öffentliche Route mit immer gleicher Antwort 202). Ergänzung: CORS-Pfade `email/*`.

**Passwort vergessen (E7)**

| | |
|---|---|
| Request | `POST /forgot-password` mit `email` |
| Antwort | immer `200` mit demselben Text; `422` nur bei formal ungültiger Adresse; `429` bei IP-Drossel |

**Freigabe (E4)**

| | |
|---|---|
| Auslöser | `approveUser` bzw. `rejectUser` |
| Wirkung | Mail an die Person in ihrer Sprache: bei Freigabe mit Link zur Anmeldung, bei Ablehnung ohne Begründungspflicht, mit Kontaktweg |
| Voraussetzung | Sprache der Person ist gespeichert oder wird aus der Registrierung übernommen (im Code nicht geprüft, ob `users` ein Sprachfeld hat) |

## 5. Offene Fragen und Alternativen

1. **Soll die Registrierung verraten dürfen, dass eine Adresse vergeben ist?**
   A) Neutral wie in 4.4 (meine Empfehlung). B) Neutral und ohne Sitzung nach der Registrierung, am strengsten, dafür ein Klick mehr. C) Wie heute mit Feldfehler; dann Auftrag 20, Szenario 1 so lassen und das Risiko bewusst tragen. Für einen Verein mit bekannten Mitgliedern ist C vertretbar, wenn die Drossel bleibt.
2. **Zweiter Faktor vor oder nach der Freigabe?**
   Heute davor (Entscheid vom 12.11.). Vorteil: Nach der Freigabe kann man sofort arbeiten. Nachteil: Abgelehnte haben umsonst eingerichtet, und auf «Freigabe ausstehend» folgt keine Handlung mehr, was die Freigabe-Mail (E4) umso wichtiger macht. Empfehlung: so lassen, E4 umsetzen.
3. **Wie lange gilt der Bestätigungslink?** 60 Minuten (heute) oder 24 Stunden. Mit «erneut senden» genügen 60 Minuten. Empfehlung: 60 Minuten lassen, Frist in die Mail schreiben.
4. **Wiederherstellung für Konten nur mit Passkey.**
   A) Eigene Wiederherstellungscodes für jedes Konto, unabhängig von der App (sauber, braucht eigene Speicherung und Abfrage). B) Nach dem ersten Passkey einen zweiten Faktor verlangen oder deutlich empfehlen (zweiter Passkey oder App). C) Nur Admin-Reset, dafür E10 und E20. Empfehlung: jetzt C plus der Hinweis aus B direkt nach der Einrichtung (er steht heute nur im Profil, `SecurityCard.vue:192-196`), A für eine spätere Version.
5. **Passwortabfrage bei der ersten Einrichtung.**
   A) Anmeldung setzt die Passwortbestätigung gleich mit. B) Die Einrichtungsrouten verlangen sie nicht, solange das Konto noch keinen Faktor hat (die Middleware kennt diesen Zustand schon). Empfehlung: A, weil kleiner und überall wirksam.
6. **Abgleich mit bekannten Passwörtern:** Laravels Prüfung fragt einen externen Dienst mit einem Teil-Hash an. Ist ein Aufruf nach aussen vom Server erlaubt und erwünscht? Sonst nur Mindestlänge 12.
7. **Wer darf den zweiten Faktor zurücksetzen, und wie wird die Identität geprüft?** Heute jede Person mit dem Recht, Benutzer zu ändern. Soll es ein eigenes Recht sein? Gibt es einen festen Weg (Anruf, persönlich)?
8. **Domains:** Bleiben Frontend- und API-Domain, wie sie sind? Jeder Wechsel der Frontend-Domain macht alle Passkeys unbrauchbar (in Produktion schon einmal passiert, `next-work/19…:33`). Vor einem Wechsel braucht es einen Plan (Related Origin Requests oder neue Einrichtung für alle).
9. **Freigabe-Mail an Admins:** Reicht eine Mail je Konto, oder soll nach x Tagen ohne Freigabe erinnert werden?
10. **E-Mail-Änderung im Profil:** `UpdateUserProfileInformation.php:51-60` setzt die Bestätigung zurück und sperrt damit das Konto, bis der Link geklickt ist. Ist das gewollt, oder soll die alte Adresse gelten, bis die neue bestätigt ist (OWASP-Vorgehen)? Ob das Frontend diese Route überhaupt nutzt, habe ich nicht geprüft.

## 6. Quellen

Abgerufen am 18.11.2026.

| Kürzel | Quelle | Wofür |
|---|---|---|
| L-FORTIFY | Laravel 13, Fortify: https://laravel.com/docs/13.x/fortify | Routen, Statuscodes, 2FA, Passkeys, Drosselung |
| L-VERIFY | Laravel 13, Email Verification: https://laravel.com/docs/13.x/verification | Routen mit `auth` und `signed` |
| L-SANCTUM | Laravel 13, Sanctum (SPA Authentication): https://laravel.com/docs/13.x/sanctum | gleiche Hauptdomain, CSRF, 401/419 |
| O-AUTH | OWASP Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html | generische Antworten, Passwortlänge, erneute Bestätigung |
| O-FORGOT | OWASP Forgot Password Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html | Token, kein Auto-Login, Sitzungen, Mail |
| O-MFA | OWASP Multifactor Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html | Pflicht, Verlust, Benachrichtigung |
| O-PASSKEY | OWASP Passkey Security Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Passkey_Security_Cheat_Sheet.html | UV, mehrere Passkeys, RP-ID, Wiederherstellung |
| N-63B | NIST SP 800-63B-4, Authenticators: https://pages.nist.gov/800-63-4/sp800-63b/authenticators | Passwortregeln 3.1.1.2, Look-up Secrets 3.1.2, Drossel 3.2.2 |
| W-338 | W3C, Understanding SC 3.3.8: https://www.w3.org/WAI/WCAG22/Understanding/accessible-authentication-minimum.html | Einfügen, Passwortmanager |
| W-RPID | web.dev, RP-ID: https://web.dev/articles/webauthn-rp-id | Bindung an die Domain, Related Origin Requests |
| F-UX | FIDO Alliance, UX Guidelines for Passkey Creation and Sign-ins: https://fidoalliance.org/ux-guidelines-for-passkey-creation-and-sign-ins/ | Zeitpunkt und Texte rund um Passkeys |
| G-PASSKEY | Google, Communicating passkeys to users: https://developers.google.com/identity/passkeys/ux/communicating-passkeys | Meldung vor und nach dem Systemdialog |
| NN-FORM | NN/g, Errors in Forms: https://www.nngroup.com/articles/errors-forms-design-guidelines/ | Fehler am Feld |
| NN-HOSTILE | NN/g, Hostile Error Messages: https://www.nngroup.com/articles/hostile-error-messages/ | keine verfrühten Fehler |
| B-SIGNUP | Baymard, Simplifying Sign-up: https://baymard.com/blog/simplifying-sign-up | Hinweis bei vergebener Adresse (Gegenposition zu OWASP) |
| B-SHOW | Baymard, Show Password Feature: https://baymard.com/guidelines/665-show-password-feature | Passwort anzeigen |
| B-PW | Baymard, Password Requirements: https://baymard.com/blog/password-requirements-and-password-reset | keine komplexen Regeln |
| SCAN | Stytch, Protected Email Magic Links: https://stytch.com/docs/b2b/guides/magic-links/protected-eml | Mail-Scanner rufen Links vorab auf (Herstellerdoku) |
| N-HYDRA | Nuxt, Hydration Best Practices: https://nuxt.com/docs/3.x/guide/best-practices/hydration | Nur-Client-Inhalte |
| N-CE | Nuxt, Diskussion 17263 zu Custom Elements: https://github.com/nuxt/nuxt/discussions/17263 | nur als Hinweis, keine Grundlage |

**Einschränkungen der Quellen**

- Die WCAG-Kriterien 3.3.1, 4.1.3 und 2.5.8 sind nach Nummer zitiert; abgerufen wurde in dieser Sitzung nur 3.3.8.
- Material Design und Apple HIG habe ich nicht ausgewertet. Für die Fragen hier liefern FIDO, NN/g und Baymard die genaueren Aussagen.
- Erfahrungsberichte aus Foren (Laracasts, Stack Overflow) habe ich gesehen, aber nicht als Grundlage verwendet.
- Zur Frage «automatisch anmelden nach der E-Mail-Bestätigung» gibt es keine Norm. L3 ist ein Analogieschluss aus O-FORGOT und SCAN.
