# Installation

So machst du das Plugin einsatzbereit: von der ZIP-Datei bis zur ersten sichtbaren Handbuch-Seite in deiner WordPress-Installation.

<details>
<summary>Voraussetzungen: Was deine Website mitbringen muss</summary>

* **WordPress 6.7 oder neuer, mit einem Block-Theme** (zum Beispiel Twenty Twenty-Five). Einstiegsseite, Einzelseiten und Navigation werden aus Block-Templates gebaut; ein klassisches Theme rendert sie nicht.
* **PHP 8.1 oder neuer.** Die PHP-Version deiner Website siehst du in WordPress unter **Werkzeuge → Website-Zustand → Bericht**.
* **Eine Einzelinstallation.** Living Handbook ist für eine einzelne Website gebaut. Auf einem Multisite-Netzwerk aktivierst du es pro Website, nicht netzwerkweit.

</details>

## Schritte

1. Öffne in WordPress **Plugins → Installieren → Plugin hochladen**.
2. Wähle die Datei `living-handbook.zip` aus und klicke auf **Jetzt installieren**.
3. Klicke auf **Plugin aktivieren**; im Menü links erscheint der neue Eintrag **Handbuch**.
4. Öffne einmal **Einstellungen → Permalinks**, falls Handbuch-Adressen wie `/handbook/...` nicht sofort funktionieren; das Öffnen genügt, du musst nichts speichern.

## Ergebnis

Die Aktivierung richtet drei Dinge für dich ein:

* Sie registriert den Inhaltstyp „Handbuch-Seite“ und legt die vier Schlagwort-Gruppen an (Seitentyp, Thema, verantwortliche Rolle, Zielgruppe), bereits mit Startwerten gefüllt.
* Sie erstellt eine normale WordPress-Seite namens **Handbuch** mit dem Übersichts-Block darauf. So zeigt eine frische Installation etwas an statt einer leeren Liste. Du kannst diese Seite später verschieben, umgestalten oder ersetzen.
* Sie aktualisiert die Permalink-Regeln, damit die Handbuch-Adressen sofort funktionieren.

Du erkennst den Erfolg daran, dass im Admin-Menü der Eintrag **Handbuch** steht und die Seite **Handbuch** im Frontend erreichbar ist. Sie ist anfangs noch leer, das ist normal: Es gibt ja noch kein Handbuch.

<details>
<summary>Stolpersteine: Wenn etwas nicht klappt</summary>

* **Die Handbuch-Seiten sehen kaputt oder unformatiert aus:** Dein Theme ist vermutlich kein Block-Theme. Prüfe unter **Design**, ob dort **Website-Editor** steht; fehlt er, ist das Theme klassisch und die Handbuch-Templates greifen nicht.
* **Handbuch-Adressen liefern „Seite nicht gefunden“:** Öffne einmal **Einstellungen → Permalinks**. Das erneuert die Adress-Regeln.
* **Du baust das Plugin aus dem Quellcode statt aus der Release-ZIP:** Dann musst du vorher `composer install` ausführen, damit der Ordner `vendor/` vorhanden ist. Die Release-ZIP bringt ihn schon mit. Details in der [Entwickler-Dokumentation auf GitHub](https://github.com/rfluethi/living-handbook#development).

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
