# Handbuch als Markdown exportieren

Du willst ein Handbuch als gewöhnliche Textdateien haben, zum Sichern, für ein Repository auf GitHub oder um es später wieder einzulesen? Dann exportierst du es als Markdown: eine ZIP-Datei mit einer Datei je Seite, den Bildern und einer Navigation. Der Markdown-Import liest sie wieder ein, mit derselben Gliederung und denselben Angaben.

<details>
<summary>Konzept: Wozu ein dritter Export</summary>

Das **Bündel** bringt ein Handbuch auf eine andere WordPress-Website mit Living Handbook, siehe [Handbuch umziehen](handbuch-umziehen.md). Die **Website** ist eine Momentaufnahme zum Lesen ohne WordPress, siehe [Handbuch als Website weitergeben](handbuch-als-website-exportieren.md).

**Markdown** ist der Weg aus dem Plugin hinaus und wieder hinein. Die Dateien lassen sich in jedem Texteditor lesen und ändern, in ein Repository legen und mit dem Markdown-Import oder der GitHub-Synchronisation wieder zu einem Handbuch machen. So ist dein Handbuch nie in Living Handbook gefangen.

</details>

## So geht es

1. Öffne **Handbuch → Export**. Der Abschnitt heißt **Als Markdown exportieren**, ganz unten.
2. Wähle das **Handbuch**, und im zweiten Feld wahlweise einen einzelnen Bereich. Zur Wahl stehen die Handbücher, die du lesen darfst.
3. Klicke auf **Markdown exportieren**. Die ZIP-Datei wird sofort heruntergeladen.

## Ergebnis

In der ZIP-Datei liegt ein Ordner mit dem Namen des Handbuchs:

* **`docs/`**: die Seiten. Eine Seite mit Unterseiten wird zu einem Ordner, ihr eigener Text steht darin in `README.md`. Jede andere Seite ist eine `.md`-Datei.
* **`docs/assets/`**: die Bilder und Dateien, auf die die Seiten verweisen.
* **`mkdocs.yml`**: die Navigation, also Titel und Reihenfolge aller Seiten. Mit ihr baut auch das Werkzeug MkDocs eine Website aus den Dateien.
* **`EXPORT.txt`**: wann und womit der Export entstand, und was er weggelassen hat.

Jede Seite beginnt mit ihrem Titel und endet mit dem Steckbrief **Transport-Metadaten**: Seitentyp, Einordnung, Textauszug, Prüfdatum und Prüfintervall. Links auf andere Seiten des Handbuchs zeigen auf die Datei daneben, Mermaid-Diagramme stehen als Code darin, aufklappbare Abschnitte bleiben aufklappbar.

Zurück ins Handbuch bringst du die Dateien mit **Handbuch → Import**, Reiter **ZIP-Datei**. Die `mkdocs.yml` gibt dabei die Gliederung vor, siehe [Markdown importieren](markdown-importieren.md).

<details>
<summary>Stolpersteine: Was nicht mitkommt</summary>

* **Die Dateien haben keinen Zugriffsschutz.** Wer die ZIP-Datei hat, liest jede Seite darin, auch Bilder aus geschützten Handbüchern. Behandle sie wie einen Ausdruck.
* **Blöcke des Plugins ohne Markdown-Form fallen weg**, etwa Feedback, Abzeichen oder Bereichsliste. `EXPORT.txt` nennt sie pro Seite. Gestaltung wie Farben oder Ausrichtung geht ebenfalls verloren.
* **Seiten mit Passwort fehlen.** Ihre Unterseiten rücken eine Ebene nach oben.
* **Lernpfade fehlen.** Sie haben keine Form in Markdown.
* **Beim erneuten Import kommen nur Bilder zurück in die Mediathek.** Links auf andere Dateien, etwa PDFs, zeigen danach in den Ordner `assets` und müssen neu gesetzt werden.
* **Seiten von GitHub** kommen so mit, wie sie hier gespeichert sind. `EXPORT.txt` nennt ihre Quelle auf GitHub.

</details>

## Verwandte Seiten

* [Markdown importieren](markdown-importieren.md)
* [Handbuch umziehen](handbuch-umziehen.md)
* [Handbuch als Website weitergeben](handbuch-als-website-exportieren.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Slug: handbuch-als-markdown-exportieren
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Inhalte
* Zielgruppe: Alle Mitglieder, Technik
* Eltern-Seite: Inhalte
* Reihenfolge: 6
* Textauszug: Wie du ein Handbuch als Markdown-Dateien exportierst, die der Markdown-Import wieder einliest, und was dabei wegfällt.
* Letzte Aktualisierung: 2026-11-09
* Letzte Prüfung: 2026-11-09
* Prüfintervall: 180 Tage
