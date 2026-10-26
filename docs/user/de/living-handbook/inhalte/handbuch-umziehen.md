# Handbuch umziehen

Du willst ein Handbuch auf eine andere Website bringen? Dafür exportierst du es als Bündel und importierst das Bündel auf der anderen Website. Das geht auch mit einem einzelnen Bereich. Voraussetzung: Auf beiden Websites läuft Living Handbook.

<details>
<summary>Konzept: Was ein Bündel ist</summary>

Ein Bündel ist eine einzelne ZIP-Datei. Darin stecken die Seiten des Handbuchs: alle Seiten samt ihrer Ordnung, die Bilder, die Einordnung (Seitentyp, Thema, Verantwortung, Zielgruppe), die Prüfdaten und die Sichtbarkeits-Einstellung. Die Ziel-Website braucht keinen Kontakt zur alten Website. Zwei Dinge fehlen mit Absicht. Erstens die einzeln freigegebenen Personen, denn das sind Personendaten, und ein Bündel ist eine Datei, die weitergegeben wird. Zweitens die Feedback-Zahlen, denn sie gehören zur alten Website.

Und eines fehlt, weil das Bündel nur Handbuch-Seiten kennt: die [Lernpfade](lernpfade-anlegen.md). Sie gehören zwar zum Handbuch, reisen aber nicht mit. Lege sie auf der Ziel-Website neu an, nachdem die Seiten dort angekommen sind.

</details>

## Exportieren

1. Öffne **Handbuch → Export**.
2. Wähle das **Handbuch**. Danach kannst du im zweiten Feld eingrenzen: das ganze Handbuch oder nur ein Bereich. Ein Bereich exportiert mitsamt seinen Unterseiten.
3. Klicke auf **Bündel exportieren**. Die ZIP-Datei wird heruntergeladen.

## Importieren

1. Öffne auf der Ziel-Website **Handbuch → Import**, Reiter **Bündel**. Dafür braucht dein Konto dort mindestens die WordPress-Rolle Redakteur, denn der Import verändert auch Seiten anderer Autorinnen und Autoren.
2. Lade die ZIP-Datei hoch.
3. Wähle, was mit Seiten passieren soll, die es dort schon gibt:
   * **Überspringen** (Standard): Vorhandene Seiten bleiben unangetastet. Nur neue werden angelegt.
   * **Aktualisieren:** Vorhandene Seiten werden aus dem Bündel aufgefrischt.
   * **Immer neu anlegen:** Jede Seite im Bündel wird eine neue Seite. Das eignet sich zum Klonen.
4. Wähle unter **Importieren nach**, in welches Handbuch die Seiten kommen. Ohne Auswahl entsteht das Handbuch aus dem Bündel neu.
5. Starte den Import und lies den Bericht. Er zählt auf, was angelegt, aktualisiert oder übersprungen wurde.

## Ergebnis

Die Seiten stehen auf der Ziel-Website. Links zwischen den Seiten führen zu den neuen Seiten. Zwei Schutzregeln gelten immer: Gelöscht wird nie. Und eine Seite, die auf der Ziel-Website als geschützt markiert ist, wird nie überschrieben.

<details>
<summary>Stolpersteine: Sicherheit geht vor Bequemlichkeit</summary>

* **Ein importiertes Handbuch startet immer mit der Sichtbarkeit „Alle Mitglieder“.** Das gilt auch, wenn es auf der alten Website öffentlich war. Ein Import soll nie versehentlich etwas veröffentlichen. Stelle die Sichtbarkeit danach von Hand um.
* **Einzeln freigegebene Personen musst du neu eintragen.** Sie reisen nicht mit.
* **Lernpfade musst du neu anlegen.** Auch sie reisen nicht mit.
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
* Letzte Aktualisierung: 2026-10-26
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
