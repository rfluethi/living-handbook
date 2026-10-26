# Technische Dokumentation

Für alle, die das Plugin installieren, gestalten oder erweitern. Die englische Fassung liegt daneben unter [`docs/technical/en/`](https://github.com/rfluethi/living-handbook/blob/main/docs/technical/en/living-handbook-technical/README.md).

## Lesereihenfolge

1. [Code-Übersicht](code-uebersicht.md): ein Rundgang in Alltagssprache durch den Aufbau des Plugins. Setzt nicht voraus, dass du je ein WordPress-Plugin geschrieben hast.
2. [Erste Schritte](erste-schritte.md): von der Installation bis zur ersten Seite, die Besuchende sehen.
3. [Blöcke](bloecke.md): was jeder Block tut, wo er rendert, und was er einstellen lässt.
4. [Templates](templates.md): die zwei mitgelieferten Seitenlayouts und wie du sie umbaust.
5. [Anpassung](anpassung.md): Farben, Klassen, und was du aus Gründen der Barrierefreiheit nicht entfernen solltest.
6. [Architektur](architektur.md): die knappe Fassung für Entwicklerinnen und Entwickler.
7. [Hooks](hooks.md): die Filter und Aktionen, mit denen sich das Plugin erweitern lässt.
8. [Import und Sync](import-und-sync.md): Markdown, GitHub, Bündel, App-Handbuch.
9. [Wartung](wartung.md): der Prüfzyklus, die Spalten und Filter der Seitenliste.
10. [Meta-Felder](meta-felder.md): jedes Custom Field, das das Plugin liest oder schreibt, mit Typ, Herkunft und Sichtbarkeit.
11. [REST-Routen](rest-routen.md): jede Route unter `living-handbook/v1`, mit Methode, Berechtigung und Parametern, und was das Plugin an den Routen von WordPress ändert.

## Die eine Regel

Jedes Lesen von Handbuch-Inhalt geht durch `AccessController::can_view_post()`. Wer einen neuen Weg ergänzt, Handbuchseiten zu lesen oder aufzulisten, führt ihn durch diese Methode. Sie ist die eine Stelle, die über Sichtbarkeit entscheidet, sie schlägt im Zweifel zu, nicht auf, und sie zu vergessen ist der eine Fehler, der Inhalt lecken lässt.

## Transport-Metadaten
* Seitentyp: Bereichs-Übersicht
* Zielgruppe: Technik
* Reihenfolge: 1
* Letzte Aktualisierung: 2026-10-26
