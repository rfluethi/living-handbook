# Inhalte schreiben

Dieses Handbuch kommt aus Markdown-Dateien auf GitHub. Beim Import wird Markdown zu HTML und danach bereinigt. Das entscheidet, was übersteht.

## Was funktioniert

- Überschriften, Absätze, Listen, Tabellen, Zitate, Fettdruck und Kursiv.
- Links, auch auf andere Seiten des Handbuchs als relative `.md`-Links. Sie werden beim Import auf die richtige Seite umgebogen.
- Bilder. Sie werden in die Mediathek geladen.
- Mermaid-Diagramme in einem Codeblock mit der Sprache `mermaid`. Ein Beispiel steht auf [Der Prüfzyklus](../pflege/der-pruefzyklus.md).

## Was nicht funktioniert

Die plugin-eigenen Blöcke (Bereichsliste, Feedback, Abzeichen, Seitenmetadaten) lassen sich in Markdown nicht ausdrücken. Sie sind technisch HTML-Kommentare, und die Bereinigung entfernt Kommentare. Schreibst du einen solchen Block in eine Markdown-Datei, verschwindet er beim Import spurlos.

Zwei davon brauchst du gar nicht selbst zu setzen: Der Navigationsbaum links entsteht aus der Ordnerstruktur, und eine Bereichsseite ohne eigene Datei bekommt ihre Kartenliste automatisch.

## Transport-Metadaten
* Reihenfolge: 3
