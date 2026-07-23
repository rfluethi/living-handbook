# Handbuch umziehen

Ein Handbuch lässt sich als Paket exportieren. Das geht auch für einen einzelnen Bereich. Auf einer anderen Website mit Living Handbook importierst du das Paket wieder. Diese Anleitung führt durch Export und Import.

<details>
<summary>Konzept: Was ein Paket ist</summary>

Ein Paket ist eine einzelne ZIP-Datei mit einer Beschreibungsdatei und den Medien. Es enthält die Konfiguration des Handbuchs, also Sichtbarkeit und erlaubte Rollen. Dazu kommen jede Seite samt Hierarchie, die vier Schlagwort-Gruppen, die Prüfdaten und die Bilder. Das Paket ist in sich vollständig. Die Ziel-Website braucht keinen Kontakt zur Quell-Website. Zwei Dinge fehlen mit Absicht. Erstens die einzeln freigegebenen Personen: Das sind E-Mail-Adressen, und ein Paket ist eine Datei, die weitergegeben wird. Zweitens lokale Betriebsdaten wie die Feedback-Zähler.

</details>

## Exportieren

1. Öffne **Handbuch → Export**.
2. Wähle das **Handbuch**. Das zweite Feld listet danach seine **Bereiche**. Lass es auf „das ganze Handbuch“ stehen. Oder wähle einen Bereich, er exportiert mitsamt seinen Unterseiten.
3. Klicke auf **Paket exportieren**. Die ZIP-Datei wird heruntergeladen.

## Importieren

1. Öffne auf der Ziel-Website **Handbuch → Import**, Reiter **Paket**. Dafür brauchst du dort die Rolle Content-Manager.
2. Lade die ZIP-Datei hoch.
3. Wähle, was mit schon vorhandenen Seiten passieren soll:
   * **Überspringen** (Standard): Vorhandene Seiten bleiben unangetastet. Nur neue werden angelegt.
   * **Aktualisieren:** Titel, Inhalt, Struktur und Schlagworte vorhandener Seiten werden aus dem Paket aufgefrischt.
   * **Immer neu anlegen:** Jede Seite im Paket wird eine neue Seite. Das ist nützlich zum Klonen in ein zweites Handbuch.
4. Wähle unter **Importieren in**, wo die Seiten landen. Standard ist das Handbuch aus dem Paket. Fehlt es, wird es angelegt. Du kannst auch ein bestehendes Handbuch wählen.
5. Starte den Import und lies den Bericht. Er zählt auf, wie viele Seiten angelegt, aktualisiert, übersprungen oder geschützt wurden. Er listet auch, was sich nicht zuordnen ließ.

## Ergebnis

Die Seiten stehen auf der Ziel-Website. Interne Links zeigen auf die neuen Seiten. Von GitHub synchronisierte Seiten setzen ihren Abgleich dort fort. Zwei Schutzregeln gelten immer: Eine als geschützt markierte Seite wird nie überschrieben. Und gelöscht wird nie. Eine Seite, die es nur auf der Ziel-Website gibt, bleibt einfach stehen.

<details>
<summary>Stolpersteine: Sicherheit geht vor Bequemlichkeit</summary>

* **Ein importiertes Handbuch startet immer mit der Sichtbarkeit „Alle Mitglieder“.** Das gilt auch, wenn das Paket „Öffentlich“ sagt. Ein Import soll nie versehentlich etwas veröffentlichen. Stelle die Sichtbarkeit danach von Hand höher.
* **Einzeln freigegebene Personen musst du neu setzen.** Sie reisen nicht mit.
* **Beim Aktualisieren bleiben die lokalen Pflegedaten erhalten.** Feedback-Zähler, Prüfdatum, Prüfintervall und prüfende Person der Ziel-Website werden nicht überschrieben.
* **Lies fremde Pakete vor dem Veröffentlichen.** Der Inhalt wird beim Import zwar wie jeder externe Inhalt bereinigt. Er bleibt aber ein Text, den jemand anderes geschrieben hat.

</details>

## Verwandte Seiten

* [Markdown importieren](markdown-importieren.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Reihenfolge: 4
* Textauszug: Ein Handbuch lässt sich als in sich vollständiges Paket exportieren und auf einer anderen Website wieder importieren; so gehst du vor.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
