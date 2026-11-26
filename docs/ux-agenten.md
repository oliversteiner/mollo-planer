# UX-Entwurf: Seite «Agenten» und Vorschläge von Agenten (Ideen)

Auftrag 77 · ToDo `t-552` (Tasks `k-2106` Bestand, `k-2107` Entwurf, `k-2108` Skizzen und Fragen, `k-2110` Ideen) · Milestone 1.9 «Agenten» · Laura, 26.11.2026 · Stand: **Entwurf**, wartet auf die Entscheide in Abschnitt 13. Nachträge aus der Abnahme von Oliver (26.11., `k-2152`, `k-2154`) und der Durchgang durch `~/www/agents/ux-regeln.md` sind eingearbeitet; was dabei entschieden wurde, steht in Abschnitt 13a, der Durchgang in 13b, der Avatar von Agenten in Abschnitt 21.

Grundlage: Konzept `next-work/76-agenten-konzept.md` (Stuard P, Entscheide Oliver in Abschnitt 0, 8 und 10). Wo dieser Entwurf davon abweicht, steht es in Abschnitt 12 mit Grund. Skizzen (nicht im Repo): `next-work/abnahme/77/`.

Alle Regeln aus `~/www/agents/ux-regeln.md` («Olivers UX-Regeln») gelten; die Regelnummern in Klammern, z. B. «UX 7», verweisen darauf. Die Regeln 5 bis 8 in Abschnitt 2 dieses Entwurfs sind dieselben Regeln in der Fassung der Abnahme.

Begriffe in der Oberfläche: **Agent**, **Kennung** (nicht «E-Mail»), **Zugang** (der Token), **Vorlage**, **Persona** (Rolle und Anweisungen eines Agenten), **Gemeinsame Regeln**, **Startbefehl** (nur wo der Befehl selbst gemeint ist; die Seite dazu heisst «Agent einsetzen»), **Administration**, **Admin**, **Taste**. Bedienung: wählen, auswählen, eingeben, einschalten.

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
| `app:create-agent`, `app:user-token` (CLI) | Schritte «Token ablegen», «Connector eintragen» → Reiter «Agent einsetzen» (10) | Schritt «Token als Umgebungsvariable ablegen» (der Schlüsselbund ersetzt ihn) |

---

## 2. Grundsätze

1. **Eine Stelle je Agent.** Alles zu einem Agenten (Persona, Projekte, Zugänge, Deaktivieren) steht auf **seiner Seite** `/admin/agents/:id`, nicht in Dialogen. Dialoge nur für kurze Aufgaben (anlegen, annehmen, Rückfragen), nie über einem anderen (R11).
2. **Gleiche Muster wie die Benutzerseite.** Zeilen, Abzeichen, Kopieren der Kennung, Zugangsstand und `ActionMenu` sehen aus wie heute. Wer die Benutzerseite kennt, kennt die Agentenliste.
3. **Langer Text auf einer Seite, nicht im Dialog.** Persona, Vorlagen und Gemeinsame Regeln werden auf eigenen Seiten mit dem bestehenden Editor bearbeitet.
4. **Kein Token im Browser, wenn es nicht sein muss.** Der Normalweg ist der Startbefehl: Er erzeugt den Zugang und legt ihn im Schlüsselbund ab. Ein Zugang im Browser bleibt möglich (Connector ohne CLI, z. B. Claude Desktop).

**Regeln für die ganze Seite** (Abnahme Oliver 26.11.; sie gelten für alle Bereiche dieses Entwurfs, bei der Umsetzung auch für die übrigen Seiten, die Stuard dafür bestimmt):

5. **«+» nur bei «anlegen» und «hinzufügen».** Tasten wie «Zugang einrichten», «Zuweisen» oder «Speichern» tragen kein «+». Gemeint sind Tasten und Symbole.
6. **Zahlen an Reitern als Pille.** Die Anzahl steht nicht in Klammern im Titel, sondern als `wa-badge pill` (neutral) hinter dem Reitertitel; Zahl 0 zeigt keine Pille. Gilt für jeden Reiter mit Zahl.
7. **Suche erst ab 7 Einträgen.** Darunter steht kein Suchfeld (es würde nichts bringen). Gilt für die Liste der Agenten und für jede Projektliste in diesem Entwurf.
8. **Eine Aktion, ein Ort.** Oben in der Kopfzeile steht, was auf dem gerade gewählten Reiter angelegt wird (4); an einer Karte steht, was mit dieser Karte geschieht, mit dem vollen Satz.

---

## 3. Einstieg und Menü

* Menüeintrag **«Agenten»** im Abschnitt **Administration**, direkt nach «Benutzer», Symbol `robot`. Pfad **`/admin/agents`** (Abweichung A1).
* Sichtbar mit dem Recht `agents.manage` (`utils/route-rights.ts`: `'/admin/agents': 'AGENTS_MANAGE'`); die Middleware schickt ohne Recht wie bei den anderen Seiten der Administration weg.
* Breadcrumbs: «Administration › Agenten», auf der Seite eines Agenten «Administration › Agenten › Bob».
* **Ohne `agents.manage`: nichts.** Kein Menüeintrag, keine Seite. Begründung: Die Seite enthält Konfiguration (Anweisungen, Zugänge), nichts für die tägliche Arbeit. Wer mit Agenten arbeitet, sieht sie dort, wo sie mitarbeiten: in den Mitgliedern eines Projekts, an Tasks und Kommentaren (Abzeichen «Agent»). Ein Lesemodus hätte nur Nutzen, wenn Persona-Texte für Mitglieder wichtig wären; das ist heute nicht der Fall.
* Hinweis für Admins, solange es keinen Agenten gibt: siehe 6.6.

---

## 4. Aufbau der Seite `/admin/agents`

`PageFrame` «Agenten», Beschreibung (eine Zeile): «Agenten arbeiten über den Connector in deinen Projekten. Hier legst du sie an, gibst ihnen eine Persona und setzt sie auf deinem Rechner ein.»

**Aktion in der Kopfzeile: hängt vom Reiter ab** (Regel 8). Eine Taste, immer am gleichen Platz, immer das, was auf diesem Reiter angelegt wird:

| Reiter | Taste in der Kopfzeile |
|---|---|
| Agenten | **«+ Agent anlegen»** (brand, `plus`) |
| Vorlagen | **«+ Vorlage anlegen»** (brand, `plus`) |
| Gemeinsame Regeln, Agent einsetzen | keine |

Warum: «Agent anlegen» auf dem Reiter «Vorlagen» liess offen, ob man eine Vorlage oder einen Agenten anlegt (Abnahme Oliver). Den Agenten aus einer Vorlage legt man an der Karte an (8.1).

Darunter vier Reiter (`wa-tab-group`, wie `pages/admin/roles.vue`), Zustand im Query-Parameter `?tab=` (ohne: `agents`), damit Links und Zurück funktionieren. Zahlen stehen als Pille am Titel (Regel 6):

| Reiter (Titel · Pille) | `tab` | Inhalt |
|---|---|---|
| Agenten · Zahl der **aktiven** Agenten | `agents` | Liste (6) |
| Vorlagen · Zahl der Vorlagen | `templates` | Karten (8) |
| Gemeinsame Regeln | `rules` | Editor (9) |
| Agent einsetzen | `launch` | Anleitung (10) |

Die Kennung `launch` bleibt trotz Umbenennung (technischer Schlüssel, der Befehl heisst weiter Startbefehl).

Warum Reiter und nicht eine lange Seite: Die vier Teile haben verschiedene Aufgaben und Häufigkeiten (Agenten täglich, «Agent einsetzen» einmal je Rechner). Untereinander würde die Anleitung unter einer langen Liste verschwinden. Warum nicht vier Menüeinträge: Es ist ein Thema; die Administration hat schon acht Einträge.

---

## 5. Daten je Agent (für die Liste)

Aus `agents` (Konzept 4): Name, Kennung, Avatar, `roleLabel`, `defaultModel`, `tool`, Projekte (id, Name), Zugänge (`expiresAt`, `lastUsedAt`), `isActive` (deaktiviert ja/nein), `hasProfile`, `template` (id, Name, kann `null` sein), letzter Zugriff (spätestes `lastUsedAt`).

---

## 6. Reiter «Agenten»

### 6.0 Beschreibung des Reiters

Über der Liste steht ein kurzer Beschreibungstext (UX 7): «Deine Agenten, ihre Projekte und der Stand ihres Zugangs. Wähle einen Namen, um Persona, Projekte und Zugänge zu bearbeiten.» (`agents.list.intro`). Er steht auch im leeren Zustand nicht doppelt; dort gilt der Einführungstext (6.5).

### 6.1 Werkzeugleiste

* Suche (`FormInput type="search"`, wie Benutzerseite), filtert Name, Kennung, Rolle und Projektnamen. Erst ab **7 Agenten** sichtbar (Regel 7; gezählt werden aktive und deaktivierte); darunter gibt es kein Suchfeld, auch kein ausgegrautes.
* Keine Sortierwahl. Reihenfolge fest: aktive nach Name, darunter der Abschnitt «Deaktiviert (n)», zugeklappt (Disclosure-Muster wie die Milestones, `docs/ux-projektseite.md` 4.3), nur wenn es deaktivierte gibt.

### 6.2 Zeile eines Agenten

```
[Avatar] Bob  Coder · Opus                               [key Zugang aktiv] [···]
         [ID]  Kundenportal · Website · +1                 zuletzt aktiv vor 2 Std.
```

| Teil | Inhalt | Komponente |
|---|---|---|
| Avatar | wie heute | `UserAvatar` |
| Zeile 1 | **Name** als Link auf `/admin/agents/:id` (erste Fokusstelle der Zeile); Rolle (`roleLabel`) und Modell leise, mit «·» getrennt; ohne Persona statt dessen Abzeichen «Ohne Persona» (`warning`, `appearance="outlined"`) | `NuxtLink`, `span.wa-caption-m`, `wa-badge` |
| Zeile 2 | **Pille «ID»** (siehe unten); Projekte als Text, **alle Namen** (umbrechen, nicht abschneiden, UX 15); ohne Projekt «Noch in keinem Projekt» leise | `wa-badge pill`, bestehend |
| Rechts oben | Zugangsstand wie heute (`agentAccessState`: aktiv, läuft ab, abgelaufen, keiner), als Taste: führt auf `/admin/agents/:id#zugaenge`. Symbol `key` (Font Awesome **Free**, Stil solid; der Name existiert in der freien Ausgabe), Farbe nach Zustand. Ist noch kein Zugang da, heisst die Taste **«Zugang einrichten»**, ohne «+» (Regel 5) | `wa-button size="s"`, `wa-icon` |
| Rechts unten | «zuletzt aktiv {relativ}» oder «noch nie aktiv» | `wa-caption-s` |
| Menü | Bearbeiten · Startbefehl kopieren · Deaktivieren (bzw. Reaktivieren) | `ActionMenu` |

Abzeichen «Agent» entfällt in dieser Liste (alle sind Agenten). Das Abzeichen «Vorlage» steht nicht in der Zeile, nur auf der Seite des Agenten (7.1).

**Pille «ID» (statt der ausgeschriebenen Kennung).** Die Kennung (`bob@agent.invalid`) ist eine Pseudo-Adresse und in der Liste nur Rauschen. Die Zeile zeigt eine kleine neutrale Pille mit dem Text «ID» (`wa-badge pill`, `appearance="outlined"`, als Taste bedienbar):

* **Tooltip** (`wa-tooltip`) und **`aria-label`** nennen die Kennung: «Kennung: bob@agent.invalid». Der sichtbare Text bleibt «ID», das `aria-label` ersetzt ihn für Screenreader, damit die Kennung dort nicht fehlt.
* **Wählen kopiert die Kennung** in die Zwischenablage, Meldung «Kennung kopiert.» (Toast, wie bisher beim Kopieren).
* Die Kennung im Klartext steht nur noch auf der Seite des Agenten (7.1).

**Unten an der Liste** steht nach der letzten aktiven Zeile eine zurückhaltende Taste **«+ Agent anlegen»** (`appearance="plain"`, `size="s"`, Symbol `plus`), linksbündig mit dem Namen der Zeilen. Sie öffnet denselben Dialog wie die Taste in der Kopfzeile (11). Warum: Bei einer langen Liste muss man nicht nach oben scrollen. Sie steht über dem Abschnitt «Deaktiviert (n)», nicht darunter, und fehlt im leeren Zustand (dort gibt es die Karten, 6.5).

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
 Lege den ersten an, von Grund auf oder aus einer Vorlage.  ← Einführungstext

 ┌──────────────────────────────┐
 │ Eigenen Agenten anlegen      │                       ← Karte, Taste «+ Agent anlegen» (ohne Vorlage)
 │ Du gibst Name, Rolle und …   │
 └──────────────────────────────┘

 Oder aus einer Vorlage einen Agenten erstellen        ← Untertitel (h2)
 [Planer]  [Coder]  [UX/UI]  [Doku]                    ← Vorlagenkarten wie in 8
```

Reihenfolge (Abnahme Oliver, UX 8): Einführungstext → Karte **«Eigenen Agenten anlegen»** → Untertitel **«Oder aus einer Vorlage einen Agenten erstellen»** → Vorlagenkarten. Der Link «Leer anlegen» entfällt; die Karte ersetzt ihn.

* `EmptyState` (Symbol **`robot`** aus Font Awesome Free, dekorativ mit `aria-hidden="true"`, **kein Emoji**, UX 2; Titel, Einführungstext) wie bisher.
* **Karte «Eigenen Agenten anlegen»**: dieselbe Karte wie die Vorlagenkarten (`AgentTemplateCard`, Variante «eigen»), aber ohne Abzeichen und ohne Menü. Text: «Du gibst Name, Rolle und Anweisungen selbst an.» Taste **«+ Agent anlegen»** (brand) öffnet den Dialog (11) mit «Ohne Vorlage». Damit ist die Karte die auffälligste Aktion; die Vorlagen darunter sind der zweite Weg.
* Untertitel als `h2`, darunter die Vorlagenkarten (Komponente aus 8). **Aktion an der Karte:** «Agent anlegen» (outlined), Beschreibung **vollständig** wie in 8.1 (nicht abgeschnitten, Karten einer Reihe gleich hoch); die Zahl der Agenten und das Menü fehlen in dieser Variante (kompakt).
* In der Kopfzeile steht weiter «+ Agent anlegen» (4).

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

`PageFrame` mit Titel = Name, davor Avatar (mit Roboter-Marke, Abschnitt 21). **Beschreibung der Seite** unter dem Titel (UX 7): «Hier legst du fest, wie Bob arbeitet: Persona, Projekte und Zugänge.» Keine Aktionstasten in der Kopfzeile (die Taste «Startbefehl kopieren» entfällt, Abnahme Oliver). Darunter zwei Zeilen:

```
Administration › Agenten › Bob
[Avatar] Bob
Kennung: bob@agent.invalid [Kopieren] · Aus Vorlage Coder · [Deaktiviert]       ← leise
Starte Bob mit:  mollo-planer agent bob  [Kopieren]                              ← Befehlszeile
── Persona ──
```

1. **Zeile «Kennung»** (leise): Kennung im Klartext mit Kopieren · «Aus Vorlage {Vorlage}» (oder «Ohne Vorlage») · Abzeichen «Deaktiviert» falls zutreffend.
2. **Befehlszeile** vor dem Abschnitt «Persona»: «Starte {Name} mit:» und der Befehl `mollo-planer agent {slug}` als Code (`--wa-font-family-code`, Feld wie in 10, schreibgeschützt) mit Taste `copy` (`aria-label` «Befehl kopieren»); Meldung «Befehl kopiert.». `{slug}` = Teil der Kennung vor `@` (`bob-2` bei gleichem Namen). Der Befehl umbricht nicht, sondern scrollt waagrecht. Bei einem **deaktivierten** Agenten entfällt die Zeile (er lässt sich nicht starten); statt dessen steht dort leise «Deaktiviert. Reaktiviere ihn, um ihn zu starten.».

Der Befehl bleibt über die Befehlspalette (`agent-copy-launch`, 15) und das Menü der Liste (6.2) erreichbar.

Herkunft aus einer Vorlage: nur dieser Hinweis, **kein** Abgleich. Tooltip am Hinweis: «Kopie der Vorlage vom {Datum}. Änderungen an der Vorlage wirken nicht auf diesen Agenten.» So sieht man die Herkunft und die Unabhängigkeit an einer Stelle.

### 7.2 Abschnitt «Persona»

| Feld | Eingabe | Pflicht | Vorgabe | Hinweis |
|---|---|---|---|---|
| Name | `FormInput`, max. 120 | ja | – | «Ändert die Kennung nicht.» |
| Rolle | `FormInput`, max. 60 | nein | aus der Vorlage (`role_label`) | «Kurz, z. B. Coder. Steht in der Liste und im Namen der Sitzung.» |
| **Standardmodell** (vorher «Modell») | `FormSelect` aus der Liste der Konfiguration (`sonnet`, `opus`, `haiku`, …) | ja | aus der Vorlage | «Mit diesem Modell startet der Agent. Umschalten ist jederzeit möglich, beim Start mit `--model`.» |
| Anweisungen | `RichTextEditor` (wie Dokumente), höchstens 20’000 Zeichen | nein | Text der Vorlage | «Hier stehen nur Anweisungen für diesen Agenten. Was für alle Agenten gilt, steht unter **Gemeinsame Regeln**. Beim Start erhält er zuerst jene, dann diese.» «Gemeinsame Regeln» ist ein **Link** auf `/admin/agents?tab=rules` (springt auf den Reiter; bei ungespeicherten Änderungen gilt die Rückfrage unten). Abweichung A2 |
| Startsatz | `FormTextarea`, 3 Zeilen, max. 500 | nein | leer = Vorgabe der Gemeinsamen Regeln (als Platzhalter sichtbar) | «Der erste Satz, den der Agent beim Start erhält.» |

**Kein Feld «Werkzeug».** Es gibt heute nur Claude Code; ein gesperrtes Feld, das nichts bewirkt, kommt nicht auf die Seite (UX 3). Das Feld erscheint, sobald es ein zweites Werkzeug gibt; bis dahin steht der Wert `claude_code` ohne Anzeige in den Daten.

Das Feld «Standardmodell» und der Hinweis zu den Anweisungen gelten auch im Formular einer Vorlage (8.2); dort heisst der Satz «Hier stehen nur Anweisungen für Agenten aus dieser Vorlage. Was für alle Agenten gilt, steht unter **Gemeinsame Regeln**.»

Taste **«Persona speichern»** (brand) unten am Abschnitt; deaktiviert, solange nichts geändert ist. (Die zweite Taste der Seite heisst «Projekte speichern», 7.3; zwei gleichnamige Tasten gibt es nicht, UX 15.) Ungespeicherte Änderungen: beim Verlassen der Seite die Rückfrage des Browsers (`beforeunload`) und beim Wechsel innerhalb der App `ConfirmModal` «Änderungen verwerfen?» (bestehendes Muster der Dokumente, falls vorhanden; sonst neu).

**Ohne Persona (Bestand):** `AppCallout variant="warning"` über dem Abschnitt: «Dieser Agent hat noch keine Persona. Übernimm eine Vorlage oder schreibe die Anweisungen selbst.» mit Auswahl Vorlage + Taste «Übernehmen» (füllt Rolle, Modell, Anweisungen; speichert erst mit «Speichern»).

### 7.3 Abschnitt «Projekte»

**Liste mit Kontrollkästchen** (Abnahme Oliver) statt Liste mit «Entfernen» und Auswahl «Projekt zuweisen». Dieselbe Komponente (`AgentProjectList`) steht im Dialog «Agent anlegen» (11).

```
Projekte
 [Suchfeld: Projekte filtern]         ← erst ab 7 Projekten (Regel 7, UX 3)
 ☑ Kundenportal
 ☑ Website
 ☐ Archiv
 Aufgeführt sind nur Projekte, in denen du Mitglieder verwalten darfst.
 Mitgliedschaften in anderen Projekten bleiben unverändert.
 Hinzu: Archiv. Entfernt: Website (Tasks bleiben bei Bob).      ← erscheint, sobald etwas geändert ist
 [Projekte speichern]
```

* **Eine Zeile je Projekt:** `wa-checkbox` mit dem Projektnamen als Beschriftung. Angehakt = der Agent ist Mitglied (Rolle fest «Agent», 58). Reihenfolge fest alphabetisch, angehakte springen nicht nach oben. Liste höchstens 16rem hoch, scrollt.
* **Welche Projekte:** nicht archivierte, in denen die Person Mitglieder verwalten darf (wie im heutigen Dialog). Hilfstext darunter: «Aufgeführt sind nur Projekte, in denen du Mitglieder verwalten darfst. Mitgliedschaften in anderen Projekten bleiben unverändert.» (Oliver schrieb «auf die du selbst Zugriff hast»; das stimmt nicht ganz, weil Zugriff allein zum Ändern nicht reicht. Frage in 13a.)
* **Festlegung (Stuard bat darum): erst mit «Projekte speichern», nicht sofort** (entschieden 26.11.). Begründung: (1) Das Entfernen aus einem Projekt ist folgenreich; erst mit «Projekte speichern» steht vorher da, was geschieht. (2) Alle anderen Abschnitte der Seite speichern ebenso; eine Regel für die Seite. (3) Der alte Zugang-Dialog verlor stillschweigend Änderungen (Auftrag 58, H1); ein Kästchen, das sofort wirkt und still scheitern könnte, wäre dasselbe in anderer Form.
* **Rückmeldung vor dem Speichern:** unter der Liste eine Zusammenfassung («Hinzu: …», «Entfernt: …»), `aria-live="polite"`; beim Entfernen der Zusatz «Tasks und Kommentare bleiben bei {Name}.». «Projekte speichern» ist deaktiviert, solange nichts geändert ist. Ungespeicherte Änderungen: dieselbe Rückfrage beim Verlassen wie bei der Persona (7.2).
* **Speichern:** je Änderung ein Aufruf nacheinander (Zuweisen bzw. Entfernen wie heute). Meldung «Projekte gespeichert.». Schlägt eine Änderung fehl, bleibt **genau diese Zeile** in ihrem gewünschten Zustand mit Fehlertext unter dem Projektnamen, die übrigen sind gespeichert; nichts geht still verloren.
* **Viele Projekte:** Filterfeld ab 7 Projekten (nicht erst ab 8 wie bisher); Filtern blendet Zeilen aus, die Zusammenfassung zählt weiter alle Änderungen. Keine Seitenaufteilung.
* **Keine Projekte zur Auswahl:** «Du kannst in keinem Projekt Mitglieder verwalten.»

### 7.4 Abschnitt «Zugänge» (`id="zugaenge"`)

Aus `AgentAccessDialog` übernommen: Liste (Name, zuletzt benutzt, Ablauf, Widerrufen), «Zugang erzeugen» mit Passwort als Feld, einmalige Anzeige im Abschnitt mit «Abgelegt». Neu darüber ein Satz:

> «Normalerweise richtet der Startbefehl den Zugang ein und legt ihn im Schlüsselbund ab (Reiter **Agent einsetzen**). Einen Zugang hier erzeugst du nur für einen Connector ohne Startbefehl, zum Beispiel in Claude Desktop.»

Darunter klein: Adresse des MCP-Servers mit Kopieren und Link «Anleitung für den Connector» (aus dem heutigen Dialog). Die vier Schritte des heutigen Dialogs entfallen (Reiter «Agent einsetzen» ersetzt sie). Die Taste «Zugang erzeugen» heisst so ohne «+» (Regel 5).

### 7.5 Abschnitt «Deaktivieren»

Am Schluss, abgesetzt (`wa-divider`), Überschrift «Agent deaktivieren».

* Text: «Widerruft alle Zugänge und sperrt das Konto. Der Agent bleibt Mitglied seiner Projekte; Tasks und Kommentare bleiben bei ihm. Du kannst ihn später reaktivieren.»
* Taste «Deaktivieren …» (`danger`, `outlined`) öffnet **an derselben Stelle** die Bestätigung (nach `vectorlab-ux-skills:surfaces`: folgenreich, aber umkehrbar → Rückfrage im Abschnitt, kein Dialog; auf der Seite steht kein anderer Dialog im Weg):
  * Aufzählung mit Zahlen: «{n} Zugänge werden widerrufen.», «Er bleibt in {m} Projekten.»
  * `PasswordConfirmField` (wenn die Bestätigung abgelaufen ist)
  * **«Agent deaktivieren»** (`danger`) und «Abbrechen». Fokus beim Öffnen auf «Abbrechen». Die Bestätigung heisst anders als die auslösende Taste «Deaktivieren …», damit auf der Seite nicht zwei gleichnamige Tasten stehen (UX 15).
* Danach: Kopf mit «Deaktiviert», Abschnitt zeigt «Reaktivieren» (`neutral`, ohne Rückfrage) mit dem Satz «Zugänge kommen nicht zurück. Richte nach dem Reaktivieren einen neuen ein.»
* Aus dem Menü der Liste: «Deaktivieren» öffnet `ConfirmModal` mit denselben Sätzen und dem Passwort als Feld (ein Dialog, R11).

---

## 8. Reiter «Vorlagen»

### 8.0 Beschreibung der Seite

Über den Karten steht ein Beschreibungstext (`wa-callout variant="neutral"` ist zu laut; schlichter Absatz, leise Farbe), der erklärt, was eine Vorlage ist:

> «Eine Vorlage ist ein fertiger Entwurf für einen Agenten: Rolle, Standardmodell und Anweisungen. Legst du aus einer Vorlage einen Agenten an, bekommt er eine **Kopie**. Danach sind beide unabhängig: Änderungen an der Vorlage wirken nicht auf bestehende Agenten, und was du am Agenten änderst, ändert die Vorlage nicht. Mitgelieferte Vorlagen kannst du anpassen und auf die Auslieferung zurücksetzen; eigene kannst du anlegen und löschen.»

Der Reiter trägt die Zahl der Vorlagen als Pille (4).

### 8.1 Karten

Raster (`wa-grid`, min. 16rem), je Vorlage eine `wa-card`:

```
┌────────────────────────────────┐
│ Coder                Mitgeliefert│
│ Setzt Aufträge in Code um,      │
│ schreibt Tests und öffnet den   │
│ Pull Request. Hält sich an …    │
│ Opus · 2 Agenten                │
│ [Agent anlegen]                [···]   │
└────────────────────────────────┘
```

* Kopf: Name (`h3`), rechts Abzeichen **«Mitgeliefert»** (neutral) oder **«Eigene»** (brand); bei mitgelieferter mit geändertem Text zusätzlich «Geändert» (warning, outlined).
* **Beschreibung vollständig, nie abgeschnitten** (`description`, höchstens 300 Zeichen; UX 15). Die Karte wird so hoch wie nötig; **Karten einer Reihe sind gleich hoch** (Raster streckt, Taste und Menü stehen unten in der Karte). Darunter Standardmodell, «{n} Agenten» (Zahl der Agenten mit dieser Herkunft; 0: nichts).
* **Aktion an der Karte:** Taste **«Agent anlegen»** (outlined, einzeilig, `white-space: nowrap`; der Bezug zur Karte ist klar, UX 9). `aria-label` mit dem Vorlagennamen: «Agent aus Vorlage {Name} anlegen». Öffnet 11 mit dieser Vorlage. Menü (`···`): Bearbeiten · Duplizieren · Auf Auslieferung zurücksetzen (nur mitgeliefert und geändert) · Löschen (nur eigene).
* **Keine leere Karte «Eigene Vorlage» mehr** am Ende; dafür steht oben in der Kopfzeile **«+ Vorlage anlegen»** (4). Grund (Regel 8): eine Aktion, ein Ort; die Kopfzeile ist bei jeder Anzahl Karten gleich erreichbar. Öffnet die Bearbeitung einer neuen Vorlage (8.2). Entschieden 26.11. Nach UX 4 steht am Ende des Rasters zusätzlich zurückhaltend «+ Vorlage anlegen» (`plain`).
* Reihenfolge: `sort`, mitgelieferte zuerst.

**Beschreibungen der mitgelieferten Vorlagen.** Planer und UX/UI sind Olivers Wortlaut (nur Schreibweise geglättet). Coder und Doku sind mein Vorschlag im selben Ton, **von Oliver freigegeben am 26.11.** Die Texte stehen als Sprachschlüssel (`agents.templates.shipped.<schluessel>.description`, 18) und werden beim Anlegen der Auslieferung in `description` gespeichert, in der Sprache der Installation; die Anweisungen der Vorlagen schreibt weiter Clara (80).

| Vorlage | Deutsch | Englisch |
|---|---|---|
| Planer | Plant mit dir zusammen das Projekt, macht Vorschläge, denkt an Vergessenes und fragt nach, wenn etwas unklar ist. Er führt den Stand, gibt Aufträge an die Agenten, prüft und beurteilt. Dein eigener Projektmanager. | Plans the project with you, makes suggestions, thinks of what you might have forgotten and asks when something is unclear. Keeps track of progress, hands out assignments to the agents, reviews and assesses their work. Your own project manager. |
| Coder | Setzt Aufträge in Code um, schreibt Tests und öffnet den Pull Request. Hält sich an dein Vorgehen und meldet sich, wenn etwas unklar oder schwerer ist als gedacht. | Turns assignments into code, writes tests and opens the pull request. Follows your way of working and speaks up when something is unclear or harder than expected. |
| UX/UI | Entwirft Abläufe, gestaltet Seiten und Oberflächen, schreibt Texte und stellt die Barrierefreiheit sicher. | Designs flows, lays out pages and interfaces, writes the texts and makes sure everything is accessible. |
| Doku | Schreibt und pflegt Handbuch, README und Anleitungen in klarer Sprache. Prüft, ob die Texte zur Oberfläche passen, und hält Begriffe und Schreibweise einheitlich. | Writes and maintains the manual, README and guides in plain language. Checks that the texts match the interface and keeps terms and spelling consistent. |

Die längste Beschreibung (Planer, 215 Zeichen) braucht auf einer Karte von 16rem etwa fünf Zeilen; das ist gewollt, die Karte wächst mit (UX 15). Die Grenze der Beschreibung ist **300 Zeichen** (nicht 160 wie zuerst vorgeschlagen).

### 8.2 Vorlage bearbeiten `/admin/agents/templates/:id` (neu: `/admin/agents/templates/new`)

Gleiche Felder wie 7.2 (Name, Rolle, Standardmodell, Anweisungen), dazu **Beschreibung** (`FormTextarea`, 3 Zeilen, max. 300, Pflicht; steht auf der Karte). Dieselbe Komponente wie die Persona (`AgentPersonaForm`), damit beide gleich aussehen. Taste **«Vorlage speichern»** (statt «Speichern»; UX 15). Unter der Beschreibung der Hinweis «Steht auf der Karte.» (UX 10).

* Hinweis oben: «Änderungen wirken nur auf Agenten, die du danach anlegst.»
* **Zurücksetzen** (mitgeliefert): Taste im Kopf, Rückfrage in der Seite wie 7.5 (Bestätigung «Auslieferung übernehmen»): «Text, Rolle und Modell der Auslieferung übernehmen? Deine Änderungen gehen verloren.»
* **Löschen** (eigene): Rückfrage in der Seite, Bestätigung «Vorlage löschen»; Agenten mit dieser Herkunft behalten ihren Text, ihr Hinweis wird «Aus gelöschter Vorlage {Name}» (Name als Text im Profil merken oder `template_id` auf `null` und Hinweis «Ohne Vorlage»; Bob entscheidet nach Aufwand, beides ist richtig).
* **Duplizieren:** öffnet `templates/new` mit allen Feldern und Name «{Name} (Kopie)».

---

## 9. Reiter «Gemeinsame Regeln»

* Satz oben: «Diese Regeln erhält jeder Agent beim Start, vor seiner Persona. Schreibe hier, was für alle gilt: Zusammenarbeit über den Planer, Umgang mit Git, keine Geheimnisse.»
* `RichTextEditor`, darunter «Zuletzt geändert am {Datum} von {Name}» und **«Regeln speichern»**.
* Darunter Feld **«Startsatz (Vorgabe)»** (`FormTextarea`), Vorgabe aus der Auslieferung, z. B. «{name}, weiter. Sieh im Planer nach deinen Tasks.»; `{name}` als einziger Platzhalter, Hinweis darunter.
* Leer (neue Installation): der mitgelieferte Text (Clara, Auftrag 80) steht schon drin; die Seite kennt keinen leeren Zustand.

---

## 10. Reiter «Agent einsetzen»

Der Reiter hiess «Startbefehl». Er heisst jetzt **«Agent einsetzen»** (Abnahme Oliver): Er zeigt nicht nur einen Befehl, sondern den ganzen Weg vom angelegten Agenten bis zur laufenden Sitzung. Das Wort «Startbefehl» bleibt dort im Text, wo der Befehl selbst gemeint ist (Schritt 4, Zugänge in 7.4, Dashboard-Hinweis 6.6, Befehlspalette «Startbefehl kopieren»).

Umbenannt sind: Reiter, Überschrift der Seite, Befehl der Palette (15), Verweise in 6.5, 7.4, 11, 16 und 19, Sprachschlüssel (18). Technischer Schlüssel `?tab=launch` bleibt.

### 10.1 Beschreibungstext und «Mehr erfahren»

Oben auf dem Reiter steht zuerst ein **kurzer Einführungstext** (zwei Sätze: wozu die Seite da ist, was man danach kann). Darunter ein zugeklapptes **«Mehr erfahren»** (`wa-details`, standardmässig zu; Zustand wird nicht gemerkt) mit der ausführlichen Erklärung. Erst danach die Schritte.

*Einführungstext:* «Hier siehst du, wie du einen Agenten auf deinem Rechner startest. Danach arbeitet er in deinen Projekten, mit seiner Persona und den Gemeinsamen Regeln.»

*«Mehr erfahren», drei kurze Absätze mit fetter Einleitung:*

* **Was auf deinem Rechner passiert.** Ein Agent ist eine Claude-Sitzung auf deinem eigenen Rechner. Das Programm `mollo-planer` holt Persona und Gemeinsame Regeln vom Planer und startet damit Claude Code in dem Arbeitsordner, den du wählst. Alles Weitere, die Arbeit im Ordner und das Gespräch mit dem Agenten, bleibt auf deinem Rechner.
* **Wo die Zugänge liegen.** Beim Einrichten erzeugt der Planer einen eigenen Zugang für den Agenten. Er wird im Schlüsselbund deines Rechners abgelegt, nicht in einer Datei und nicht im Browser. Jeder Agent hat seinen eigenen Zugang; du kannst ihn jederzeit widerrufen (Seite des Agenten, Abschnitt Zugänge) oder den Agenten deaktivieren.
* **Was der Planer nie zu sehen bekommt.** Weder deinen Arbeitsordner noch deinen Code noch das Gespräch mit dem Agenten. Der Planer sieht nur, was der Agent über den Connector tut: Tasks lesen und ändern, Kommentare schreiben, und nur in den Projekten, denen du ihn zugewiesen hast.

### 10.2 Schritte

Nummerierte Schritte (Muster der Schritte im heutigen Zugangsdialog: Nummer im Kreis, Titel, darunter Text und Feld). Jeder Befehl in einem schreibgeschützten Feld mit Schrift `--wa-font-family-code` und Taste mit Symbol `copy` (`aria-label` «Befehl kopieren»).

**Die Titel bleiben.** Neu steht **unter jedem Titel** eine kurze Erklärung in drei Zeilen mit leiser Beschriftung: **Wer** macht das, **Wozu**, **Wirkung**. Die Beschriftungen stehen als feste Wörter links (schmal: über dem Text). Danach das Feld mit dem Befehl.

1. **Installieren**
   * Wer: Du, am eigenen Rechner, einmal je Rechner.
   * Wozu: Das Programm `mollo-planer` verbindet deinen Rechner mit dem Planer. Ohne es kann kein Agent starten.
   * Wirkung: Installiert den Befehl `mollo-planer`. Braucht Node 20 oder neuer. Im Planer ändert sich nichts.
   * `npm i -g {cliUrl}` (`{cliUrl}` = `<Adresse des Planers>/cli/mollo-planer.tgz` aus der Laufzeit-Konfiguration, nie fest)
2. **Anmelden**
   * Wer: Du, am eigenen Rechner, einmal je Rechner und Planer.
   * Wozu: Das Programm muss wissen, in wessen Namen es Agenten einrichten darf. Dafür dient dein persönlicher Zugang (Profil → Zugang für Claude).
   * Wirkung: Legt deinen Zugang im Schlüsselbund ab. Es entsteht keine Datei mit Zugangsdaten.
   * `mollo-planer login {planerUrl}`
3. **Agent einrichten**
   * Wer: Du, am eigenen Rechner, mit dem Recht «Agenten verwalten». Der Agent muss vorher im Planer angelegt sein (Admin).
   * Wozu: Jeder Agent bekommt einen eigenen Zugang. So kann er nur, was seine Projekte erlauben, und du kannst ihn einzeln widerrufen.
   * Wirkung: Erzeugt im Planer einen Zugang für diesen Agenten, legt ihn im Schlüsselbund ab und fragt nach dem Arbeitsordner.
   * Auswahl «Agent» (`FormSelect`, aktive Agenten, Vorgabe: der erste) über dem Feld; Beschriftung der Einträge siehe 10.3. `mollo-planer agent setup {slug}` (Abweichung A5)
4. **Starten**
   * Wer: Du, am eigenen Rechner, jedes Mal, wenn der Agent arbeiten soll.
   * Wozu: Startet die Sitzung, in der der Agent arbeitet.
   * Wirkung: Startet Claude Code im Arbeitsordner mit der Persona, den Gemeinsamen Regeln und dem Startsatz; der Agent sieht im Planer seine Tasks. Ohne Namen erscheint ein Menü: Agent, Ordner, Modell.
   * `mollo-planer agent {slug}` und darunter `mollo-planer agent`
5. **Optional: Kurzbefehl**
   * Wer: Du, am eigenen Rechner, einmal, wenn du magst.
   * Wozu: Kürzerer Befehl.
   * Wirkung: Richtet in deiner Shell den Kurzbefehl `agent` ein, der `mollo-planer agent` aufruft.
   * `mollo-planer agent --alias`

Unter den Schritten: `AppCallout variant="neutral"`: «Läuft auf macOS (Schlüsselbund) und Linux (Secret Service). Windows folgt.» und Link «Handbuch: Agenten einrichten» (`/help/agenten`, Kapitel von Clara, Auftrag 80; bis es da ist, ohne Link).

**Ohne Agent:** Einführungstext, «Mehr erfahren» und Schritte 1 und 2 wie oben; statt 3 und 4 der Satz «Lege zuerst einen Agenten an.» mit Taste «+ Agent anlegen».

Die Seite zeigt **keinen** Token und erzeugt keinen.

### 10.3 Auswahl des Agenten in Schritt 3

Im Auswahlfeld steht **nicht die Kennung**, sondern **Name und Vorlage bzw. Rolle**: «Bob (Coder)».

* Klammer: der Name der Vorlage, aus der der Agent stammt (`template.name`); hat er keine Vorlage, die Rollenbezeichnung des Profils (`roleLabel`); fehlt auch die, nur der Name («Kim»).
* Gibt es zwei Einträge mit gleichem Text (gleiche Namen sind erlaubt), hängt die Kennung ohne Domain an: «Bob (Coder) · bob-2». Der Befehl darunter nennt den Agenten ohnehin über `{slug}`.
* Deaktivierte Agenten stehen nicht in der Auswahl.

## 11. Dialog «Agent anlegen»

Ein Dialog mit **einem Formular**, nicht mit Schritten (Abweichung A3). Öffnet über «Agent anlegen» (Kopfzeile, Befehlspalette), über **«Agent anlegen»** an einer Vorlage (8.1), über die Karte «Eigenen Agenten anlegen» (6.5), die Taste unten an der Liste (6.2), aus der Mitgliederseite (6.8).

```
Agent anlegen
  Vorlage      [Coder ▾]                 ← nur, wenn nicht von einer Vorlagenkarte geöffnet; dort als Text «Vorlage: Coder · Ändern»
               Der Agent bekommt eine Kopie. Spätere Änderungen an der Vorlage wirken nicht auf ihn.
  Name         [Bob            ]
               Du kannst den Namen später ändern.
  Projekte     [Suchfeld: Projekte filtern]  ← erst ab 7 Projekten
               ☑ Kundenportal
               ☐ Website
               ☐ Archiv
               Aufgeführt sind nur Projekte, in denen du Mitglieder verwalten darfst.
               Auch später auf der Seite des Agenten änderbar.
  Standardmodell [Opus ▾]                ← Vorgabe aus der Vorlage
               Mit diesem Modell startet der Agent. Umschalten ist jederzeit möglich, …
  ◻ Zugang gleich hier erzeugen           ← Schalter, aus
    Normalerweise richtet der Startbefehl den Zugang ein.
  [Passwort]                              ← nur wenn nötig (PasswordConfirmField)
                                [Abbrechen] [Anlegen]
```

| Feld | Pflicht | Vorgabe | Regel |
|---|---|---|---|
| Vorlage | nein | die gewählte; aus der Kopfzeile «Ohne Vorlage» | Auswahl: Vorlagen + «Ohne Vorlage»; Hinweis darunter (UX 10) |
| Name | ja | leer, Fokus hier | max. 120; **keine Kennungsvorschau** (die Kennung steht nur auf der Seite des Agenten, UX 1); Hinweis, dass sich der Name ändern lässt (UX 10) |
| Projekte | nein | aus `?project=`, sonst keins | Dieselbe Liste mit Kontrollkästchen wie 7.3 (`AgentProjectList`), nur nicht archivierte Projekte, in denen die Person Mitglieder verwalten darf, mit demselben Hilfstext; Filterfeld ab 7 Projekten (Regel 7); Liste höchstens 12rem hoch, scrollt. Im Dialog wirken die Kästchen erst mit «Anlegen» |
| Standardmodell | ja | aus der Vorlage, sonst erstes der Liste | Hinweis wie 7.2 |
| Zugang gleich hier erzeugen | nein | aus | an: nach dem Anlegen wechselt der Dialog in den Zustand «Token einmal anzeigen» (Muster heute, «Abgelegt» schliesst) |

* **Gleicher Name:** erlaubt (wie heute). Gibt es schon einen Agenten mit diesem Namen, steht unter dem Feld (warning, kein Fehler): «Es gibt schon einen Agenten «Bob». Das ist erlaubt: Jeder Agent bekommt eine eigene Kennung. Mit einem anderen Namen unterscheidest du sie leichter.» Grund: Die Kennung unterscheidet sie, und der Startbefehl spricht Agenten über sie an; sie steht aber nicht im Dialog (UX 1, Abweichung von der ersten Fassung, in 13b).
* Persona (Rolle, Anweisungen) kommt aus der Vorlage und wird **nicht** im Dialog gezeigt; anpassen danach auf der Seite des Agenten (Meldung mit «Persona ansehen»).
* Eine Transaktion (Konzept: `createAgent`); Fehler erscheinen am Feld oder als `AppCallout` oben im Dialog, nichts ist halb angelegt.
* Schliessen durch Wählen ausserhalb des Dialogs gesperrt, solange ein Token sichtbar ist (wie heute).
* Nach dem Anlegen ohne Token: Dialog zu, Liste (6.3).

---

## 12. Abweichungen vom Konzept

| Nr. | Konzept | Vorschlag | Grund |
|---|---|---|---|
| A1 | Pfad `/agents`, eigener Eintrag im Menü | `/admin/agents` im Abschnitt Administration | Alle Seiten, die ein Recht der Installation brauchen, liegen dort (Benutzer, Rollen, Einstellungen); Breadcrumbs, Middleware und `route-rights` folgen dem Präfix. Ein Hauptmenüeintrag nur für Admins wäre der einzige seiner Art. Frage F1 |
| A2 | `instructions` als Markdown | Gespeichert als **RichText** (TipTap-JSON, Regel des Projekts), bearbeitet mit dem bestehenden `RichTextEditor`; `agentLaunchConfig` liefert **Markdown** (bestehender Wandler `DocumentContent`/`RichTextDocument`) | Ein Markdown-Feld mit Vorschau braucht einen neuen Markdown-Renderer im Frontend und ein zweites Bearbeitungsmuster. Der Editor der Dokumente ist da, bekannt und liefert Markdown für die CLI. Gilt auch für Vorlagen und Gemeinsame Regeln. Frage F2 |
| A3 | Schritt-Dialog Name → Projekte → Modell | **ein Formular** mit drei Feldern | Drei kurze Felder brauchen keine Schritte; «Weiter»/«Zurück» kosten Bedienschritte und Zustand. Die Persona (der lange Teil) gehört nicht in den Dialog. Frage F3 |
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

### 13a. Entschieden am 26.11.2026 (Abnahme Oliver, `k-2152`, `k-2154`)

Die Vorschläge aus den Nachträgen sind alle angenommen und im Entwurf als Entscheid eingetragen:

1. Texte **Coder** und **Doku** wie in 8.1 (de/en).
2. Projekte eines Agenten: Liste mit Kontrollkästchen, **«Projekte speichern»** mit Zusammenfassung «Hinzu / Entfernt»; Suche erst ab 7 Projekten, auch im Dialog «Agent anlegen» (7.3, 11).
3. Englischer Name des Reiters: **«Use an agent»** (10).
4. Reiter «Vorlagen»: oben «+ Vorlage anlegen», die gestrichelte Karte «Eigene Vorlage» entfällt (4, 8.1).
5. Hilfstext der Projektliste: «… in denen du Mitglieder verwalten darfst.» (7.3).
6. Leerer Zustand, letzter Satz: «Lege den ersten an, von Grund auf oder aus einer Vorlage.» (6.5).
7. Beschreibung der Vorlagen bis **300 Zeichen** (8.1, 8.2).
8. Dropdown «Bob (Coder)»: Vorlage, sonst Rolle; bei gleichem Text hängt die Kennung an (10.3).
9. Vorlagen-Karten zeigen die Beschreibung **ganz** und sind je Reihe gleich hoch; Taste an der Karte **«Agent anlegen»** (8.1). «Weniger tippen.» heisst **«Kürzerer Befehl.»** (10.2). Leerer Zustand mit Symbol `robot` statt Emoji (6.5). Die Tasten der Seite eines Agenten heissen **«Persona speichern»** und **«Projekte speichern»** (7.2, 7.3). «Wer / Wozu / Wirkung» bleibt (10.2).
10. **Avatar von Agenten:** **Variante C** (Entscheid Oliver 26.11.), Begründung und Stellen in Abschnitt 21. Die Kennungsvorschau im Dialog «Agent anlegen» ist wie in der Kürzung gewollt weg (Entscheid Oliver 26.11.).

### 13b. Durchgang durch Olivers UX-Regeln (`~/www/agents/ux-regeln.md`, 26.11.)

Jede Regel gegen den ganzen Entwurf, auch gegen Teil D (Ideen). Geändert wurde:

| UX | Befund | Änderung |
|---|---|---|
| 1 Kennungen nicht in Listen | Dialog «Agent anlegen» zeigte eine Kennungsvorschau und beim gleichen Namen die Kennung `bob-2@agent.invalid`; Meldung bei ungültigem Entwurf nannte `lea@agent.invalid` | Vorschau entfällt, Warnung ohne Kennung (11); Meldungen nennen Namen (14.4). **Bewusste Abweichung von der ersten Fassung, von Oliver bestätigt am 26.11.** («Kennungsvorschau so lassen», also weglassen) |
| 1 | Projekte in der Zeile «bis zu zwei Namen, dann +n» | alle Namen, umbrechen (6.2); siehe UX 15 |
| 2 Keine Emojis | Skizze: 🤖, 🪄, ⧉ | durch Font-Awesome-Symbole ersetzt (Skizze); im Entwurf Symbolnamen ohne Emoji |
| 3 Erst anzeigen, wenn es etwas bewirkt | Feld «Werkzeug» gesperrt mit einer einzigen Auswahl | entfällt, bis es ein zweites Werkzeug gibt (7.2) |
| 4 Hauptaktion am Listenende | Raster der Vorlagen und Liste der Ideen ohne Taste am Ende | «+ Vorlage anlegen» (8.1) und «+ Neue Idee» (14.1) am Ende |
| 5 «+» | geprüft: nur bei anlegen/hinzufügen | – (die Zahlzusätze «+n» entfallen mit UX 15) |
| 6 Zahl als Pille | Filter «Für mich (2)» in den Ideen | Pille (14.1); die Filterleiste verhält sich wie Reiter. Wenn Oliver sie als Filter und nicht als Reiter sieht: zurück auf Klammern |
| 7 Beschreibungstext | Reiter «Agenten», Seite eines Agenten, Ideen «Für mich» hatten keinen | Texte ergänzt (6.0, 7.1, 14.1) |
| 8 Leerer Zustand | Ideen «Für mich» hatte keinen | Einführung ergänzt (14.1); einen eigenen Weg gibt es dort nicht |
| 9 Eindeutige Aktionen | «Agent aus dieser Vorlage anlegen» (lang) | «Agent anlegen» an der Karte (8.1) |
| 10 Felder genau + Hinweis | Dialog «Agent anlegen» (Vorlage, Name, Projekte), Beschreibung der Vorlage, Projektname und Grund der Ideen ohne Hinweis | Hinweise ergänzt (11, 8.2, 14.3, 14.4) |
| 11 Kontrollkästchen | Projekte ✓ | – |
| 12 Befehle sichtbar | Menüpunkt «Startbefehl kopieren» in der Liste kopiert, ohne den Befehl zu zeigen | bleibt als Abkürzung; der Befehl steht sichtbar auf der Seite des Agenten und in «Agent einsetzen». Wenn Oliver den Menüpunkt streichen will: einfach |
| 13 Name und Rolle | Auswahl in «Agent einsetzen» ✓; Dropdown hängt bei gleichem Text die Kennung an | von Oliver angenommen (A.8); in Ideen und Mitgliedern stehen Namen |
| 14 Schritte mit Wer/Wozu/Wirkung | ✓ | – |
| 15 Nichts abschneiden, einzeilige Tasten, keine gleichnamigen Tasten | Karten mit drei Zeilen und «…»; zwei «Speichern»; Bestätigungen gleichnamig mit der auslösenden Taste («Deaktivieren», «Verwerfen», «Löschen») | volle Beschreibung, gleiche Höhe; «Persona speichern», «Projekte speichern», «Vorlage speichern», «Regeln speichern»; Bestätigungen «Agent deaktivieren», «Vorschlag verwerfen», «Vorlage löschen»; Tasten einzeilig, Wiederholungen mit Namen im `aria-label` (17) |

**Bewusst so gelassen (Grund):** (1) Oben und unten an der Liste steht je «+ Agent anlegen»; UX 4 verlangt es, die zwei Tasten tun dasselbe. (2) Das Auswahlfeld «Vorlage» im Dialog ist ein Dropdown mit mehr als vier Einträgen und einer Einzelauswahl; UX 11 gilt für Mehrfachauswahl. (3) Auf schmalen Bildschirmen (390 px) verschwinden Projekte und «zuletzt aktiv» aus der Zeile; das ist eine Anpassung an die Breite, die Angaben stehen auf der Seite des Agenten.

---

## 14. Seite «Ideen»: Vorschläge von Agenten (Teil D)

Konzept Abschnitt 10. Agenten legen keine Projekte an; sie schlagen sie als **adressierte Idee** vor, ein Mensch nimmt an.

### 14.1 Finden

* **Beschreibung der Seite** (UX 7), wenn «Für mich» gewählt ist: «Vorschläge von Agenten für dich. Nimm sie an, passe sie an oder verwirf sie.» (`ideas.forMeIntro`).
* **Leerer Zustand von «Für mich»:** «Noch keine Vorschläge. Agenten können dir neue Projekte als Idee vorschlagen; sie erscheinen dann hier.» (`ideas.forMeEmpty`, Symbol `lightbulb`, UX 8: erst die Einführung; einen eigenen Weg gibt es hier nicht).
* **Am Ende der Liste** (UX 4) steht zurückhaltend «+ Neue Idee» (`plain`), wie schon oben in der Kopfzeile.

* **Filter «Für mich»** als erste Taste der Filterleiste, mit Zahl der offenen **als Pille** (UX 6, die Filterleiste verhält sich wie Reiter): «Für mich ⟨2⟩». Nur sichtbar, wenn die Person je eine adressierte Idee erhalten hat. Ist beim Öffnen von `/ideas` mindestens ein Vorschlag offen, ist «Für mich» vorgewählt; sonst wie heute «Alle». Filter im Query-Parameter `?filter=for-me` (auch für Link und Zurück).
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
* Feld **Projektname** (Vorgabe aus dem Entwurf, änderbar, Pflicht); darunter «Du kannst ihn später ändern.» (UX 10).
* **Vorschau:** die bestehende Vorschau des Imports (`components/planer/Tree.vue`) mit Zählung, zugeklappt auf Milestones; höchstens 50vh, scrollt.
* Satz: «Du wirst Owner. {Agent} wird Mitglied mit der Rolle Agent.» Kommen im Entwurf weitere Personen vor: «Zugewiesen werden: {Liste}.»
* Der Server prüft den Entwurf beim Öffnen noch einmal (Trockenlauf). Ist er ungültig: `AppCallout variant="danger"` mit den Meldungen, Taste «Anlegen» gesperrt, Hinweis «Passe den Entwurf im Assistenten an.» mit Taste dorthin.
* Tasten «Abbrechen», «Projekt anlegen» (brand).
* Danach: Dialog zu, Meldung «Projekt {Name} angelegt.» mit «Öffnen»; die Karte zeigt «Angenommen» (14.4). Kein automatischer Wechsel ins Projekt, damit weitere Vorschläge in der Liste bleiben.

**«In Projekt übernehmen»** (`kind: plan`): gleicher Dialog ohne Projektname, mit Zielprojekt als Text; Vorschau zeigt, was neu dazukommt (Vorschau der Teil-Übernahme wie beim Import). Gesperrt mit Grund, wenn das Recht `planer.import` im Zielprojekt fehlt: «Dir fehlt im Projekt {Projekt} das Recht, Pläne zu übernehmen.»

**«Im Assistenten anpassen»:** wie heute «Mit dem Assistenten planen» (Entwurf in den Assistenten). Was dort angenommen wird, setzt die Idee ebenfalls auf «Angenommen».

### 14.4 Verwerfen und Zustände

**Verwerfen** öffnet **in der Karte** (kein Dialog) ein Feld «Grund (optional)» (`FormTextarea`, 2 Zeilen, max. 500) mit **«Vorschlag verwerfen»** (neutral; anders als die auslösende Taste «Verwerfen», UX 15) und «Abbrechen»; Fokus ins Feld. Unter dem Feld: «Geht an den Agenten.» (UX 10). Der Grund geht an den Agenten (über `list_ideas`).

| Zustand | Abzeichen | Was die Karte zusätzlich zeigt | Aktionen der Empfängerin |
|---|---|---|---|
| offen | – (Art-Abzeichen genügt) | – | Annehmen, Anpassen, Verwerfen |
| angenommen | «Angenommen» (`success`) | «Projekt: {Link}» bzw. «Übernommen in {Link}», wer und wann | keine |
| verworfen | «Verworfen» (`neutral`) | «Grund: …» (wenn gegeben), wer und wann | keine (ein Agent kann neu vorschlagen) |
| Entwurf ungültig | «Prüfen» (`warning`) | `AppCallout warning` mit den Meldungen des Servers; Kennungen darin werden, wo möglich, durch Namen ersetzt («Lea» statt `lea@agent.invalid`, UX 1, UX 13) | Anpassen, Verwerfen; Annehmen gesperrt mit Grund |

Angenommene und verworfene Vorschläge stehen unter «Für mich» nicht mehr, sondern unter «Angenommen» bzw. «Verworfen».

### 14.5 Was der Agent sieht

Nur über MCP (`list_ideas`): Status, Grund, Projekt-ID nach der Annahme. Keine Oberfläche nötig. Die Grenze «höchstens 10 offene Vorschläge» meldet der Server dem Agenten; in der Oberfläche erscheint sie nicht.

---

## 15. Befehlspalette

Auf `/admin/agents` (`useCommands`, Gruppe `action`):

| id | Text | Wirkung |
|---|---|---|
| `agents-create` | Agent anlegen | Dialog 11 |
| `agents-template-create` | Vorlage anlegen | `templates/new` (ersetzt die Taste «+ Vorlage anlegen» der Kopfzeile, 4) |
| `agents-rules` | Gemeinsame Regeln bearbeiten | Reiter `rules` |
| `agents-launch` | Agent einsetzen | Reiter `launch` |

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
* Zeile eines Agenten in drei Zeilen: (1) Avatar, Name, Menü; (2) Rolle · Modell; (3) Pille «ID» (Tooltip und Kopieren wie in 6.2). Projekte und «zuletzt aktiv» ausgeblendet; Zugangsstand als Taste **nur mit Symbol** (`key`, Farbe nach Zustand), `aria-label` und `wa-tooltip` mit dem Text (behebt F1).
* Reiter scrollen waagrecht (Verhalten von `wa-tab-group`); die Pille bleibt am Titel.
* Seite des Agenten: Befehlszeile «Starte Bob mit:» auf zwei Zeilen (Text, darunter Befehl mit Kopieren-Taste); Projektliste einspaltig; Wer/Wozu/Wirkung in «Agent einsetzen» untereinander mit der Beschriftung über dem Text.
* Leerer Zustand: Karte «Eigenen Agenten anlegen» und Vorlagenkarten einspaltig, Reihenfolge wie breit.
* Vorlagenkarten einspaltig.
* Befehle im Reiter «Agent einsetzen»: Feld bricht nicht um, scrollt waagrecht; Kopieren-Taste rechts bleibt sichtbar.
* Dialoge wie `AppDialog` heute; die Projektliste im Anlegen-Dialog höchstens 40vh.
* Trefferflächen mindestens 44 × 44 px.

**Tasten (UX 15):** Alle Tasten bleiben einzeilig (`white-space: nowrap`); wo der Platz fehlt, bleibt nur das Symbol mit `aria-label` und Tooltip (Zugangsstand). Tasten, die je Zeile oder Karte wiederkehren («Verwerfen», «Als Projekt anlegen», «Agent anlegen» an Vorlagen), tragen den Namen des Eintrags im `aria-label` («Vorschlag «Archiv-Neubau» verwerfen»); die zwei Tasten «+ Agent anlegen» oben und unten an der Liste lösen dasselbe aus (UX 4).

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
| `agents.avatarLabel` | {name}, Agent | {name}, agent |
| `agents.intro` | Agenten arbeiten über den Connector in deinen Projekten. Hier legst du sie an, gibst ihnen eine Persona und setzt sie auf deinem Rechner ein. | Agents work in your projects through the connector. Create them here, give them a persona and put them to work on your computer. |
| `agents.list.intro` | Deine Agenten, ihre Projekte und der Stand ihres Zugangs. Wähle einen Namen, um Persona, Projekte und Zugänge zu bearbeiten. | Your agents, their projects and the state of their access. Choose a name to edit persona, projects and access. |
| `agents.detail.intro` | Hier legst du fest, wie {name} arbeitet: Persona, Projekte und Zugänge. | Here you decide how {name} works: persona, projects and access. |
| `agents.tabs.agents` | Agenten | Agents |
| `agents.tabs.templates` | Vorlagen | Templates |
| `agents.tabs.rules` | Gemeinsame Regeln | Shared rules |
| `agents.tabs.launch` | Agent einsetzen | Use an agent |
| `agents.tabs.countLabel` | {tab}, {count} (nur `aria-label`; sichtbar ist die Pille, Regel 6) | {tab}, {count} (`aria-label` only; the pill is what you see, rule 6) |
| `agents.create` | Agent anlegen | Create agent |
| `agents.templates.createNew` | Vorlage anlegen | Create template |
| `agents.search` | Agenten suchen | Search agents |
| `agents.deactivatedSection` | Deaktiviert ({count}) | Deactivated ({count}) |
| `agents.noProfile` | Ohne Persona | No persona |
| `agents.noProjects` | Noch in keinem Projekt | Not in any project yet |
| `agents.lastActive` | zuletzt aktiv {time} | last active {time} |
| `agents.neverActive` | noch nie aktiv | never active |
| `agents.id.label` | ID | ID |
| `agents.id.tooltip` | Kennung: {identifier} | Identifier: {identifier} |
| `agents.id.copied` | Kennung kopiert. | Identifier copied. |
| `agents.launchWith` | Starte {name} mit: | Start {name} with: |
| `agents.launchDeactivated` | Deaktiviert. Reaktiviere ihn, um ihn zu starten. | Deactivated. Reactivate it to start it. |
| `agents.copyLaunch` | Startbefehl kopieren | Copy launch command |
| `agents.launchCopied` | Befehl kopiert. | Command copied. |
| `agents.loadError` | Die Agenten konnten nicht geladen werden. | The agents could not be loaded. |
| `agents.empty.title` | Noch keine Agenten | No agents yet |
| `agents.empty.description` | Ein Agent arbeitet über den Connector in deinen Projekten, zum Beispiel als Coder oder für die Planung. Lege den ersten an, von Grund auf oder aus einer Vorlage. | An agent works in your projects through the connector, for example as a coder or for planning. Create the first one from scratch or from a template. |
| `agents.empty.own.title` | Eigenen Agenten anlegen | Create your own agent |
| `agents.empty.own.text` | Du gibst Name, Rolle und Anweisungen selbst an. | You enter name, role and instructions yourself. |
| `agents.empty.fromTemplate` | Oder aus einer Vorlage einen Agenten erstellen | Or create an agent from a template |
| `agents.hint.dashboard` | Mit Agenten arbeiten: Lege einen Agenten an und starte ihn mit dem Startbefehl auf deinem Rechner. | Work with agents: create an agent and start it with the launch command on your computer. |
| `agents.hint.open` | Agenten ansehen | View agents |
| `agents.hint.dismiss` | Ausblenden | Dismiss |
| `agents.usersPage.link` | {count} Agenten verwaltest du unter {link}. | You manage {count} agents under {link}. |
| `agents.usersPage.noRight` | {count} Agenten. Sie verwalten Personen mit dem Recht «Agenten verwalten». | {count} agents, managed by people with the right “Manage agents”. |
| `agents.members.createNew` | Neuen Agenten anlegen | Create new agent |
| `agents.origin.template` | Aus Vorlage {template} | From template {template} |
| `agents.origin.none` | Ohne Vorlage | No template |
| `agents.origin.tooltip` | Kopie der Vorlage vom {date}. Änderungen an der Vorlage wirken nicht auf diesen Agenten. | Copied from the template on {date}. Changes to the template do not affect this agent. |
| `agents.origin.identifier` | Kennung: {identifier} | Identifier: {identifier} |
| `agents.persona.title` | Persona | Persona |
| `agents.persona.name` | Name | Name |
| `agents.persona.nameHint` | Ändert die Kennung nicht. | Does not change the identifier. |
| `agents.persona.role` | Rolle | Role |
| `agents.persona.roleHint` | Kurz, z. B. Coder. Steht in der Liste und im Namen der Sitzung. | Short, e.g. Coder. Shown in the list and in the session name. |
| `agents.persona.model` | Standardmodell | Default model |
| `agents.persona.modelHint` | Mit diesem Modell startet der Agent. Umschalten ist jederzeit möglich, beim Start mit --model. | The agent starts with this model. You can switch at any time, at launch with --model. |
| `agents.persona.instructions` | Anweisungen | Instructions |
| `agents.persona.instructionsHint` | Hier stehen nur Anweisungen für diesen Agenten. Was für alle Agenten gilt, steht unter {link}. Beim Start erhält er zuerst jene, dann diese. (`{link}` = «Gemeinsame Regeln», Link auf `?tab=rules`) | These are instructions for this agent only. What applies to all agents is under {link}. At launch it receives those first, then these. (`{link}` = “Shared rules”, link to `?tab=rules`) |
| `agents.persona.instructionsHintTemplate` | Hier stehen nur Anweisungen für Agenten aus dieser Vorlage. Was für alle Agenten gilt, steht unter {link}. | These are instructions for agents from this template only. What applies to all agents is under {link}. |
| `agents.persona.startPrompt` | Startsatz | Opening line |
| `agents.persona.startPromptHint` | Der erste Satz, den der Agent beim Start erhält. Leer: Vorgabe der Gemeinsamen Regeln. | The first line the agent receives at launch. Empty: the default from the shared rules. |
| `agents.persona.missing` | Dieser Agent hat noch keine Persona. Übernimm eine Vorlage oder schreibe die Anweisungen selbst. | This agent has no persona yet. Apply a template or write the instructions yourself. |
| `agents.persona.apply` | Übernehmen | Apply |
| `agents.persona.saved` | Persona gespeichert. | Persona saved. |
| `agents.persona.save` | Persona speichern | Save persona |
| `agents.templates.save` | Vorlage speichern | Save template |
| `agents.persona.discard` | Änderungen verwerfen? | Discard changes? |
| `agents.projects.title` | Projekte | Projects |
| `agents.projects.hint` | Aufgeführt sind nur Projekte, in denen du Mitglieder verwalten darfst. Mitgliedschaften in anderen Projekten bleiben unverändert. | Only projects in which you can manage members are listed. Memberships in other projects stay unchanged. |
| `agents.projects.filter` | Projekte filtern | Filter projects |
| `agents.projects.empty` | Du kannst in keinem Projekt Mitglieder verwalten. | You cannot manage members in any project. |
| `agents.projects.added` | Hinzu: {list}. | Added: {list}. |
| `agents.projects.removed` | Entfernt: {list}. Tasks und Kommentare bleiben bei {name}. | Removed: {list}. Tasks and comments stay with {name}. |
| `agents.projects.save` | Projekte speichern | Save projects |
| `agents.projects.saved` | Projekte gespeichert. | Projects saved. |
| `agents.projects.rowError` | Nicht gespeichert: {message} | Not saved: {message} |
| `agents.access.title` | Zugänge | Access |
| `agents.access.intro` | Normalerweise richtet der Startbefehl den Zugang ein und legt ihn im Schlüsselbund ab (Reiter Agent einsetzen). Einen Zugang hier erzeugst du nur für einen Connector ohne Startbefehl, zum Beispiel in Claude Desktop. | Usually the launch command sets up the access and stores it in the keychain (Use an agent tab). Create an access here only for a connector without the launch command, for example in Claude Desktop. |
| `agents.deactivate.title` | Agent deaktivieren | Deactivate agent |
| `agents.deactivate.text` | Widerruft alle Zugänge und sperrt das Konto. Der Agent bleibt Mitglied seiner Projekte; Tasks und Kommentare bleiben bei ihm. Du kannst ihn später reaktivieren. | Revokes all access and locks the account. The agent stays a member of its projects; tasks and comments stay with it. You can reactivate it later. |
| `agents.deactivate.start` | Deaktivieren … | Deactivate … |
| `agents.deactivate.tokens` | {count} Zugang wird widerrufen. \| {count} Zugänge werden widerrufen. | {count} access will be revoked. \| {count} accesses will be revoked. |
| `agents.deactivate.projects` | Er bleibt in {count} Projekt. \| Er bleibt in {count} Projekten. | It stays in {count} project. \| It stays in {count} projects. |
| `agents.deactivate.confirm` | Agent deaktivieren | Deactivate agent |
| `agents.deactivate.done` | {name} ist deaktiviert. | {name} is deactivated. |
| `agents.deactivated` | Deaktiviert | Deactivated |
| `agents.reactivate` | Reaktivieren | Reactivate |
| `agents.reactivateHint` | Zugänge kommen nicht zurück. Richte nach dem Reaktivieren einen neuen ein. | Access does not come back. Set up a new one after reactivating. |
| `agents.templates.builtin` | Mitgeliefert | Built-in |
| `agents.templates.custom` | Eigene | Custom |
| `agents.templates.changed` | Geändert | Changed |
| `agents.templates.usage` | {count} Agent \| {count} Agenten | {count} agent \| {count} agents |
| `agents.templates.intro` | Eine Vorlage ist ein fertiger Entwurf für einen Agenten: Rolle, Standardmodell und Anweisungen. Legst du aus einer Vorlage einen Agenten an, bekommt er eine Kopie. Danach sind beide unabhängig: Änderungen an der Vorlage wirken nicht auf bestehende Agenten, und was du am Agenten änderst, ändert die Vorlage nicht. Mitgelieferte Vorlagen kannst du anpassen und auf die Auslieferung zurücksetzen; eigene kannst du anlegen und löschen. | A template is a ready-made draft for an agent: role, default model and instructions. When you create an agent from a template, it gets a copy. After that the two are independent: changes to the template do not affect existing agents, and what you change on the agent does not change the template. You can adjust built-in templates and reset them to the default; you can create and delete your own. |
| `agents.templates.shipped.planner.description` | Plant mit dir zusammen das Projekt, macht Vorschläge, denkt an Vergessenes und fragt nach, wenn etwas unklar ist. Er führt den Stand, gibt Aufträge an die Agenten, prüft und beurteilt. Dein eigener Projektmanager. | Plans the project with you, makes suggestions, thinks of what you might have forgotten and asks when something is unclear. Keeps track of progress, hands out assignments to the agents, reviews and assesses their work. Your own project manager. |
| `agents.templates.shipped.coder.description` | Setzt Aufträge in Code um, schreibt Tests und öffnet den Pull Request. Hält sich an dein Vorgehen und meldet sich, wenn etwas unklar oder schwerer ist als gedacht. | Turns assignments into code, writes tests and opens the pull request. Follows your way of working and speaks up when something is unclear or harder than expected. |
| `agents.templates.shipped.ux.description` | Entwirft Abläufe, gestaltet Seiten und Oberflächen, schreibt Texte und stellt die Barrierefreiheit sicher. | Designs flows, lays out pages and interfaces, writes the texts and makes sure everything is accessible. |
| `agents.templates.shipped.docs.description` | Schreibt und pflegt Handbuch, README und Anleitungen in klarer Sprache. Prüft, ob die Texte zur Oberfläche passen, und hält Begriffe und Schreibweise einheitlich. | Writes and maintains the manual, README and guides in plain language. Checks that the texts match the interface and keeps terms and spelling consistent. |
| `agents.templates.use` | Agent anlegen | Create agent |
| `agents.templates.useLabel` | Agent aus Vorlage {name} anlegen (`aria-label`) | Create agent from template {name} (`aria-label`) |
| `agents.templates.createBottom` | Vorlage anlegen (am Ende des Rasters, `plain`; Text wie `agents.templates.createNew`) | Create template (at the end of the grid, `plain`; text as `agents.templates.createNew`) |
| `agents.templates.edit` | Bearbeiten | Edit |
| `agents.templates.duplicate` | Duplizieren | Duplicate |
| `agents.templates.copyName` | {name} (Kopie) | {name} (copy) |
| `agents.templates.reset` | Auf Auslieferung zurücksetzen | Reset to default |
| `agents.templates.resetConfirm` | Text, Rolle und Modell der Auslieferung übernehmen? Deine Änderungen gehen verloren. | Restore the default text, role and model? Your changes will be lost. |
| `agents.templates.delete` | Löschen | Delete |
| `agents.templates.deleteConfirm` | Vorlage löschen | Delete template |
| `agents.templates.resetNow` | Auslieferung übernehmen | Restore default |
| `agents.templates.description` | Beschreibung | Description |
| `agents.templates.effectHint` | Änderungen wirken nur auf Agenten, die du danach anlegst. | Changes only affect agents you create afterwards. |
| `agents.rules.intro` | Diese Regeln erhält jeder Agent beim Start, vor seiner Persona. Schreibe hier, was für alle gilt: Zusammenarbeit über den Planer, Umgang mit Git, keine Geheimnisse. | Every agent receives these rules at launch, before its persona. Write what applies to all: working through the planner, handling Git, no secrets. |
| `agents.rules.startPrompt` | Startsatz (Vorgabe) | Opening line (default) |
| `agents.rules.startPromptHint` | {name} wird durch den Namen des Agenten ersetzt. | {name} is replaced by the agent's name. |
| `agents.rules.lastChanged` | Zuletzt geändert am {date} von {name} | Last changed on {date} by {name} |
| `agents.rules.save` | Regeln speichern | Save rules |
| `agents.launch.install` | Installieren | Install |
| `agents.launch.login` | Anmelden | Sign in |
| `agents.launch.setup` | Agent einrichten | Set up agent |
| `agents.launch.start` | Starten | Start |
| `agents.launch.alias` | Optional: Kurzbefehl | Optional: shortcut |
| `agents.launch.intro` | Hier siehst du, wie du einen Agenten auf deinem Rechner startest. Danach arbeitet er in deinen Projekten, mit seiner Persona und den Gemeinsamen Regeln. | Here you see how to start an agent on your computer. After that it works in your projects, with its persona and the shared rules. |
| `agents.launch.more` | Mehr erfahren | Learn more |
| `agents.launch.more1` | **Was auf deinem Rechner passiert.** Ein Agent ist eine Claude-Sitzung auf deinem eigenen Rechner. Das Programm mollo-planer holt Persona und Gemeinsame Regeln vom Planer und startet damit Claude Code in dem Arbeitsordner, den du wählst. Alles Weitere, die Arbeit im Ordner und das Gespräch mit dem Agenten, bleibt auf deinem Rechner. | **What happens on your computer.** An agent is a Claude session on your own computer. The mollo-planer program fetches persona and shared rules from the planner and uses them to start Claude Code in the working folder you choose. Everything else, the work in the folder and the conversation with the agent, stays on your computer. |
| `agents.launch.more2` | **Wo die Zugänge liegen.** Beim Einrichten erzeugt der Planer einen eigenen Zugang für den Agenten. Er wird im Schlüsselbund deines Rechners abgelegt, nicht in einer Datei und nicht im Browser. Jeder Agent hat seinen eigenen Zugang; du kannst ihn jederzeit widerrufen (Seite des Agenten, Abschnitt Zugänge) oder den Agenten deaktivieren. | **Where the access is stored.** When you set up an agent, the planner creates a separate access for it. It is stored in your computer's keychain, not in a file and not in the browser. Every agent has its own access; you can revoke it at any time (agent page, Access section) or deactivate the agent. |
| `agents.launch.more3` | **Was der Planer nie zu sehen bekommt.** Weder deinen Arbeitsordner noch deinen Code noch das Gespräch mit dem Agenten. Der Planer sieht nur, was der Agent über den Connector tut: Tasks lesen und ändern, Kommentare schreiben, und nur in den Projekten, denen du ihn zugewiesen hast. | **What the planner never sees.** Neither your working folder nor your code nor the conversation with the agent. The planner only sees what the agent does through the connector: reading and changing tasks, writing comments, and only in the projects you assigned it to. |
| `agents.launch.who` | Wer | Who |
| `agents.launch.why` | Wozu | Why |
| `agents.launch.effect` | Wirkung | Effect |
| `agents.launch.installWho` | Du, am eigenen Rechner, einmal je Rechner. | You, on your own computer, once per computer. |
| `agents.launch.installWhy` | Das Programm mollo-planer verbindet deinen Rechner mit dem Planer. Ohne es kann kein Agent starten. | The mollo-planer program connects your computer to the planner. No agent can start without it. |
| `agents.launch.installEffect` | Installiert den Befehl mollo-planer. Braucht Node 20 oder neuer. Im Planer ändert sich nichts. | Installs the mollo-planer command. Needs Node 20 or later. Nothing changes in the planner. |
| `agents.launch.loginWho` | Du, am eigenen Rechner, einmal je Rechner und Planer. | You, on your own computer, once per computer and planner. |
| `agents.launch.loginWhy` | Das Programm muss wissen, in wessen Namen es Agenten einrichten darf. Dafür dient dein persönlicher Zugang (Profil → Zugang für Claude). | The program has to know on whose behalf it may set up agents. Your personal access (Profile → Access for Claude) does that. |
| `agents.launch.loginEffect` | Legt deinen Zugang im Schlüsselbund ab. Es entsteht keine Datei mit Zugangsdaten. | Stores your access in the keychain. No file with credentials is created. |
| `agents.launch.setupWho` | Du, am eigenen Rechner, mit dem Recht «Agenten verwalten». Der Agent muss vorher im Planer angelegt sein (Admin). | You, on your own computer, with the right “Manage agents”. The agent must be created in the planner first (admin). |
| `agents.launch.setupWhy` | Jeder Agent bekommt einen eigenen Zugang. So kann er nur, was seine Projekte erlauben, und du kannst ihn einzeln widerrufen. | Every agent gets its own access. It can then only do what its projects allow, and you can revoke it individually. |
| `agents.launch.setupEffect` | Erzeugt im Planer einen Zugang für diesen Agenten, legt ihn im Schlüsselbund ab und fragt nach dem Arbeitsordner. | Creates an access for this agent in the planner, stores it in the keychain and asks for the working folder. |
| `agents.launch.startWho` | Du, am eigenen Rechner, jedes Mal, wenn der Agent arbeiten soll. | You, on your own computer, every time the agent should work. |
| `agents.launch.startWhy` | Startet die Sitzung, in der der Agent arbeitet. | Starts the session in which the agent works. |
| `agents.launch.startEffect` | Startet Claude Code im Arbeitsordner mit der Persona, den Gemeinsamen Regeln und dem Startsatz; der Agent sieht im Planer seine Tasks. Ohne Namen erscheint ein Menü: Agent, Ordner, Modell. | Starts Claude Code in the working folder with the persona, the shared rules and the opening line; the agent sees its tasks in the planner. Without a name a menu appears: agent, folder, model. |
| `agents.launch.aliasWho` | Du, am eigenen Rechner, einmal, wenn du magst. | You, on your own computer, once, if you like. |
| `agents.launch.aliasWhy` | Kürzerer Befehl. | A shorter command. |
| `agents.launch.aliasEffect` | Richtet in deiner Shell den Kurzbefehl agent ein, der mollo-planer agent aufruft. | Sets up the shortcut agent in your shell, which calls mollo-planer agent. |
| `agents.launch.pick` | {name} ({role}) | {name} ({role}) |
| `agents.launch.pickDuplicate` | {name} ({role}) · {slug} | {name} ({role}) · {slug} |
| `agents.launch.copy` | Befehl kopieren | Copy command |
| `agents.launch.platforms` | Läuft auf macOS (Schlüsselbund) und Linux (Secret Service). Windows folgt. | Runs on macOS (Keychain) and Linux (Secret Service). Windows will follow. |
| `agents.launch.manual` | Handbuch: Agenten einrichten | Manual: setting up agents |
| `agents.launch.noAgent` | Lege zuerst einen Agenten an. | Create an agent first. |
| `agents.new.title` | Agent anlegen | Create agent |
| `agents.new.template` | Vorlage | Template |
| `agents.new.templateHint` | Der Agent bekommt eine Kopie. Spätere Änderungen an der Vorlage wirken nicht auf ihn. | The agent gets a copy. Later changes to the template do not affect it. |
| `agents.new.nameHint` | Du kannst den Namen später ändern. | You can change the name later. |
| `agents.new.projectsLater` | Auch später auf der Seite des Agenten änderbar. | Can also be changed later on the agent's page. |
| `agents.new.noTemplate` | Ohne Vorlage | No template |
| `agents.new.sameName` | Es gibt schon einen Agenten «{name}». Das ist erlaubt: Jeder Agent bekommt eine eigene Kennung. Mit einem anderen Namen unterscheidest du sie leichter. | There is already an agent “{name}”. That is allowed: every agent gets its own identifier. A different name makes them easier to tell apart. |
| `agents.new.projects` | Projekte (Liste und Hilfstext: `agents.projects.*`) | Projects (list and hint: `agents.projects.*`) |
| `agents.new.createToken` | Zugang gleich hier erzeugen | Create access here now |
| `agents.new.createTokenHint` | Normalerweise richtet der Startbefehl den Zugang ein. | Usually the launch command sets up the access. |
| `agents.new.submit` | Anlegen | Create |
| `agents.new.created` | Agent {name} angelegt. | Agent {name} created. |
| `agents.new.showPersona` | Persona ansehen | View persona |
| `agents.new.backToProject` | Zurück zu {project} | Back to {project} |

**Ideen** (unter `ideas`):

| Schlüssel | Deutsch | Englisch |
|---|---|---|
| `ideas.status.FOR_ME` | Für mich (Zahl als `wa-badge pill` dahinter, UX 6) | For me (count as `wa-badge pill` after it, UX 6) |
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
| `ideas.discardReasonHint` | Geht an den Agenten. | Goes to the agent. |
| `ideas.discardConfirm` | Vorschlag verwerfen | Discard proposal |
| `ideas.projectNameHint` | Du kannst ihn später ändern. | You can change it later. |
| `ideas.forMeIntro` | Vorschläge von Agenten für dich. Nimm sie an, passe sie an oder verwirf sie. | Proposals from agents for you. Accept them, adjust them or discard them. |
| `ideas.forMeEmpty` | Noch keine Vorschläge. Agenten können dir neue Projekte als Idee vorschlagen; sie erscheinen dann hier. | No proposals yet. Agents can suggest new projects to you as an idea; they will appear here. |
| `ideas.new` | Neue Idee (am Ende der Liste, `plain`, mit «+») | New idea (at the end of the list, `plain`, with “+”) |
| `ideas.discardReasonShown` | Grund: {reason} | Reason: {reason} |

---

## 19. Für die Bau-Aufträge (Hinweise an Stuard)

* **Backend (76):** zusätzlich zum Konzept: `agents` liefert `isActive`, `hasProfile`, `template { id name }`, `templateCopiedAt`, `lastActiveAt`; `agentTemplates` liefert `isModified` (mitgeliefert, Text weicht von der Auslieferung ab) und `agentCount`; `deactivateAgent`/`reactivateAgent`; `agentSharedRules` mit `updatedAt`, `updatedBy`. Instructions als RichText, `agentLaunchConfig` als Markdown (A2). Ideen: `viewerCanAdopt`, `addressedTo`, `kind`, `discardReason`, `adoptedProject`, `isDraftValid` + Meldungen, Zahl `openProposalsForMe` am Viewer (Menü, Dashboard).
* **Seite (78):** Komponenten `AgentList`, `AgentRow`, `AgentPersonaForm` (Agent und Vorlage), `AgentTemplateCard`, `AgentCreateDialog`, `LaunchSteps` (mit Einführung, «Mehr erfahren» und Wer/Wozu/Wirkung), `AgentProjectList` (Kontrollkästchen, Zusammenfassung, Speichern; Agentenseite und Dialog), `IdeaProposalCard`, `IdeaAdoptDialog`. `AgentAccessDialog` wird in zwei Abschnittskomponenten zerlegt (`AgentAccessSection`, `AgentProjectsSection` (nutzt `AgentProjectList`)); Verhalten und Tests (`e2e/agent-access.spec.ts`) ziehen mit.
* **CLI (79):** `setup` statt `einrichten`, Agent per Kennungs-Teil (A5); `--alias` für den Kurzbefehl.
* **Avatar (Abschnitt 21):** `UserAvatar` bekommt `isAgent`; mehrere Abfragen liefern es noch nicht (Tabelle 21.3). Variante nach Olivers Wahl.
* **Handbuch (80):** Kapitel-Kennung `agenten` für den Link aus 10. Das Kapitel heisst im Handbuch ebenfalls «Agent einsetzen».
* **Reiter mit Zahl (Regel 6):** `wa-tab-group` bekommt die Zahl als `wa-badge pill` im Titel (Komponente `TabLabel`); weitere Reiter der App mit Zahl in Klammern (`grep "({count})"`) stellt Stuard in einem eigenen kleinen Auftrag um.
* **Tests:** Vitest für die Zustände der Liste (leer mit Karte «Eigenen Agenten anlegen» vor den Vorlagen, ohne Persona, deaktiviert, **Suche erst ab 7**, Pille «ID» mit Kennung im Tooltip und `aria-label`, Kopieren der Kennung, Taste unten an der Liste, Zahl als Pille am Reiter), Vorlagen (Abzeichen, Zurücksetzen nur bei geändert), Anlegen (gleicher Name, Vorwahl Projekt), Ideen (Filter «Für mich» vorgewählt, Zahl, vier Zustände); Playwright: Anlegen bis Startbefehl kopieren, Deaktivieren mit Rückfrage, Annehmen eines Vorschlags, 390 px, dunkles Thema; Bildschirmfotos nach R1.

## 20. Nicht Teil

* Anweisungen der Vorlagen und Text der Gemeinsamen Regeln (Clara, 80). Die **Beschreibungen** der vier mitgelieferten Vorlagen stehen in 8.1.
* Protokoll von Änderungen an Agenten (53).
* Weitere Werkzeuge ausser Claude Code (Adapter offen, Auswahl gesperrt).
* F3 (Breadcrumb «Verwaltung») und F1 als Sofort-Fix: eigener kleiner Punkt, wenn Stuard ihn vor 78 will.

---

## 21. Avatar von Agenten (Wunsch Oliver, `k-2154`)

Wunsch: im Avatar eines Agenten ein **kleines Roboter-Symbol** zusätzlich zu den Anfangsbuchstaben. Bild: `next-work/abnahme/77/f-de-avatar-varianten.png` (hell und dunkel, alle fünf Grössen, je mit einem Menschen daneben); klickbar in der Skizze unter «Avatar». **Entschieden am 26.11.2026: Variante C** (Oliver), siehe 21.1.

**Ausgangslage.** `UserAvatar` (`frontend/app/components/UserAvatar.vue`) nutzt `wa-avatar`, das Bild **oder** Initialen **oder** Symbol zeigt, nicht Initialen und Symbol zusammen. Grössen: `2xs` 1.25 rem (20 px), `xs` 1.5 rem (24 px), `sm` 2 rem (32 px), `md` 2.5 rem (40 px), `3xl` 4 rem (64 px). Symbol und Marke brauchen also eigenen Inhalt im Avatar (Slot oder eigene Marke); Font Awesome Free `robot` (der Name existiert in der freien Ausgabe).

### 21.1 Varianten

| | Beschreibung | Lesbarkeit |
|---|---|---|
| **A** | Symbol und Initiale nebeneinander im Kreis (Olivers Wortlaut) | ab 2.5 rem gut, bei 2 rem knapp (Buchstabe und Symbol je etwa 13 px), **unter 2 rem nicht lesbar** (beides unter 10 px). Mehr als ein Buchstabe hat nirgends Platz; bei Initialen aus zwei Wörtern («Bob Lang») entfällt der zweite Buchstabe. Bild und Initiale konkurrieren im Kreis |
| **B** | Initiale wie heute, dazu eine kleine runde Roboter-Marke unten rechts (Durchmesser 48 % des Avatars, Rand in der Farbe der Fläche) | ab 2 rem gut. Bei 1.5 rem und 1.25 rem ist die Marke etwa 10 bzw. 9 px gross, das Symbol darin nicht mehr zu erkennen; sie verdeckt zudem die Initiale |
| **C** | wie B ab 2 rem; **unter 2 rem nur das Roboter-Symbol** statt der Initiale (Regel für die kleinsten Grössen) | durchgehend lesbar. Unter 2 rem fehlt die Initiale; dort steht der Name immer daneben (Task-Karte, Kommentar), und die Farbe bleibt die der übrigen Avatare |

**Entscheid: C** (Oliver, 26.11.). Begründung: Die Initiale bleibt, wo sie lesbar ist (ab 2 rem), und das Symbol steht in jeder Grösse sichtbar da. A scheitert an den zwei kleinsten Grössen, die in Task-Karten, Kommentaren und Mitgliederlisten am häufigsten vorkommen. B allein wird dort zum Fleck. Die Marke von C ist ein Zusatz zum bestehenden Avatar, nicht ein anderer Avatar; Menschen und Agenten bleiben auf einen Blick verwandt und unterscheidbar.

### 21.2 Farbe, Kontrast, Screenreader

* **Farben** nur Tokens: Kreis wie heute (`wa-avatar`), Marke in der Markenfarbe mit dem Symbol in der Flächenfarbe (`--wa-color-brand-fill-loud` und dessen Textfarbe), Rand der Marke in `--wa-color-surface-default`. Keine eigenen Farben.
* **Kontrast (WCAG 2.2, in der Skizze gerechnet):** Initiale oder Symbol auf dem Kreis: hell 6.9 : 1, dunkel 6.3 : 1 (nötig 4.5 : 1 für Text, 3 : 1 für das Symbol). Symbol auf der Marke: hell 8.2 : 1, dunkel 8.1 : 1. Marke gegen die Fläche dahinter: hell 8.2 : 1, dunkel 7.2 : 1 (nötig 3 : 1 für Bedienelemente und Grafiken). Die Zahlen gelten für die Skizzenfarben; bei der Umsetzung mit den echten Tokens des Themas «Matter» nachrechnen und bei weniger als 4.5 : 1 bzw. 3 : 1 das Token wechseln.
* **Symbol und Marke sind dekorativ** (`aria-hidden="true"`). Der Avatar trägt `label`: **«Bob, Agent»** (de), **«Bob, agent»** (en); Schlüssel `agents.avatarLabel` = «{name}, Agent».
* **Die Pille «Agent»** (`AgentBadge`) neben dem Namen bleibt, wo sie heute steht (z. B. in der Auswahl einer Person). Sie ist Text für alle, der Avatar die schnelle Orientierung. In Task-Karten und Kommentaren gibt es die Pille heute nicht; dort trägt die Marke allein die Information, und das `label` sagt es dem Screenreader.
* **Nicht nur Farbe, nicht nur Form:** Der Unterschied ist ein Symbol, kein Farbton; für Menschen mit eingeschränktem Farbsehen genügt das.

### 21.3 Wo es gelten soll

Überall, wo ein Agent als Person erscheint. `UserAvatar` braucht dafür die Angabe **`isAgent`** (neu: Prop `isAgent?: boolean`, durchgereicht von `UserLine` und `UserAvatarGroup`). Die Daten gibt es im Schema (`User.isAgent`), aber nicht jede Abfrage holt sie. Stand am 26.11. (Hinweis für Bob):

| Stelle | Datei | `isAgent` heute vorhanden? | Aufwand |
|---|---|---|---|
| Liste der Agenten, Seite eines Agenten (neu) | `AgentRow` | ja (`agents`-Abfrage) | gering |
| Mitglieder eines Projekts | `components/project/MembersCard.vue` | ja (`graphql/settings.ts`), wird nicht an den Avatar gegeben | gering |
| Kommentare | `components/task/Comments.vue` | ja (`graphql/task.ts`, Autor), wird nicht an den Avatar gegeben | gering |
| Auswahl einer Person | `components/form/PersonSelect.vue` | ja, zeigt schon `AgentBadge` | gering |
| Statistik je Person | `pages/projects/[id]/statistics.vue` | ja (`graphql/statistics.ts`) | gering |
| Benutzerverwaltung | `pages/admin/users.vue` | ja (`graphql/admin.ts`), Agenten wandern auf die Agentenseite (6.7) | entfällt |
| Task-Karte (Zugewiesene Person) | `components/task/Card.vue` | **nein** (`assignee { id name avatarUrl }` in `graphql/project.ts`, `task.ts`, `profile.ts`) | Abfragen erweitern |
| Zeitachse (Task) | `components/project/TimelineTask.vue` über `UserLine` | **nein** (gleiche `assignee`-Abfrage) | Abfragen erweitern |
| Gruppe der Mitglieder in der Projektübersicht | `components/project/Grid.vue` über `UserAvatarGroup` | **nein** (`graphql/projects.ts`: `user { id name avatarUrl }`) | Abfrage erweitern |
| Ergebnis von `assignTask` | `graphql/task.ts` | **nein** | Abfrage erweitern |
| Vorschläge in «Ideen» (Autor) | `graphql/ideas.ts` (`author { id name }`) | **nein** | Abfrage erweitern |
| Projekt anlegen, Mitgliederauswahl | `components/project/CreateModal.vue` | zu prüfen | – |
| Eigene Person im Menü, Profil, Wartende Konten | `layouts/default.vue`, `profile/AvatarCard.vue`, `admin/PendingUsers.vue` | nicht nötig (Menschen) | – |

Ohne `isAgent` erscheint ein Agent dort weiter als normaler Avatar; das ist kein Fehler, aber eine Lücke, die Bob mit den Abfragen schliessen sollte (eigener Teil im Auftrag 78). Der Test (`UserAvatar`, Vitest): Marke und `label` mit `isAgent`, keine Marke ohne; unter 2 rem nur das Symbol (Variante C).
