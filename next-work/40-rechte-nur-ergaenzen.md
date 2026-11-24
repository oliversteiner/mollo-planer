# 40 · Standardrechte: nur Fehlendes ergänzen, beim Deploy automatisch

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-375` (Status der Teile dort nachführen).

Status: Teil A umgesetzt und gemergt (PR #40, Branch `fix/deploy-check-log`). Teil B offen (kommt nach Auftrag 42 B bis E). Ursprünglich: Status: offen. Auftrag von Oliver über Stuart (2026-11-23). Nach 38. Branch von `dev` (`feature/rechte-ergaenzen`), PR nach `dev`, nicht mergen.

## A. Sofort, im offenen PR #33 (`feature/agent-rollen-pruefung`)

Abnahme Stuart: PR #33 ist gut (nur lesend, getestet, CI grün). Eine Änderung noch vor dem Merge:
* `deploy/deploy.sh`: Die Ausgabe von `permissions:sync-defaults --check` **nicht** verwerfen (`>/dev/null 2>&1`), sondern ins Deploy-Log schreiben, damit man sieht, *was* fehlt. Und Fehlschlag aus anderem Grund (Exception, keine Datenbank) nicht als «Standardrollen fehlen» melden: z. B. einen eigenen Exit-Code für «fehlt etwas» (2) und alles andere als «Prüfung konnte nicht laufen» ausgeben.
* Test für den neuen Exit-Code anpassen.

## B. Neuer Modus: nur Fehlendes ergänzen

### Problem
`permissions:sync-defaults` (`App\Support\RightsDefaults::apply`) setzt die Standardrollen und -funktionen auf `config/permissions.php` **zurück** (löscht und schreibt `function_rights` und `role_functions` neu). Deshalb läuft es beim Deploy nicht, und eine neue Standardrolle (z. B. «Agent», Auftrag 31) oder ein neues Recht erreicht bestehende Umgebungen nur von Hand.

### Ziel
Ein Modus, der nur **Neues aus der Konfiguration** in die Datenbank bringt und **nichts überschreibt**, was jemand in den Einstellungen geändert hat. `deploy.sh` ruft ihn bei jedem Deployment nach `migrate` auf; die Warnung aus A wird damit zur Ausnahme.

### Regeln
* **Fehlende Funktion oder Rolle:** anlegen, mit ihren Standardrechten bzw. -funktionen aus der Konfiguration.
* **Bestehende Funktion oder Rolle:** Namen und Zuordnungen **nicht** anfassen. Ausnahme: Paare, die **neu in die Konfiguration gekommen** sind (ein neues Recht in einer Standardfunktion, eine neue Funktion in einer Standardrolle) und **noch nie angeboten** wurden, werden ergänzt.
* **Unterscheidung «neu» gegen «vom Admin entfernt»:** eine kleine Tabelle der bereits angebotenen Standard-Paare (z. B. `rights_defaults_applied`: Art, Rolle oder Funktion, Recht oder Funktion, Zeitpunkt). Ein Paar, das schon angeboten wurde und heute fehlt, hat ein Admin bewusst entfernt: nicht wieder hinzufügen. Beim ersten Lauf auf einer bestehenden Datenbank alle heute vorhandenen Standard-Paare als «angeboten» eintragen (ohne etwas zu ändern); `apply()` (der Reset) trägt ebenfalls ein.
* **Nie entfernen:** Rechte, Funktionen oder Rollen, die nicht (mehr) in der Konfiguration stehen, bleiben, wie sie sind; nur melden.
* Alles in einer Transaktion, danach `Rights::clearCache()`.

### Befehl und Deploy
* `php artisan permissions:sync-defaults --add-missing` (Name Bobs Wahl): ergänzt und listet, was ergänzt wurde (eine Zeile je Eintrag), sonst «nichts zu tun». `--check` (aus #33) zeigt zusätzlich, was `--add-missing` tun würde.
* `deploy/deploy.sh`: nach `migrate --force` `--add-missing` ausführen, Ausgabe ins Log. Fehler bricht den Deploy ab (wie `migrate`). Ohne Rückfrage: der Modus ist sicher.
* Ein **Log-Eintrag** je ergänztem Eintrag (welche Rolle/Funktion/welches Recht), keine Personen.
* Doku `docs/deploy-template.md` anpassen: der Reset (`permissions:sync-defaults` ohne Option) bleibt für den Notfall, mit Hinweis, dass er Anpassungen überschreibt.

### Starter-Kit
Rechte gehören zur Basis (`docs/starter-kit.md`): neue Klassen unter `App\Core\…`, bestehende nicht verschieben.

## Tests

* Fehlende Standardrolle wird mit Standardfunktionen angelegt; bestehende bleibt unverändert.
* Admin entfernt ein Standardrecht aus einer Funktion → `--add-missing` fügt es **nicht** wieder hinzu.
* Neues Recht in der Konfiguration → wird einmal ergänzt, danach nicht erneut, auch wenn der Admin es wieder entfernt.
* Erster Lauf auf bestehendem Bestand ändert nichts an vorhandenen Zuordnungen.
* Eigene (nicht konfigurierte) Rollen und Funktionen bleiben unberührt.
* Zweimal hintereinander: zweiter Lauf «nichts zu tun».
* `composer test`, `composer test:mariadb`, `composer analyse`.
