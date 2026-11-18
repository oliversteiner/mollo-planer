# 22 · Onboarding härten (Backend)

Status: erledigt (O1 55a2f6b, O2, O3, O4 6b4c15d, O5 c492233, O6 8e40121, O7 eb4da8d und 54309b8, O8 e37c730, O9 9827aa0, Doku 7f44cda auf `fix/onboarding-backend`); wartet auf Oliver
Reihenfolge: nach Auftrag 21, vor Auftrag 23 (Frontend) und 20 (Browser-Tests). Ein Commit je Punkt (O1 bis O9), Tests im jeweiligen Commit.
Branch: `fix/onboarding-backend` **von `fix/verify-email-public`** (Auftrag 21; die Aufträge sind gestapelt). **Zuerst** `feature/user-commands` (Auftrag 18) hineinmergen, als eigener Merge-Commit: O5 und O7 brauchen den Dienst `App\Services\UserSecurity` und `app:user-2fa` von dort. Konflikte beim Merge löst Bob nicht still, sondern nennt sie im Bericht. Nichts mergen nach und nichts pushen auf `dev` oder `main`, bis Oliver es sagt; der Tag `v1.0.0` wird heute nach der Umsetzung des Onboardings gesetzt (Oliver).
Grundlage: `docs/onboarding-gutachten.md` (Fable, 2026-11-18), Empfehlungen E4, E7 bis E12, E16. Dieser Auftrag ist ohne das Gutachten lesbar; die genannten Fundstellen sind die des Gutachtens und von Bob nachzuprüfen.
Entscheide von Oliver (2026-11-18): Registrierung **neutral**; Passkey-only-Konten: **Admin-Reset + Hinweis** (keine eigenen Codes jetzt); Anmeldung zählt als **Passwortbestätigung**, gesetzt erst nach vollständiger Anmeldung; Passwörter **12 Zeichen + Abgleich** mit bekannten Passwörtern; neue Spalte **`users.locale`** für die Sprache der Konto-Mails (O8).
Vorläufig festgelegt (Standard von Fable, Oliver kann noch ändern; Bob baut so und nennt die drei Punkte im Bericht): Text der Mail bei unbestätigtem Konto (O3), Info-Mail auch beim ersten Faktor (O5), Adresse in `ACCOUNT_NOT_READY` (O9).
Prozess: reiner Backend-Auftrag, kein UI. Texte der Mails in `lang/de` und `lang/en`. Mails laufen über die Queue (`ShouldQueue`, `afterCommit`), Muster wie `ResetPasswordNotification`.

Wichtig: Bob **liest** die genannten Fundstellen im Code nach, bevor er ändert (Zeilenangaben können seit dem Gutachten gewandert sein) und meldet Abweichungen, statt zu raten.

## O1 · «Passwort vergessen» neutral (E7)

- `POST /forgot-password` antwortet **immer 200** mit demselben Text, auch bei unbekannter Adresse und bei der 60-Sekunden-Drossel des Brokers. 422 nur bei formal ungültiger Adresse, 429 bei der IP-Drossel (O2).
- Fortify liefert heute 422 «Zu dieser E-Mail-Adresse gibt es kein Konto» (`FailedPasswordResetLinkRequestResponse`). Eigene Antwort an Fortify binden (`FortifyServiceProvider`), Text in `lang/passwords.php` (DE/EN) so, dass er zum Erfolgstext im Frontend passt («Wenn die Adresse bei uns bekannt ist …»).
- Test: bekannte und unbekannte Adresse geben dieselbe Antwort; nur bei der bekannten geht eine Mail raus.

## O2 · Drossel auf `/forgot-password` und `/reset-password` (E11)

- Je IP gedrosselt, Muster der anderen Auth-Routen in `FortifyServiceProvider` (Anmeldung, Registrierung). Zahlen wie dort, bei Abweichung Begründung in der Commit-Nachricht.
- Test: die Drossel greift, die Antwort ist 429.

## O3 · Registrierung neutral (E8, Entscheid Oliver)

Vertrag (aus dem Gutachten, 4.4):

| Fall | Antwort | Nebenwirkung |
|---|---|---|
| Adresse neu | `201`, Sitzung wie heute | Bestätigungsmail (Queue) |
| Adresse vergeben | `201`, **gleicher Körper**, **keine** Sitzung | Mail «Du hast schon ein Konto» an die Adresse, Links: Anmelden, Passwort vergessen. Kein Admin-Hinweis, kein neues Konto. |
| Eingabe ungültig (Name, Format der Adresse, Passwort) | `422` mit Feldfehlern | nie «vergeben» |

- `unique:users` aus `CreateNewUser` entfernen; die Prüfung wandert in die Antwortlogik. Die Datenbank bleibt mit dem Unique-Index die letzte Sicherung (ein Wettlauf zweier Registrierungen darf nicht zu einem 500 führen).
- **Antwortzeit:** Mail wird in die Queue gelegt, die Antwort wartet nicht darauf (sonst verrät die Zeit den Fall). Passwort-Hashing läuft in beiden Fällen (einen Hash mit demselben Aufwand auch im «vergeben»-Fall berechnen und verwerfen).
- **Sitzung:** Nach der Registrierung meldet Fortify heute an. Im «vergeben»-Fall entsteht keine Sitzung; das Frontend (Auftrag 23) zeigt in beiden Fällen die Seite «E-Mail bestätigen» und darf dafür **nicht** auf eine Sitzung bauen. Der Rest, dass der Aufrufer am Cookie den Fall sieht, ist bekannt (Gutachten 4.4, Alternative B bei Frage 1 wurde nicht gewählt).
- Die Mail «Du hast schon ein Konto» geht nur an bestätigte **und** unbestätigte Konten, nie an abgelehnte (gelöschte) Adressen. Drossel je Adresse, damit niemand mit der Registrierung fremde Postfächer zumüllt (z. B. höchstens eine solche Mail pro Adresse und Stunde).
- **Adresse normalisieren wie heute** (Kleinschreibung über `lowercase_usernames`, Leerzeichen am Rand): «Vergeben» wird an der normalisierten Adresse geprüft, mit demselben Vergleich wie der Unique-Index der Datenbank.
- **Vergebene Adresse, Konto noch unbestätigt** (jemand hat sich mit dieser Adresse registriert und nie bestätigt, vielleicht eine fremde Person): eigener Text der Mail, weil «Du hast schon ein Konto» dort nur halb stimmt: «Für diese Adresse gibt es schon eine Registrierung, die noch nicht bestätigt ist. Ist sie von dir: Lass dir die Bestätigungsmail erneut senden. Kennst du das Passwort nicht: Setze es mit ‹Passwort vergessen› neu.» Links: «Bestätigungsmail erneut senden» (Seite aus Auftrag 21), «Passwort vergessen». Das bestehende Konto wird **nicht** verändert und nicht überschrieben (Name und Passwort bleiben), sonst könnte eine fremde Person eine laufende Registrierung übernehmen. Antwort der API wie im Fall «vergeben».
- Die Antwort enthält in keinem Fall Daten des Kontos (Fortify antwortet heute mit leerem Körper und `201`; das bleibt).
- Tests: alle drei Fälle, gleicher Körper und Statuscode, keine Sitzung im Fall «vergeben», genau eine Mail je Fall, Drossel der Zusatzmail, Wettlauf (zweiter Insert → kein 500). Der bestehende `SelfRegistrationTest` (erwartet heute 422 bei «vergeben») wird angepasst; Bob nennt die Änderung ausdrücklich.

## O4 · Passwortregeln (E9, Entscheid Oliver)

- Zentral in `AppServiceProvider` (`Password::defaults`): mindestens **12** Zeichen, `uncompromised()` (Abgleich mit haveibeenpwned über Teil-Hash). **Keine** Zusammensetzungsregeln (keine Pflicht für Ziffer, Sonderzeichen usw.), Einfügen bleibt erlaubt, Obergrenze mindestens 64 (NIST), dokumentieren.
- Ist der Dienst nicht erreichbar, wird **nicht** blockiert. Laravels Prüfung lässt bei einem Fehler durch, wartet aber standardmässig **30 Sekunden** (`Illuminate\Validation\NotPwnedVerifier`). Deshalb den Prüfer mit kurzem Timeout binden (**3 Sekunden**); Laravel sieht dafür eine eigene Bindung des Vertrags `UncompromisedVerifier` vor. Test: Dienst antwortet nicht → Passwort angenommen, Antwort ohne lange Wartezeit.
- **Schalter:** Konfigurationswert (z. B. `auth.password_uncompromised`, Umgebungsvariable `PASSWORD_UNCOMPROMISED_CHECK`, Standard `true`). Aus ist er nur in Umgebungen ohne Netz: in den Browser-Tests von Auftrag 20 und wo Bob es für die Testsuite braucht. In `.env.example` und `docs/deploy-template.md` eintragen; in Produktion bleibt er an.
- **Tests rufen den Dienst nie wirklich auf:** `Http::preventStrayRequests()` in der Basisklasse der Tests, der Abgleich wird gefälscht (`Http::fake`), wo er geprüft wird. Die Regel trifft viele bestehende Tests: Die Fabrik setzt `password`, die Tests nutzen z. B. `Correct-Horse-9` (zu kurz für 12 Zeichen). Bob passt die Konstanten an (ein gemeinsamer Wert, mindestens 12 Zeichen) und nennt die Zahl der geänderten Tests. Kein Test wird gelöscht oder abgeschwächt, um grün zu werden.
- **Obergrenze und Abschneiden:** bcrypt verarbeitet höchstens 72 Byte. Bob prüft, was die App heute mit längeren Passwörtern tut (Fehler oder stilles Abschneiden) und meldet es; still abgeschnitten wird nicht. Wenn nötig eine Obergrenze als Regel mit verständlicher Meldung.
- Alle Stellen, die Passwörter annehmen, nutzen die Regel: Registrierung, Passwort zurücksetzen, Passwort ändern im Profil, Einladung annehmen, `app:create-admin`, `app:user-password`. Bob listet sie im Commit und prüft, dass keine eine eigene (schwächere) Regel hat.
- Bestehende Konten bleiben unberührt (keine erzwungene Änderung).
- Der Hinweistext im Frontend («mindestens 8 Zeichen») gehört zu Auftrag 23; Bob meldet die Fundstellen.
- Test: 11 Zeichen abgelehnt, 12 angenommen, bekanntes Passwort abgelehnt (gefälschte Antwort), Dienst nicht erreichbar → angenommen.

## O5 · Nach Passwort-Reset (E12)

- `ResetUserPassword`: danach **alle Sitzungen** des Nutzers beenden (beim Reset per Mail ist niemand angemeldet) und die Zugänge für Claude widerrufen (derselbe Dienst wie bei `app:user-password`: `UserSecurity::revokeAccess`, aus Auftrag 18). Sitzungen lassen sich nur beim Sitzungstreiber `database` beenden, wie es der Dienst heute schon handhabt; das im Test und in `docs/deploy-template.md` festhalten. Kein Auto-Login (Fortify meldet nicht an; das bleibt so). Der zweite Faktor bleibt unverändert.
- Mail «Dein Passwort wurde geändert» an die Adresse (Queue, DE/EN, ohne Passwort und Link, mit dem Satz «Warst du das nicht, melde dich bei einem Admin»).
- Gleiche Info-Mail bei: Passwort im Profil geändert, zweiter Faktor **eingerichtet oder entfernt** (Authenticator, Passkey), Wiederherstellungscodes neu erzeugt. Über Ereignisse/Listener, nicht an jede Stelle einzeln (Fortify löst Ereignisse für 2FA aus, Passkeys über `PasskeyRegistered` usw.; vorhandene Listener ansehen).
- **Auch beim ersten Faktor** (Einrichtung im Onboarding und nach einem Admin-Reset) geht die Mail raus (vorläufig festgelegt). Grund: Nach einem Admin-Reset schützt nur das Passwort das Konto; richtet dann jemand Fremdes den ersten Faktor ein, ist die Mail der einzige Hinweis für die Besitzerin. Eine Mail je Ereignis, nicht mehrere für denselben Vorgang (Authenticator bestätigt: eine Mail, nicht zusätzlich eine für die erzeugten Codes).
- Sprache dieser Mails: `users.locale` (O8), sonst die Sprache der laufenden Anfrage, sonst `APP_LOCALE`.
- Test je Ereignis: Mail geht raus; nach Reset sind andere Sitzungen weg.

## O6 · Anmeldung zählt als Passwortbestätigung (E16, Entscheid Oliver)

- Heute verlangt die Einrichtung des ersten Faktors direkt nach der Anmeldung nochmals das Passwort (Fortify-Zeitstempel der Bestätigung wird von der Anmeldung nicht gesetzt, nur von `POST /user/confirm-password`).
- Die Anmeldung mit Passwort setzt den Zeitstempel mit (Sitzungswert `auth.password_confirmed_at`, den Fortify prüft; Fundstelle laut Gutachten: `ConfirmablePasswordController`). **Erst nach vollständiger Anmeldung:** bei Konten mit Authenticator-App nach dem gültigen Code bzw. Wiederherstellungscode, nicht schon nach dem Passwort-Schritt. Gilt **nur**, wenn in dieser Anmeldung ein Passwort eingegeben wurde, nicht für den Weg über Passkey allein.
- Die Frist bleibt wie konfiguriert (`auth.password_timeout`, heute 10800 Sekunden = 3 Stunden; Entscheid Oliver: nicht ändern). Nach Ablauf der Frist verlangen Änderungen am zweiten Faktor weiter die Eingabe. Der Schutz durch die starke Sitzung (`ProtectAccountSecurityRoutes`) bleibt unberührt.
- Test: Nach der Anmeldung mit Passwort liefert `GET /user/confirmed-password-status` `confirmed: true`, die Einrichtung geht ohne weitere Abfrage; nach dem Passwort-Schritt **vor** dem Code der App ist der Wert noch nicht gesetzt; nach Ablauf der Frist mit Abfrage; nach Passkey-Anmeldung mit Abfrage.
- Das Frontend (`useSecurity.ts`) fragt heute vorab die Bestätigung ab und zeigt dann den Dialog. Wenn die API den Status «bestätigt» liefert, entfällt der Dialog von selbst. Bob prüft das und meldet, ob eine Frontend-Änderung nötig ist (dann in Auftrag 23).

## O7 · Admin-Reset des zweiten Faktors (E10)

- Mutation `resetUserTwoFactor` und Befehl `app:user-2fa reset`: Mail an die betroffene Person («Dein zweiter Faktor wurde von einem Admin zurückgesetzt. Du richtest bei der nächsten Anmeldung einen neuen ein. Warst du das nicht, melde dich sofort.»), Queue, DE/EN. Einmal im Dienst `UserSecurity` auslösen, damit beide Wege dasselbe tun.
- Sprache der Mail: `users.locale` der betroffenen Person (O8), sonst `APP_LOCALE`; **nicht** die Sprache des Admins.
- Protokolleintrag mit Admin-ID (Mutation) bzw. «console» (Befehl), Nutzer-ID und Zeit; nie Adresse, nie Geheimnisse (Muster der Befehle aus Auftrag 18: `logIntervention`).
- Recht: heute reicht `update`. Bob meldet nur, **wie** das Recht heute geprüft wird; eine neue Rolle oder ein eigenes Recht ist **nicht** Teil dieses Auftrags (offene Frage 7 im Gutachten, geht an Oliver).
- Test: Mail geht raus, Protokollzeile hat Admin und Nutzer, keine Adresse.

## O8 · Freigabe- und Ablehnungsmail (E4)

- `ApproveUser`: Mail an die Person in **ihrer Sprache** («Dein Konto ist freigegeben», Link zur Anmeldung, aus `config('app.frontend_url')`). `RejectUser` löscht heute das Konto: Mail **vor** dem Löschen in die Queue legen und die Daten (Name, Adresse, Sprache) in die Benachrichtigung kopieren, damit sie nach dem Löschen noch versendet werden kann (nicht auf das Modell verweisen). Text ohne Begründungspflicht, mit Kontaktweg.
- **Sprache (Entscheid Oliver): neue Spalte `users.locale`.** `users` hat heute kein Sprachfeld (im Modell und in den Migrationen gesucht); ohne Speichern ist die Sprache der Person bei der Freigabe nicht bekannt.

  | Tabelle | Spalte | Typ | Bedeutung |
  |---|---|---|---|
  | `users` | `locale` | `string(5)`, nullable | Sprache für Konto-Mails, die nicht in einer Anfrage der Person entstehen. Werte wie `App\Enums\Locale`. |

  - Gesetzt bei der Selbstregistrierung (Sprache der Anfrage, wie sie `SetLocaleFromRequest` bestimmt) und bei der Einladung (Sprache, in der eingeladen wird: `UserInvitation::invite`). `app:create-admin --password` und bestehende Konten: `null`.
  - `null` heisst `APP_LOCALE`.
  - Kein neues Feld im GraphQL-Schema, keine Änderung über die API in diesem Auftrag. (Später kann die Sprachwahl im Profil die Spalte setzen; nicht jetzt.)
  - Nicht in `#[Fillable]` aufnehmen, wenn es ohne geht; gesetzt wird sie nur an den zwei Stellen oben.
  - Migration: nur die Spalte, kein Index. Wegen der Migration vor dem Commit `composer test:mariadb`.
- **Text der Ablehnung:** kein Grund, kein Link. Kontaktweg ist der Satz «Bei Fragen melde dich bei deiner Kontaktperson.»; es wird keine Adresse und kein Name eines Admins genannt.
- **Text der Freigabe:** «Dein Konto ist freigegeben. Du kannst dich jetzt anmelden.» Knopf «Anmelden».
- Auch wenn eine Freigabe über die Befehlszeile oder auf anderem Weg erfolgt: Die Mail hängt an der Freigabe-Logik, nicht an der Mutation allein (zentral, z. B. im Aktionsobjekt, das `ApproveUser` aufruft).
- Test: Freigabe → Mail in der Sprache der Person; Ablehnung → Mail geht raus, obwohl das Konto danach gelöscht ist.

## O9 · Adresse in der Antwort `ACCOUNT_NOT_READY` (für Auftrag 23 F2)

- Die Seite «E-Mail bestätigen» soll die Adresse zeigen, an die die Mail ging. Für unbestätigte Konten ist aber auch die Abfrage `Viewer` gesperrt (`EnsureEmailVerified`); nach einem Neuladen weiss das Frontend die Adresse nicht mehr.
- `AccountReadiness::locked` gibt bei `reason: email` zusätzlich `extensions.email` mit: die Adresse des **angemeldeten** Kontos. Bei `reason: approval` nicht nötig (dort ist `Viewer` offen).
- Das ist keine Auskunft an Dritte: Die Antwort gibt es nur mit der Sitzung dieses Kontos. Ohne Sitzung (Fall «vergeben» aus O3) gibt es keine Antwort mit Adresse.
- Test: unbestätigtes, angemeldetes Konto bekommt `ACCOUNT_NOT_READY` mit `reason: email` und der eigenen Adresse; ein Gast bekommt nichts davon.
- Vorläufig festgelegt; lehnt Oliver das ab, entfällt O9 und die Seite zeigt nach dem Neuladen keine Adresse.

## Abnahme

- `php artisan test`, `./vendor/bin/pint` und, wegen der Migration aus O8, `composer test:mariadb` grün; Testzahl und Dauer in der Abschlussmeldung.
- Pro Punkt ein Commit: `Fix: … (task 22 O1)` usw., davor der Merge-Commit von `feature/user-commands`.
- `docs/umsetzung.md` (Stand) und `docs/deploy-template.md` ergänzen: neue Drossel, Mails, Passwortregel (inkl. externer Aufruf des Prüfdienstes, damit die Netzwerkfreigabe auf dem Server bekannt ist).
- Dem Frontend-Auftrag 23 meldet Bob: neue API-Antworten (Registrierung 201 für beide Fälle, Passwort vergessen immer 200, `extensions.email` aus O9), geänderte Passwortregel samt neuer Fehlermeldung, Status der Passwortbestätigung nach der Anmeldung.
- Oben `Status: erledigt (<Commit>)`, dann anhalten.
