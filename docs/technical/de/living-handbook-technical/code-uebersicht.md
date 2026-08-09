# Code-Übersicht

Ein Rundgang in Alltagssprache durch den Aufbau des Plugins, für alle, die den Code verstehen wollen, ohne ihn schon zu kennen. Er setzt nicht voraus, dass du je ein WordPress-Plugin geschrieben hast. Die knappe Fassung für Entwicklerinnen und Entwickler steht in [architektur.md](architektur.md); diese Seite ist die freundliche Variante.

## Was das Plugin ist, in einem Absatz

Living Handbook macht aus WordPress ein internes Team-Handbuch. Ein Handbuch ist eine Menge Seiten, die zusammengehören, mit einer zuständigen Person, einem Prüfdatum und einer Regel, wer sie lesen darf. Das Plugin ergänzt eine neue Art Seite (eine „Handbuchseite"), gruppiert diese Seiten zu Handbüchern, steuert, wer was sieht, verfolgt, wie aktuell jede Seite ist, und kann Seiten aus Markdown-Dateien oder aus einem GitHub-Repository holen. Alles, was Besuchende sehen, wird bei jedem Seitenaufruf frisch aus kleinen Bausteinen zusammengesetzt.

## Die Begriffe, die du brauchst

Eine Handvoll Begriffe kommt überall vor. Sobald die sitzen, liest sich der Rest leicht.

- **Handbuchseite** (`handbook`): eine Seite eines Handbuchs. Technisch ein „Custom Post Type" von WordPress, was schlicht heisst: eine Inhaltsart, die das Plugin selbst definiert, wie Beiträge oder Seiten, nur eigen.
- **Handbuch** (`handbook_set`): die Gruppe, zu der eine Seite gehört. Technisch ein „Taxonomie-Begriff", derselbe Mechanismus wie eine Kategorie. Jede Seite gehört zu genau einem Handbuch, was beim Speichern durchgesetzt wird, und jedes Handbuch hat seine eigene Zugriffsregel und seine eigene Startseite.
- **Taxonomie**: eine Art, Seiten zu klassifizieren. Neben der Handbuch-Gruppierung gibt es vier klassifizierende Vokabulare: Seitentyp, Thema, verantwortliche Rolle und Zielgruppe. Sie treiben die Filter und die Abzeichen.
- **Zugriff**: ob die aktuell besuchende Person ein Handbuch lesen darf. Drei Stufen: öffentlich, alle angemeldeten Mitglieder, oder beschränkt auf benannte Rollen und Personen. Geprüft wird nur im Frontend; das Bearbeiten im wp-admin nutzt die normalen WordPress-Rollen.
- **Aktualität**: wie aktuell eine Seite ist, ermittelt aus dem letzten Prüfdatum und dem Prüfintervall. Vier Zustände: geprüft, Prüfung fällig, Prüfung überfällig, und nicht geprüft für eine Seite ohne Prüfdatum oder ohne Intervall.
- **Block**: ein Stück der Seite (Navigation, Inhaltsverzeichnis, Abzeichen und so weiter). Das Plugin bringt eigene Blöcke mit; jeder baut sein HTML auf dem Server.
- **Die drei Oberflächen**: die drei Arten Seite, die ein Handbuch hat. Die **Übersicht** listet alle Handbücher; die **Einstiegsseite** eines Handbuchs ist dessen Startseite (Suche, Filter, Bereiche, zuletzt geändert); eine **Einzelseite** ist eine Handbuchseite mit Navigation, Inhalt und Fusszeile.

## Wie der Code aufgeteilt ist

Der ganze plugin-eigene Code liegt in `src/`, ein Ordner pro Zuständigkeitsbereich. Die Datei `living-handbook.php` im Wurzelverzeichnis ist der Einstieg: WordPress lädt sie, sie richtet einen Autoloader ein (eine kleine Funktion, die die Datei zu einer Klasse findet, sobald die Klasse zum ersten Mal gebraucht wird, sodass nichts von Hand eingebunden werden muss), und übergibt dann an `src/Plugin.php`.

`src/Plugin.php` ist die Verdrahtung. Die Methode `boot()` erzeugt ein Objekt pro Modul und ruft auf jedem `register()` auf, und dort hängt sich jedes Modul in WordPress ein. Wenn du wissen willst, was das Plugin alles tut, lies `boot()`: das ist das Inhaltsverzeichnis des Codes. `Plugin.php` hält auch die Schritte `activate()` und `deactivate()`, die beim Ein- und Ausschalten laufen.

Die Module unter `src/`:

- **`PostType/`** definiert die Handbuchseite. `Handbook.php` registriert sie und hält sie aus Suchmaschinen, Sitemaps und Feeds heraus, damit ein internes Handbuch nicht leckt.
- **`Taxonomy/`** definiert die vier klassifizierenden Vokabulare (Seitentyp, Thema, Rolle, Zielgruppe) in `Taxonomies.php`.
- **`Handbook/`** definiert die Handbuch-Gruppierung. `Handbooks.php` registriert die Gruppierung und ihre Zugriffseinstellungen; `HandbookAdmin.php` ist der kleine Bildschirm, auf dem du Sichtbarkeit, Rollen und Personen eines Handbuchs setzt.
- **`Access/`** ist das Tor. `AccessController.php` beantwortet eine einzige Frage, „darf diese Person diese Seite lesen?", und jeder Lesepfad im ganzen Plugin fragt hier. Es ist bewusst streng: eine Seite, die zu keinem Handbuch gehört, ist nicht lesbar. Es schliesst auch die Seitentüren, damit Kommentare und REST-Anfragen keine Seite verraten, die die besuchende Person nicht sehen darf.
- **`Meta/`** registriert die Felder pro Seite in `Metadata.php`: zuletzt geändert, zuletzt geprüft, Prüfintervall, prüfende Person, ein „vor KI verbergen"-Flag und die Tiefe von „Auf dieser Seite". Es stellt ausserdem eine einzige, nur lesbare Zusammenfassung der Aktualität über die REST-Schnittstelle bereit.
- **`Frontend/`** ist alles, was Besuchende sehen. Vom `AccessController` abgesehen ist das der grösste Ordner:
  - `FrontendRenderer.php` lädt Stylesheet und Skript und kann die Handbuchliste in das Menü des Themes einhängen.
  - `Templates.php` liefert die Layouts für die Einstiegsseite und die Einzelseiten.
  - `Cards.php` zeichnet Karten und Kacheln (ein Handbuch, eine Seite, ein Bereich).
  - `Entry.php` baut den Rumpf der Einstiegsseite: Suche, Filter, Bereichskacheln, zuletzt geändert.
  - `Filters.php` baut Suche und Facettenfilter und den Endpunkt, der die gefilterte Liste zurückgibt.
  - `Navigation.php` baut den Seitenbaum eines Handbuchs, die einklappbare Seitenleiste.
  - `PageTree.php` lädt alle veröffentlichten Seiten eines Handbuchs in einer einzigen Abfrage und gruppiert sie nach Elternseite, sodass Navigation und Bereichskacheln ihre Hierarchie aus derselben Karte aufbauen, statt pro Zweig abzufragen.
  - `PageMeta.php` baut die Frage „War das hilfreich?" und die Metadaten-Fusszeile.
  - `FreshnessStatus.php` ermittelt den Zustand geprüft, fällig, überfällig oder nicht geprüft und dessen Beschriftung.
- **`Blocks/`** registriert die Blöcke des Plugins. `Blocks.php` registriert die meisten und rendert sie auf dem Server; `MermaidBlock.php` und `SourceNoteBlock.php` sind zwei besondere (ein lebendiges Diagramm, und ein Hinweis, der nur auf Seiten mit GitHub-Abgleich erscheint). Die Blöcke rufen für ihr HTML nach `Frontend/`.
- **`Feedback/`** behandelt die Stimmen zu „War das hilfreich?" und ihre Zähler (`Feedback.php`).
- **`Admin/`** ist die Wartungsoberfläche im Backend. `Maintenance.php` ist das Dashboard-Widget mit den überfälligen Seiten sowie die Spalten und die Filterleiste in der Seitenliste. `MoveToHandbook.php` ist die Mehrfachaktion, die bestehende WordPress-Seiten zu Handbuchseiten macht. `ListScreen.php` beantwortet die zwei Fragen, die die Filterleiste stellt, bevor sie ein Bedienelement zeichnet: ist diese Spalte sichtbar, und hat dieses Vokabular überhaupt einen Begriff.
- **`Import/`** holt Markdown herein. `MarkdownImportPage.php` ist die Import-Seite mit ihren Endpunkten; `MarkdownConverter.php` macht aus Markdown HTML; `TransportBlock.php` liest den kleinen Metadaten-Block, den ein Entwurf mitbringt; `MkDocsImport.php` liest eine `mkdocs.yml`, um die Struktur eines Projekts zu erhalten; `Postprocessor.php` wendet diese Metadaten an und schreibt interne Links um, sobald die Seiten existieren; `ImageRefs.php` sammelt die relativen Bildverweise in einem Markdown-Entwurf, sowohl in Markdown-Syntax als auch als rohe `<img>`-Tags, damit die Dateien neben der Seite mitreisen; `HtmlSanitizer.php` ist die gemeinsame Positivliste, die alles Unsichere aus importiertem HTML entfernt. Zwei Dateien im selben Ordner arbeiten mit ganzen Handbüchern statt mit einzelnen Dokumenten: `HandbookExport.php` schreibt ein Handbuch, oder einen Bereich davon, in ein in sich geschlossenes Bündel, und `HandbookImport.php` liest ein solches Bündel auf einer anderen Seite wieder ein. `AppHandbook.php` ist eine kleine dritte: sie verweist die Import-Seite auf das Handbuch der App selbst, das als Markdown unter `docs/user/` im Plugin mitgeliefert und aus diesem lokalen Ordner importiert wird; ein Fork kann sie über den Filter `living_handbook_app_handbook_url` stattdessen auf ein GitHub-Repository verweisen lassen.
- **`Git/`** ist der GitHub-Abgleich. `GitSync.php` erlaubt einer Seite, aus einer Markdown-URL zu stammen, holt sie beim Speichern, auf Zuruf und nach Zeitplan, speichert das Ergebnis sicher und sperrt den Editor für solche Seiten.
- **`Setup/`** ist der Code für den ersten Start und die Einstellungen. `Seeder.php` füllt die Standard-Vokabulare bei der Aktivierung; `Onboarding.php` legt die Übersichtsseite an und zeigt den Willkommens-Hinweis; `Settings.php` ist der Einstellungs-Bildschirm (Abgleich-Häufigkeit, Verhalten bei der Deinstallation).

Der Code für den Browser liegt in `assets/`: `frontend.css` und `frontend.js` für die Besucherseiten, `blocks.js` für den Block-Editor, und ein paar kleine Skripte unter `assets/js/` für die Import-Seite und die Diagramme. Die Übersetzungen liegen in `languages/`.

## Wie ein Seitenaufruf durch den Code läuft

Folge einer Person, die eine einzelne Handbuchseite öffnet. Der Aufruf berührt die Module in einer festen Reihenfolge, und diese Reihenfolge zu sehen ist der schnellste Weg, das Ganze zu verstehen.

1. WordPress erkennt die URL und entscheidet, dass es eine Handbuchseite ist.
2. Der **Zugriff** kommt zuerst (`AccessController`): darf diese Person das lesen? Wenn nicht, wird ein Gast zur Anmeldung geschickt und alle anderen bekommen „nicht gefunden". Weiter läuft nichts.
3. Ist der Zugriff erlaubt, legt das **Template** für eine Einzelseite (`Templates`) drei Spalten an: Navigation links, Inhalt in der Mitte, „Auf dieser Seite" rechts.
4. Jeder **Block** in diesem Template rendert nun auf dem Server und ruft dafür nach `Frontend/`: `Navigation` baut den Seitenbaum des Handbuchs, `Cards` und `PageMeta` bauen Abzeichen und Fusszeile, der Inhaltsverzeichnis-Block gibt einen leeren Behälter aus.
5. **Stylesheet und Skript** laden (`FrontendRenderer`). Im Browser füllt `frontend.js` das Inhaltsverzeichnis aus den Überschriften der Seite, verdrahtet das Auf- und Zuklappen der Navigation und behandelt die Feedback-Knöpfe.
6. Stimmt die Person bei „War das hilfreich?" ab, ruft das Skript den **Feedback**-Endpunkt (`Feedback`), der den Zugriff erneut prüft, bevor er die Stimme zählt.

Eine Einstiegsseite (die Startseite eines Handbuchs) ist dieselbe Idee mit einem anderen Template: Suche, Filter, Bereichskacheln und zuletzt geändert, wobei eine Filterauswahl den **Filter**-Endpunkt (`Filters`) ruft und die Liste an Ort und Stelle austauscht.

## Wie ein Import läuft

Die Import-Seite (`MarkdownImportPage`) nimmt einen eingefügten Entwurf, ein ZIP mit Markdown-Dateien oder eine GitHub-URL. Text wird von `MarkdownConverter` in HTML verwandelt, der Browser macht aus diesem HTML bearbeitbare Blöcke, und den kleinen Metadaten-Block auf jedem Entwurf liest `TransportBlock`. Sobald die Seiten existieren, läuft `Postprocessor` ein zweites Mal darüber: er setzt Handbuch, Taxonomien, Prüfdaten und Elternseite und schreibt Links, die auf andere importierte Dateien zeigen, so um, dass sie zu den richtigen Seiten führen. Seiten aus GitHub nehmen den kürzeren Weg über `GitSync`, der gerendertes HTML speichert statt bearbeitbarer Blöcke, weil eine geplante Aufgabe keinen Browser hat, der die Umwandlung machen könnte.

Ein ganzes Handbuch auf eine andere Seite zu bringen funktioniert anders, weil es nichts umzuwandeln gibt. `HandbookExport` geht die Seiten des Handbuchs durch und schreibt sie, ihre Struktur, ihre Vokabulare, ihre Prüfdaten und ihre Medien in ein ZIP mit einer `manifest.json`. `HandbookImport` liest diese Datei auf der anderen Seite und setzt die Seiten wieder ein, gibt jeder eine neue ID und verdrahtet Eltern und interne Links passend neu. Was geschieht, wenn eine Seite schon da ist, entscheidet die importierende Person; der Standard überschreibt nie etwas.

## Die eine Regel, auf die es ankommt

Es gibt eine einzige, nicht verhandelbare Regel im Code: **jedes Lesen von Handbuch-Inhalt geht durch `AccessController::can_view_post()`**. Lies nie selbst das Handbuch einer Seite und entscheide den Zugriff auf eigene Faust, und frage nie an der Prüfung vorbei in der Datenbank. Wenn du einen neuen Weg ergänzt, Handbuchseiten zu lesen oder aufzulisten (ein Widget, ein REST-Feld, eine KI-Anbindung), führe ihn durch diese Methode. Sie ist die eine Stelle, die über Sichtbarkeit entscheidet, sie ist filterbar, und sie schlägt im Zweifel zu, nicht auf. Sie zu vergessen ist der eine Fehler, der Inhalt lecken lässt.

## Wo du wofür nachsiehst

| Du willst ändern … | Sieh nach in |
| --- | --- |
| Wer ein Handbuch sehen darf | `Access/AccessController.php`, `Handbook/HandbookAdmin.php` |
| Den Seitenbaum, die Seitenleiste | `Frontend/Navigation.php`, `assets/frontend.css`, `assets/frontend.js` |
| Die Einstiegsseite (Suche, Filter, Kacheln) | `Frontend/Entry.php`, `Frontend/Filters.php`, `Frontend/Cards.php` |
| Die Aktualität (geprüft, fällig, überfällig, nicht geprüft) | `Frontend/FreshnessStatus.php`, `Meta/Metadata.php` |
| Das Dashboard für überfällige Seiten | `Admin/Maintenance.php` |
| Das Markup eines Blocks | `Blocks/Blocks.php` (Server), `assets/blocks.js` (Editor) |
| Den Markdown-Import | `Import/` (fang bei `MarkdownImportPage.php` an) |
| Ein Handbuch zwischen Seiten bewegen | `Import/HandbookExport.php`, `Import/HandbookImport.php` |
| Das App-Handbuch | `Import/AppHandbook.php` (welche Quelle genutzt wird), `Git/GitSync.php` (`import_local_folder` für die mitgelieferte Kopie, `import_folder` für eine GitHub-Übersteuerung) |
| Den GitHub-Abgleich und die Einstellungen | `Git/GitSync.php`, `Setup/Settings.php` |
| Was bei der Aktivierung läuft | `Plugin.php` (`activate`), `Setup/Seeder.php`, `Setup/Onboarding.php` |
| Was nach einem Update läuft | `Plugin.php` (`maybe_upgrade`, `rename_meta_keys`) |
| Wie schnell eine Seite ist, und warum | `bin/seed-performance.php`, `bin/measure-performance.php`, die `*QueryCostTest`-Dateien unter `tests/Integration/` |

## Wie sich der Code selbst dokumentiert

- Jede Klasse, Methode und Funktion trägt einen Doc-Kommentar; die Prüfung der Coding Standards (`composer lint`) schlägt fehl, wenn einer fehlt, das läuft also nie auseinander.
- Kommentare erklären die Begründung, nicht das Offensichtliche. Wo Code seltsam aussieht, sagt der Kommentar darüber, warum er so ist.
- Es gibt absichtlich fast keine Einstellungen: Gestalterisches gehört in den Site Editor, das Verhalten hat wenige begründete Optionen, und alles andere ist ein Hook (siehe [hooks.md](hooks.md)) statt einer Checkbox.
- Englisch ist die Sprache des Repositories; die deutsche Quelle dieser Entwickler-Dokumentation (`docs/technical/de/`) liegt im Arbeitsbereich des Teams. Das deutsche App-Handbuch ist die Ausnahme: es liegt im Repository, unter `docs/user/de/`.
