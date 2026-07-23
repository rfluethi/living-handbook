# GitHub-Synchronisation

Eine Seite kann dauerhaft aus einem GitHub-Repository gepflegt werden. Die Markdown-Datei dort ist das Original. WordPress zeigt immer den aktuellen Stand. Diese Anleitung richtet das ein. Sie erklärt auch, wann die Seite aktualisiert wird.

<details>
<summary>Konzept: Eine Quelle pro Seite</summary>

Jede Seite hat eine Quelle. Der Normalfall ist **In WordPress gepflegt**: ganz normal bearbeitbar. Die Alternative ist **Von GitHub synchronisiert**. Bei einer synchronisierten Seite ist der Inhaltseditor gesperrt. So kann niemand Änderungen machen, die der nächste Abgleich überschreiben würde. Eine Spalte in der Seitenliste zeigt die Quelle jeder Seite. Der Block „GitHub-Quellenhinweis“ kann die öffentliche Seite als extern gepflegt kennzeichnen. Dieses Handbuch hier ist selbst so eine synchronisierte Quelle.

</details>

## Schritte

1. Öffne die Seite im Editor und suche die Box **Quelle**.
2. Stelle die Quelle auf **Von GitHub synchronisiert**.
3. Trage die Adresse der Markdown-Datei ein (`raw.githubusercontent.com/...`). Aus Sicherheitsgründen sind nur Adressen von erlaubten Hosts über HTTPS zugelassen.
4. Speichere. Schon beim Speichern wird die Datei abgeholt und die Seite neu aufgebaut.

## Ergebnis

Die Seite zeigt den Stand aus dem Repository. Künftig aktualisiert sie sich selbst. Es gibt drei Auslöser:

```mermaid
graph TD;
  A["Beim Speichern der Seite"] --> S["Abgleich: Datei holen und neu rendern"];
  B["Von Hand: Knopf 'Jetzt synchronisieren'"] --> S;
  C["Nach Zeitplan (WordPress-Cron)"] --> S;
  S --> D["Seite zeigt aktuellen Stand"];
  S -->|"Fehler"| E["Seite behält alten Stand, Hinweis im Backend"];
```

Den Zeitplan stellst du unter **Handbuch → Einstellungen** ein. Zur Wahl stehen: aus, stündlich, zweimal täglich, täglich oder wöchentlich. Wöchentlich ist der Standard auf einer neuen Installation. „Aus“ heißt nur: kein Zeitplan. Beim Speichern und per Knopf wird trotzdem abgeglichen. Ein großes Handbuch wird in Etappen abgeglichen, nie alles auf einmal.

<details>
<summary>Stolpersteine: Wenn ein Abgleich fehlschlägt</summary>

Ein fehlgeschlagener Abgleich leert die Seite nie. Sie behält ihren letzten Stand. Der Fehler wird an der Seite vermerkt. Ein Hinweis im Backend sagt dir, wie viele Seiten betroffen sind. Den Grund findest du auf der Seite selbst: in der Box **Quelle** unter „Letzter Abgleich“. Typische Gründe sind ein GitHub-Limit, ein HTTP-Fehler oder ein nicht erreichbarer Host. Für private Repositories funktioniert der Live-Abgleich nicht. Nimm dort den [ZIP-Import](markdown-importieren.md).

</details>

<details>
<summary>Hintergrund: Was gespeichert wird und warum</summary>

Synchronisierte Seiten werden als fertig gerendertes, bereinigtes HTML gespeichert, nicht als bearbeitbare Blöcke. Der Grund: Der zeitgesteuerte Abgleich läuft ohne Browser. Nur der Browser kann HTML in Blöcke umwandeln. Einen Webhook gibt es nicht. WordPress holt die Datei selbst ab (Pull). Details für Entwickler stehen in der [Entwickler-Dokumentation zum Import und Sync](https://github.com/rfluethi/living-handbook/blob/main/docs/import-and-sync.md).

</details>

## Verwandte Seiten

* [Markdown importieren](markdown-importieren.md)
* [Inhalte schreiben](inhalte-schreiben.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Reihenfolge: 3
* Textauszug: Eine Seite kann dauerhaft aus einem GitHub-Repository gepflegt werden; diese Anleitung richtet das ein und erklärt die drei Auslöser des Abgleichs.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
