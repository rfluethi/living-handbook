# Handbuch umziehen

Du willst ein Handbuch auf eine andere Website bringen? Dafür exportierst du es als Bündel und importierst das Bündel auf der anderen Website. Das geht auch mit einem einzelnen Bereich. Voraussetzung: Auf beiden Websites läuft Living Handbook.

<details>
<summary>Konzept: Was ein Bündel ist</summary>

Ein Bündel ist eine einzelne ZIP-Datei. Darin stecken die Seiten des Handbuchs: alle Seiten samt ihrer Ordnung, die Bilder, die Einordnung (Seitentyp, Thema, Verantwortung, Zielgruppe), die Prüfdaten und die Sichtbarkeits-Einstellung. Die Ziel-Website braucht keinen Kontakt zur alten Website. Zwei Dinge fehlen mit Absicht. Erstens die einzeln freigegebenen Personen, denn das sind Personendaten, und ein Bündel ist eine Datei, die weitergegeben wird. Zweitens die Feedback-Zahlen, denn sie gehören zur alten Website.

Auch die [Lernpfade](lernpfade-anlegen.md) des Handbuchs reisen mit. Auf der Ziel-Website zeigen ihre Lektionen auf die importierten Seiten. Exportierst du nur einen Bereich, behält ein Pfad nur die Lektionen aus diesem Bereich; ein Pfad ohne Lektion im Bereich bleibt zurück.

</details>

## Exportieren

1. Öffne **Handbuch → Export**.
2. Wähle das **Handbuch**. Zur Wahl stehen die Handbücher, die du lesen darfst. Danach kannst du im zweiten Feld eingrenzen: das ganze Handbuch oder nur ein Bereich. Ein Bereich exportiert mitsamt seinen Unterseiten. Einen Bereich, den du nicht sehen darfst, kannst du nicht exportieren.
3. Klicke auf **Bündel exportieren**. Die ZIP-Datei wird heruntergeladen.

## Importieren

1. Öffne auf der Ziel-Website **Handbuch → Import**, Reiter **Bündel**. Dafür braucht dein Konto dort mindestens die WordPress-Rolle Redakteur, denn der Import verändert auch Seiten anderer Autorinnen und Autoren.
2. Lade die ZIP-Datei hoch.
3. Wähle, was mit Seiten und Lernpfaden passieren soll, die es dort schon gibt:
   * **Überspringen** (Standard): Vorhandene Seiten bleiben unangetastet. Nur neue werden angelegt.
   * **Aktualisieren:** Vorhandene Seiten werden aus dem Bündel aufgefrischt.
   * **Immer neu anlegen:** Jede Seite im Bündel wird eine neue Seite. Das eignet sich zum Klonen.
4. Wähle unter **Importieren nach**, in welches Handbuch die Seiten kommen. Zur Wahl stehen die Handbücher, die du lesen darfst. Ohne Auswahl entsteht das Handbuch aus dem Bündel neu.
5. Starte den Import und lies den Bericht. Er zählt auf, was angelegt, aktualisiert oder übersprungen wurde, für Seiten und Lernpfade getrennt.

## Ergebnis

Die Seiten und die Lernpfade stehen auf der Ziel-Website. Links zwischen den Seiten führen zu den neuen Seiten. Zwei Schutzregeln gelten immer: Gelöscht wird nie. Und eine Seite, die auf der Ziel-Website als geschützt markiert ist, wird nie überschrieben.

<details>
<summary>Stolpersteine: Sicherheit geht vor Bequemlichkeit</summary>

* **Ein importiertes Handbuch startet immer mit der Sichtbarkeit „Alle Mitglieder“.** Das gilt auch, wenn es auf der alten Website öffentlich war. Ein Import soll nie versehentlich etwas veröffentlichen. Stelle die Sichtbarkeit danach von Hand um.
* **Einzeln freigegebene Personen musst du neu eintragen.** Sie reisen nicht mit.
* **In ein Handbuch, das du nicht lesen darfst, importierst du nicht.** Gibt es das Handbuch aus dem Bündel auf der Ziel-Website schon und darfst du es dort nicht lesen, bricht der Import mit „Du darfst nicht in dieses Handbuch importieren.“ ab, bevor er eine Seite anfasst. Ein Aktualisieren würde sonst Seiten überschreiben, die du nicht sehen darfst. Wähle ein anderes Ziel oder bitte jemanden, der das Handbuch lesen darf.
* **Eine vorhandene Seite, die du nicht bearbeiten darfst, bleibt unangetastet**, auch bei **Aktualisieren**. Sie dient auch nicht als Eltern-Seite oder Lektion. Der Bericht nennt die Anzahl, für Lernpfade ebenso.
* **Auch ein Import als gewöhnliche WordPress-Seiten folgt der Laufregel.** Ein späterer Lauf findet seine Seiten wieder und überspringt oder aktualisiert sie, statt neue Entwürfe anzulegen. Er erkennt sie an ihrer Herkunft aus dem Bündel, nicht am Namen: Eine andere Seite, die zufällig gleich heißt, bleibt in Ruhe.
* **Ein Lernpfad verliert Lektionen, die nicht im Bündel stecken.** Sie fallen aus dem Pfad, und der Bericht weist darauf hin. Importierst du das Bündel als gewöhnliche WordPress-Seiten, kommen gar keine Pfade mit.
* **Lernpfade erscheinen erst mit dem Modul.** Ist das Lernpfad-Modul auf der Ziel-Website aus, werden die Pfade trotzdem importiert. Sichtbar werden sie, sobald du es unter **Handbuch → Einstellungen → Lernpfade** einschaltest.
* **Beim Aktualisieren bleiben die Pflegedaten der Ziel-Website erhalten.** Feedback-Zahlen und Prüfdaten werden nicht überschrieben.
* **Lies fremde Bündel, bevor du sie veröffentlichst.** Der Inhalt wird beim Import zwar bereinigt. Er bleibt aber ein Text, den jemand anderes geschrieben hat.

</details>

## Verwandte Seiten

* [Markdown importieren](markdown-importieren.md)
* [Handbuch als Website weitergeben](handbuch-als-website-exportieren.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Inhalte
* Zielgruppe: Alle Mitglieder, Technik
* Eltern-Seite: Inhalte
* Reihenfolge: 4
* Textauszug: Ein Handbuch lässt sich als Bündel exportieren und auf einer anderen Website wieder importieren; so gehst du vor.
* Letzte Aktualisierung: 2026-10-27
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
