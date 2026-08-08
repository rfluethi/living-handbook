# Vorlagen

Living Handbook registriert Block-Vorlagen (WordPress 6.8 und neuer, nur Block-Themes). Sie fügen sich automatisch in die Vorlagen-Hierarchie ein und nutzen Kopf- und Fussbereich des aktiven Themes, das Handbuch passt sich also in den Rest deiner Website ein. Öffnen und bearbeiten kannst du sie im Site-Editor unter **Design → Editor → Vorlagen**.

Zwei davon machen die Arbeit:

| Vorlage | Gilt für |
| --- | --- |
| **Handbuch-Einstieg** | Die Einstiegsseite jedes Handbuchs, das Term-Archiv von `handbook_set`, zum Beispiel `/handbook-set/allgemein/` |
| **Handbuch-Seite** | Eine einzelne Handbuch-Seite, zum Beispiel `/handbook/onboarding/` |

Für die **Übersicht gibt es keine Vorlage**. Die Übersicht ist eine normale WordPress-Seite mit dem Block „Handbuch-Übersicht" darauf; die Aktivierung legt eine für dich an, und du kannst sie verschieben, umgestalten oder ersetzen. Siehe [bloecke.md](bloecke.md).

Ein Hinweis zum Bearbeiten: Sobald du Änderungen an einer dieser Vorlagen im Site-Editor speicherst, behält WordPress deine gespeicherte Fassung und nutzt die mitgelieferte des Plugins nicht mehr, auch über Plugin-Updates hinweg. Wirkt eine Vorlage nach einem Update veraltet, öffne sie im Site-Editor und wähle **Anpassungen löschen**, um auf die aktuelle Fassung des Plugins zurückzufallen.

## Handbuch-Einstieg

Gilt für die Einstiegsseite jedes Handbuchs. Ihr Aufbau:

- der Titel des Handbuchs (`core/query-title`) und seine Beschreibung (`core/term-description`),
- eine zweispaltige Zeile: die Handbuch-Navigation (`living-handbook/navigation`, auf *Akkordeon* gestellt) in einer schmalen linken Spalte, und der Einstiegs-Block (`living-handbook/entry`, mit Suche, Filtern, Bereichen und zuletzt aktualisierten Seiten) in der breiten rechten Spalte.

## Handbuch-Seite

Gilt für eine einzelne Handbuch-Seite. Ein dreispaltiger Aufbau, 22 / 54 / 22 Prozent:

- **Links (schmal):** die Handbuch-Navigation (`living-handbook/navigation`, auf *Akkordeon* gestellt) und darunter die Handbuch-Suche (`living-handbook/search`).
- **Mitte (breit):** der Titel (`core/post-title`), das mobile Inhaltsverzeichnis (`living-handbook/toc` auf *mobil* gestellt, auf kleinen Bildschirmen über dem Inhalt) und der Inhalt (`core/post-content`). Danach alles, was über die Seite Auskunft gibt, am Fuss: die Feedback-Frage (`living-handbook/feedback`), eine Trennlinie, der Herkunftshinweis (`living-handbook/git-source-note`), die Badges (`living-handbook/badges`), eine zweite Trennlinie, die Metadaten-Fusszeile (`living-handbook/pagemeta`) und zuunterst der Kommentarblock des Kerns (`core/comments`), der nichts rendert, solange die Kommentare einer Seite geschlossen sind.
- **Rechts (schmal):** das Desktop-Inhaltsverzeichnis (`living-handbook/toc`, klebend, auf breiten Bildschirmen sichtbar).

Die beiden Trennlinien sind statische `core/separator`-Blöcke mit der Klasse `living-handbook-divider`. Statisch sind sie mit Absicht: zwei ihrer Nachbarn rendern in manchen Fällen nichts, eine Besucherin ohne öffentliches Feedback bekommt keine Frage und eine in WordPress gepflegte Seite keinen Herkunftshinweis, und der Fuss soll in beiden Fällen gleich aussehen.

Die beiden Inhaltsverzeichnisse sind derselbe Block mit unterschiedlicher Einstellung **Platzierung**; CSS zeigt nur das, welches zur aktuellen Bildschirmbreite passt, du musst dich also nicht entscheiden.

## Umstellen

Das sind gewöhnliche Block-Vorlagen, du kannst sie im Site-Editor also umstellen: die Navigation nach rechts verschieben, das Desktop-Inhaltsverzeichnis weglassen, den Inhalt verbreitern und so weiter. Die Blöcke sind eigenständig und rendern dort, wo du sie platzierst, solange sie in ihrem vorgesehenen Zusammenhang bleiben (siehe „Erscheint auf" in [bloecke.md](bloecke.md)).

Woher die mitgelieferten Fassungen kommen: `src/Frontend/Templates.php`, als Block-Markup in `entry_content()` und `single_content()`. Das ist der Aufbau, den eine frische Installation bekommt, und der, auf den **Anpassungen löschen** zurückfällt. Wer ihn dort ändert, ändert also den Ausgangspunkt für jede Website, die die Vorlage nicht selbst bearbeitet hat. `BlockTemplatesTest` bewacht das Markup, weil es sonst niemand tut: ein falsch geschriebener Blockname rendert als nichts, ein kaputter Block-Kommentar schluckt den Rest der Vorlage, beides zur Laufzeit ohne ein Wort.

## Die Vorlagen wieder entfernen

Wenn du das Plugin löschst und auf der Einstellungsseite „Auch alle Handbuch-Inhalte löschen" gewählt hast, werden auch alle Fassungen dieser Vorlagen entfernt, die du im Site-Editor gespeichert hast. Ohne diese Option bleiben deine gespeicherten Vorlagen erhalten, was der sichere Standard ist. Zum Verhalten beim Deinstallieren siehe [import-und-sync.md](import-und-sync.md).
