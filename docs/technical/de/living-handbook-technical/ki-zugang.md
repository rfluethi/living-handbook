# KI-Zugang: Abilities und die Zählung erfolgloser Suchen

Ab 0.77.0 registriert das Plugin sieben lesende Abilities über die [Abilities API](https://developer.wordpress.org/news/2025/11/introducing-the-wordpress-abilities-api/) von WordPress. Mit dem [MCP Adapter](https://github.com/WordPress/mcp-adapter) kann ein KI-Werkzeug wie Claude oder Cursor sie als Werkzeuge benutzen: Handbücher auflisten, den Aufbau lesen, suchen, eine Seite lesen, und für die Redaktion auflisten, welche Seiten zur Prüfung anstehen, was Leserinnen und Leser nicht hilfreich fanden und wonach sie ohne Treffer gesucht haben. Wie ein Werkzeug angebunden wird, steht im Benutzerhandbuch unter **Zugriff → KI-Werkzeug anbinden**.

Diese Seite ist für alle, die den Code ändern. Die Entscheide dahinter sind E-0056 (lesende Abilities, kein Chat auf der Website, kein eigener Index) und E-0078 (was die Zählung erfolgloser Suchen speichert).

## Wo der Code liegt

| Datei | Was sie tut |
| --- | --- |
| `src/Abilities/Abilities.php` | Registriert die Kategorie `living-handbook` und die sieben Abilities, jede mit denselben Metadaten. Tut nichts, wo `wp_register_ability()` fehlt. |
| `src/Abilities/Ability.php` | Die Basisklasse: Name, Texte, Schemas, Berechtigung, Ausführung. |
| `src/Abilities/Pages.php` | Die Zugriffsregel und die Seitenbeschreibung, die alle Abilities teilen. |
| `src/Abilities/TextConverter.php` | Gerendertes HTML zu Markdown-artigem Text, für `get-page`. |
| `src/Abilities/ListHandbooks.php` … `ListFailedSearches.php` | Eine Klasse pro Ability. |
| `src/Search/SearchLog.php` | Die Tabelle der erfolglosen Suchen: zählen, aufräumen, lesen. |
| `src/Frontend/RenderedContent.php` | Der Inhalt einer Seite, wie Leserinnen und Leser ihn sehen, gemeinsam mit dem Website-Export. |

## Die Abilities

| Ability | Wer | Was sie liefert |
| --- | --- | --- |
| `living-handbook/list-handbooks` | angemeldet | Die lesbaren Handbücher mit Name, Titelform, Beschreibung, Adresse, Anzahl Seiten und Version des Handbuchs. |
| `living-handbook/get-structure` | angemeldet | Den Seitenbaum eines Handbuchs, flach, in der Reihenfolge der Navigation, mit Tiefe und Eltern-Seite. Höchstens 1000 Seiten, `truncated` sagt, wenn es mehr waren. |
| `living-handbook/search` | angemeldet | Bis zu 20 Seiten zu einer Volltextsuche, wahlweise in einem Handbuch und nach den vier Einordnungen. |
| `living-handbook/get-page` | angemeldet | Eine Seite nach ID oder Adresse, ihr Text als Markdown mit Überschriften, Listen und Tabellen. Höchstens 100 000 Zeichen. |
| `living-handbook/list-due-pages` | `edit_others_posts` | Überfällige, fällige und ungeprüfte Seiten und Seiten ohne prüfende Person, die dringendsten zuerst. |
| `living-handbook/list-feedback` | `edit_others_posts` | Seiten nach Anteil der „Nicht hilfreich“-Stimmen, mit einer Mindestzahl Stimmen (Standard 5). |
| `living-handbook/list-failed-searches` | `edit_others_posts` | Die häufigsten Suchen ohne Treffer, pro Handbuch und Zeitraum. |

Jede Seite in einer Antwort trägt ihre Adresse und ihren Prüfstatus (`ok`, `due`, `overdue`, `none`, mit Datum und Intervall). So kann ein Werkzeug seine Quelle nennen und sagen, wie aktuell sie ist. Das ist Vorkehrung 3 aus Konzept 04.

## Die Zugriffsregel

`Pages::readable()` ist die eine Prüfung, die jede Ability für jede Seite durchläuft. Eine Seite wird nur ausgeliefert, wenn alles zutrifft:

- sie ist eine veröffentlichte Handbuchseite ohne Passwort,
- `AccessController::can_view_post()` lässt das aktuelle Konto sie lesen, dieselbe Regel wie auf jedem anderen Weg in ein Handbuch,
- sie trägt kein `living_handbook_ai_exclude`.

Handbücher laufen über `Pages::handbook()` oder `Pages::readable_handbooks()`, beide mit `AccessController::can_view_term()`. Ein Handbuch oder eine Seite, die es nicht gibt, und eines, das das Konto nicht lesen darf, geben denselben Fehler `living_handbook_not_found` mit derselben Meldung. Eine Antwort verrät also nicht, was existiert.

Der Aufbau folgt der Navigation: Eine Seite, die das Konto nicht lesen darf, steht nicht in `PageTree::children_map()`, und ihre Unterseiten werden nicht erreicht. Eine Seite, die nur für den KI-Zugang wegfällt (ausgeschlossen oder mit Passwort), nimmt nichts mit: Ihre Unterseiten rücken zu ihrer Eltern-Seite hoch.

`get-page` lässt in jedem Handbuch, das nicht öffentlich ist, die Adressen hochgeladener Dateien weg. Solange der Dateischutz aus E-0076 nicht gebaut ist, kann jede Person mit der Adresse eine Datei öffnen, und eine Adresse, die bei einem KI-Anbieter gelandet ist, hat die Website nicht mehr in der Hand. Der Linktext und der Alternativtext eines Bildes bleiben.

`AbilitiesTest` sichert das ab. Jede Bedingung von `Pages::readable()` wurde einmal entfernt, und für jede fiel ein Test durch.

## Die Metadaten jeder Ability

`Abilities::meta()` gibt jeder Ability dieselben Metadaten:

- `public`, `show_in_rest` und `mcp.public`, alle `true`. WordPress 7.1 liest `public`, 6.9 nur `show_in_rest`, der MCP Adapter (0.7.1) liest `mcp.public` vor `public`. Zusammen gesetzt, ist die Freigabe überall gleich.
- Die Annotationen `readonly: true`, `destructive: false`, `idempotent: true`. Die Suche zählt eine Suche ohne Treffer. Das ist Buchhaltung über die Suche, keine Änderung an etwas, das Leserinnen und Leser oder die Redaktion sehen, und es gilt hier nicht als Schreiben.

## Die Zählung erfolgloser Suchen

`SearchLog` führt eine Tabelle, `{prefix}living_handbook_failed_searches`, die erste eigene Tabelle des Plugins:

| Spalte | Inhalt |
| --- | --- |
| `week` | Montag der Kalenderwoche, in der Zeitzone der Website. |
| `term_id` | Das durchsuchte Handbuch. |
| `source` | `site` für die Suche des Handbuchs, `ai` für die Such-Ability. |
| `term` | Der Suchbegriff, klein geschrieben, Leerraum zusammengezogen, 3 bis 100 Zeichen. Kürzere und längere Begriffe werden nicht gespeichert. |
| `hits` | Wie oft. |

Es gibt keine Benutzer-ID, keine IP-Adresse, keine Uhrzeit, kein Cookie und keinen Referrer, und `SearchLogTest` schlägt fehl, sobald eine Spalte dazukommt. Gezählt wird:

- eine leere Antwort der Schnellsuche (`/living-handbook/v1/search`),
- eine leere Ergebnisliste auf der Einstiegsseite eines Handbuchs, nur auf der ersten Seite und nur ohne gewählte Facette, denn eine leere Liste mit Facette sagt, dass die Kombination leer ist, nicht dass eine Seite fehlt,
- eine leere Antwort der Such-Ability ohne Filter, einmal pro durchsuchtes Handbuch.

`record()` prüft selbst, ob die Person das Handbuch lesen darf. Höchstens 500 verschiedene Begriffe pro Handbuch und Woche kommen in die Tabelle. Danach zählen nur noch die Begriffe hoch, die schon da sind, so kann ein Skript sie nicht füllen. Gezählt wird mit einem einzigen `INSERT … ON DUPLICATE KEY UPDATE`, damit zwei gleichzeitige Suchen keine Zählung verlieren.

Ein täglicher Cron-Lauf (`living_handbook_search_log_cleanup`) löscht jede Woche, deren erster Tag älter ist als die Aufbewahrungsfrist: 90 Tage, sofern `living_handbook_search_log_retention_days` nichts anderes sagt (siehe [hooks.md](hooks.md)). Der Schalter liegt unter **Handbuch → Einstellungen → Feedback** und ist standardmäßig ein. Die Deinstallation löscht die Tabelle jedes Mal, nicht nur mit „Alles entfernen“, denn sie ist Buchhaltung, kein Inhalt. Der Vorschlag für die Datenschutzerklärung beschreibt die Zählung.

Die Live-Suche schickt schon beim Tippen Anfragen, ein Wort ohne Treffer kommt also als „mül“, „müll“, „mülle“, „müller“ an. `most_frequent()` lässt einen Begriff weg, der der Anfang eines anderen Begriffs desselben Handbuchs, derselben Quelle und derselben Woche ist. Der längere Begriff trägt die Zählung. Ein echtes Kurzwort, das ein längeres beginnt, geht dabei mit verloren.

## Versionen

Die Abilities API gibt es in WordPress ab 6.9. Das Plugin läuft weiter ab 6.8 und registriert dort einfach keine Abilities. Die Tests laufen auf 6.8, 6.9, 7.1 und trunk; auf 6.8 wird `AbilitiesTest` übersprungen.

## Transport-Metadaten
* Seitentyp: Hintergrund / Konzept
* Zielgruppe: Technik
* Reihenfolge: 13
* Letzte Aktualisierung: 2026-11-10
