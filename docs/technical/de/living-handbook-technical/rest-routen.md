# REST-Routen

Jede Route, die das Plugin registriert, alle im Namensraum `living-handbook/v1`; eine Route `/feedback` erreichst du also unter `/wp-json/living-handbook/v1/feedback`. Aus den Aufrufen von `register_rest_route()` in `src/` abgelesen; widersprechen sich die beiden, hat der Code recht und diese Seite den Fehler.

Hier leben zwei Arten von Routen. Die **Lese-Routen** (Filter, Suche, Feedback) bedienen das Frontend und sind mit Absicht für Gäste offen, die Zugriffsprüfung sitzt im Handler. Die **Redaktions-Routen** (Import, Export, Lektionssuche) bedienen Admin-Bildschirme und sind durch ihren Permission-Callback geschlossen. Alle werden von den eigenen Skripten des Plugins angesteuert; keine ist eine stabile öffentliche Schnittstelle, und ihre Antwort kann sich mit dem Bildschirm ändern, der sie benutzt.

Schreibende Routen brauchen die REST-Nonce (`X-WP-Nonce`) wie jeder REST-Aufruf von WordPress mit Cookie-Anmeldung; die Skripte des Plugins schicken sie über `apiFetch` mit.

## Lese-Routen

| Methode | Pfad | Berechtigung | Parameter | Antwort |
| --- | --- | --- | --- | --- |
| GET | `/filter` | alle (`__return_true`) | `term_id` (Pflicht, Term-ID des Handbuchs); `lh_s` (Suchtext); `lh_type`, `lh_topic`, `lh_role`, `lh_audience` (Arrays von Term-Slugs, nur für die Taxonomien, die diese Website nutzt); `paged` | `{ html }`: die Ergebnisspalte der Einstiegsseite für diese Auswahl. Leeres HTML, wenn das Handbuch nicht existiert oder die aufrufende Person es nicht lesen darf. |
| GET | `/search` | alle (`__return_true`) | `term_id` (Pflicht); `q` (Suchtext) | `{ results: [ { title, url, snippet } ] }`, höchstens 8 veröffentlichte Seiten dieses Handbuchs. Leer, wenn `q` leer ist oder das Handbuch nicht lesbar. `snippet` ist der Text rund um die Fundstelle, als Liste von Stücken `{ text, mark }` statt als Markup. |
| POST | `/feedback` | angemeldet, oder **Öffentliches Feedback** eingeschaltet | `post_id` (Pflicht, integer); `value` (Pflicht, `yes` oder `no`) | `{ ok, counted }`. 400 für alles, was keine Handbuchseite oder nicht `yes`/`no` ist; 403, wenn die aufrufende Person die Seite nicht lesen darf; 429, wenn die Obergrenze für anonyme Stimmen pro Stunde erreicht ist. |

„Alle" ist Absicht: ein öffentliches Handbuch muss für Gäste durchsuchbar bleiben. Die Entscheidung sitzt eine Ebene tiefer, und sie ist die, die zählt: `/filter` und `/search` fragen `AccessController::can_view_term()` für das Handbuch und `can_view_post()` für jeden einzelnen Treffer, bevor irgendetwas zurückgeht. `/feedback` fragt `can_view_post()` für die Seite, ein Gast kann also nur je auf einer Seite eines öffentlichen Handbuchs abstimmen. `tests/Integration/RestAccessTest.php` hält diese Linie.

Eine angemeldete Stimme zählt einmal je Person und Seite (siehe `_lh_feedback_voters` in [meta-felder.md](meta-felder.md)); eine zweite antwortet mit `counted: false`. Eine anonyme Stimme wird nie dedupliziert, mit Absicht, und stattdessen je Seite und Stunde gedeckelt (`living_handbook_anonymous_feedback_limit`, siehe [hooks.md](hooks.md)).

## Redaktions-Routen

| Methode | Pfad | Berechtigung | Parameter | Antwort |
| --- | --- | --- | --- | --- |
| POST | `/convert` | `edit_posts` | `markdown` (Pflicht, string) | `{ title, html, transport }`: ein eingefügter Entwurf, in HTML umgewandelt, mit gelesenem Transport-Block. Geschrieben wird nichts. |
| POST | `/import-zip` | `upload_files` | die Datei `zip`, als Multipart-Upload | `{ files: [ { name, slug, title, html, transport } ], images, notes }`, oder für ein MkDocs-Projekt `{ mode: "mkdocs", pages, images, notes }`. Die Bilder werden hier eingespielt; die Seiten entstehen danach über `/create`. |
| POST | `/create` | `edit_posts` | `title`, `content` (Blockmarkup, hier bereinigt), `handbook` (Term-ID), `transport` (Objekt), `parent` (Post-ID), `sourcePath`, `slug`, `order` | `{ id, sourcePath, updated, editUrl }`. Legt einen Entwurf an, oder frischt die Seite auf, die ein erneuter Import trifft, aber nur eine, die die aufrufende Person bearbeiten darf. |
| POST | `/finalize` | `edit_posts` | `ids` (Array von Post-IDs) | `{ converted }`: löst Elternseiten und interne `.md`-Links auf. Angefasst werden nur Handbuchseiten, die die aufrufende Person bearbeiten darf. |
| POST | `/import-github` | `edit_posts` | `url` (eine Datei- oder `tree`-URL), `handbook`, `title`; oder `job`, um einen Ordner-Import fortzusetzen; oder `app_handbook` mit `app_entry` und `keep_source` | `{ pages: [ { id, title, editUrl } ] }`, dazu `notes`, wenn es etwas zu melden gibt. Ein Ordner-Import, dem die Zeit ausgeht, antwortet mit `job`, `phase` (`pages` oder `links`), `remaining` und `total` und wird mit diesem `job` erneut gefragt; ein aufgebrauchtes GitHub-Kontingent ergänzt `retry_after` in Sekunden. Das Laden des App-Handbuchs verlangt zusätzlich `edit_others_posts` und antwortet ohne mit 403. |
| POST | `/static-export` | `edit_others_posts` | `handbook` (Term-ID), `area` (Seiten-ID oberster Ebene, optional), `theme` (`site`, `plain`, `dark`, `paper`, oder eine über `living_handbook_static_export_themes` ergänzte); oder `job`, um fortzusetzen | Ein Durchgang eines Website-Exports: `{ job, done: false, total, remaining }`, bis er fertig ist, dann `{ job, done: true, total, url, name, size }`, wobei `url` das ZIP über `admin-post.php` herunterlädt. 403, wenn die aufrufende Person das Handbuch nicht lesen darf; ein Auftrag gehört der Person, die ihn gestartet hat. |
| GET | `/training-lessons` | `edit_post` auf den Lernpfad, den `training_id` nennt | `training_id` (Pflicht); `q` (Titelsuche) | `{ results: [ { id, title } ] }`, höchstens 10 veröffentlichte Seiten aus dem eigenen Handbuch des Pfads, nur über den Titel gesucht. 400, wenn `training_id` kein Lernpfad ist; 403, wenn die aufrufende Person dieses Handbuch nicht lesen darf. Jeder Treffer läuft durch `can_view_post()`. |

Der Permission-Callback ist das erste Tor, nicht das einzige. `/create` und `/finalize` prüfen `edit_post` für jede Seite, die sie anfassen würden, weil `edit_posts` allein schon eine Mitarbeiterin hat; `/import-github` veröffentlicht nichts ausser dem App-Handbuch, und dieser Weg verlangt mehr.

## Was das Plugin an Routen von WordPress ändert

Das Plugin registriert nicht nur Routen, es engt auch solche von WordPress ein und erweitert sie. Wissenswert, wenn du `/wp/v2` selbst liest:

- **`/wp/v2/handbook`** (der Seitentyp, `show_in_rest`) trägt die redaktionellen Felder und `_lh_source`, `_lh_source_url` unter `meta` (siehe [meta-felder.md](meta-felder.md)), dazu ein nur lesbares Feld `living_handbook_status`: `{ freshness, reviewed, interval, permalink, ai_exclude }`. Eine einzelne Seite, die die aufrufende Person nicht lesen darf, antwortet mit 404, in jedem Kontext, auch mit `context=edit`; Sammlungen werden genauso gefiltert.
- **`/wp/v2/lh_training`** gibt es nur, solange das Lernpfad-Modul eingeschaltet ist, und es folgt denselben Zugriffsregeln wie die Seiten.
- **`/wp/v2/handbook_set`** antwortet allen ohne `edit_posts` mit einer leeren Liste, und für einen einzelnen Term mit 403. Die Zugriffs-Konfiguration eines Handbuchs ist dort gar nicht zu sehen.
- **`/wp/v2/media`** und **`/wp/v2/comments`**: ein Anhang oder ein Kommentar an einer Seite, die die aufrufende Person nicht lesen darf, fehlt in Sammlungen und antwortet einzeln mit 404.
- **`/oembed/1.0/embed`** antwortet für eine Handbuchseite, die die aufrufende Person nicht lesen darf, mit nichts.

## Transport-Metadaten
* Seitentyp: Tool-Übersicht
* Zielgruppe: Technik
* Reihenfolge: 11
* Letzte Aktualisierung: 2026-10-26
