# Installation

So machst du das Plugin einsatzbereit. Der Weg führt von der ZIP-Datei bis zur ersten sichtbaren Handbuch-Seite.

<details>
<summary>Voraussetzungen: Was deine Website mitbringen muss</summary>

* **WordPress 6.7 oder neuer, mit einem Block-Theme.** Ein Beispiel ist Twenty Twenty-Five. Die Handbuch-Seiten werden aus Block-Templates gebaut. Ein klassisches Theme kann sie nicht darstellen.
* **PHP 8.1 oder neuer.** Die PHP-Version deiner Website steht unter **Werkzeuge → Website-Zustand → Bericht**.
* **Eine Einzelinstallation.** Living Handbook ist für eine einzelne Website gebaut. Auf einem Multisite-Netzwerk aktivierst du es pro Website, nicht netzwerkweit.

</details>

## Schritte

1. Öffne in WordPress **Plugins → Installieren → Plugin hochladen**.
2. Wähle die Datei `living-handbook.zip` aus und klicke auf **Jetzt installieren**.
3. Klicke auf **Plugin aktivieren**. Im Menü links erscheint der neue Eintrag **Handbuch**.
4. Funktionieren Handbuch-Adressen wie `/handbook/...` nicht sofort? Dann öffne einmal **Einstellungen → Permalinks**. Das Öffnen genügt, du musst nichts speichern.

## Ergebnis

Die Aktivierung richtet drei Dinge für dich ein:

* Sie registriert den Inhaltstyp „Handbuch-Seite“. Außerdem legt sie die vier Schlagwort-Gruppen an: Seitentyp, Thema, verantwortliche Rolle und Zielgruppe. Alle sind mit Startwerten gefüllt.
* Sie erstellt eine normale WordPress-Seite namens **Handbuch** mit dem Übersichts-Block darauf. So zeigt eine frische Installation etwas an. Du kannst diese Seite später verschieben, umgestalten oder ersetzen.
* Sie aktualisiert die Permalink-Regeln. Die Handbuch-Adressen funktionieren damit sofort.

Den Erfolg erkennst du an zwei Dingen: Im Admin-Menü steht der Eintrag **Handbuch**, und die Seite **Handbuch** ist im Frontend erreichbar. Sie ist anfangs leer. Das ist normal, denn es gibt noch kein Handbuch.

<details>
<summary>Stolpersteine: Wenn etwas nicht klappt</summary>

* **Die Handbuch-Seiten sehen kaputt oder unformatiert aus:** Dein Theme ist vermutlich kein Block-Theme. Prüfe das unter **Design**. Fehlt dort der Eintrag **Website-Editor**, ist das Theme klassisch. Die Handbuch-Templates greifen dann nicht.
* **Handbuch-Adressen liefern „Seite nicht gefunden“:** Öffne einmal **Einstellungen → Permalinks**. Das erneuert die Adress-Regeln.
* **Du baust das Plugin aus dem Quellcode statt aus der Release-ZIP:** Führe vorher `composer install` aus. Sonst fehlt der Ordner `vendor/`. Die Release-ZIP bringt ihn schon mit. Details stehen in der [Entwickler-Dokumentation auf GitHub](https://github.com/rfluethi/living-handbook#development).

</details>

## Verwandte Seiten

* [Erstes Handbuch anlegen](erstes-handbuch-anlegen.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Reihenfolge: 1
* Textauszug: So machst du das Plugin einsatzbereit, von der ZIP-Datei bis zur ersten sichtbaren Handbuch-Seite in deiner WordPress-Installation.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
