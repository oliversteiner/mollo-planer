# 41 · OAuth und SSO: Mollo Planer als Anmeldedienst

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-376` (Status der Teile dort nachführen).

Status: A und B gemergt (PR #36, #37). Nachbesserungen F1 bis F6: PR #43 nach dev, nicht gemergt (Branch `fix/oauth-abnahme`). PROD-Deploy gesperrt, bis F1 bis F5 gemergt sind und Oliver auf PROD `passport:keys` ausgeführt hat. Offen: D (iOS), E (Registrierung aus Club DB). Ursprünglich: Status: A gemergt (PR #36). B: PR #37 nach dev, nicht gemergt (Branch `feature/oauth-mcp`). Offen: D (iOS), E (Registrierung aus Club DB), Test auf TEST (Schlüssel `php artisan passport:keys`, Client anlegen). Stuart nimmt A und B zusammen ab. Siehe docs/oauth-sso.md.

Zusammengeführt aus zwei Wünschen:
* **OAuth ergänzen:** Der Connector für Claude soll ohne Token-Bridge funktionieren («Custom Connector» in Claude Desktop und claude.ai braucht OAuth, siehe `docs/claude-connector.md`, Abschnitt Grenzen).
* **SSO:** Eine Anmeldung für die Apps von Mollo. Auslöser: Mollo Planer und Club DB laufen auf demselben Testserver, der Passwortmanager verwechselt die Konten. Frage von Oliver: Geht SSO mit dem eigenen Stack auf Hostpoint?

Antwort: Ja. Beides ist derselbe Baustein. Der Planer wird **Authorization Server** (OAuth 2.1) und **OpenID Provider** (OpenID Connect). Laravel Passport läuft auf Hostpoint (nur PHP und MariaDB, keine weiteren Dienste).

## Ziel

1. **Claude-Connector mit OAuth:** In Claude den Server `https://planer.mollo.ch/mcp` (Domain gemäss Deploy) als Custom Connector eintragen. Anmeldung im Browser mit dem Konto des Planers, ohne Token und ohne Node-Bridge.
2. **SSO für Club DB (und spätere Apps):**
   * «Mit Mollo anmelden» auf den Anmeldeseiten von Club DB.
   * Ein Konto, ein zweiter Faktor, ein Passwortmanager-Eintrag.
3. **Bestehende Zugänge bleiben:** Die Tokens der Agenten (Bob, Steve, Kevin, Claude) gelten weiter, ebenso die Anmeldung mit Passwort und Passkey im Planer.

## Bausteine

### A. Authorization Server und OpenID Provider (Planer)

* Laravel Passport, Grant **Authorization Code mit PKCE** (S256 Pflicht). Kein Implicit, kein Password-Grant. Refresh-Token mit Rotation.
* **OpenID Connect:** `id_token`, Discovery unter `/.well-known/openid-configuration`, Schlüssel unter `/.well-known/jwks.json`, Endpunkt `userinfo`. Claims: `sub` (stabile ID), `email`, `email_verified`, `name`, `amr` (z. B. `pwd`, `otp`, `hwk` für Passkey) und `acr`.
* **Zustimmungsseite** im Stil der App (Web Awesome): «Club DB möchte auf dein Konto zugreifen». Für eigene Clients der Organisation (Club DB) einmal pro Client, danach still.
* **Anmeldung beim Autorisieren** über die bestehende Fortify-Anmeldung mit 2FA und Passkey. Ist die Sitzung nicht stark angemeldet, zuerst der zweite Faktor.
* **Clients verwalten:** Seite unter Systemverwaltung (nur Admins): Name, Redirect-URIs (exakt, nur https ausser localhost), vertraulich oder öffentlich, Scopes, Widerruf. Clients nie per Code anlegen.

### B. OAuth für den MCP-Server (Spezifikation MCP, Autorisierung)

* **Protected Resource Metadata** (RFC 9728) unter `/.well-known/oauth-protected-resource` für `/mcp`.
* **Authorization Server Metadata** (RFC 8414).
* **401 mit `WWW-Authenticate`**, der auf die Metadaten zeigt.
* **Dynamische Client-Registrierung** (RFC 7591), damit Claude sich selbst registriert:
  * nur öffentliche Clients mit PKCE;
  * Rate-Limit;
  * Admins sehen und widerrufen die registrierten Clients.
* **Resource Indicator** (`resource`, RFC 8707): Ein Token für `/mcp` gilt nur dort.
* Prüfen, was `laravel/mcp` dafür schon mitbringt (OAuth-Routen mit Passport). Nicht neu bauen, was das Paket kann.
* **Scopes:** `mcp:read` (export_plan, list_*), `mcp:write` (import_plan, update_tasks, add_comment). Die Rechte der Person gelten zusätzlich, wie heute.
* Die bestehende Token-Anmeldung (Sanctum) für `/mcp` bleibt parallel.

### C. SSO-Client in Club DB (Arbeit von Kevin, eigener Auftrag im Repo club-db)

Nur als Vorgabe, damit die Schnittstelle passt:
* Club DB ist ein **vertraulicher Client** (Backend tauscht den Code; das Secret liegt nur in der `.env` von Club DB).
* **Zuordnung beim ersten Login:** über `sub`. Ein bestehendes Konto in Club DB wird nur über eine bestätigte E-Mail verknüpft, und nur nach Anmeldung mit dem alten Passwort (kein stilles Übernehmen).
* **Rollen bleiben in Club DB.** Der Planer liefert nur die Identität.
* **Zweiter Faktor:** Club DB akzeptiert eine Anmeldung als «stark», wenn `amr` einen zweiten Faktor enthält. Sonst verlangt Club DB den eigenen.
* **Abmelden:** lokal; optional RP-initiated Logout.

## Offen (Oliver, Auswahldialog vor dem Bau)

1. **Domain des Anmeldedienstes:**
   * `planer.mollo.ch` (einfach, Vorschlag für den Anfang);
   * oder eigene Subdomain wie `konto.mollo.ch` (sauberer für spätere Apps, mehr Hosting-Aufwand).
2. **Wer darf sich per SSO bei Club DB anmelden:**
   * alle Konten des Planers;
   * oder nur freigegebene (Vorschlag: Club DB legt das Konto beim ersten Login als Gast ohne Rechte an, Admin vergibt Rollen).
3. **Registrierung:** Neue Konten nur im Planer, oder auch über Club DB (dann leitet Club DB zur Registrierung des Planers)?
4. **Testserver:** zuerst auf TEST mit einem Testclient, PROD danach.

## Sicherheit

* PKCE Pflicht, exakte Redirect-URIs, `state` und `nonce` geprüft.
* Kurze Access-Token (z. B. 1 h), Refresh-Token mit Rotation und Widerruf bei Wiederverwendung.
* Schlüssel für die Signatur nur auf dem Server, nicht im Repo; Rotation dokumentiert.
* Rate-Limits auf `authorize`, `token`, `register`.
* Protokoll: Autorisierung, Widerruf, Registrierung eines Clients.
* Tests:
  * vollständiger Ablauf (Code mit PKCE, Token, `userinfo`);
  * falsche Redirect-URI;
  * falscher Verifier;
  * Token für falsche Ressource;
  * Wiederverwendung eines Refresh-Tokens;
  * `amr` bei Passwort, 2FA, Passkey;
  * `/mcp` mit Token aus OAuth und mit bisherigem Token.
* `composer test:mariadb`.

## Doku

* `docs/claude-connector.md`: neuer Weg «Custom Connector mit OAuth», alter Weg bleibt beschrieben.
* Neues Dokument `docs/oauth-sso.md`: Ablauf, Endpunkte, Clients einrichten, Anleitung für eine App, die SSO nutzt (Club DB als Beispiel).
* `docs/umsetzung.md` nachführen.

## Abschluss

* Auf TEST: Claude (Desktop oder claude.ai) als Custom Connector verbinden und `list_projects` aufrufen.
* Einen Testclient für Club DB lokal anmelden lassen (Ablauf bis `userinfo`).
* Ergebnis an Oliver.

## Entscheide (Oliver, 2026-11-23, mit Stuart)

1. **Domain:** `planer.mollo.ch` (PROD) bzw. `planer.mollo.dev` (TEST). Der Aussteller (`issuer`) ist die API-Domain der Umgebung, aus `APP_URL`, nie fest im Code. Ein späterer Umzug auf eine eigene Domain wäre ein Wechsel für alle Clients; das nehmen wir bewusst in Kauf.
2. **SSO bei Club DB:** alle Konten des Planers dürfen sich anmelden; Club DB legt beim ersten Login ein **Gastkonto ohne Rechte** an, ein Admin von Club DB vergibt Rollen.
3. **Registrierung auch in Club DB:** Club DB hat ein eigenes Formular, das Konto entsteht aber **im Planer** (eine Identität). Vorgabe für die Schnittstelle (Teil E unten).
4. **Die iOS-App meldet sich über OAuth an** (statt der eigenen App-Routen aus dem Vorschlag von Steve, `mollo-planer-ios/docs/anmeldung.md`). Teil D unten. Damit entfallen eigene Anmelde-Routen und Geräte-Token für die App.

## D. Client «Planer iOS» (öffentlicher Client)

* Authorization Code mit PKCE (S256), öffentlicher Client ohne Secret, Anmeldung in der App über `ASWebAuthenticationSession`. Zweiter Faktor und Passkey laufen über die Web-Anmeldung des Planers (der Passkey gehört zur Website, kein `apple-app-site-association` und kein bezahltes Entwicklerkonto nötig).
* **Redirect-URI:** eigenes URL-Schema der App (z. B. `ch.mollo.planer:/oauth/callback`), exakt registriert. Später, mit aktivem Entwicklerkonto, optional ein Universal Link.
* **Das Token gilt für die GraphQL-API (`/api`)**, nicht nur für `/mcp`: eigener Scope (z. B. `api`), Prüfung im Guard der API zusätzlich zu Sanctum. Die Rechte der Person gelten wie im Web.
* **«Stark angemeldet»:** Ein Token aus diesem Ablauf gilt als stark, **weil** es nur nach Anmeldung mit zweitem Faktor ausgestellt wird (`amr` enthält einen zweiten Faktor). Ohne diesen Zweig bekäme die App überall `reauth-required` (`EnsureStrongAuthentication`). Test dazu, auch für den Gegenfall (Anmeldung ohne zweiten Faktor, wo er Pflicht ist: kein Token).
* **Laufzeit:** Access-Token kurz (z. B. 1 Stunde), Refresh-Token mit Rotation (z. B. 90 Tage gleitend). Passwortwechsel, «2FA zurücksetzen» (`UserSecurity::resetTwoFactor`/`revokeAccess`) und «Abmelden auf allen Geräten» widerrufen alle Tokens der Person. In den Einstellungen des Profils: Liste der angemeldeten Apps und Geräte mit Widerruf.
* **Drossel:** bei 429 `Retry-After` mitliefern (Frage B von Steve).
* Agent-Konten (`is_agent`) bekommen über OAuth **kein** Token (sie haben keinen Web-Zugang, Auftrag 31).

## E. Registrierung aus Club DB

* Club DB schickt die Registrierung **vom Server** (vertraulicher Client, eigener Scope z. B. `accounts:register`, Client-Credentials) an einen Endpunkt des Planers. Das Formular von Club DB sieht nie Passwörter des Planers ausser bei der Eingabe; Passwortregel des Planers gilt (12 Zeichen, Leak-Prüfung), Fehler am Feld kommen zurück.
* **Gleiche Regeln wie die Registrierung im Planer** (Auftrag 22): neutrale Antwort bei vergebener Adresse (Mail «Du hast schon ein Konto»), Bestätigungsmail des Planers, Sprache, Drossel. Kein Weg, über Club DB Konten zu erraten.
* Nach der Bestätigung der Adresse meldet sich die Person per SSO bei Club DB an (Gastkonto, Rollen durch den Admin von Club DB). Ob das Konto im Planer zusätzlich die Freigabe des Planer-Admins braucht: **ja, wie heute** (Freigabe gilt für den Planer; für Club DB genügt die bestätigte Identität). Bob prüft, dass SSO für Club DB nicht an der Freigabe im Planer hängt, und meldet, wenn das im Code nicht sauber trennbar ist.

## Reihenfolge der Teile

A (Authorization Server und OIDC) → B (OAuth für `/mcp`) → D (iOS) → E (Registrierung aus Club DB). C baut Kevin im Repo club-db. Je Teil ein Commit, Tests, `composer test`, `test:mariadb`, `analyse`.

## Trennregel für das Starter-Kit (ab diesem Auftrag)

Siehe `docs/starter-kit.md`. Der ganze Code dieses Auftrags gehört zur Basis (Modul Identität): Backend unter `App\Core\…` (bzw. `App\Core\Identity\…`), Oberfläche im Nuxt-Layer `layers/core` (Layer anlegen, falls er fehlt, und in `nuxt.config` einbinden). Die Basis importiert nichts aus dem Fachcode (Projekte, Aufgaben, Dokumente). Dazu ein Architekturtest (Pest `arch()`), der das prüft. Oberfläche im Layer `core` nur mit Komponenten, Themes und Symbolen, die es auch in der **freien** Ausgabe von Web Awesome bzw. Font Awesome Free gibt (das Kit wird öffentlich, siehe Konzept). Nichts Mollo-Spezifisches fest im Code: Namen, Domains und Absender kommen aus der Konfiguration. Bestehenden Code nicht verschieben; das kommt mit dem Refactoring.

## Abnahme A und B (Stuart, 2026-11-23 22:45) – Nachbesserungen vor PROD

Gesamturteil: gute Arbeit. Code unter `App\Core\Identity` (Trennregel eingehalten), 39 Tests grün, Discovery/JWKS/Metadaten auf TEST erreichbar, Refresh-Rotation mit Erkennung der Wiederverwendung, Scopes `mcp:read`/`mcp:write` sauber durchgesetzt, Konto-Prüfung im MCP (bestätigt, freigegeben, kein Agent). **Vor dem Deploy auf PROD** diese Punkte, Branch `fix/oauth-abnahme` von `origin/dev`, PR nach `dev`, nicht mergen:

* **F1 · PKCE nur S256 und für alle Clients.** league/oauth2-server akzeptiert standardmässig auch `code_challenge_method=plain` (Discovery behauptet nur S256), und für vertrauliche Clients ist PKCE nicht Pflicht. OAuth 2.1: S256 für alle. In `OidcAuthCodeGrant` nur den S256-Verifier zulassen und `code_challenge` immer verlangen. Tests: `plain` abgelehnt; vertraulicher Client ohne `code_challenge` abgelehnt.
* **F2 · Wer autorisieren darf.** `/oauth/authorize` und `/oauth/consent/approve` prüfen das Konto nicht. Verlangen: bestätigte E-Mail, kein Agent (Sitzung ohnehin unmöglich, trotzdem prüfen), Konto nicht gesperrt. Freigabe durch den Admin nur für die Scopes `api` und `mcp:*` verlangen; für `openid profile email` allein genügt die bestätigte Identität (Entscheid SSO Club DB). Klare Fehlermeldung auf der Zustimmungsseite. Tests je Fall.
* **F3 · Client-Credentials-Grant.** Passport schaltet `client_credentials` immer ein (nicht in der Discovery genannt). Ein vertraulicher Client bekäme damit Tokens ohne Person. Bis Teil E: Anfragen mit `grant_type=client_credentials` am Token-Endpunkt ablehnen (`unsupported_grant_type`). Test.
* **F4 · Schlüssel.** Ohne `storage/oauth-*.key` (oder `PASSPORT_*_KEY` in `.env`) laufen JWKS, Token und id_token in einen 500. `deploy/deploy.sh`: prüfen, ob die Schlüssel vorhanden sind; wenn nicht, **Abbruch** mit dem Hinweis auf `php artisan passport:keys` (nicht automatisch erzeugen). Rechte 600 prüfen.
* **F5 · Selbst registrierte Clients erkennbar.** Zustimmungsseite: bei dynamisch registrierten Clients sichtbar «selbst registriert, nicht von Mollo geprüft» und die Adresse, an die zurückgegeben wird (Host ist schon da). In `docs/oauth-sso.md` empfehlen: auf PROD `MCP_REDIRECT_DOMAINS` auf `https://claude.ai/,https://claude.com/` beschränken, solange niemand lokale Clients braucht.
* **F6 · Nummer.** Titel, Commits und `docs/oauth-sso.md`: Auftrag 41 (nicht 35).

Danach: Oliver erzeugt auf PROD die Schlüssel (`passport:keys`), dann Deploy auf PROD.
