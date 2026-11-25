# Tests lokal: schnell und auf MariaDB

Wie die Tests des Backends auf dem eigenen Rechner schnell laufen (Auftrag 57). Gilt **nur für die lokale Testdatenbank** (`molloplaner_test` und ihre Kopien für den parallelen Lauf). **Nie für TEST oder PROD**: Dort bleiben alle Einstellungen der Datenbank so, wie der Webhoster sie vorgibt.

## Befehle

Im Ordner `backend/`:

| Befehl | Was läuft | Wann |
|---|---|---|
| `composer test` | alle Tests auf SQLite im Speicher, parallel (ein Prozess je Kern) | während der Arbeit, vor jedem Push |
| `composer test:mariadb:quick` | nur die Gruppe `mariadb` (Sperren, Migrationen und Schema, MariaDB-eigene Abfragen) auf MariaDB, nacheinander | vor jedem Push |
| `composer test:mariadb` | alle Tests auf MariaDB, parallel | in der CI; lokal bei Änderungen an Abfragen, Sperren oder Migrationen |
| `composer test:serial`, `composer test:mariadb:serial` | wie oben, nacheinander | zur Fehlersuche, wenn ein Test nur parallel scheitert |

Ein neuer Test, der MariaDB wirklich braucht (eine Sperre, eine Migration, eine Abfrage, die SQLite anders beantwortet), bekommt `#[Group('mariadb')]`.

## Paralleler Lauf auf MariaDB: Recht für die Testdatenbanken

Laravel legt beim parallelen Lauf je Prozess eine eigene Datenbank an: `molloplaner_test_test_1`, `molloplaner_test_test_2`, … Der Benutzer aus `backend/.env` braucht dafür das Recht, Datenbanken mit diesem Namen anzulegen und zu löschen. Einmal als Administrator der lokalen MariaDB (Platzhalter `<benutzer>` ersetzen):

```sql
GRANT ALL PRIVILEGES ON `molloplaner\_test%`.* TO '<benutzer>'@'localhost';
FLUSH PRIVILEGES;
```

Das Recht gilt nur für Datenbanken, deren Name mit `molloplaner_test` beginnt. Ohne es scheitert `composer test:mariadb` lokal sofort mit «Access denied … molloplaner_test_test_1»; dann `composer test:mariadb:serial`.

## Einstellungen der lokalen MariaDB (nur für Tests)

Die Tests schreiben viele kleine Transaktionen und spielen das Schema einmal je Lauf ein. Auf einem Entwicklungsrechner muss nichts davon einen Stromausfall überstehen. Diese Einstellungen machen die lokale MariaDB dafür schneller:

| Einstellung | Wert für Tests | Warum |
|---|---|---|
| `innodb_flush_log_at_trx_commit` | `2` | schreibt das Log je Sekunde statt je Transaktion auf die Platte |
| `innodb_doublewrite` | `OFF` | spart einen zweiten Schreibvorgang je Seite |
| `sync_binlog` | `0` | kein Abgleich des Binärlogs |
| Binärlog (`log_bin`) | aus (`--disable-log-bin` / `skip-log-bin`) | keine Replikation nötig |
| `innodb_buffer_pool_size` | `1G` (oder mehr, je nach Speicher) | das ganze Testschema bleibt im Speicher |

Wo sie stehen, hängt von der Installation ab (eine `my.cnf` im Abschnitt `[mysqld]`, bei einer App wie DBngin in deren Einstellungen der Instanz). Danach MariaDB neu starten und prüfen:

```sql
SHOW VARIABLES WHERE Variable_name IN ('innodb_flush_log_at_trx_commit', 'innodb_doublewrite', 'sync_binlog', 'log_bin', 'innodb_buffer_pool_size');
```

**Nur lokal.** Auf einem Server mit echten Daten bedeuten `innodb_flush_log_at_trx_commit=2` und `innodb_doublewrite=OFF`, dass bei einem Absturz die letzte Sekunde oder eine halb geschriebene Seite verloren gehen kann.

## Was die Testbasis selbst tut

- `RefreshDatabase` baut das Schema einmal je Lauf (je Prozess) auf, jeder Test läuft in einer Transaktion, die danach zurückgerollt wird.
- Tests, deren Zeilen wirklich festgeschrieben sein müssen (Volltextindex von InnoDB, IDs ab 1), nutzen `Tests\Concerns\CommittedData`: Es leert vor und nach dem Test alle Tabellen (`TRUNCATE`) und spielt die Zeilen der Migrationen (Rollen, Rechte) zurück, ohne das Schema neu aufzubauen.
- Kein Test führt DDL in der Transaktion eines anderen aus (auf MariaDB schreibt DDL die Transaktion fest, und Laravel baut danach das ganze Schema neu auf).
- Tests, die das Schema lesen, lesen nur die eigene Datenbank: Ein MariaDB-Server listet sonst die Tabellen aller seiner Datenbanken.
