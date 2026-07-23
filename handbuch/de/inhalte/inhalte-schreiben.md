# Inhalte schreiben

Diese Seite ist dein Nachschlagewerk fürs Schreiben. Sie zeigt, was im Block-Editor geht. Sie zeigt auch, welches Markdown den Import übersteht und wie du Diagramme einsetzt.

## Schreiben im Block-Editor

Eine Handbuch-Seite ist eine normale Block-Editor-Seite. Alles, was der Editor kann, kannst du auch im Handbuch verwenden: Überschriften, Listen, Tabellen, Bilder, Zitate. Zusätzlich bringt das Plugin eigene Blöcke mit, etwa das Mermaid-Diagramm. Du findest sie im Block-Einfüger unter der Kategorie **Living Handbook**.

Zwei Empfehlungen für lesbare Handbuch-Seiten:

* **Beginne mit einem kurzen Einleitungssatz.** Er sagt, was die Seite leistet und für wen sie ist. Er eignet sich zugleich als Textauszug für die Karten.
* **Gliedere mit Überschriften ab Ebene 2.** Der Seitentitel ist die einzige Überschrift der Ebene 1. Aus den Ebenen darunter baut sich das Inhaltsverzeichnis rechts automatisch auf.

## Schreiben in Markdown

Deine Seiten können auch aus Markdown-Dateien kommen, per [Import](markdown-importieren.md) oder [GitHub-Synchronisation](github-synchronisation.md). Dabei wird Markdown zu HTML umgewandelt und danach bereinigt. Das entscheidet, was übersteht.

### Was funktioniert

* Überschriften, Absätze, Listen, Tabellen, Zitate, Fettdruck und Kursiv.
* Links, auch auf andere Seiten des Handbuchs als relative `.md`-Links. Der Import biegt sie automatisch auf die richtigen Seiten um, samt sichtbarem Linktext.
* Bilder, auch SVG-Vektorgrafiken. Sie werden aus dem `assets`-Ordner des Repositories in die Mediathek geladen. SVG-Dateien werden dabei bereinigt.
* Mermaid-Diagramme in einem Codeblock mit der Sprachangabe `mermaid`. Der Import macht daraus einen echten Diagramm-Block. Er wird im Editor und im Frontend gerendert.
* Aufklappbereiche als `<details>`-Abschnitte. Der Import macht daraus echte Details-Blöcke.

### Was nicht funktioniert

* Die plugin-eigenen Blöcke: Bereichsliste, Feedback, Abzeichen, Seitenmetadaten. Sie lassen sich in Markdown nicht ausdrücken. Technisch bestehen sie aus HTML-Kommentaren, und die Bereinigung entfernt Kommentare. Schreibst du einen solchen Block in eine Markdown-Datei, verschwindet er beim Import spurlos. Zwei davon brauchst du ohnehin nicht selbst: Der Navigationsbaum entsteht aus der Ordnerstruktur. Und eine Bereichsseite ohne eigene Datei bekommt ihre Kartenliste automatisch.
* Skripte, Event-Handler und unsichere Adressen. Sie werden aus Sicherheitsgründen entfernt.
* MkDocs-Spezialsyntax wie Admonitions (`!!! note`). Sie gehört nicht zu GitHub Flavored Markdown und wird zu einfachem Text.

## Diagramme einsetzen

Hat ein Ablauf mehrere Schritte, Rollen oder Zustände? Dann sagt ein Diagramm mehr als ein langer Absatz. Living Handbook rendert [Mermaid](https://mermaid.js.org/)-Diagramme direkt im Browser. Ein Beispiel steht auf [Der Prüfzyklus](../pflege/der-pruefzyklus.md). Im Editor fügst du den Block **Mermaid-Diagramm** ein und trägst den Diagramm-Text ein. Ein Titel wird zur Bildunterschrift. Eine Beschreibung wird zur Textalternative für Screenreader.

## Transport-Metadaten am Dateiende

Eine Markdown-Datei kann am Ende einen Abschnitt `## Transport-Metadaten` tragen. Er ist kein Seiteninhalt, sondern eine Mitgift für den Import. Seitentyp, Thema, Zielgruppe, Reihenfolge, Textauszug und Prüfdaten wandern daraus in die Felder der Seite. Die Feldliste steht unter [Markdown importieren](markdown-importieren.md).

## Verwandte Seiten

* [Markdown importieren](markdown-importieren.md)
* [Erste Seite anlegen](../erste-schritte/erste-seite-anlegen.md)

## Transport-Metadaten
* Seitentyp: Tool-Übersicht
* Reihenfolge: 1
* Textauszug: Dein Nachschlagewerk fürs Schreiben: was der Block-Editor kann, welches Markdown den Import übersteht und wie du Diagramme einsetzt.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
