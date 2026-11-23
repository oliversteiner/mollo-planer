# OAuth und OpenID Connect: der Planer als Anmeldedienst

Stand: Auftrag 35, Teil A (Authorization Server, OpenID Provider, Zustimmungsseite, Verwaltung der Clients). Noch offen:
Teil B (OAuth für `/mcp`), D (iOS), E (Registrierung aus Club DB), siehe unten.

Der Code gehört zur Basis (Modul Identität, `docs/starter-kit.md`): Backend unter `App\Core\Identity`, Routen in
`backend/routes/oauth.php`. Er importiert nichts aus dem Fachcode (Projekte, Aufgaben, Dokumente).

## Was der Planer kann

| | |
|---|---|
| Protokoll | OAuth 2.1: Authorization Code mit PKCE (S256), Refresh-Token mit Rotation. Kein Implicit, kein Password-Grant. |
| Identität | OpenID Connect: `id_token` (RS256), Discovery, JWKS, `userinfo`. |
| Aussteller (`issuer`) | `APP_URL` der Umgebung (TEST: `https://api.planer.mollo.dev`, PROD: `https://api.planer.mollo.ch`), nie im Code. |
| Lebensdauer | Access-Token 1 Stunde, Refresh-Token 90 Tage, Autorisierungscode 10 Minuten. |

## Endpunkte (alle auf der API-Domain)

| Pfad | Zweck |
|---|---|
| `GET /.well-known/openid-configuration` | Discovery (Aussteller, Endpunkte, Scopes, `S256`) |
| `GET /.well-known/jwks.json` | öffentlicher Schlüssel für das `id_token` (Schlüssel-ID = Fingerabdruck) |
| `GET /oauth/authorize` | Beginn: die Person wird zur Zustimmungsseite des Frontends geleitet |
| `POST /oauth/token` | Code gegen Tokens tauschen (`authorization_code`) oder erneuern (`refresh_token`) |
| `GET/POST /oauth/userinfo` | Angaben zur Person, mit Access-Token und dem Scope `openid` |
| `GET /oauth/consent`, `POST /oauth/consent/approve`, `POST /oauth/consent/deny` | JSON-Seite der Zustimmungsseite (Sitzung und CSRF wie alle Aufrufe des Frontends) |

Die Zustimmungsseite ist eine Seite des Frontends (`/oauth/consent`). Die Seite `/oauth/authorize` des Frontends ist der
Rückweg nach der Anmeldung und gibt die Anfrage wieder an die API.

## Ablauf

1. Die Anwendung schickt den Browser an `/oauth/authorize?client_id=…&redirect_uri=…&response_type=code&scope=openid email&state=…&nonce=…&code_challenge=…&code_challenge_method=S256`.
2. Ohne Sitzung, oder mit einer Sitzung ohne zweiten Faktor, geht der Browser zu `<Frontend>/login?redirect=/oauth/authorize?…`
   und kommt nach der Anmeldung zurück. Die Sperre ist dieselbe wie bei GraphQL (`EnsureStrongAuthentication`): ohne
   zweiten Faktor oder Passkey wird nichts autorisiert.
3. Das Frontend zeigt «Zugriff erlauben» (wer fragt, wofür, wohin es danach geht). Zulassen oder Ablehnen geht als JSON
   an die API; die Antwort enthält die Weiterleitung (`redirect`), das Frontend navigiert dorthin (auch zu einem URL-Schema einer App).
4. Die Anwendung tauscht den Code mit `code_verifier` gegen `access_token`, `refresh_token` und, bei `openid`, `id_token`.

## Das `id_token`

`iss`, `aud` (Client), `sub` (stabile Benutzer-ID), `exp`, `iat`, `auth_time`, `nonce` (wenn angefragt), `amr`, dazu
`name` (Scope `profile`) und `email`, `email_verified` (Scope `email`).

`amr` sagt, womit sich die Person angemeldet hat: `pwd`, `otp` (Code der 2FA-App oder Wiederherstellungscode), `hwk`
(Passkey). Eine starke Anmeldung enthält immer einen zweiten Faktor (`otp` oder `hwk`). Die Methode wird beim Login in der
Sitzung vermerkt (`StrongAuthentication`), Nonce und `amr` beim Ausstellen des Codes in `oauth_auth_code_claims`.

## Scopes

`openid`, `profile`, `email`, `api`, `mcp:use`, `mcp:read`, `mcp:write`. Die Texte der Zustimmungsseite stehen in
`lang/de/oauth.php` und `lang/en/oauth.php`. `api` und `mcp:*` werden mit den Teilen B und D ausgewertet; die Rechte der
Person gelten dann zusätzlich.

## Betrieb

- **Schlüssel:** Passport signiert mit einem RSA-Schlüsselpaar. Einmal je Umgebung auf dem Server erzeugen (**Server**, im
  Ordner `backend/`): `php artisan passport:keys`. Die Dateien `storage/oauth-private.key` und `storage/oauth-public.key` bleiben auf
  dem Server, nicht im Repository und nicht in Sicherungen, die weitergegeben werden. Alternativ in `backend/.env`:
  `PASSPORT_PRIVATE_KEY` und `PASSPORT_PUBLIC_KEY`. Wechselt man den Schlüssel, ändert sich die Schlüssel-ID im JWKS;
  bestehende Tokens werden ungültig, die Personen melden sich neu an.
- **Migrationen:** `php artisan migrate` (Tabellen `oauth_*`, dazu `oauth_auth_code_claims`).
- **Aufräumen:** Der Scheduler ruft täglich `passport:purge` (abgelaufene und widerrufene Tokens und Codes nach einer
  Woche) und löscht die Notizen zu Codes nach einem Tag (`routes/console.php`).
- **Clients** werden nie im Code angelegt, sondern von Admins in der Oberfläche unter **System → Anmeldedienst**
  (`/admin/oauth-clients`, Recht `settings.manage`):
  - Angelegt wird mit Name, den exakten Redirect-Adressen (eine pro Zeile, höchstens 10) und der Art: *vertraulich* (ein Server
    mit Geheimnis, das nur einmal angezeigt wird) oder *öffentlich* (Browser oder App, nur mit PKCE).
  - Erlaubte Adressen: `https`, `http` nur für `localhost` und `127.0.0.1`, ein eigenes URL-Schema (zum Beispiel
    `ch.mollo.planer:/oauth/callback`) nur für öffentliche Clients. Keine Platzhalter, kein Fragment, keine Zugangsdaten in der Adresse.
  - Anlegen und Widerrufen verlangen eine frische Passwortbestätigung. Widerrufen sperrt den Client und widerruft alle seine
    Zugänge und Refresh-Tokens. Beides steht im Log (wer, welcher Client, nie ein Geheimnis).
  - GraphQL: `oauthClients`, `createOAuthClient`, `revokeOAuthClient` (`backend/graphql/identity.graphql`).

## Sicherheit (was erzwungen ist)

- PKCE ist Pflicht, die Redirect-URI muss genau übereinstimmen, `state` wird unverändert zurückgegeben.
- `/oauth/authorize` verlangt eine starke Sitzung; Gäste gehen zum Login. Drosseln: 30 Anfragen pro Minute und IP auf
  `authorize` und `consent`, 60 auf `token` und `userinfo`.
- Ein Refresh-Token gilt einmal. Kommt ein benutztes zurück, werden alle Tokens der Person für diese Anwendung
  widerrufen (Rotation mit Erkennung der Wiederverwendung) und ein Eintrag im Log geschrieben.
- Das `userinfo` verlangt den Scope `openid` (sonst 403).
- Agent-Konten (`is_agent`) haben keinen Web-Zugang und kommen deshalb nicht bis zur Zustimmung.

## Tests

`backend/tests/Feature/Core/`: der ganze Ablauf bis zu den Tokens, Ablehnung, falscher `code_verifier`, nicht
registrierte Redirect-URI, Gast und schwache Sitzung, Wiederverwendung eines Refresh-Tokens, Discovery, JWKS (ohne private
Teile), Signatur und alle Claims des `id_token`, `amr` bei Passkey, `userinfo` je Scope. Clients: `backend/tests/Feature/GraphQL/OAuthClientsTest.php` (Rechte, Passwortbestätigung, Adressregeln, Widerruf mit Tokens). Frontend:
`frontend/tests/nuxt/oauth-consent.test.ts` und `frontend/tests/nuxt/admin-oauth-clients.test.ts`.

## Noch offen

- **B:** OAuth für `/mcp` (`Mcp::oauthRoutes()` von `laravel/mcp` bringt die Metadaten und die dynamische Registrierung mit; zu
  prüfen, wie das mit den Routen und der Sperre hier zusammengeht). Dafür muss der Guard `oauth` mit Sanctum zusammen auf `/mcp` stehen.
- **D:** Client «Planer iOS» und der Scope `api` im Guard der GraphQL-API.
- **E:** Registrierung aus Club DB.
- **Abmelden auf allen Geräten** und die Liste der angemeldeten Apps im Profil (Teil D).
