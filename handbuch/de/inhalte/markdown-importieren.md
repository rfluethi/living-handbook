# Markdown importieren

Diese Anleitung bringt bestehende Markdown-Inhalte als Seiten in dein Handbuch. Als Quelle dienen ein eingefügter Entwurf, eine ZIP-Datei oder eine GitHub-Adresse. Bei GitHub geht beides: eine einzelne Datei oder ein ganzer Ordner samt Unterordnern.

<details>
<summary>Konzept: Wie der Import arbeitet</summary>

Der Import wandelt Markdown in HTML um. Daraus entstehen bearbeitbare Blöcke. Ein ```` ```mermaid ````-Codeblock wird zu einem live gerenderten Diagramm-Block. `<details>`-Abschnitte werden zu Details-Blöcken. Bilder aus einem `assets`-Ordner landen in der Mediathek. Zum Schluss läuft ein Nachbearbeitungsschritt. Er überträgt die Transport-Metadaten in die Felder der Seite. Er biegt außerdem Eltern-Seiten und interne `.md`-Links auf die richtigen Seiten um.

```mermaid
graph LR;
  A["Markdown-Datei"] --> B["Umwandlung zu HTML"];
  B --> C["Bereinigung"];
  C --> D["Bearbeitbare Blöcke"];
  D --> E["Nachbearbeitung: Metadaten, Eltern-Seiten, Links"];
  E --> F["Handbuch-Seite (Entwurf)"];
```

</details>

## Schritte

1. Öffne **Handbuch → Import**. Jede Quelle hat einen eigenen Reiter. Darin steht alles, was sie braucht: Feld, Optionen und ein Import-Knopf.
2. Wähle den passenden Reiter:
   * **Text einfügen:** einen Markdown-Entwurf einfügen, dann **Markdown importieren**. Ein eingefügter Entwurf erzeugt immer eine neue Seite.
   * **ZIP-Datei:** eine ZIP mit `.md`-Dateien hochladen, dann **ZIP importieren**. Die ZIP darf flach sein oder ein MkDocs-Projekt. Ein vorhandenes `mkdocs.yml` bestimmt dann Titel, Reihenfolge und Verschachtelung.
   * **GitHub:** eine GitHub-Adresse eintragen, dann **Von GitHub importieren**. Eine Datei-Adresse (`.../blob/...` oder `raw.githubusercontent.com`) importiert eine Seite. Eine Ordner-Adresse (`.../tree/...`) importiert alle `.md`-Dateien darunter, Unterordner eingeschlossen.
   * **Paket:** ein Export-Paket von einer anderen Website, siehe [Handbuch umziehen](handbuch-umziehen.md).
   * **App-Handbuch:** lädt genau dieses Handbuch von GitHub, siehe [App-Handbuch laden](../erste-schritte/app-handbuch-laden.md).
3. Wähle das **Ziel-Handbuch**, in das die Seiten gehören.
4. Starte den Import und lies die Ergebnisliste. Sie meldet Warnungen, etwa eine nicht gefundene Eltern-Seite.
5. Prüfe die entstandenen Seiten und **veröffentliche** sie. Importierte Seiten sind zunächst Entwürfe. Nur das App-Handbuch wird direkt veröffentlicht.

> **Screenshot folgt:** Die Import-Seite mit den Reitern (Text einfügen, ZIP-Datei, GitHub, Paket, App-Handbuch), geöffnet ist der GitHub-Reiter mit dem Adressfeld.

## Ergebnis

Für jede Markdown-Datei existiert eine Handbuch-Seite. Bei einem Ordner-Import wird die Ordnerstruktur zur Seitenhierarchie. Dabei gilt: Ein Ordner mit `index.md` oder `README.md` wird durch diese Datei vertreten. Ein Ordner ohne beides bekommt eine automatisch erzeugte Seite mit der Kartenliste seines Inhalts. Die Bereichsseite erhält ihre Adresse vom Ordnernamen, nicht vom Dateinamen `readme`.

## Reihenfolge und erneuter Import

* **Reihenfolge:** Eine Seite mit der Zeile `Reihenfolge:` in den Transport-Metadaten wird nach dieser Zahl einsortiert. Seiten ohne Zahl folgen dahinter, alphabetisch. Nummeriere also nur, was eine feste Position braucht. Nimm kleine Zahlen (1, 2, 3).
* **Erneuter Import derselben Quelle aktualisiert die bestehenden Seiten.** Es entstehen keine Duplikate. Adresse und Veröffentlichungsstatus bleiben erhalten. Titel, Inhalt und Eltern-Seite werden aus der Quelle aufgefrischt. Achtung bei einem Ordner-Import: Eine von Hand gesetzte Eltern-Seite wird zurückgesetzt. Das Repository bestimmt die Struktur.

## Transport-Metadaten: die Feldliste

Jede Datei kann am Ende einen Metadaten-Abschnitt tragen. Der Import überträgt ihn in die Felder der Seite. Erkannt wird die deutsche Überschrift `## Transport-Metadaten`. Darüber steht der Seiteninhalt. Die erste `#`-Überschrift wird der Titel.

```markdown
## Transport-Metadaten
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

Alle Zeilen sind freiwillig. `Zielgruppe` darf mehrere Werte tragen, durch Komma getrennt. `Eltern-Seite` wird über den Titel zugeordnet. Das passiert erst, wenn alle Seiten des Imports existieren. Die Eltern-Seite darf also später in derselben Lieferung stehen. Mit einer Zeile `Handbuch:` kann eine Datei ihr Ziel-Handbuch selbst bestimmen. Sie übersteuert die Auswahl auf der Import-Seite.

<details>
<summary>Stolpersteine: Grenzen des Imports</summary>

* **Höchstens 200 Dateien pro Ordner-Import.** Ist die Grenze erreicht, sagt es die Ergebnisliste. Importiere die restlichen Unterordner einzeln.
* **GitHub erlaubt ohne Anmeldung etwa 60 Anfragen pro Stunde.** Ein großer Import kann das Limit erreichen. Der Import meldet es, und du versuchst es später erneut. Das Plugin liest nur die Adressen, die du selbst einträgst. Es sendet nichts nach draußen.
* **Private Repositories** lassen sich nicht live abrufen. Exportiere sie als ZIP und importiere die Datei. Eine MkDocs-Struktur bleibt dabei erhalten.
* **ZIP-Grenzen:** höchstens 2000 Einträge, 5 MB pro Datei, 100 MB entpackt. Die wirkliche Obergrenze bleibt die PHP-Konfiguration deines Servers.

</details>

## Verwandte Seiten

* [Inhalte schreiben](inhalte-schreiben.md)
* [GitHub-Synchronisation](github-synchronisation.md)
* [Handbuch umziehen](handbuch-umziehen.md)
* [Technische Details in der Entwickler-Dokumentation](https://github.com/rfluethi/living-handbook/blob/main/docs/import-and-sync.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Reihenfolge: 2
* Textauszug: Diese Anleitung bringt bestehende Markdown-Inhalte als Seiten in dein Handbuch: eingefügt, als ZIP-Datei oder von einer GitHub-Adresse.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
