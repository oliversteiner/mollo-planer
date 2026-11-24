# 36 · Symbole: weniger Anfragen, richtig cachen

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-380` (Status der Teile dort nachführen).

Status: umgesetzt auf Branch `feature/symbole-laden-neu` (neu von `origin/dev`, der alte Branch `feature/symbole-laden` bleibt unberührt), PR nach `dev`, nicht gemergt. A: `frontend/public/.htaccess` (mit lokalem Apache geprüft; nach dem Deploy `curl -sI` auf TEST, Oliver). B: Debounce 250 ms ab 2 Zeichen, Symbol erst wenn die Kachel sichtbar war, ein gemeinsamer Tooltip. C und D waren mit Auftrag 38 erledigt (`icons/<version>/…`, `scripts/icon-source.mjs`). Auftrag von Oliver über Stuart (2026-11-23). Branch von `dev` (`refactor/symbole-laden`), PR nach `dev`, nicht mergen.

## Ausgangslage (geprüft von Stuart am 2026-11-23 auf PROD)

Font Awesome kommt **nicht** über ein CDN. Seit `624d8a9b` liefert der Planer die SVGs von Font Awesome Pro «Duotone Light» selbst aus (`/icons/duotone-light/<name>.svg`, `nuxt.config.ts` → `nitro.publicAssets`). Die Startseite von PROD lädt 68 Dateien, keine von einem fremden Host. Die CDN-Adressen (`ka-f`/`ka-p.fontawesome.com`) im Bundle sind der eingebaute Standard von Web Awesome, den `utils/wa-icons.ts` für `default` und `system` ersetzt.

Teuer ist etwas anderes:
1. **Eine Anfrage pro Symbol.** Die Auswahl (`components/icon/Picker.vue`) zeigt bis zu 120 Treffer (`searchIcons`, `limit = 120`). Jede Eingabe im Suchfeld kann bis zu 120 neue SVG-Anfragen auslösen, ohne Verzögerung (kein Debounce). Dazu kommen 120 `wa-tooltip`-Elemente.
2. **Kein Cache.** Auf PROD fehlt der Kopf `Cache-Control` bei `/icons/duotone-light/*.svg` und bei `/icons/catalog.json` (540 KB, 4804 Einträge). `maxAge` in `nitro.publicAssets` wirkt nicht, weil Apache die statischen Dateien ausliefert, nicht Nitro. Jeder Besuch fragt also alles neu an.

## Ziel

Die Symbole werden weiterhin lokal ausgeliefert, aber mit wenigen Anfragen und langem Cache. Messgrösse: Anzahl Anfragen für «Auswahl öffnen, ‹rak› tippen, ‹rocket› wählen», vorher und nachher, beim ersten und beim zweiten Mal. Beide Werte gehören in die Abschlussmeldung.

## A. Cache-Köpfe für statische Dateien

* Im Web-Root des Frontends eine `.htaccess` mitliefern (z. B. `frontend/public/.htaccess`, landet über `generate` im Docroot; mit `deploy/deploy.sh` prüfen, dass sie dort ankommt):
  * `/_nuxt/*`: `public, max-age=31536000, immutable` (Dateinamen tragen den Hash).
  * `/icons/*`: lang, z. B. `public, max-age=2592000` (30 Tage). Wird ein Symbol ersetzt, ändert sich der Pfad (siehe C).
  * `index.html` und die übrigen HTML-Seiten: `no-cache`, damit ein Deploy sofort greift.
  * Kompression für `.json` und `.svg`, falls sie noch nicht aktiv ist.
* Hostpoint: nginx vor Apache. Falls `.htaccess`-Köpfe nicht durchkommen, melden; dann lösen wir es im Panel.
* Nach dem Deploy auf TEST mit `curl -sI` prüfen und die Ausgabe in die Abschlussmeldung.

## B. Auswahlfeld sparsam

* **Debounce** der Suche (rund 250 ms), keine Suche unter 2 Zeichen ausser den Vorschlägen.
* **Nur sichtbare Kacheln laden:** Die Kacheln rendern, aber `<ItemIcon>` erst setzen, wenn die Kachel sichtbar ist (IntersectionObserver oder ein kleines Composable). Beim Scrollen kommen die nächsten nach. Das Limit von 120 Treffern bleibt.
* **Ein gemeinsamer Tooltip** statt 120: Name und Bezeichnung als `aria-label` an der Kachel (schon da), ein Tooltip, der dem fokussierten bzw. überfahrenen Knopf folgt, oder gar keiner, wenn das Muster der Seiten es zulässt. Vorher mit `ui-consistency:adjusting` prüfen, wie die anderen Raster Tooltips machen.
* Der Katalog wird wie heute erst beim Öffnen geladen, danach im Speicher gehalten (schon so).

## C. Versionierte Pfade

* **Erledigt mit Auftrag 38**, falls dort umgesetzt. Sonst: Der Pfad der Symbole und des Katalogs enthält die Version von Font Awesome (z. B. `/icons/7.3.1/duotone-light/…`, `/icons/7.3.1/catalog.json`), aus `package.json` des Pakets gelesen. Ein Update von Font Awesome bekommt so neue Pfade; der lange Cache aus A schadet nie.
* `utils/wa-icons.ts`, `ItemIcon`, `Picker.vue` und die Tests (`tests/nuxt/wa-icons.test.ts`) entsprechend anpassen.

## D. Vorbereitung für das Starter-Kit (klein halten)

Siehe `docs/starter-kit.md`. Kein Umzug bestehender Dateien. Aber: Quelle der SVGs (Paket und Stil) und Pfad an **einer** Stelle als Konfiguration, damit das Kit später Font Awesome Free (`@fortawesome/fontawesome-free`, `svgs/solid` o. ä.) und der Planer Pro nutzen kann. Neue Dateien (z. B. das Composable für sichtbare Kacheln) gehören nach `layers/core`, falls der Layer mit Auftrag 41 schon existiert, sonst nach `app/composables` mit einem Vermerk.

## Nicht Teil dieses Auftrags

* Kein Sprite und kein Endpunkt für mehrere Symbole auf einmal. Falls die Messung nach A bis C immer noch viele Anfragen zeigt, Vorschlag in der Abschlussmeldung (z. B. Bündel je Anfangsbuchstabe oder `GET /icons?names=…` im Backend), nicht bauen.
* Keine Änderung am Aussehen der Symbole.

## Prüfen

`npm run lint`, `npm run typecheck`, `npm test` (Frontend), die Browser-Tests der Seiten mit Auswahlfeld (Projekt, Meilenstein, Todo, Task). Ein Test für Debounce und das Nachladen sichtbarer Kacheln.
