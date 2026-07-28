# Markdown importieren

Diese Anleitung übernimmt fertige Markdown-Texte als Seiten in dein Handbuch. Es gibt drei Quellen: eingefügter Text, eine ZIP-Datei mit Markdown-Dateien oder eine Adresse auf GitHub. Was Markdown und GitHub sind, erklärt die [Übersicht des Bereichs Inhalte](README.md).

<details>
<summary>Konzept: Was beim Import passiert</summary>

Der Import liest jede Markdown-Datei und baut daraus eine normale Handbuch-Seite. Die Seite lässt sich danach in WordPress bearbeiten wie jede andere. Überschriften, Listen, Bilder, Diagramme und aufklappbare Abschnitte bleiben erhalten. Links zwischen den Dateien werden zu Links zwischen den fertigen Seiten. Was genau übersteht, steht unter [Inhalte schreiben](inhalte-schreiben.md).

</details>

## Schritte

1. Öffne **Handbuch → Import**. Jede Quelle hat einen eigenen Reiter mit allem, was sie braucht.
2. Wähle den passenden Reiter:
   * **Text einfügen:** Füge einen Markdown-Text ein und klicke auf **Markdown importieren**. Daraus entsteht immer eine neue Seite.
   * **ZIP-Datei:** Lade eine ZIP-Datei mit Markdown-Dateien hoch und klicke auf **ZIP importieren**.
   * **GitHub:** Trage eine GitHub-Adresse ein und klicke auf **Von GitHub importieren**. Zeigt die Adresse auf eine einzelne Datei, entsteht eine Seite. Zeigt sie auf einen Ordner, werden alle Markdown-Dateien darin importiert, Unterordner eingeschlossen.
   * **Paket:** Übernimm ein ganzes Handbuch von einer anderen Website, siehe [Handbuch umziehen](handbuch-umziehen.md).
   * **App-Handbuch:** Lade genau dieses Handbuch, siehe [App-Handbuch laden](../erste-schritte/app-handbuch-laden.md).
3. Wähle das **Ziel-Handbuch**, in das die Seiten gehören.
4. Starte den Import und lies die Ergebnisliste. Sie meldet, was angelegt wurde und wo etwas fehlte.
5. Prüfe die neuen Seiten und **veröffentliche** sie. Importierte Seiten sind zuerst unveröffentlichte Entwürfe. So geht nichts ungeprüft online. Einzige Ausnahme ist das App-Handbuch, es erscheint sofort.

![Die Import-Seite mit den Reitern (Text einfügen, ZIP-Datei, GitHub, Paket, App-Handbuch), geöffnet ist der GitHub-Reiter mit dem Adressfeld.](../assets/import-github.webp)

## Aus Ordnern werden Bereiche

Beim Import eines ganzen Ordners wird die Ordnerstruktur zur Handbuch-Struktur. Jeder Unterordner wird ein Bereich. Jede Datei wird eine Seite in ihrem Bereich. Die Navigation entsteht so von selbst.

Jeder Bereich braucht dabei eine Startseite. Der Import löst das so:

* Liegt im Ordner eine Datei namens `index.md` oder `README.md`, wird sie die Startseite des Bereichs. Die übrigen Seiten des Ordners werden ihr untergeordnet.
* Liegt dort keine solche Datei, erzeugt der Import die Startseite selbst. Sie zeigt automatisch eine Liste aller Seiten des Bereichs.

## Der Steckbrief am Dateiende: Transport-Metadaten

Zu jeder Handbuch-Seite gehören Angaben wie Seitentyp, Zielgruppe oder Prüfdatum. In WordPress trägst du sie in Felder ein. Eine Markdown-Datei hat diese Felder nicht. Darum darf jede Datei am Ende einen kurzen Steckbrief tragen. Er heißt **Transport-Metadaten**. Der Import liest ihn, füllt damit die Felder der Seite und entfernt ihn aus dem Text.

Der Steckbrief beginnt mit einer Überschrift der zweiten Ebene, die genau „Transport-Metadaten“ lautet. Ab dieser Überschrift zählt alles bis zum Dateiende als Steckbrief. Darunter steht eine Liste; jede Zeile ist freiwillig:

```markdown
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Applikation
* Zielgruppe: Alle Mitglieder, Technik
* Eltern-Seite: Übersicht
* Reihenfolge: 3
* Textauszug: Kurz erklärt.
* Letzte Prüfung: 2026-07-08
* Prüfintervall: 90 Tage
```

**Wichtig:** Ab Plugin-Version 0.43 darf die Überschrift „Transport-Metadaten“ auch in Beispielen und Codeblöcken vorkommen. Der Import überspringt Codeblöcke und nimmt das letzte Vorkommen außerhalb davon. Ältere Versionen trennen dagegen am ersten Auftreten; dort schneidet ein zweites Vorkommen den Rest der Seite ab. Deshalb fehlt die Überschrift im Beispiel oben.

Drei Zeilen brauchen eine Erklärung:

* **Eltern-Seite** ordnet die Seite einer übergeordneten Seite zu. Genannt wird deren Titel. Die Eltern-Seite darf im selben Import erst später drankommen, das Zuordnen passiert am Schluss.
* **Reihenfolge** bestimmt die Position im Menü. Kleine Zahlen stehen oben. Seiten ohne Zahl folgen dahinter, alphabetisch. Nummeriere also nur, was eine feste Position braucht.
* **Handbuch** darf zusätzlich ergänzt werden. Damit bestimmt die Datei ihr Ziel-Handbuch selbst und übersteuert die Auswahl auf der Import-Seite.

## Dieselbe Quelle noch einmal importieren

Ein zweiter Import derselben Quelle legt keine Kopien an. Er aktualisiert die bestehenden Seiten. Deren Adresse bleibt gleich. Eine veröffentlichte Seite bleibt veröffentlicht. Titel, Inhalt und Zuordnung werden aus der Quelle aufgefrischt. Eine Ausnahme gibt es beim Ordner-Import: Er stellt auch die Struktur wieder her. Hast du eine Eltern-Seite von Hand geändert, wird das zurückgesetzt. Bei importierten Handbüchern ordnest du darum besser in den Dateien, über die Zeile „Reihenfolge“.

<details>
<summary>Stolpersteine: Grenzen des Imports</summary>

* **Höchstens 200 Dateien pro Ordner-Import.** Wird die Grenze erreicht, sagt es die Ergebnisliste. Importiere die restlichen Unterordner danach einzeln.
* **GitHub bremst nach etwa 60 Abrufen pro Stunde.** Ein sehr großer Import kann diese Grenze erreichen. Der Import meldet es. Warte dann eine Stunde und versuche es erneut.
* **Nicht öffentliche GitHub-Projekte** lassen sich nicht direkt abrufen. Lade die Dateien dort als ZIP herunter und importiere die ZIP-Datei.
* **ZIP-Grenzen:** höchstens 2000 Dateien, 5 MB pro Datei, 100 MB entpackt.

</details>

## Verwandte Seiten

* [Inhalte schreiben](inhalte-schreiben.md)
* [GitHub-Synchronisation](github-synchronisation.md)
* [Handbuch umziehen](handbuch-umziehen.md)
* [Technische Details in der Entwickler-Dokumentation](https://github.com/rfluethi/living-handbook/blob/main/docs/import-and-sync.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Inhalte
* Zielgruppe: Alle Mitglieder
* Reihenfolge: 2
* Textauszug: Diese Anleitung übernimmt fertige Markdown-Texte als Seiten in dein Handbuch: eingefügt, als ZIP-Datei oder von einer GitHub-Adresse.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
