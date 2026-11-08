# Handbuch als Website weitergeben

Du willst ein Handbuch jemandem geben, der kein WordPress hat und keinen Zugang zu dieser Website? Dann exportierst du es als Website: eine ZIP-Datei mit fertigen HTML-Seiten. Wer sie auspackt, öffnet `index.html` per Doppelklick und liest das Handbuch im Browser, ohne Server, ohne Installation, ohne Internet.

<details>
<summary>Konzept: Der Unterschied zum Bündel</summary>

Es gibt zwei Exporte, und sie haben verschiedene Ziele.

Das **Bündel** bringt ein Handbuch auf eine andere WordPress-Website, wo Living Handbook läuft. Es trägt die Seiten als Daten, samt Schlagworten, Prüfdaten und Sichtbarkeit, damit dort wieder ein lebendiges Handbuch daraus wird. Siehe [Handbuch umziehen](handbuch-umziehen.md).

Die **Website** ist eine Momentaufnahme zum Lesen. Sie trägt fertige Seiten, keine Daten: eine Datei je Seite, die Bilder daneben, eine Seitenliste und eine Suche. Zurück in ein Handbuch verwandeln lässt sie sich nicht.

Typische Fälle: eine Prüfung oder ein Audit, das eine Fassung zum Stichtag will; ein externes Team, dem du kein Konto geben willst; eine Ablage fürs Archiv; ein Laptop ohne Netz.

</details>

## So geht es

1. Öffne **Handbuch → Export**. Der Abschnitt heißt **Als Website exportieren**, unterhalb des Bündel-Exports.
2. Wähle das **Handbuch**, und im zweiten Feld wahlweise einen einzelnen Bereich. Zur Wahl stehen die Handbücher, die du lesen darfst. Einen Bereich, den du nicht sehen darfst, kannst du nicht exportieren.
3. Wähle die **Gestaltung**. Zur Wahl stehen vier:
   * **Wie diese Website:** dein Theme reist mit. Farben, Schriften und Abstände kommen aus seiner `theme.json`, dazu kommen deine Einstellungen unter Darstellung. Die Schriftdateien, die auf dieser Website liegen, kommen ins ZIP; eine Schrift von einem fremden Server behält ihre Adresse. Das ist die Voreinstellung.
   * **Schlicht, neutral:** ohne Hausfarben. Meist die bessere Wahl für eine Kopie, die das Team verlässt.
   * **Dunkel:** helle Schrift auf dunklem Grund, zum Lesen am Abend.
   * **Papier, zum Drucken:** Serifenschrift, schmalere Spalte, hoher Kontrast. Gedruckt wird jede Gestaltung sauber: Seitenliste, Suchfeld und Fußzeile fallen auf Papier weg, und bei Links nach draußen steht die Adresse dahinter.
4. Klicke auf **Website erzeugen**. Der Fortschritt zählt die Seiten mit, denn gebaut wird in mehreren Durchgängen: der Browser holt sich einen nach dem anderen ab, damit auch ein großes Handbuch nicht in eine Zeitüberschreitung läuft.
5. Wenn es fertig ist, erscheint ein Knopf mit der Dateigröße. Damit lädst du die ZIP-Datei herunter.

## Ergebnis

In der ZIP-Datei liegt eine vollständige kleine Website:

* **`index.html`**: die Startseite mit allen Seiten des Handbuchs.
* **Eine Datei je Seite**, in Ordnern, die der Gliederung folgen. Eine Unterseite von „Pflege" liegt also unter `pflege/`.
* **`assets/`**: die Gestaltung, die Bilder und der Suchindex.
* **`README.txt`**: eine kurze Notiz, was die Datei ist, für den Fall, dass sie in einem Jahr jemand ohne Zusammenhang findet.

Enthalten sind die Seiten, die **du** lesen darfst. Links zwischen Handbuch-Seiten führen zu den Dateien daneben, Bilder liegen mit in der ZIP-Datei, und die Suche oben rechts durchsucht alle Seiten im Browser. Links nach draußen bleiben, was sie waren.

**Die Seiten entstehen aus derselben Vorlage wie auf der Website.** Hast du im Website-Editor die Handbuch-Seite umgebaut, etwa die Navigation verschoben oder die Abzeichen woanders hingestellt, sieht der Export genauso aus. Nur was ohne Server nicht funktioniert, fällt vorher heraus: Kopf- und Fußbereich deines Themes, die Feedback-Frage, die Kommentare und die Schnellsuche in der Seitenleiste. Das Inhaltsverzeichnis wird beim Export gefüllt statt im Browser, es steht also auch ohne JavaScript und auf Papier.

Zwei weitere Dinge funktionieren wie auf der Website: **Bilder und Diagramme vergrößern sich per Klick**, und **Mermaid-Diagramme werden gezeichnet**. Die Bibliothek dafür ist 3,5 MB groß und reist nur mit, wenn im Export überhaupt ein Diagramm vorkommt; geladen wird sie nur auf den Seiten, die eins haben.

<details>
<summary>Stolpersteine: Was eine Kopie nicht kann</summary>

* **Eine statische Kopie hat keinen Zugriffsschutz.** Wer die Datei hat, liest jede Seite darin. Behandle sie wie ein ausgedrucktes Handbuch, nicht wie einen Zugang.
* **Sie veraltet ab der ersten Minute.** Nichts an ihr aktualisiert sich. Für dauerhaft aktuelle Inhalte gibt es Zugänge, nicht Exporte.
* **Kommentare und die Frage „War das hilfreich?" fehlen.** Beides braucht einen Server, und es gibt keinen.
* **Filter nach Einordnung gibt es nicht**, nur die Suche. Die Filterleiste lebt von Abfragen an WordPress.
* **Personen stehen nicht darin.** Die Fußzeile einer Seite zeigt die Daten und die verantwortliche Rolle (die Rolle nur, solange die Gruppe Verantwortung eingeschaltet ist), aber keine Namen und keine Profilbilder: die Datei geht nach draußen, die Namen bleiben hier.
* **Passwortgeschützte Seiten fehlen**, denn ihr Passwortformular funktioniert ohne die Website nicht; ein Link darauf führt weiterhin auf die Website.
* **Ein sehr großes Handbuch wird eine große Datei.** Die Bilder machen den Löwenanteil aus. Grenze im Zweifel auf einen Bereich ein.

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
* Letzte Aktualisierung: 2026-11-08
* Letzte Prüfung: 2026-08-09
* Prüfintervall: 180 Tage
