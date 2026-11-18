# next-work

Hier legt die Planung (Stuart) den nächsten Auftrag für die Umsetzung (Bob) ab.

- Je Auftrag eine kurze Datei `NN-<name>.md` mit Ziel, Verweis auf das Dokument in `docs/`, Reihenfolge und Abschluss.
- Bob arbeitet die Datei mit der kleinsten Nummer ab, die nicht als erledigt markiert ist.
- Erledigt: Bob setzt oben `Status: erledigt (<Commit>)` und nennt den Stand in `docs/umsetzung.md`. Abgenommene Aufträge liegen in `erledigt/` (Verschieben mit `git mv`, nicht löschen).
- Maßgeblich ist immer das verlinkte Dokument in `docs/`; diese Dateien fassen nur zusammen.
