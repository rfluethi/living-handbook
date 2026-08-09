# Handbuch als Website weitergeben

Du willst ein Handbuch jemandem geben, der kein WordPress hat und keinen Zugang zu dieser Website? Dann exportierst du es als Website: eine ZIP-Datei mit fertigen HTML-Seiten. Wer sie auspackt, öffnet `index.html` per Doppelklick und liest das Handbuch im Browser, ohne Server, ohne Installation, ohne Internet.

<details>
<summary>Konzept: Der Unterschied zum Paket</summary>

Es gibt zwei Exporte, und sie haben verschiedene Ziele.

Das **Paket** bringt ein Handbuch auf eine andere WordPress-Website, wo Living Handbook läuft. Es trägt die Seiten als Daten, samt Schlagworten, Prüfdaten und Sichtbarkeit, damit dort wieder ein lebendiges Handbuch daraus wird. Siehe [Handbuch umziehen](handbuch-umziehen.md).

Die **Website** ist eine Momentaufnahme zum Lesen. Sie trägt fertige Seiten, keine Daten: eine Datei je Seite, die Bilder daneben, eine Seitenliste und eine Suche. Zurück in ein Handbuch verwandeln lässt sie sich nicht.

Typische Fälle: eine Prüfung oder ein Audit, das eine Fassung zum Stichtag will; ein externes Team, dem du kein Konto geben willst; eine Ablage fürs Archiv; ein Laptop ohne Netz.

</details>

## So geht es

1. Öffne **Handbuch → Export**. Der Abschnitt heisst **Als Website exportieren**, unterhalb des Paket-Exports.
2. Wähle das **Handbuch**, und im zweiten Feld wahlweise einen einzelnen Bereich.
3. Klicke auf **Website erzeugen**. Der Fortschritt zählt die Seiten mit, denn gebaut wird in mehreren Durchgängen: der Browser holt sich einen nach dem anderen ab, damit auch ein grosses Handbuch nicht in eine Zeitüberschreitung läuft.
4. Wenn es fertig ist, erscheint ein Knopf mit der Dateigrösse. Damit lädst du die ZIP-Datei herunter.

## Ergebnis

In der ZIP-Datei liegt eine vollständige kleine Website:

* **`index.html`**: die Startseite mit allen Seiten des Handbuchs.
* **Eine Datei je Seite**, in Ordnern, die der Gliederung folgen. Eine Unterseite von „Pflege" liegt also unter `pflege/`.
* **`assets/`**: die Gestaltung, die Bilder und der Suchindex.
* **`README.txt`**: eine kurze Notiz, was die Datei ist, für den Fall, dass sie in einem Jahr jemand ohne Zusammenhang findet.

Enthalten sind die Seiten, die **du** lesen darfst. Links zwischen Handbuch-Seiten führen zu den Dateien daneben, Bilder liegen mit im Paket, und die Suche oben rechts durchsucht alle Seiten im Browser. Links nach draussen bleiben, was sie waren.

<details>
<summary>Stolpersteine: Was eine Kopie nicht kann</summary>

* **Eine statische Kopie hat keinen Zugriffsschutz.** Wer die Datei hat, liest jede Seite darin. Behandle sie wie ein ausgedrucktes Handbuch, nicht wie einen Zugang.
* **Sie veraltet ab der ersten Minute.** Nichts an ihr aktualisiert sich. Für dauerhaft aktuelle Inhalte gibt es Zugänge, nicht Exporte.
* **Kommentare und die Frage „War das hilfreich?" fehlen.** Beides braucht einen Server, und es gibt keinen.
* **Filter nach Einordnung gibt es nicht**, nur die Suche. Die Filterleiste lebt von Abfragen an WordPress.
* **Personen stehen nicht darin.** Die Fusszeile einer Seite zeigt die Daten und die verantwortliche Rolle, aber keine Namen und keine Profilbilder: die Datei geht nach draussen, die Namen bleiben hier.
* **Ein sehr grosses Handbuch wird eine grosse Datei.** Die Bilder machen den Löwenanteil aus. Grenze im Zweifel auf einen Bereich ein.

</details>

## Verwandte Seiten

* [Handbuch umziehen](handbuch-umziehen.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Inhalte
* Zielgruppe: Alle Mitglieder, Technik
* Eltern-Seite: Inhalte
* Reihenfolge: 5
* Textauszug: Ein Handbuch als fertige HTML-Website exportieren, für Leserinnen und Leser ohne WordPress und ohne Zugang zu dieser Website.
* Letzte Aktualisierung: 2026-08-09
* Letzte Prüfung: 2026-08-09
* Prüfintervall: 180 Tage
