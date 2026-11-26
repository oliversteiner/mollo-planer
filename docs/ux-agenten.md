# UX-Entwurf: Seite «Agenten» und Vorschläge von Agenten (Ideen)

Auftrag 77 · ToDo `t-552` (Tasks `k-2106` Bestand, `k-2107` Entwurf, `k-2108` Skizzen und Fragen, `k-2110` Ideen) · Milestone 1.9 «Agenten» · Laura, 26.11.2026 · Stand: **Entwurf**, wartet auf die Entscheide in Abschnitt 13.

Grundlage: Konzept `next-work/76-agenten-konzept.md` (Stuard P, Entscheide Oliver in Abschnitt 0, 8 und 10). Wo dieser Entwurf davon abweicht, steht es in Abschnitt 12 mit Grund. Skizzen (nicht im Repo): `next-work/abnahme/77/`.

Begriffe in der Oberfläche: **Agent**, **Kennung** (nicht «E-Mail»), **Zugang** (der Token), **Vorlage**, **Persona** (Rolle und Anweisungen eines Agenten), **Gemeinsame Regeln**, **Startbefehl**, **Administration**, **Admin**, **Taste**. Bedienung: wählen, auswählen, eingeben, einschalten.

---

## 1. Bestand (Teil A)

Lokal durchgespielt mit der Wegwerf-Datenbank der Browser-Tests (eigener Port, SQLite), als Admin, 1280 px und 390 px. Bildschirmfotos `next-work/abnahme/77/a-de-*.png`.

| Was | Heute | Datei |
|---|---|---|
| Agenten anzeigen | Administration → Benutzer, Abschnitt «Agenten (n)»; Zeile: Avatar, Name, Abzeichen «Agent», Kennung mit Kopieren, Taste mit Zugangsstand, Menü | `pages/admin/users.vue` |
| Agent anlegen | Taste «Agent anlegen» in der Kopfzeile und im leeren Abschnitt; Dialog nur mit **Name**, Hinweis zur Kennung, Passwort als Feld | `components/admin/AgentFormModal.vue` |
| Kennung | aus dem Namen, `bob@agent.invalid`; ist sie vergeben, `bob-2@agent.invalid` (gleiche Namen sind erlaubt) | `app/Core/Accounts/AgentAccounts.php` |
| Zugang und Projekte | Dialog «Zugang für {Agent}»: Erklärung, vier Schritte mit Stand, Kennung, MCP-Adresse, Zugänge (erzeugen, einmal anzeigen, widerrufen), Projekte (zuweisen, entfernen) | `components/admin/AgentAccessDialog.vue` (713 Zeilen) |
| Projekt → Mitglieder | Agent wie eine Person hinzufügen; Rolle fest «Agent» (58) | `components/project/MembersCard.vue` |
| Persona, Startbefehl | nur lokal bei Oliver (`~/www/agents/personas/`, `agent()` in `.zshrc`) | – |
| Ideen | privat, Filter Alle/Offen/Angenommen/Verworfen, Karten mit «Mit dem Assistenten planen», «Bearbeiten», «Verwerfen», «Löschen» | `pages/ideas.vue` |

**Befunde beim Durchspielen**

* **F1 (hoch) · Schmales Fenster:** In der Agenten-Zeile fallen Name und Kennung auf null Breite; zu sehen sind nur «B», das Abzeichen und die Taste «Zugang einrichten» (`a-de-benutzer-schmal-390.png`). Ursache: Die Taste mit Text nimmt sich den Platz, `.users-text` darf schrumpfen. Wird mit der neuen Liste ohnehin neu gebaut (6.2); bis dahin klein zu beheben (Taste unter 40em nur mit Symbol).
* **F2 (mittel) · Der Dialog «Zugang für …» ist zu voll.** Erklärung, Schritte, Kennung, Adresse, Zugänge und Projekte in einem Dialog; die Hälfte liegt unter dem Rand (`a-de-zugang-dialog.png`). Für eine Persona ist dort kein Platz mehr.
* **F3 (gering) · «Verwaltung» in den Breadcrumbs**, obwohl der Bereich seit dem 25.11. «Administration» heisst (`nav.admin`). Gehört zu einem eigenen kleinen Auftrag oder zu 78.
* **F4 (gering) · «Agent anlegen» fragt nur den Namen.** Danach muss man den Zugang öffnen, um Projekte zuzuweisen: zwei Wege für eine Absicht.

**Was bleibt, was umzieht, was wegfällt**

| Bleibt | Zieht um auf die Seite «Agenten» | Fällt weg |
|---|---|---|
| Agent-Konto, Kennung (58), Rolle «Agent» fest | Liste der Agenten mit Zugangsstand (`agentAccessState`) | Abschnitt «Agenten» auf der Benutzerseite (wird ein Verweis, 6.7) |
| Zugänge erzeugen, einmal anzeigen, widerrufen (Verhalten, Texte, Passwort als Feld) | Inhalt von `AgentAccessDialog` → Abschnitte «Zugänge» und «Projekte» der Agentenseite (7) | der Dialog «Zugang für …» als Dialog |
| Agent auf der Mitgliederseite eines Projekts hinzufügen | «Agent anlegen» (Dialog, erweitert um Vorlage, Projekte, Modell) | Taste «Agent anlegen» auf der Benutzerseite und Befehl `users-create-agent` |
| `app:create-agent`, `app:user-token` (CLI) | Schritte «Token ablegen», «Connector eintragen» → Reiter «Startbefehl» (10) | Schritt «Token als Umgebungsvariable ablegen» (der Schlüsselbund ersetzt ihn) |

---

## 2. Grundsätze

1. **Eine Stelle je Agent.** Alles zu einem Agenten (Persona, Projekte, Zugänge, Deaktivieren) steht auf **seiner Seite** `/admin/agents/:id`, nicht in Dialogen. Dialoge nur für kurze Aufgaben (anlegen, annehmen, Rückfragen), nie über einem anderen (R11).
2. **Gleiche Muster wie die Benutzerseite.** Zeilen, Abzeichen, Kopieren der Kennung, Zugangsstand und `ActionMenu` sehen aus wie heute. Wer die Benutzerseite kennt, kennt die Agentenliste.
3. **Langer Text auf einer Seite, nicht im Dialog.** Persona, Vorlagen und Gemeinsame Regeln werden auf eigenen Seiten mit dem bestehenden Editor bearbeitet.
4. **Kein Token im Browser, wenn es nicht sein muss.** Der Normalweg ist der Startbefehl: Er erzeugt den Zugang und legt ihn im Schlüsselbund ab. Ein Zugang im Browser bleibt möglich (Connector ohne CLI, z. B. Claude Desktop).

---

## 3. Einstieg und Menü

* Menüeintrag **«Agenten»** im Abschnitt **Administration**, direkt nach «Benutzer», Symbol `robot`. Pfad **`/admin/agents`** (Abweichung A1).
* Sichtbar mit dem Recht `agents.manage` (`utils/route-rights.ts`: `'/admin/agents': 'AGENTS_MANAGE'`); die Middleware schickt ohne Recht wie bei den anderen Seiten der Administration weg.
* Breadcrumbs: «Administration › Agenten», auf der Seite eines Agenten «Administration › Agenten › Bob».
* **Ohne `agents.manage`: nichts.** Kein Menüeintrag, keine Seite. Begründung: Die Seite enthält Konfiguration (Anweisungen, Zugänge), nichts für die tägliche Arbeit. Wer mit Agenten arbeitet, sieht sie dort, wo sie mitarbeiten: in den Mitgliedern eines Projekts, an Tasks und Kommentaren (Abzeichen «Agent»). Ein Lesemodus hätte nur Nutzen, wenn Persona-Texte für Mitglieder wichtig wären; das ist heute nicht der Fall.
* Hinweis für Admins, solange es keinen Agenten gibt: siehe 6.6.

---

## 4. Aufbau der Seite `/admin/agents`

`PageFrame` «Agenten», Beschreibung (eine Zeile): «Agenten arbeiten über den Connector in deinen Projekten. Hier legst du sie an, gibst ihnen eine Persona und richtest den Startbefehl ein.»

Aktionen in der Kopfzeile: **«Agent anlegen»** (brand, `robot`).

Darunter vier Reiter (`wa-tab-group`, wie `pages/admin/roles.vue`), Zustand im Query-Parameter `?tab=` (ohne: `agents`), damit Links und Zurück funktionieren:

| Reiter | `tab` | Inhalt |
|---|---|---|
| Agenten (Anzahl aktive) | `agents` | Liste (6) |
| Vorlagen | `templates` | Karten (8) |
| Gemeinsame Regeln | `rules` | Editor (9) |
| Startbefehl | `launch` | Anleitung (10) |

Warum Reiter und nicht eine lange Seite: Die vier Teile haben verschiedene Aufgaben und Häufigkeiten (Agenten täglich, Startbefehl einmal je Rechner). Untereinander würde der Startbefehl unter einer langen Liste verschwinden. Warum nicht vier Menüeinträge: Es ist ein Thema; die Administration hat schon acht Einträge.

---

## 5. Daten je Agent (für die Liste)

Aus `agents` (Konzept 4): Name, Kennung, Avatar, `roleLabel`, `defaultModel`, `tool`, Projekte (id, Name), Zugänge (`expiresAt`, `lastUsedAt`), `isActive` (deaktiviert ja/nein), `hasProfile`, `template` (id, Name, kann `null` sein), letzter Zugriff (spätestes `lastUsedAt`).

---

## 6. Reiter «Agenten»

### 6.1 Werkzeugleiste

* Suche (`FormInput type="search"`, wie Benutzerseite), filtert Name, Kennung, Rolle und Projektnamen. Erst ab **6 Agenten** sichtbar; darunter hilft sie nicht.
* Keine Sortierwahl. Reihenfolge fest: aktive nach Name, darunter der Abschnitt «Deaktiviert (n)», zugeklappt (Disclosure-Muster wie die Milestones, `docs/ux-projektseite.md` 4.3), nur wenn es deaktivierte gibt.

### 6.2 Zeile eines Agenten

```
[Avatar] Bob  Coder · Opus                                    [● Zugang aktiv]  [···]
         bob@agent.invalid [⧉]   Kundenportal · Website · +1    zuletzt aktiv vor 2 Std.
```

| Teil | Inhalt | Komponente |
|---|---|---|
| Avatar | wie heute | `UserAvatar` |
| Zeile 1 | **Name** als Link auf `/admin/agents/:id` (erste Fokusstelle der Zeile); Rolle (`roleLabel`) und Modell leise, mit «·» getrennt; ohne Persona statt dessen Abzeichen «Ohne Persona» (`warning`, `appearance="outlined"`) | `NuxtLink`, `span.wa-caption-m`, `wa-badge` |
| Zeile 2 | Kennung mit Kopieren (wie heute); Projekte als Text, höchstens zwei Namen, dann «+n» (ganze Liste im `title` und auf der Agentenseite); ohne Projekt «Noch in keinem Projekt» leise | bestehend |
| Rechts oben | Zugangsstand wie heute (`agentAccessState`: aktiv, läuft ab, abgelaufen, keiner), als Taste: führt auf `/admin/agents/:id#zugaenge` | `wa-button size="s"` |
| Rechts unten | «zuletzt aktiv {relativ}» oder «noch nie aktiv» | `wa-caption-s` |
| Menü | Bearbeiten · Startbefehl kopieren · Deaktivieren (bzw. Reaktivieren) | `ActionMenu` |

Abzeichen «Agent» entfällt in dieser Liste (alle sind Agenten). Das Abzeichen «Vorlage» steht nicht in der Zeile, nur auf der Seite des Agenten (7.1).

**Startbefehl kopieren** legt `mollo-planer agent <slug>` in die Zwischenablage (`<slug>` = Teil der Kennung vor `@`, z. B. `bob-2`) und meldet «Befehl kopiert.».

**Deaktivierte Zeile:** leise (`opacity` über Token `--wa-color-text-quiet` für Text, Avatar in Graustufen), Abzeichen «Deaktiviert» (`neutral`), keine Zugangstaste, Menü: Bearbeiten · Reaktivieren.

### 6.3 Neuer Agent in der Liste

Nach dem Anlegen: Zeile hervorgehoben wie heute (`data-new`, 3 s), Fokus auf den Namen-Link, Meldung «Agent {name} angelegt.» mit Aktion «Persona ansehen».

### 6.4 Zustände

| Zustand | Anzeige |
|---|---|
| Lädt | drei `wa-skeleton`-Zeilen in Zeilenhöhe |
| Fehler | `wa-callout variant="danger"` «Die Agenten konnten nicht geladen werden.» + «Erneut versuchen» (Muster aus `ux-projektseite.md` 4.6) |
| Leer (neue Installation) | 6.5 |
| Suche ohne Treffer | «Keine Treffer.» (wie Benutzerseite) |
| Agent ohne Persona (Bestand vor 1.9) | Abzeichen «Ohne Persona»; auf seiner Seite Hinweis und «Persona aus Vorlage übernehmen» (7.2) |

### 6.5 Leerer Zustand

```
            [robot]
     Noch keine Agenten
 Ein Agent arbeitet über den Connector in deinen Projekten,
 zum Beispiel als Coder oder für die Planung.
 Wähle eine Vorlage, um den ersten anzulegen.

 [Planer]  [Coder]  [UX/UI]  [Doku]        ← Vorlagenkarten wie in 8, mit Taste «Anlegen»
 Leer anlegen                               ← Link-Taste, öffnet den Dialog ohne Vorlage
```

`EmptyState` (Symbol `robot`), darunter die Vorlagen-Karten (Komponente aus 8, Variante kompakt: Name, eine Zeile Beschreibung, Taste «Anlegen»).

### 6.6 Hinweis auf dem Dashboard

Nur für Personen mit `agents.manage`, nur solange es **keinen** Agenten gibt: `AppCallout variant="brand"` in der Spalte der Hinweise des Dashboards: «Mit Agenten arbeiten: Lege einen Agenten an und starte ihn mit dem Startbefehl auf deinem Rechner.» Tasten «Agenten ansehen» und «Ausblenden». Ausblenden merkt sich der Browser (`localStorage`, Schlüssel `mollo.hint.agents`), wie `useStoredSwitch`. Kein Hinweis in der Zen-Ansicht.

### 6.7 Benutzerseite danach

* Der Abschnitt «Agenten» mit Zeilen **fällt weg**, die Taste «Agent anlegen» ebenso.
* An seiner Stelle eine Zeile: «{n} Agenten verwaltest du unter **Agenten**.» (Link auf `/admin/agents`). Ohne `agents.manage`: «{n} Agenten. Sie verwalten Personen mit dem Recht «Agenten verwalten».» ohne Link.
* Die Suche der Benutzerseite findet keine Agenten mehr.

### 6.8 Projekt → Mitglieder

Bleibt wie heute (58). Neu unter der Personenauswahl, nur mit `agents.manage`: Link-Taste «Neuen Agenten anlegen» → `/admin/agents?create=1&project=<id>`: Die Seite öffnet den Dialog «Agent anlegen» mit diesem Projekt vorgewählt. Nach dem Anlegen bietet die Meldung «Zurück zu {Projekt}» an.

---

## 7. Seite eines Agenten `/admin/agents/:id`

Eigene Seite statt Dialog: Die Persona ist ein langer Text, dazu Projekte, Zugänge und Deaktivieren (F2). Aufbau wie die Projekteinstellungen: untereinander Abschnitte mit `h2`, jeder mit eigener Taste «Speichern» dort, wo Eingaben sind.

### 7.1 Kopf

`PageFrame` mit Titel = Name, davor Avatar. Darunter eine Zeile (leise): Kennung mit Kopieren · «Aus Vorlage {Vorlage}» (oder «Ohne Vorlage») · Abzeichen «Deaktiviert» falls zutreffend.
Aktionen: «Startbefehl kopieren» (neutral, outlined).

Herkunft aus einer Vorlage: nur dieser Hinweis, **kein** Abgleich. Tooltip am Hinweis: «Kopie der Vorlage vom {Datum}. Änderungen an der Vorlage wirken nicht auf diesen Agenten.» So sieht man die Herkunft und die Unabhängigkeit an einer Stelle.

### 7.2 Abschnitt «Persona»

| Feld | Eingabe | Pflicht | Vorgabe | Hinweis |
|---|---|---|---|---|
| Name | `FormInput`, max. 120 | ja | – | «Ändert die Kennung nicht.» |
| Rolle | `FormInput`, max. 60 | nein | aus der Vorlage (`role_label`) | «Kurz, z. B. Coder. Steht in der Liste und im Namen der Sitzung.» |
| Modell | `FormSelect` aus der Liste der Konfiguration (`sonnet`, `opus`, `haiku`, …) | ja | aus der Vorlage | «Beim Start mit `--model` änderbar.» |
| Werkzeug | `FormSelect`, heute nur «Claude Code», darum **gesperrt** mit Hinweis «Weitere Werkzeuge folgen.» | ja | `claude_code` | |
| Anweisungen | `RichTextEditor` (wie Dokumente), höchstens 20’000 Zeichen | nein | Text der Vorlage | «Wird beim Start nach den Gemeinsamen Regeln mitgegeben.» Abweichung A2 |
| Startsatz | `FormTextarea`, 3 Zeilen, max. 500 | nein | leer = Vorgabe der Gemeinsamen Regeln (als Platzhalter sichtbar) | «Der erste Satz, den der Agent beim Start erhält.» |

Taste «Speichern» (brand) unten am Abschnitt; deaktiviert, solange nichts geändert ist. Ungespeicherte Änderungen: beim Verlassen der Seite die Rückfrage des Browsers (`beforeunload`) und beim Wechsel innerhalb der App `ConfirmModal` «Änderungen verwerfen?» (bestehendes Muster der Dokumente, falls vorhanden; sonst neu).

**Ohne Persona (Bestand):** `AppCallout variant="warning"` über dem Abschnitt: «Dieser Agent hat noch keine Persona. Übernimm eine Vorlage oder schreibe die Anweisungen selbst.» mit Auswahl Vorlage + Taste «Übernehmen» (füllt Rolle, Modell, Anweisungen; speichert erst mit «Speichern»).

### 7.3 Abschnitt «Projekte»

Aus `AgentAccessDialog` übernommen: Liste «Projekt · Rolle Agent · Entfernen» (Rückfrage in der Zeile, wie heute), darunter Auswahl «Projekt zuweisen» und Taste «Zuweisen». Verhalten, Grenzen und Meldungen unverändert (`verwaltung-agenten-anfasser.md` 3.3, Punkt 4).

### 7.4 Abschnitt «Zugänge» (`id="zugaenge"`)

Aus `AgentAccessDialog` übernommen: Liste (Name, zuletzt benutzt, Ablauf, Widerrufen), «Zugang erzeugen» mit Passwort als Feld, einmalige Anzeige im Abschnitt mit «Abgelegt». Neu darüber ein Satz:

> «Normalerweise richtet der Startbefehl den Zugang ein und legt ihn im Schlüsselbund ab (Reiter **Startbefehl**). Einen Zugang hier erzeugst du nur für einen Connector ohne Startbefehl, zum Beispiel in Claude Desktop.»

Darunter klein: Adresse des MCP-Servers mit Kopieren und Link «Anleitung für den Connector» (aus dem heutigen Dialog). Die vier Schritte des heutigen Dialogs entfallen (Reiter «Startbefehl» ersetzt sie).

### 7.5 Abschnitt «Deaktivieren»

Am Schluss, abgesetzt (`wa-divider`), Überschrift «Agent deaktivieren».

* Text: «Widerruft alle Zugänge und sperrt das Konto. Der Agent bleibt Mitglied seiner Projekte; Tasks und Kommentare bleiben bei ihm. Du kannst ihn später reaktivieren.»
* Taste «Deaktivieren …» (`danger`, `outlined`) öffnet **an derselben Stelle** die Bestätigung (nach `vectorlab-ux-skills:surfaces`: folgenreich, aber umkehrbar → Rückfrage im Abschnitt, kein Dialog; auf der Seite steht kein anderer Dialog im Weg):
  * Aufzählung mit Zahlen: «{n} Zugänge werden widerrufen.», «Er bleibt in {m} Projekten.»
  * `PasswordConfirmField` (wenn die Bestätigung abgelaufen ist)
  * «Deaktivieren» (`danger`) und «Abbrechen». Fokus beim Öffnen auf «Abbrechen».
* Danach: Kopf mit «Deaktiviert», Abschnitt zeigt «Reaktivieren» (`neutral`, ohne Rückfrage) mit dem Satz «Zugänge kommen nicht zurück. Richte nach dem Reaktivieren einen neuen ein.»
* Aus dem Menü der Liste: «Deaktivieren» öffnet `ConfirmModal` mit denselben Sätzen und dem Passwort als Feld (ein Dialog, R11).

---

## 8. Reiter «Vorlagen»

### 8.1 Karten

Raster (`wa-grid`, min. 16rem), je Vorlage eine `wa-card`:

```
┌──────────────────────────────┐
│ Coder              Mitgeliefert│
│ Setzt Aufträge in Code um,    │
│ mit Tests und Pull Request.   │
│ Opus · 2 Agenten              │
│ [Anlegen]          [···]      │
└──────────────────────────────┘
```

* Kopf: Name (`h3`), rechts Abzeichen **«Mitgeliefert»** (neutral) oder **«Eigene»** (brand); bei mitgelieferter mit geändertem Text zusätzlich «Geändert» (warning, outlined).
* Beschreibung (eine Zeile, `description`), Modell, «{n} Agenten» (Zahl der Agenten mit dieser Herkunft; 0: nichts).
* Taste «Anlegen» (öffnet 11 mit dieser Vorlage). Menü: Bearbeiten · Duplizieren · Auf Auslieferung zurücksetzen (nur mitgeliefert und geändert) · Löschen (nur eigene).
* Am Ende eine leere Karte «Eigene Vorlage» mit `plus`, öffnet die Bearbeitung einer neuen Vorlage.
* Reihenfolge: `sort`, mitgelieferte zuerst.

### 8.2 Vorlage bearbeiten `/admin/agents/templates/:id` (neu: `/admin/agents/templates/new`)

Gleiche Felder wie 7.2 (Name, Rolle, Modell, Werkzeug, Anweisungen), dazu **Beschreibung** (eine Zeile, max. 160, Pflicht). Dieselbe Komponente wie die Persona (`AgentPersonaForm`), damit beide gleich aussehen.

* Hinweis oben: «Änderungen wirken nur auf Agenten, die du danach anlegst.»
* **Zurücksetzen** (mitgeliefert): Taste im Kopf, Rückfrage in der Seite wie 7.5: «Text, Rolle und Modell der Auslieferung übernehmen? Deine Änderungen gehen verloren.»
* **Löschen** (eigene): Rückfrage in der Seite; Agenten mit dieser Herkunft behalten ihren Text, ihr Hinweis wird «Aus gelöschter Vorlage {Name}» (Name als Text im Profil merken oder `template_id` auf `null` und Hinweis «Ohne Vorlage»; Bob entscheidet nach Aufwand, beides ist richtig).
* **Duplizieren:** öffnet `templates/new` mit allen Feldern und Name «{Name} (Kopie)».

---

## 9. Reiter «Gemeinsame Regeln»

* Satz oben: «Diese Regeln erhält jeder Agent beim Start, vor seiner Persona. Schreibe hier, was für alle gilt: Zusammenarbeit über den Planer, Umgang mit Git, keine Geheimnisse.»
* `RichTextEditor`, darunter «Zuletzt geändert am {Datum} von {Name}» und «Speichern».
* Darunter Feld **«Startsatz (Vorgabe)»** (`FormTextarea`), Vorgabe aus der Auslieferung, z. B. «{name}, weiter. Sieh im Planer nach deinen Tasks.»; `{name}` als einziger Platzhalter, Hinweis darunter.
* Leer (neue Installation): der mitgelieferte Text (Clara, Auftrag 80) steht schon drin; die Seite kennt keinen leeren Zustand.

---

## 10. Reiter «Startbefehl»

Anleitung als nummerierte Schritte (Muster der Schritte im heutigen Zugangsdialog: Nummer im Kreis, Text, darunter das Feld). Jeder Befehl in einem schreibgeschützten Feld mit Schrift `--wa-font-family-code` und Taste mit Symbol `copy` (`aria-label` «Befehl kopieren»), wie Kennung und Adresse heute.

1. **Installieren** – «Einmal je Rechner. Braucht Node 20 oder neuer.»
   `npm i -g {cliUrl}` (`{cliUrl}` = `<Adresse des Planers>/cli/mollo-planer.tgz` aus der Laufzeit-Konfiguration, nie fest)
2. **Anmelden** – «Mit deinem persönlichen Zugang (Profil → Zugang für Claude). Er landet im Schlüsselbund.»
   `mollo-planer login {planerUrl}`
3. **Agent einrichten** – Auswahl «Agent» (`FormSelect`, aktive Agenten, Vorgabe: der erste) über dem Feld. «Erzeugt einen Zugang für den Agenten, legt ihn im Schlüsselbund ab und fragt nach dem Arbeitsordner.»
   `mollo-planer agent setup {slug}` (Abweichung A5)
4. **Starten** – «Ohne Namen erscheint ein Menü: Agent, Ordner, Modell.»
   `mollo-planer agent {slug}` und darunter `mollo-planer agent`
5. **Optional: Kurzbefehl** – «Danach genügt `agent`.» `mollo-planer agent --alias`

Unter den Schritten: `AppCallout variant="neutral"`: «Läuft auf macOS (Schlüsselbund) und Linux (Secret Service). Windows folgt.» und Link «Handbuch: Agenten einrichten» (`/help/agenten`, Kapitel von Clara, Auftrag 80; bis es da ist, ohne Link).

**Ohne Agent:** Schritte 1 und 2 wie oben; statt 3 und 4 der Satz «Lege zuerst einen Agenten an.» mit Taste «Agent anlegen».

Die Seite zeigt **keinen** Token und erzeugt keinen.

---

## 11. Dialog «Agent anlegen»

Ein Dialog mit **einem Formular**, nicht mit Schritten (Abweichung A3). Öffnet über «Agent anlegen» (Kopfzeile, Befehlspalette), über «Anlegen» an einer Vorlage, aus der Mitgliederseite (6.8).

```
Agent anlegen
  Vorlage      [Coder ▾]                 ← nur, wenn nicht von einer Vorlagenkarte geöffnet; dort als Text «Vorlage: Coder · Ändern»
  Name         [Bob            ]
               Kennung: bob@agent.invalid
  Projekte     [🔍 Projekte filtern]
               ☑ Kundenportal
               ☐ Website
               ☐ Archiv
  Modell       [Opus ▾]                  ← Vorgabe aus der Vorlage
  ◻ Zugang gleich hier erzeugen           ← Schalter, aus
    Normalerweise richtet der Startbefehl den Zugang ein.
  [Passwort]                              ← nur wenn nötig (PasswordConfirmField)
                                [Abbrechen] [Anlegen]
```

| Feld | Pflicht | Vorgabe | Regel |
|---|---|---|---|
| Vorlage | nein | die gewählte; aus der Kopfzeile «Ohne Vorlage» | Auswahl: Vorlagen + «Ohne Vorlage» |
| Name | ja | leer, Fokus hier | max. 120; Kennung live darunter (Vorschau, Server bestimmt endgültig) |
| Projekte | nein | aus `?project=`, sonst keins | Liste mit Kontrollkästchen, nur nicht archivierte Projekte, in denen die Person Mitglieder verwalten darf; Filterfeld ab 8 Projekten; Liste höchstens 12rem hoch, scrollt |
| Modell | ja | aus der Vorlage, sonst erstes der Liste | |
| Zugang gleich hier erzeugen | nein | aus | an: nach dem Anlegen wechselt der Dialog in den Zustand «Token einmal anzeigen» (Muster heute, «Abgelegt» schliesst) |

* **Gleicher Name:** erlaubt (wie heute). Gibt es schon einen Agenten mit diesem Namen, steht unter dem Feld (warning, kein Fehler): «Es gibt schon einen Agenten «Bob». Der neue bekommt die Kennung bob-2@agent.invalid.» Grund: Die Kennung unterscheidet sie, und der Startbefehl spricht Agenten über sie an.
* Persona (Rolle, Anweisungen) kommt aus der Vorlage und wird **nicht** im Dialog gezeigt; anpassen danach auf der Seite des Agenten (Meldung mit «Persona ansehen»).
* Eine Transaktion (Konzept: `createAgent`); Fehler erscheinen am Feld oder als `AppCallout` oben im Dialog, nichts ist halb angelegt.
* Schliessen per Klick daneben gesperrt, solange ein Token sichtbar ist (wie heute).
* Nach dem Anlegen ohne Token: Dialog zu, Liste (6.3).

---

## 12. Abweichungen vom Konzept

| Nr. | Konzept | Vorschlag | Grund |
|---|---|---|---|
| A1 | Pfad `/agents`, eigener Eintrag im Menü | `/admin/agents` im Abschnitt Administration | Alle Seiten, die ein Recht der Installation brauchen, liegen dort (Benutzer, Rollen, Einstellungen); Breadcrumbs, Middleware und `route-rights` folgen dem Präfix. Ein Hauptmenüeintrag nur für Admins wäre der einzige seiner Art. Frage F1 |
| A2 | `instructions` als Markdown | Gespeichert als **RichText** (TipTap-JSON, Regel des Projekts), bearbeitet mit dem bestehenden `RichTextEditor`; `agentLaunchConfig` liefert **Markdown** (bestehender Wandler `DocumentContent`/`RichTextDocument`) | Ein Markdown-Feld mit Vorschau braucht einen neuen Markdown-Renderer im Frontend und ein zweites Bearbeitungsmuster. Der Editor der Dokumente ist da, bekannt und liefert Markdown für die CLI. Gilt auch für Vorlagen und Gemeinsame Regeln. Frage F2 |
| A3 | Schritt-Dialog Name → Projekte → Modell | **ein Formular** mit drei Feldern | Drei kurze Felder brauchen keine Schritte; «Weiter»/«Zurück» kosten Klicks und Zustand. Die Persona (der lange Teil) gehört nicht in den Dialog. Frage F3 |
| A4 | Dialog «Zugang für …» zieht in die Seite um | als **Abschnitte** auf der Seite des Agenten, nicht als Dialog | F2: der Dialog ist schon heute zu voll; auf der Seite haben Persona, Projekte und Zugänge Platz |
| A5 | `mollo-planer agent einrichten <name>`, Agent per Name | `mollo-planer agent setup <slug>`, Agent per Kennung (`bob-2`) | Befehle sind Code (englisch, wie `login`); Namen sind nicht eindeutig, Kennungen schon |
| A6 | «Zugang gleich erzeugen» als Option | Option bleibt, **Standard aus**, mit Satz zum Startbefehl | Ein Token im Browser ist der unsichere Weg (Zwischenablage, Bildschirm); der Startbefehl legt ihn direkt in den Schlüsselbund. Frage F5 |
| A7 | Hinweis im Onboarding/Dashboard «abschaltbar» | Abschalten je Browser (`localStorage`) | Keine neue Einstellung am Konto für einen einmaligen Hinweis |
| A8 | Abschnitt «Agenten» auf der Benutzerseite verweist | Zeilen fallen weg, eine Zeile mit Zahl und Link bleibt | Eine Stelle je Agent; die Suche der Benutzerseite wird einfacher. Frage F4 |

---

## 13. Offene Fragen an Oliver

Am Task `k-2108` als Kommentar. Je mit Empfehlung zuerst.

* **F1 · Ort:** (a) Administration → Agenten, `/admin/agents` *(Empfehlung)*; (b) eigener Eintrag im Hauptmenü `/agents`.
* **F2 · Anweisungen:** (a) mit dem Editor der Dokumente, CLI bekommt Markdown *(Empfehlung)*; (b) Markdown-Feld mit Vorschau.
* **F3 · Anlegen:** (a) ein Formular im Dialog *(Empfehlung)*; (b) Schritte wie im Konzept.
* **F4 · Benutzerseite:** (a) Agenten-Zeilen weg, eine Zeile mit Link *(Empfehlung)*; (b) Zeilen lesend behalten.
* **F5 · Zugang beim Anlegen:** (a) Schalter, Standard aus *(Empfehlung)*; (b) Standard an; (c) ganz weg, nur Startbefehl und Seite des Agenten.

---

## 14. Seite «Ideen»: Vorschläge von Agenten (Teil D)

Konzept Abschnitt 10. Agenten legen keine Projekte an; sie schlagen sie als **adressierte Idee** vor, ein Mensch nimmt an.

### 14.1 Finden

* **Filter «Für mich»** als erste Taste der Filterleiste, mit Zahl der offenen: «Für mich (2)». Nur sichtbar, wenn die Person je eine adressierte Idee erhalten hat. Ist beim Öffnen von `/ideas` mindestens ein Vorschlag offen, ist «Für mich» vorgewählt; sonst wie heute «Alle». Filter im Query-Parameter `?filter=for-me` (auch für Link und Zurück).
* **Zahl am Menüeintrag «Ideen»:** `wa-badge pill` (brand) mit der Zahl der offenen Vorschläge für mich; ohne offene keine Zahl. `aria-label` des Eintrags: «Ideen, 2 Vorschläge für dich».
* **Dashboard:** `AppCallout variant="brand"`, Symbol `lightbulb`: «{n} Vorschläge von Agenten warten auf dich.» Taste «Ansehen» → `/ideas?filter=for-me`. Verschwindet, wenn keiner mehr offen ist; nicht ausblendbar (es ist Arbeit, kein Hinweis). Nicht in der Zen-Ansicht.
* Mail nur nach Einstellung der Person (Konzept); keine Gestaltung hier.

### 14.2 Karte eines Vorschlags

```
┌────────────────────────────────────────────────────────────────┐
│ Archiv-Neubau                                   [Neues Projekt]│
│ Vorschlag von Ada [Agent]    · für dich · vor 2 Std.          │
│ Entwurf: 3 Milestones · 9 ToDos · 27 Tasks                       │
│ Beschreibung (RichText) …                                        │
│ ─────────────────────────────────────────────────────────────── │
│ [Als Projekt anlegen]  [Im Assistenten anpassen]  [Verwerfen]    │
└────────────────────────────────────────────────────────────────┘
```

* Kopf: Titel (`h2`) und rechts Abzeichen der Art: **«Neues Projekt»** (`brand`, outlined) oder **«Ergänzung zu {Projekt}»** (`neutral`, outlined, Projektname als Link). Den Status zeigt die Karte nur, wenn er nicht «offen» ist (14.4).
* Zeile 2 (leise): «Vorschlag von {Autor}» mit `AgentBadge`, «für dich» (bzw. «für {Empfänger}» in der Sicht der Autorin und der Admins), Zeit relativ (Datum im `title`).
* Zeile 3: Zählung des Entwurfs wie heute (`draftSummary`), mit dem Präfix «Entwurf:».
* Beschreibung: `RichTextView` wie heute.
* Fusszeile nur für die **Empfängerin** (`viewerCanAdopt`): «Als Projekt anlegen» bzw. «In Projekt übernehmen» (brand), «Im Assistenten anpassen» (outlined, `wand-magic-sparkles`, wie «Mit dem Assistenten planen»), «Verwerfen» (plain). Bearbeiten und Löschen gibt es für Empfänger nicht (gehört der Autorin).

### 14.3 Annehmen

**«Als Projekt anlegen»** öffnet einen Dialog (ein Dialog, R11):

* Titel «Vorschlag als Projekt anlegen».
* Feld **Projektname** (Vorgabe aus dem Entwurf, änderbar, Pflicht).
* **Vorschau:** die bestehende Vorschau des Imports (`components/planer/Tree.vue`) mit Zählung, zugeklappt auf Milestones; höchstens 50vh, scrollt.
* Satz: «Du wirst Owner. {Agent} wird Mitglied mit der Rolle Agent.» Kommen im Entwurf weitere Personen vor: «Zugewiesen werden: {Liste}.»
* Der Server prüft den Entwurf beim Öffnen noch einmal (Trockenlauf). Ist er ungültig: `AppCallout variant="danger"` mit den Meldungen, Taste «Anlegen» gesperrt, Hinweis «Passe den Entwurf im Assistenten an.» mit Taste dorthin.
* Tasten «Abbrechen», «Projekt anlegen» (brand).
* Danach: Dialog zu, Meldung «Projekt {Name} angelegt.» mit «Öffnen»; die Karte zeigt «Angenommen» (14.4). Kein automatischer Wechsel ins Projekt, damit weitere Vorschläge in der Liste bleiben.

**«In Projekt übernehmen»** (`kind: plan`): gleicher Dialog ohne Projektname, mit Zielprojekt als Text; Vorschau zeigt, was neu dazukommt (Vorschau der Teil-Übernahme wie beim Import). Gesperrt mit Grund, wenn das Recht `planer.import` im Zielprojekt fehlt: «Dir fehlt im Projekt {Projekt} das Recht, Pläne zu übernehmen.»

**«Im Assistenten anpassen»:** wie heute «Mit dem Assistenten planen» (Entwurf in den Assistenten). Was dort angenommen wird, setzt die Idee ebenfalls auf «Angenommen».

### 14.4 Verwerfen und Zustände

**Verwerfen** öffnet **in der Karte** (kein Dialog) ein Feld «Grund (optional)» (`FormTextarea`, 2 Zeilen, max. 500) mit «Verwerfen» (neutral) und «Abbrechen»; Fokus ins Feld. Der Grund geht an den Agenten (über `list_ideas`).

| Zustand | Abzeichen | Was die Karte zusätzlich zeigt | Aktionen der Empfängerin |
|---|---|---|---|
| offen | – (Art-Abzeichen genügt) | – | Annehmen, Anpassen, Verwerfen |
| angenommen | «Angenommen» (`success`) | «Projekt: {Link}» bzw. «Übernommen in {Link}», wer und wann | keine |
| verworfen | «Verworfen» (`neutral`) | «Grund: …» (wenn gegeben), wer und wann | keine (ein Agent kann neu vorschlagen) |
| Entwurf ungültig | «Prüfen» (`warning`) | `AppCallout warning` mit den Meldungen des Servers | Anpassen, Verwerfen; Annehmen gesperrt mit Grund |

Angenommene und verworfene Vorschläge stehen unter «Für mich» nicht mehr, sondern unter «Angenommen» bzw. «Verworfen».

### 14.5 Was der Agent sieht

Nur über MCP (`list_ideas`): Status, Grund, Projekt-ID nach der Annahme. Keine Oberfläche nötig. Die Grenze «höchstens 10 offene Vorschläge» meldet der Server dem Agenten; in der Oberfläche erscheint sie nicht.

---

## 15. Befehlspalette

Auf `/admin/agents` (`useCommands`, Gruppe `action`):

| id | Text | Wirkung |
|---|---|---|
| `agents-create` | Agent anlegen | Dialog 11 |
| `agents-template-create` | Vorlage anlegen | `templates/new` |
| `agents-rules` | Gemeinsame Regeln bearbeiten | Reiter `rules` |
| `agents-launch` | Startbefehl einrichten | Reiter `launch` |

Auf der Seite eines Agenten: `agent-copy-launch` «Startbefehl kopieren», `agent-create-token` «Zugang erzeugen» (springt zu `#zugaenge`). Auf `/ideas`: `ideas-for-me` «Vorschläge für mich zeigen».

`users-create-agent` entfällt (6.7).

---

## 16. Zen-Ansicht

* Liste: Rolle, Modell, Projekte und «zuletzt aktiv» ausgeblendet (`data-zen="hide"`); Name und Zugangsstand bleiben. Reiter bleiben.
* Seite eines Agenten: Abschnitt «Deaktivieren» ausgeblendet.
* Ideen: Dashboard-Hinweis und Zahl am Menü wie die übrigen Hinweise (ausgeblendet); Karten unverändert.
* Alle Aktionen bleiben über die Befehlspalette erreichbar (15).

---

## 17. Schmal (unter 40em), Tastatur, Fokus, dunkles Thema

**Schmal (390 px):**
* Zeile eines Agenten in drei Zeilen: (1) Avatar, Name, Menü; (2) Rolle · Modell; (3) Kennung mit Kopieren. Projekte und «zuletzt aktiv» ausgeblendet; Zugangsstand als Taste **nur mit Symbol** (`key`, Farbe nach Zustand), `aria-label` und `wa-tooltip` mit dem Text (behebt F1).
* Reiter scrollen waagrecht (Verhalten von `wa-tab-group`).
* Vorlagenkarten einspaltig.
* Befehle im Reiter «Startbefehl»: Feld bricht nicht um, scrollt waagrecht; Kopieren-Taste rechts bleibt sichtbar.
* Dialoge wie `AppDialog` heute; die Projektliste im Anlegen-Dialog höchstens 40vh.
* Trefferflächen mindestens 44 × 44 px.

**Tastatur und Fokus:**
* Reiter: Pfeiltasten wechseln (Muster von `wa-tab-group`), Tabulatortaste geht in den Inhalt.
* Zeile: Name (Link) → Kopieren → Zugangsstand → Menü. Eine Fokusstelle je Bedienelement, kein Roving.
* Dialog «Agent anlegen»: Fokus immer auf «Name» (die Vorlage ist vorbelegt). Eingabetaste im Namen sendet das Formular.
* Rückfragen in der Seite (7.5, 14.4): Fokus auf «Abbrechen» bzw. ins Grund-Feld; nach Abbrechen zurück auf die auslösende Taste.
* Nach dem Kopieren bleibt der Fokus auf der Taste; Meldung über die bestehende Toast-Region (`aria-live`).

**Dunkles Thema:** nur Tokens (`--wa-color-*`), keine eigenen Farben. Befehlsfelder mit `--wa-color-surface-lowered`; Abzeichen über ihre Varianten. In beiden Themen prüfen: deaktivierte Zeile (Kontrast des leisen Textes mindestens 4.5 : 1 für Text, sonst nur das Abzeichen leise machen).

---

## 18. Texte (de / en)

Neuer Bereich `agents` in `i18n/locales/{de,en}.json`. Bestehende Schlüssel unter `admin.users.access.*` und `admin.users.newAgent.*` wandern mit (umbenennen, nicht doppeln; `grep` vor dem Löschen).

| Schlüssel | Deutsch | Englisch |
|---|---|---|
| `nav.agents` | Agenten | Agents |
| `agents.title` | Agenten | Agents |
| `agents.intro` | Agenten arbeiten über den Connector in deinen Projekten. Hier legst du sie an, gibst ihnen eine Persona und richtest den Startbefehl ein. | Agents work in your projects through the connector. Create them here, give them a persona and set up the launch command. |
| `agents.tabs.agents` | Agenten ({count}) | Agents ({count}) |
| `agents.tabs.templates` | Vorlagen | Templates |
| `agents.tabs.rules` | Gemeinsame Regeln | Shared rules |
| `agents.tabs.launch` | Startbefehl | Launch command |
| `agents.create` | Agent anlegen | Create agent |
| `agents.search` | Agenten suchen | Search agents |
| `agents.deactivatedSection` | Deaktiviert ({count}) | Deactivated ({count}) |
| `agents.noProfile` | Ohne Persona | No persona |
| `agents.noProjects` | Noch in keinem Projekt | Not in any project yet |
| `agents.moreProjects` | +{count} | +{count} |
| `agents.lastActive` | zuletzt aktiv {time} | last active {time} |
| `agents.neverActive` | noch nie aktiv | never active |
| `agents.copyLaunch` | Startbefehl kopieren | Copy launch command |
| `agents.launchCopied` | Befehl kopiert. | Command copied. |
| `agents.loadError` | Die Agenten konnten nicht geladen werden. | The agents could not be loaded. |
| `agents.empty.title` | Noch keine Agenten | No agents yet |
| `agents.empty.description` | Ein Agent arbeitet über den Connector in deinen Projekten, zum Beispiel als Coder oder für die Planung. Wähle eine Vorlage, um den ersten anzulegen. | An agent works in your projects through the connector, for example as a coder or for planning. Choose a template to create the first one. |
| `agents.empty.blank` | Leer anlegen | Create blank |
| `agents.hint.dashboard` | Mit Agenten arbeiten: Lege einen Agenten an und starte ihn mit dem Startbefehl auf deinem Rechner. | Work with agents: create an agent and start it with the launch command on your computer. |
| `agents.hint.open` | Agenten ansehen | View agents |
| `agents.hint.dismiss` | Ausblenden | Dismiss |
| `agents.usersPage.link` | {count} Agenten verwaltest du unter {link}. | You manage {count} agents under {link}. |
| `agents.usersPage.noRight` | {count} Agenten. Sie verwalten Personen mit dem Recht «Agenten verwalten». | {count} agents, managed by people with the right “Manage agents”. |
| `agents.members.createNew` | Neuen Agenten anlegen | Create new agent |
| `agents.origin.template` | Aus Vorlage {template} | From template {template} |
| `agents.origin.none` | Ohne Vorlage | No template |
| `agents.origin.tooltip` | Kopie der Vorlage vom {date}. Änderungen an der Vorlage wirken nicht auf diesen Agenten. | Copied from the template on {date}. Changes to the template do not affect this agent. |
| `agents.persona.title` | Persona | Persona |
| `agents.persona.name` | Name | Name |
| `agents.persona.nameHint` | Ändert die Kennung nicht. | Does not change the identifier. |
| `agents.persona.role` | Rolle | Role |
| `agents.persona.roleHint` | Kurz, z. B. Coder. Steht in der Liste und im Namen der Sitzung. | Short, e.g. Coder. Shown in the list and in the session name. |
| `agents.persona.model` | Modell | Model |
| `agents.persona.modelHint` | Beim Start mit --model änderbar. | Can be changed at launch with --model. |
| `agents.persona.tool` | Werkzeug | Tool |
| `agents.persona.toolHint` | Weitere Werkzeuge folgen. | More tools will follow. |
| `agents.persona.instructions` | Anweisungen | Instructions |
| `agents.persona.instructionsHint` | Wird beim Start nach den Gemeinsamen Regeln mitgegeben. | Given at launch after the shared rules. |
| `agents.persona.startPrompt` | Startsatz | Opening line |
| `agents.persona.startPromptHint` | Der erste Satz, den der Agent beim Start erhält. Leer: Vorgabe der Gemeinsamen Regeln. | The first line the agent receives at launch. Empty: the default from the shared rules. |
| `agents.persona.missing` | Dieser Agent hat noch keine Persona. Übernimm eine Vorlage oder schreibe die Anweisungen selbst. | This agent has no persona yet. Apply a template or write the instructions yourself. |
| `agents.persona.apply` | Übernehmen | Apply |
| `agents.persona.saved` | Persona gespeichert. | Persona saved. |
| `agents.persona.discard` | Änderungen verwerfen? | Discard changes? |
| `agents.access.title` | Zugänge | Access |
| `agents.access.intro` | Normalerweise richtet der Startbefehl den Zugang ein und legt ihn im Schlüsselbund ab (Reiter Startbefehl). Einen Zugang hier erzeugst du nur für einen Connector ohne Startbefehl, zum Beispiel in Claude Desktop. | Usually the launch command sets up the access and stores it in the keychain (Launch command tab). Create an access here only for a connector without the launch command, for example in Claude Desktop. |
| `agents.deactivate.title` | Agent deaktivieren | Deactivate agent |
| `agents.deactivate.text` | Widerruft alle Zugänge und sperrt das Konto. Der Agent bleibt Mitglied seiner Projekte; Tasks und Kommentare bleiben bei ihm. Du kannst ihn später reaktivieren. | Revokes all access and locks the account. The agent stays a member of its projects; tasks and comments stay with it. You can reactivate it later. |
| `agents.deactivate.start` | Deaktivieren … | Deactivate … |
| `agents.deactivate.tokens` | {count} Zugang wird widerrufen. \| {count} Zugänge werden widerrufen. | {count} access will be revoked. \| {count} accesses will be revoked. |
| `agents.deactivate.projects` | Er bleibt in {count} Projekt. \| Er bleibt in {count} Projekten. | It stays in {count} project. \| It stays in {count} projects. |
| `agents.deactivate.confirm` | Deaktivieren | Deactivate |
| `agents.deactivate.done` | {name} ist deaktiviert. | {name} is deactivated. |
| `agents.deactivated` | Deaktiviert | Deactivated |
| `agents.reactivate` | Reaktivieren | Reactivate |
| `agents.reactivateHint` | Zugänge kommen nicht zurück. Richte nach dem Reaktivieren einen neuen ein. | Access does not come back. Set up a new one after reactivating. |
| `agents.templates.builtin` | Mitgeliefert | Built-in |
| `agents.templates.custom` | Eigene | Custom |
| `agents.templates.changed` | Geändert | Changed |
| `agents.templates.usage` | {count} Agent \| {count} Agenten | {count} agent \| {count} agents |
| `agents.templates.use` | Anlegen | Create |
| `agents.templates.new` | Eigene Vorlage | Custom template |
| `agents.templates.edit` | Bearbeiten | Edit |
| `agents.templates.duplicate` | Duplizieren | Duplicate |
| `agents.templates.copyName` | {name} (Kopie) | {name} (copy) |
| `agents.templates.reset` | Auf Auslieferung zurücksetzen | Reset to default |
| `agents.templates.resetConfirm` | Text, Rolle und Modell der Auslieferung übernehmen? Deine Änderungen gehen verloren. | Restore the default text, role and model? Your changes will be lost. |
| `agents.templates.delete` | Löschen | Delete |
| `agents.templates.description` | Beschreibung | Description |
| `agents.templates.effectHint` | Änderungen wirken nur auf Agenten, die du danach anlegst. | Changes only affect agents you create afterwards. |
| `agents.rules.intro` | Diese Regeln erhält jeder Agent beim Start, vor seiner Persona. Schreibe hier, was für alle gilt: Zusammenarbeit über den Planer, Umgang mit Git, keine Geheimnisse. | Every agent receives these rules at launch, before its persona. Write what applies to all: working through the planner, handling Git, no secrets. |
| `agents.rules.startPrompt` | Startsatz (Vorgabe) | Opening line (default) |
| `agents.rules.startPromptHint` | {name} wird durch den Namen des Agenten ersetzt. | {name} is replaced by the agent's name. |
| `agents.rules.lastChanged` | Zuletzt geändert am {date} von {name} | Last changed on {date} by {name} |
| `agents.launch.install` | Installieren | Install |
| `agents.launch.installHint` | Einmal je Rechner. Braucht Node 20 oder neuer. | Once per computer. Needs Node 20 or later. |
| `agents.launch.login` | Anmelden | Sign in |
| `agents.launch.loginHint` | Mit deinem persönlichen Zugang (Profil → Zugang für Claude). Er landet im Schlüsselbund. | With your personal access (Profile → Access for Claude). It is stored in the keychain. |
| `agents.launch.setup` | Agent einrichten | Set up agent |
| `agents.launch.setupHint` | Erzeugt einen Zugang für den Agenten, legt ihn im Schlüsselbund ab und fragt nach dem Arbeitsordner. | Creates an access for the agent, stores it in the keychain and asks for the working folder. |
| `agents.launch.start` | Starten | Start |
| `agents.launch.startHint` | Ohne Namen erscheint ein Menü: Agent, Ordner, Modell. | Without a name a menu appears: agent, folder, model. |
| `agents.launch.alias` | Optional: Kurzbefehl | Optional: shortcut |
| `agents.launch.aliasHint` | Danach genügt agent. | Afterwards agent is enough. |
| `agents.launch.copy` | Befehl kopieren | Copy command |
| `agents.launch.platforms` | Läuft auf macOS (Schlüsselbund) und Linux (Secret Service). Windows folgt. | Runs on macOS (Keychain) and Linux (Secret Service). Windows will follow. |
| `agents.launch.manual` | Handbuch: Agenten einrichten | Manual: setting up agents |
| `agents.launch.noAgent` | Lege zuerst einen Agenten an. | Create an agent first. |
| `agents.new.title` | Agent anlegen | Create agent |
| `agents.new.template` | Vorlage | Template |
| `agents.new.noTemplate` | Ohne Vorlage | No template |
| `agents.new.identifierPreview` | Kennung: {identifier} | Identifier: {identifier} |
| `agents.new.sameName` | Es gibt schon einen Agenten «{name}». Der neue bekommt die Kennung {identifier}. | There is already an agent “{name}”. The new one gets the identifier {identifier}. |
| `agents.new.projects` | Projekte | Projects |
| `agents.new.filterProjects` | Projekte filtern | Filter projects |
| `agents.new.createToken` | Zugang gleich hier erzeugen | Create access here now |
| `agents.new.createTokenHint` | Normalerweise richtet der Startbefehl den Zugang ein. | Usually the launch command sets up the access. |
| `agents.new.submit` | Anlegen | Create |
| `agents.new.created` | Agent {name} angelegt. | Agent {name} created. |
| `agents.new.showPersona` | Persona ansehen | View persona |
| `agents.new.backToProject` | Zurück zu {project} | Back to {project} |

**Ideen** (unter `ideas`):

| Schlüssel | Deutsch | Englisch |
|---|---|---|
| `ideas.status.FOR_ME` | Für mich ({count}) | For me ({count}) |
| `ideas.navBadge` | Ideen, {count} Vorschlag für dich \| Ideen, {count} Vorschläge für dich | Ideas, {count} proposal for you \| Ideas, {count} proposals for you |
| `ideas.dashboard` | {count} Vorschlag von Agenten wartet auf dich. \| {count} Vorschläge von Agenten warten auf dich. | {count} proposal from agents is waiting for you. \| {count} proposals from agents are waiting for you. |
| `ideas.dashboardOpen` | Ansehen | View |
| `ideas.kind.project` | Neues Projekt | New project |
| `ideas.kind.plan` | Ergänzung zu {project} | Addition to {project} |
| `ideas.proposedBy` | Vorschlag von {name} | Proposed by {name} |
| `ideas.forMe` | für dich | for you |
| `ideas.forUser` | für {name} | for {name} |
| `ideas.draftPrefix` | Entwurf: {list} | Draft: {list} |
| `ideas.adopt` | Als Projekt anlegen | Create as project |
| `ideas.adoptPlan` | In Projekt übernehmen | Add to project |
| `ideas.adapt` | Im Assistenten anpassen | Adjust in assistant |
| `ideas.adoptTitle` | Vorschlag als Projekt anlegen | Create proposal as project |
| `ideas.adoptPlanTitle` | Vorschlag in {project} übernehmen | Add proposal to {project} |
| `ideas.projectName` | Projektname | Project name |
| `ideas.adoptRoles` | Du wirst Owner. {agent} wird Mitglied mit der Rolle Agent. | You become owner. {agent} becomes a member with the role Agent. |
| `ideas.adoptAssignees` | Zugewiesen werden: {list}. | Assigned: {list}. |
| `ideas.adoptSubmit` | Projekt anlegen | Create project |
| `ideas.adoptPlanSubmit` | Übernehmen | Add |
| `ideas.adopted` | Projekt {name} angelegt. | Project {name} created. |
| `ideas.adoptedOpen` | Öffnen | Open |
| `ideas.adoptedProject` | Projekt: {project} | Project: {project} |
| `ideas.adoptedInto` | Übernommen in {project} | Added to {project} |
| `ideas.byAt` | {name}, {date} | {name}, {date} |
| `ideas.noImportRight` | Dir fehlt im Projekt {project} das Recht, Pläne zu übernehmen. | You lack the right to import plans in project {project}. |
| `ideas.invalid` | Prüfen | Check |
| `ideas.invalidHint` | Der Entwurf passt nicht mehr. Passe ihn im Assistenten an. | The draft no longer fits. Adjust it in the assistant. |
| `ideas.discardReason` | Grund (optional) | Reason (optional) |
| `ideas.discardReasonShown` | Grund: {reason} | Reason: {reason} |

---

## 19. Für die Bau-Aufträge (Hinweise an Stuard)

* **Backend (76):** zusätzlich zum Konzept: `agents` liefert `isActive`, `hasProfile`, `template { id name }`, `templateCopiedAt`, `lastActiveAt`; `agentTemplates` liefert `isModified` (mitgeliefert, Text weicht von der Auslieferung ab) und `agentCount`; `deactivateAgent`/`reactivateAgent`; `agentSharedRules` mit `updatedAt`, `updatedBy`. Instructions als RichText, `agentLaunchConfig` als Markdown (A2). Ideen: `viewerCanAdopt`, `addressedTo`, `kind`, `discardReason`, `adoptedProject`, `isDraftValid` + Meldungen, Zahl `openProposalsForMe` am Viewer (Menü, Dashboard).
* **Seite (78):** Komponenten `AgentList`, `AgentRow`, `AgentPersonaForm` (Agent und Vorlage), `AgentTemplateCard`, `AgentCreateDialog`, `LaunchSteps`, `IdeaProposalCard`, `IdeaAdoptDialog`. `AgentAccessDialog` wird in zwei Abschnittskomponenten zerlegt (`AgentAccessSection`, `AgentProjectsSection`); Verhalten und Tests (`e2e/agent-access.spec.ts`) ziehen mit.
* **CLI (79):** `setup` statt `einrichten`, Agent per Kennungs-Teil (A5); `--alias` für den Kurzbefehl.
* **Handbuch (80):** Kapitel-Kennung `agenten` für den Link aus 10.
* **Tests:** Vitest für die Zustände der Liste (leer, ohne Persona, deaktiviert, Suche ab 6), Vorlagen (Abzeichen, Zurücksetzen nur bei geändert), Anlegen (gleicher Name, Vorwahl Projekt), Ideen (Filter «Für mich» vorgewählt, Zahl, vier Zustände); Playwright: Anlegen bis Startbefehl kopieren, Deaktivieren mit Rückfrage, Annehmen eines Vorschlags, 390 px, dunkles Thema; Bildschirmfotos nach R1.

## 20. Nicht Teil

* Texte der Vorlagen und Gemeinsamen Regeln (Clara, 80).
* Protokoll von Änderungen an Agenten (53).
* Weitere Werkzeuge ausser Claude Code (Adapter offen, Auswahl gesperrt).
* F3 (Breadcrumb «Verwaltung») und F1 als Sofort-Fix: eigener kleiner Punkt, wenn Stuard ihn vor 78 will.
