# Meta-Felder

Jedes Custom Field, das das Plugin liest oder schreibt, an Handbuchseiten, an Lernpfaden, an Medien und an Handbüchern. Aus dem Code in `src/` abgelesen; widersprechen sich die beiden, hat der Code recht und diese Seite den Fehler.

Zwei Sorten, am Namen zu unterscheiden (siehe [architektur.md](architektur.md)):

- **Redaktionelle** Felder tragen das Präfix `living_handbook_`. Eine Person füllt sie aus, und die registrierten sind über die REST-API lesbar.
- **Buchhaltungs**-Felder tragen das Präfix `_lh_`. Das Plugin führt sie über eine Seite, nicht für sie. Der führende Unterstrich macht sie geschützt: WordPress blendet sie in der Box „Custom Fields" aus, wo eine falsche Bearbeitung den Abgleich wortlos kaputt machen würde.

„REST" heißt unten, das Feld ist mit `show_in_rest` registriert und erscheint unter `meta` in `/wp/v2/handbook`. Jedes registrierte Feld schreibt nur, wer dieses Objekt bearbeiten darf: der Auth-Callback fragt `edit_post` für die Seite, oder `manage_categories` und `edit_term` für ein Handbuch; `edit_term` verweigert das Plugin für ein Handbuch, das die Person nicht lesen darf. Ein Feld mit „nicht registriert" ist gewöhnliches Post-Meta, das nur der eigene Code des Plugins anfasst.

## Handbuchseiten (`handbook`)

### Redaktionelle Felder

| Schlüssel | Typ | REST | Geschrieben von | Inhalt |
| --- | --- | --- | --- | --- |
| `living_handbook_last_updated` | string, `JJJJ-MM-TT` | ja | `Metadata::set_updated()` bei jedem Speichern, `GitSync` nach jedem erfolgreichen Abgleich | Das Datum, das als „Zuletzt aktualisiert" erscheint. Wird nicht von Hand eingetragen. |
| `living_handbook_last_reviewed` | string, `JJJJ-MM-TT` | ja | Die Box **Handbuch-Wartung**, QuickEdit, Mehrfachbearbeitung; ein Import aus der Transport-Zeile `Letzte Prüfung`; ein Bündel-Import bei Seiten, die er anlegt | Das letzte Prüfdatum. Was kein Datum ist, wird leer gespeichert. |
| `living_handbook_review_interval` | integer, Tage | ja | Wie das Prüfdatum; Transport-Zeile `Prüfintervall` | Wie lange eine Prüfung gilt. 0 oder leer heißt kein Intervall, die Seite gilt dann als nicht geprüft. |
| `living_handbook_reviewer` | integer, Benutzer-ID | ja | Die Wartungs-Box, QuickEdit, Mehrfachbearbeitung; ein Bündel-Import bei Seiten, die er anlegt, aufgelöst aus Login oder E-Mail-Adresse | Wer die Seite zuletzt geprüft hat. |
| `living_handbook_toc_depth` | integer, 0 bis 6 | ja | Die Wartungs-Box; ein Bündel-Import bei Seiten, die er anlegt | Übersteuert die Tiefe des Inhaltsverzeichnisses dieser Seite. 0 nimmt die Einstellung des Blocks; ein Wert über 6 wird als 0 gespeichert. |
| `living_handbook_ai_exclude` | boolean | ja, Standard `false` | Die Wartungs-Box; ein Bündel-Import bei Seiten, die er anlegt | Bittet künftige KI-Leser, die Seite auszulassen. Das Plugin selbst speichert und zeigt das Feld nur. |

Der abgeleitete Zustand einer Seite (geprüft, Prüfung fällig, Prüfung überfällig, nicht geprüft) ist kein Feld. Er wird bei jedem Lesen berechnet und als nur lesbares `living_handbook_status` herausgegeben; siehe [rest-routen.md](rest-routen.md).

### Quelle und Abgleich

| Schlüssel | Typ | REST | Geschrieben von | Inhalt |
| --- | --- | --- | --- | --- |
| `_lh_source` | string, `wordpress` oder `github` | nein; Schreiben verlangt `edit_post` und `upload_files` | Die Box **Quelle**, der GitHub-Import, das Laden des App-Handbuchs | Wo die Seite gepflegt wird, standardmäßig `wordpress`. Jeder andere Wert wird als `wordpress` gespeichert. |
| `_lh_source_url` | string, URL | nein; Schreiben verlangt `edit_post` und `upload_files` | Die Box Quelle, der GitHub-Import | Die Markdown-Datei, aus der eine GitHub-Seite geholt wird. Die Positivliste der Hosts wird vor jedem Abruf erneut geprüft. |
| `_lh_sync_status` | string | nicht registriert | `GitSync` nach jedem Abruf | Das letzte Ergebnis als Text, in der Box Quelle als „Letzter Sync" gezeigt. |
| `_lh_sync_error` | `'1'` oder fehlend | nicht registriert | `GitSync` bei einem gescheiterten Abruf, beim nächsten guten entfernt | Markiert die Seite für den Admin-Hinweis „konnte nicht abgeglichen werden". |
| `_lh_github_folder` | string | nicht registriert | GitHub-Ordner-Import und Laden des App-Handbuchs | Markiert die Seite, die für einen Ordner ohne eigene Markdown-Datei steht, damit ein zweiter Import sie wiederfindet. `owner/repo@branch:pfad` für GitHub, `local:pfad` für die mitgelieferten Handbücher. |
| `_lh_app_handbook` | `'1'` oder fehlend | nicht registriert | Laden des App-Handbuchs mit eingeschalteter **Verbindung zur Quelle** | Bindet die Seite an die mitgelieferte Kopie: der Editor ist gesperrt, und ein späteres Laden frischt sie auf. Fehlt, wenn die Verbindung ausgeschaltet war; eine solche Seite lässt ein späteres Laden unangetastet. |

### Import-Buchhaltung

| Schlüssel | Typ | REST | Geschrieben von | Inhalt |
| --- | --- | --- | --- | --- |
| `_lh_source_path` | string, relativer Pfad | nicht registriert | ZIP-, MkDocs- und Ordner-Import, Laden des App-Handbuchs | Die Datei, aus der eine Seite kam, relativ zur Import-Wurzel. Ein erneuter Import findet die Seite darüber wieder, und interne `.md`-Links werden darüber aufgelöst. |
| `_lh_import_parent_title` | string | nicht registriert | `Postprocessor::apply_transport()` aus der Zeile `Eltern-Seite` | Der Titel der gewünschten Elternseite, aufbewahrt, bis jede Seite des Imports existiert. Gelöscht, sobald die Elternseite aufgelöst ist. Die gefundene Seite wird nur Elternseite, wenn die importierende Person sie bearbeiten darf. |
| `_lh_origin_id` | string, `<Website-URL>#<Post-ID>` | nicht registriert | Bündel-Import | Woher die Seite exportiert wurde. Der erste Schlüssel, über den ein späterer Bündel-Import zuordnet, auch bei gewöhnlichen Seiten aus einem Import als Seiten. |
| `_lh_bundle_key` | string | nicht registriert | Bündel-Import | Der Hierarchie-Schlüssel der Seite in dem Bündel, aus dem sie kam. Der zweite Schlüssel, über den ein Bündel-Import zuordnet, und der letzte bei einem Import als gewöhnliche Seiten, der nie über den Slug zuordnet. |
| `_lh_import_protected` | ein beliebiger nicht leerer Wert | nicht registriert | Niemand im Plugin: setze ihn selbst, per Code oder WP-CLI | Vom Bündel-Import gelesen: eine passende Seite mit diesem Feld wird nie überschrieben, egal welche Regel gewählt ist. |
| `_lh_moved_from` | string, Pfad | nicht registriert | Die Mehrfachaktion **In ein Handbuch verschieben…** | Die Adresse, die eine Seite hatte, bevor sie Handbuchseite wurde, etwa `about/team`. Eine Anfrage an die alte Adresse wird dauerhaft umgeleitet. |

### Feedback

| Schlüssel | Typ | REST | Geschrieben von | Inhalt |
| --- | --- | --- | --- | --- |
| `_lh_feedback_yes` | integer | nicht registriert | Der Feedback-Endpunkt | Ja-Stimmen. |
| `_lh_feedback_no` | integer | nicht registriert | Der Feedback-Endpunkt | Nein-Stimmen. |
| `_lh_feedback_voters` | Array von Benutzer-IDs | nicht registriert | Der Feedback-Endpunkt, bei angemeldeten Stimmen | Wer abgestimmt hat, damit eine angemeldete Person je Seite einmal zählt. Anonyme Stimmen fügen hier nichts hinzu. |

Alle drei entfernt die Zeilenaktion **Feedback zurücksetzen** in der Seitenliste. Anonyme Stimmen werden außerdem je Seite und Stunde in einem Transient gezählt, `lh_fb_<Post-ID>_<JJJJMMTTHH>`, für die Obergrenze pro Stunde (`living_handbook_anonymous_feedback_limit` in [hooks.md](hooks.md)); er läuft nach zwei Stunden ab und identifiziert niemanden.

## Lernpfade (`lh_training`)

| Schlüssel | Typ | REST | Geschrieben von | Inhalt |
| --- | --- | --- | --- | --- |
| `_lh_training_lessons` | Array von Post-IDs | nein (`show_in_rest` absichtlich aus) | Die Box **Lektionen** beim Speichern | Die geordnete Lektionsliste, höchstens 200 Einträge, Doppelte entfernt. Wer welche Lektion sieht, wird beim Lesen des Pfads entschieden, nicht hier. |

## Medien (`attachment`)

| Schlüssel | Typ | REST | Geschrieben von | Inhalt |
| --- | --- | --- | --- | --- |
| `_lh_import_file` | string, Dateiname | nicht registriert | Jedes Bild, das ein Import oder ein Abgleich einspielt | Markiert ein Bild, das das Plugin hereingeholt hat, damit ein erneuter Import seine eigenen Uploads wiederverwendet und keine fremden. |
| `_lh_import_hash` | string | nicht registriert | Ebenso | Ein Inhalts-Hash, damit ein geändertes Quellbild neu hochgeladen statt wiederverwendet wird. |

## Handbücher (Terms von `handbook_set`)

| Schlüssel | Typ | REST | Geschrieben von | Inhalt |
| --- | --- | --- | --- | --- |
| `living_handbook_visibility` | string: `public`, `members`, `restricted` | nein | Die Bearbeitungsseite des Handbuchs (`manage_categories` und `edit_term`, also nur wer das Handbuch lesen darf); ein Bündel-Import, der ein Handbuch anlegt, schreibt immer `members` | Die Zugriffsstufe. Leer wird als `members` gelesen. |
| `living_handbook_roles` | Array von Rollen-Slugs | nein | Die Bearbeitungsseite des Handbuchs; ein Bündel-Import | Die Rollen, die ein eingeschränktes Handbuch zulässt. |
| `living_handbook_users` | Array von Benutzer-IDs | nein | Die Bearbeitungsseite des Handbuchs; `Privacy::forget_deleted_user()` nimmt ein gelöschtes Konto heraus | Die Personen, die ein eingeschränktes Handbuch zusätzlich zu den Rollen zulässt. Nie in einem Bündel exportiert und nie importiert. Der Export personenbezogener Daten nennt die Handbücher, deren Liste die Person enthält; die Löschung lässt den Eintrag stehen und meldet ihn. |
| `living_handbook_comments` | string: `inherit`, `open`, `closed` | nein | Die Bearbeitungsseite des Handbuchs | Kommentare für das ganze Handbuch: jeder Seite überlassen, oder für alle ein- oder ausgeschaltet. |
| `living_handbook_version_edition` | string, eine bis drei Zahlen mit Punkt | nein | Die Bearbeitungsseite des Handbuchs; `HandbookVersion::refresh_tag()` aus dem GitHub-Tag; ein Bündel-Import, der ein Handbuch anlegt | Die Ausgabe der Handbuch-Version (E-0084). Leer wird als `1` gelesen. |
| `living_handbook_version_count` | int | nein | `HandbookVersion::bump()`, höchstens einmal pro Tag bei einer Änderung an veröffentlichtem Inhalt; 0 bei einer neuen Ausgabe | Der Zähler der Handbuch-Version. |
| `living_handbook_version_day` | string, `Y-m-d` in der Zeitzone der Website | nein | `HandbookVersion::bump()`; gelöscht bei einer neuen Ausgabe | Der Tag, der zuletzt gezählt wurde. |
| `living_handbook_version_source` | string: `manual`, `github` | nein | Die Bearbeitungsseite des Handbuchs | Woher die Ausgabe kommt. |
| `living_handbook_version_repo` | string, `owner/repo` | nein | Ebenso | Das Repository, dessen Tags die Ausgabe geben. |
| `living_handbook_version_tag` | string | nein | `HandbookVersion::refresh_tag()` | Der Tag, aus dem die Ausgabe zuletzt kam, so wie GitHub ihn nennt. |
| `living_handbook_version_error` | string | nein | Ebenso | Warum die Tags zuletzt nicht gelesen werden konnten; die Bearbeitungsseite zeigt es. |

Keines dieser Felder ist über die REST-API lesbar: wer ein Handbuch lesen darf, gibt man nicht jedem heraus, der fragt, und die Version zeigen Website, Exporte und die Ability `list-handbooks`.

## Ältere Namen

Installationen von vor 0.56.0 trugen einige dieser Felder unter anderen Namen. `Plugin::rename_meta_keys()`, einmal von `Plugin::maybe_upgrade()` aufgerufen, benennt sie an Ort und Stelle um: `living_handbook_source` und `living_handbook_markdown_source` wurden zu `_lh_source` und `_lh_source_url`, und die Feedback-Zähler gingen von `living_handbook_feedback_*` über `_living_handbook_feedback_*` zu `_lh_feedback_*`. Code, der noch einen alten Namen liest, liest nichts.

## Transport-Metadaten
* Seitentyp: Tool-Übersicht
* Zielgruppe: Technik
* Reihenfolge: 10
* Letzte Aktualisierung: 2026-11-10
