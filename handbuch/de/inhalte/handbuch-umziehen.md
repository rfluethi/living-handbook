# Handbuch umziehen

Ein Handbuch, oder ein einzelner Bereich davon, lässt sich als Paket exportieren und auf einer anderen Website mit Living Handbook wieder importieren. Diese Anleitung führt durch Export und Import.

<details>
<summary>Konzept: Was ein Paket ist</summary>

Ein Paket ist eine einzelne ZIP-Datei mit einer Beschreibungsdatei und den Medien. Es enthält die Konfiguration des Handbuchs (Sichtbarkeit und erlaubte Rollen), jede Seite samt Hierarchie, die vier Schlagwort-Gruppen, die Prüfdaten und die Bilder. Es ist in sich vollständig: Die Ziel-Website braucht keinen Kontakt zur Quell-Website. Bewusst nicht enthalten sind die einzeln freigegebenen Personen (das sind E-Mail-Adressen, und ein Paket ist eine Datei, die weitergegeben wird) sowie lokale Betriebsdaten wie Feedback-Zähler.

</details>

## Exportieren

1. Öffne **Handbuch → Export**.
2. Wähle das **Handbuch**. Das zweite Feld listet dann seine **Bereiche**; lass es auf „das ganze Handbuch“ stehen oder wähle einen Bereich (er exportiert mitsamt seinen Unterseiten).
3. Klicke auf **Paket exportieren**; die ZIP-Datei wird heruntergeladen.

## Importieren

1. Öffne auf der Ziel-Website **Handbuch → Import**, Reiter **Paket**. Dafür brauchst du dort die Rolle Content-Manager.
2. Lade die ZIP-Datei hoch.
3. Wähle, was mit schon vorhandenen Seiten passieren soll:
   * **Überspringen** (Standard): Vorhandene Seiten bleiben unangetastet, nur neue werden angelegt.
   * **Aktualisieren:** Titel, Inhalt, Struktur und Schlagworte vorhandener Seiten werden aus dem Paket aufgefrischt.
   * **Immer neu anlegen:** Jede Seite im Paket wird eine neue Seite, nützlich zum Klonen in ein zweites Handbuch.
4. Wähle unter **Importieren in**, wo die Seiten landen: standardmäßig im Handbuch aus dem Paket (es wird angelegt, falls es fehlt), oder in einem bestehenden Handbuch deiner Wahl.
5. Starte den Import und lies den Bericht: wie viele Seiten angelegt, aktualisiert, übersprungen oder geschützt wurden, und was sich nicht zuordnen liess.

## Ergebnis

Die Seiten stehen auf der Ziel-Website, interne Links zeigen auf die neuen Seiten, und von GitHub synchronisierte Seiten setzen ihren Abgleich dort fort. Zwei Schutzregeln gelten immer: Eine als geschützt markierte Seite wird nie überschrieben, und **gelöscht wird nie**; eine Seite, die es nur auf der Ziel-Website gibt, bleibt einfach stehen.

<details>
<summary>Stolpersteine: Sicherheit geht vor Bequemlichkeit</summary>

* **Ein importiertes Handbuch startet immer mit der Sichtbarkeit „Alle Mitglieder“**, selbst wenn das Paket „Öffentlich“ sagt. Ein Import soll nie versehentlich etwas veröffentlichen; stelle die Sichtbarkeit danach von Hand höher.
* **Einzeln freigegebene Personen musst du neu setzen**; sie reisen nicht mit.
* **Beim Aktualisieren bleiben die lokalen Pflegedaten erhalten:** Feedback-Zähler, Prüfdatum, Prüfintervall und prüfende Person der Ziel-Website werden nicht überschrieben.
* **Lies fremde Pakete vor dem Veröffentlichen.** Der Inhalt wird beim Import zwar wie jeder externe Inhalt bereinigt, aber er bleibt ein Text, den jemand anderes geschrieben hat.

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
