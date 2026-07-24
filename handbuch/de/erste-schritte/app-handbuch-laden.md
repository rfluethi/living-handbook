# App-Handbuch laden

Dieses Handbuch ist die Dokumentation von Living Handbook. Du kannst es mit einem Klick in deine eigene Installation laden. Damit hast du die aktuelle Anleitung direkt in WordPress. Zugleich siehst du ein fertiges, gefülltes Handbuch als Beispiel.

<details>
<summary>Voraussetzungen: Was du brauchst</summary>

* Ein Benutzerkonto, das Handbuch-Seiten bearbeiten darf.
* Ein Handbuch als Ziel, zum Beispiel eines mit dem Namen „App-Handbuch“. Lege es vorher an, siehe [Erstes Handbuch anlegen](erstes-handbuch-anlegen.md). Bestimme dort auch, wer es lesen darf.
* Deine Website muss GitHub erreichen können. GitHub ist die Website, auf der die Texte dieses Handbuchs öffentlich liegen.

</details>

## Schritte

1. Öffne **Handbuch → Import** und wechsle auf den Reiter **App-Handbuch**.
2. Wähle unter **Laden in** das Ziel-Handbuch aus.
3. Klicke auf **App-Handbuch laden**.
4. Lies die Ergebnisliste. Sieh dir danach das geladene Handbuch auf der Website an.

> **Screenshot folgt:** Die Import-Seite mit geöffnetem Reiter „App-Handbuch“, markiert sind die Auswahl „Laden in“ und der Lade-Knopf.

## Ergebnis

Alle Seiten dieses Handbuchs stehen jetzt in deinem gewählten Handbuch, samt Navigation und Bildern. Sie sind sofort veröffentlicht. Wer die Seiten sehen darf, bestimmt allein die Sichtbarkeit des Ziel-Handbuchs. Steht es auf „Alle Mitglieder“, lesen nur Angemeldete. Öffentlich wird es erst, wenn du das Handbuch auf „Öffentlich“ stellst.

<details>
<summary>Konzept: Warum das Handbuch aktuell bleibt</summary>

Die Texte dieses Handbuchs werden öffentlich auf GitHub gepflegt. Der Reiter **App-Handbuch** lädt sie von dort, passend zur Sprache deiner WordPress-Verwaltung. Technisch ist das ein normaler [Import von GitHub](../inhalte/markdown-importieren.md) mit fest hinterlegter Adresse. Die geladenen Seiten bleiben [mit GitHub verbunden](../inhalte/github-synchronisation.md). Wird das Handbuch dort verbessert, erreicht dich die Verbesserung beim nächsten Laden. Geladen wird nie automatisch, nur wenn du es anstößt.

Das Handbuch gibt es auf Deutsch und Englisch. Der Reiter wählt die Sprache deiner Verwaltung. Willst du die jeweils andere Sprache, importiere ihren Ordner von Hand über den [GitHub-Import](../inhalte/markdown-importieren.md).

</details>

<details>
<summary>Stolpersteine: Zwei Eigenheiten dieses Imports</summary>

* **Die Seiten sind sofort veröffentlicht.** Jeder andere Import legt erst unveröffentlichte Entwürfe an. Hier ist das anders, mit Absicht: Es ist die geprüfte Dokumentation des Plugins, und wer sie sehen darf, regelt ohnehin das Ziel-Handbuch.
* **Bearbeiten kannst du die Seiten in WordPress nicht.** Sie sind mit GitHub verbunden, und der nächste Abgleich würde Änderungen überschreiben. Verbesserungen gehören in die [Original-Dateien auf GitHub](https://github.com/rfluethi/living-handbook/tree/main/handbuch/de).

</details>

## Verwandte Seiten

* [Markdown importieren](../inhalte/markdown-importieren.md)
* [GitHub-Synchronisation](../inhalte/github-synchronisation.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Einstieg
* Zielgruppe: Alle Mitglieder
* Reihenfolge: 5
* Textauszug: Dieses Handbuch lässt sich mit einem Klick in deine eigene Installation laden und bleibt danach mit GitHub abgeglichen.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
