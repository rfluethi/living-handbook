# Über dieses Handbuch

Dies ist das Benutzerhandbuch der Anwendung Living Handbook, geschrieben als Living Handbook. Es erklärt Schritt für Schritt, wie du mit dem Plugin arbeitest, und ist zugleich ein echtes Beispiel dafür, wie ein fertiges Handbuch aussieht: Alles, was du hier siehst (Navigation, Abzeichen, Prüfdaten, Feedback), erzeugt das Plugin selbst.

## Für wen es ist

Dieses Handbuch richtet sich an alle, die Living Handbook benutzen: Du brauchst keine Programmierkenntnisse und keine WordPress-Erfahrung über das Übliche hinaus. Wenn du schon einmal eine Seite im Block-Editor geschrieben hast, reicht das.

Für Entwicklerinnen und Entwickler gibt es ein eigenes, englischsprachiges technisches Handbuch: die [Entwickler-Dokumentation auf GitHub](https://github.com/rfluethi/living-handbook/tree/main/docs). Sie beschreibt Architektur, Blöcke, Templates, Hooks und den Code. Dieses Handbuch hier verlinkt an den passenden Stellen dorthin.

## Wie es aufgebaut ist

Ein Handbuch besteht aus **Bereichen**, und jeder Bereich enthält **Seiten**. In diesem Repository ist ein Bereich ein Ordner und eine Seite eine Markdown-Datei; die Ordnerstruktur wird beim Import zur Seitenhierarchie, die du links im Navigationsbaum siehst.

Die Bereiche in Leserichtung:

1. **[Erste Schritte](erste-schritte/README.md):** von der Installation bis zur ersten sichtbaren Seite.
2. **[Inhalte](inhalte/README.md):** Seiten schreiben, Markdown importieren, mit GitHub synchronisieren, ein Handbuch auf eine andere Website umziehen.
3. **[Oberfläche](oberflaeche/README.md):** die drei Seitentypen im Frontend, die Navigation im Theme-Menü, Farben anpassen.
4. **[Zugriff](zugriff/README.md):** wer welche Handbücher sehen darf und warum das Plugin im Zweifel lieber versteckt als zeigt.
5. **[Pflege](pflege/README.md):** der Prüfzyklus, das Wartungs-Dashboard und das Leser-Feedback. Das ist der Kern des Plugins.
6. **[Häufige Fragen](haeufige-fragen.md):** kurze Antworten mit Wegweisern, allen voran „Warum wird nichts angezeigt?“.

## Wie du es liest

Du musst nicht alles lesen. Für den Start reichen die [Ersten Schritte](erste-schritte/README.md); alles andere schlägst du nach, wenn du es brauchst. Die Suche oben auf der Einstiegsseite findet jede Seite dieses Handbuchs.

<details>
<summary>Hintergrund: Woher diese Seiten kommen</summary>

Dieses Handbuch liegt als gewöhnliche Markdown-Dateien in einem [öffentlichen GitHub-Repository](https://github.com/rfluethi/living-handbook/tree/main/handbuch/de) und wird über den Reiter **App-Handbuch** auf der Import-Seite in eine Installation geladen. Weil es ein GitHub-Import ist, bleiben die Seiten mit dem Repository abgeglichen: Eine spätere Änderung auf GitHub erreicht deine Website beim nächsten Laden. Wie das technisch funktioniert, steht unter [Markdown importieren](inhalte/markdown-importieren.md).

</details>

## Transport-Metadaten
* Seitentyp: Hintergrund/Konzept
* Reihenfolge: 1
* Textauszug: Dieses Benutzerhandbuch erklärt Schritt für Schritt, wie du mit Living Handbook arbeitest, und ist zugleich ein Beispiel für ein fertiges Handbuch.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 365 Tage
