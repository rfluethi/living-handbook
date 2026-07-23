# App-Handbuch laden

Dieses Handbuch ist die Dokumentation von Living Handbook. Du kannst es mit einem Klick in deine eigene Installation laden. Damit hast du die aktuelle Anleitung direkt in WordPress. Zugleich siehst du ein fertiges, gefülltes Handbuch als Beispiel.

<details>
<summary>Voraussetzungen: Was du brauchst</summary>

* Ein Benutzerkonto mit Bearbeitungsrechten für Handbuch-Seiten.
* Ein Handbuch als Ziel, zum Beispiel eines mit dem Namen „App-Handbuch“. Lege es vorher an, siehe [Erstes Handbuch anlegen](erstes-handbuch-anlegen.md). Bestimme dort auch, wer es lesen darf.
* Dein Server muss GitHub erreichen können. Die Seiten kommen aus einem öffentlichen Repository.

</details>

## Schritte

1. Öffne **Handbuch → Import** und wechsle auf den Reiter **App-Handbuch**.
2. Wähle unter **Laden in** das Ziel-Handbuch aus.
3. Klicke auf **App-Handbuch laden**.
4. Lies die Ergebnisliste. Sieh dir danach das geladene Handbuch im Frontend an.

> **Screenshot folgt:** Die Import-Seite mit geöffnetem Reiter „App-Handbuch“, markiert sind die Auswahl „Laden in“ und der Lade-Knopf.

## Ergebnis

Alle Seiten dieses Handbuchs stehen jetzt in deinem gewählten Handbuch. Sie sind sofort veröffentlicht. Die Ordnerstruktur des Repositories ist als Navigationsbaum übernommen. Wer die Seiten sehen darf, bestimmt allein die Sichtbarkeit des Ziel-Handbuchs. Steht es auf „Alle Mitglieder“, lesen nur Angemeldete. Öffentlich wird es erst, wenn du das Handbuch auf „Öffentlich“ stellst.

<details>
<summary>Konzept: Warum das Handbuch aktuell bleibt</summary>

Hinter dem Reiter steckt der normale [GitHub-Ordner-Import](../inhalte/markdown-importieren.md) mit einer fest hinterlegten Adresse. Der Ordner richtet sich nach deiner Admin-Sprache. Die Seiten sind darum [von GitHub synchronisiert](../inhalte/github-synchronisation.md). Eine spätere Änderung im Repository erreicht deine Website beim nächsten Laden. Die Bilder aus dem Repository kommen mit. Geladen wird nie automatisch, nur wenn du es anstößt.

</details>

<details>
<summary>Stolpersteine: Zwei Eigenheiten dieses Imports</summary>

* **Die Seiten sind direkt veröffentlicht.** Jeder andere GitHub-Import legt Entwürfe an. Hier ist das anders, und zwar mit Absicht: Es ist kuratierter, im Editor gesperrter Inhalt. Die Sichtbarkeit regelt das Ziel-Handbuch.
* **Bearbeiten kannst du die Seiten in WordPress nicht.** Ihr Editor ist gesperrt. Der nächste Abgleich würde Änderungen ohnehin überschreiben. Die Quelle ist das [GitHub-Repository](https://github.com/rfluethi/living-handbook/tree/main/handbuch/de).

</details>

## Verwandte Seiten

* [Markdown importieren](../inhalte/markdown-importieren.md)
* [GitHub-Synchronisation](../inhalte/github-synchronisation.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Reihenfolge: 5
* Textauszug: Dieses Handbuch lässt sich mit einem Klick in deine eigene Installation laden und bleibt danach mit GitHub abgeglichen.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 90 Tage
