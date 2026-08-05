# Inhalte schreiben

Diese Seite hilft dir beim Schreiben von Handbuch-Seiten. Der erste Teil gilt für alle. Der zweite Teil betrifft nur Seiten, die aus Markdown-Dateien kommen.

## Schreiben in WordPress

Eine Handbuch-Seite schreibst du wie jede andere WordPress-Seite. Alles Gewohnte funktioniert: Überschriften, Listen, Tabellen, Bilder, Zitate. Dazu bringt das Plugin eigene Bausteine mit, zum Beispiel für Diagramme. Du findest sie beim Einfügen eines Blocks unter der Kategorie **Living Handbook**.

Zwei Empfehlungen für lesbare Seiten:

* **Beginne mit einem kurzen Einleitungssatz.** Er sagt, was die Seite leistet und für wen sie ist. Derselbe Satz eignet sich als Kurztext auf den Übersichts-Kacheln.
* **Gliedere mit Zwischentiteln.** Nutze dafür Überschriften der Ebene 2 und tiefer. Aus ihnen baut sich das Verzeichnis „Auf dieser Seite“ automatisch auf.

## Diagramme einsetzen

Hat ein Ablauf mehrere Schritte oder Entscheidungen? Dann zeigt ein Diagramm oft mehr als ein langer Absatz. Living Handbook zeichnet Diagramme selbst, direkt auf der Seite. Beschrieben werden sie in [Mermaid](https://mermaid.js.org/), einer einfachen Textsprache für Diagramme. Füge im Editor den Block **Mermaid** ein und schreibe die Diagramm-Beschreibung hinein. Wie der Block funktioniert, steht mit einem Beispiel auf [Die Blöcke des Plugins](../oberflaeche/bloecke.md); ein größeres Diagramm siehst du auf [Der Prüfzyklus](../pflege/der-pruefzyklus.md).

## Schreiben in Markdown-Dateien

Dieser Teil betrifft dich nur, wenn deine Seiten aus Markdown-Dateien kommen. Die zwei Wege dafür sind der [Import](markdown-importieren.md) und die [GitHub-Synchronisation](github-synchronisation.md). Beim Einlesen wird der Text umgewandelt und aus Sicherheitsgründen bereinigt. Das meiste übersteht das problemlos. Ein paar Dinge gehen verloren.

### Was funktioniert

* Überschriften, Absätze, Listen, Tabellen, Zitate, Fettdruck und Kursiv.
* Links zwischen den Dateien. Der Import macht daraus automatisch Links zwischen den fertigen Seiten.
* Bilder. Sie werden in die WordPress-Mediathek übernommen. Das gilt auch für SVG-Grafiken, also verlustfrei skalierbare Vektorbilder.
* Diagramme. Schreibe die Mermaid-Beschreibung in der Datei als Codeblock und gib als Sprache `mermaid` an. Daraus wird auf der fertigen Seite ein gezeichnetes Diagramm.
* Aufklappbare Abschnitte, wie die Kästen „Was du brauchst“ oder „Stolpersteine“ in diesem Handbuch. In der Datei schreibst du sie als `<details>`-Abschnitt. Auf der fertigen Seite bleiben sie aufklappbar.

### Was nicht funktioniert

* Die eigenen Bausteine des Plugins, etwa die Feedback-Frage oder die Abzeichen. Sie lassen sich in einer Markdown-Datei nicht schreiben. Das brauchst du aber auch nicht: Das Plugin setzt sie von selbst an die richtigen Stellen.
* Programmcode, der auf der Seite etwas ausführen würde. Die Bereinigung entfernt ihn. Das schützt deine Website.
* Sonderformate anderer Werkzeuge, zum Beispiel farbige Hinweiskästen aus MkDocs (`!!! note`). Sie werden zu einfachem Text.

## Verwandte Seiten

* [Markdown importieren](markdown-importieren.md)
* [Erste Seite anlegen](../erste-schritte/erste-seite-anlegen.md)

## Transport-Metadaten
* Seitentyp: Tool-Übersicht
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Inhalte
* Zielgruppe: Alle Mitglieder
* Eltern-Seite: Inhalte
* Reihenfolge: 1
* Textauszug: Was auf eine gute Handbuch-Seite gehört, wie du Diagramme einsetzt und was beim Einlesen von Markdown-Dateien übersteht.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
