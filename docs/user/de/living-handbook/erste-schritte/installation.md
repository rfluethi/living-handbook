# Installation

So machst du das Plugin einsatzbereit. Der Weg führt von der ZIP-Datei bis zur ersten sichtbaren Handbuch-Seite.

<details>
<summary>Voraussetzungen: Was deine Website mitbringen muss</summary>

* **WordPress 6.7 oder neuer, mit einem Block-Theme.** Ein Block-Theme ist ein neueres WordPress-Design, das sich vollständig im Website-Editor bearbeiten lässt. Ein Beispiel ist das mitgelieferte Theme Twenty Twenty-Five. Mit einem älteren, klassischen Theme werden die Handbuch-Seiten nicht richtig dargestellt.
* **PHP 8.1 oder neuer.** Die PHP-Version deiner Website steht unter **Werkzeuge → Website-Zustand → Bericht**.
* **Eine einzelne WordPress-Website.** Betreibst du ein Netzwerk aus mehreren Websites (Multisite), aktiviere das Plugin pro Website, nicht fürs ganze Netzwerk.

</details>

## Schritte

1. Öffne in WordPress **Plugins → Installieren → Plugin hochladen**.
2. Wähle die Datei `living-handbook.zip` aus und klicke auf **Jetzt installieren**.
3. Klicke auf **Plugin aktivieren**. Im Menü links erscheint der neue Eintrag **Handbuch**.
4. Funktionieren Handbuch-Adressen wie `/handbook/...` nicht sofort? Dann öffne einmal **Einstellungen → Permalinks**. Das Öffnen genügt, du musst nichts speichern.

## Ergebnis

Die Aktivierung richtet drei Dinge für dich ein:

* Eine eigene Seiten-Art für Handbuch-Seiten, getrennt von deinen normalen Seiten und Beiträgen.
* Vier Gruppen zum Einordnen der Seiten: Seitentyp, Thema, verantwortliche Rolle und Zielgruppe. Alle sind mit Startwerten gefüllt.
* Eine normale WordPress-Seite namens **Handbuch**. Sie zeigt später die Liste deiner Handbücher. Du kannst sie verschieben, umgestalten oder ersetzen.

Den Erfolg erkennst du an zwei Dingen: Im Verwaltungsmenü steht der Eintrag **Handbuch**, und die Seite **Handbuch** ist auf der Website erreichbar. Sie ist anfangs leer. Das ist normal, denn es gibt noch kein Handbuch.

<details>
<summary>Stolpersteine: Wenn etwas nicht klappt</summary>

* **Die Handbuch-Seiten sehen kaputt oder unformatiert aus:** Dein Theme ist vermutlich kein Block-Theme. Prüfe das unter **Design**. Fehlt dort der Eintrag **Editor**, ist das Theme klassisch.
* **Handbuch-Adressen liefern „Seite nicht gefunden“:** Öffne einmal **Einstellungen → Permalinks**. Das erneuert die Adress-Regeln.
* **Du arbeitest mit dem Quellcode statt mit der fertigen ZIP-Datei:** Dann sind zusätzliche Schritte nötig. Sie stehen in der [Entwickler-Dokumentation auf GitHub](https://github.com/rfluethi/living-handbook#development).

</details>

## Verwandte Seiten

* [Erstes Handbuch anlegen](erstes-handbuch-anlegen.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Einstieg
* Zielgruppe: Alle Mitglieder
* Eltern-Seite: Erste Schritte
* Reihenfolge: 1
* Textauszug: So machst du das Plugin einsatzbereit, von der ZIP-Datei bis zur ersten sichtbaren Handbuch-Seite in deiner WordPress-Installation.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
