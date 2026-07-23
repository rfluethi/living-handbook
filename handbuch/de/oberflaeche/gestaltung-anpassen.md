# Gestaltung anpassen

Die Handbuch-Seiten übernehmen Schrift und Abstände von deinem Theme und passen sich dessen Farben an. Diese Anleitung zeigt, wie du die Farben gezielt änderst, ohne das Plugin anzufassen.

<details>
<summary>Konzept: CSS-Variablen statt Plugin-Änderungen</summary>

Alle Oberflächen des Plugins beziehen ihre Farben aus CSS-Variablen mit dem Präfix `--lh-`. Fläche, Text und Akzentfarbe folgen standardmäßig den Farb-Voreinstellungen deines Themes; ein dunkles Theme macht Karten, Navigation und Inhaltsverzeichnis automatisch dunkel. Wer eigene Werte setzt, übersteuert diese Automatik, und ein Plugin-Update überschreibt nichts davon.

</details>

## Schritte

1. Öffne **Handbuch → Einstellungen** und suche das Feld **Eigenes CSS**. Es wirkt nur auf den Handbuch-Seiten und wird mit dem Plugin wieder entfernt.
2. Trage die Variablen ein, die du ändern willst, zum Beispiel die Akzentfarbe:

   ```css
   .living-handbook-overview,
   .living-handbook-entry,
   .living-handbook-cards,
   .living-handbook-card,
   .living-handbook-nav,
   .living-handbook-toc,
   .living-handbook-meta,
   .living-handbook-feedback,
   .living-handbook-badge {
     --lh-accent: #7a1f3d;   /* Akzentfarbe: Links, Knöpfe, Markierungen */
     --lh-sticky-top: 5rem;  /* Abstand unter einem fixierten Header */
   }
   ```

3. Speichere und prüfe das Ergebnis im Frontend.

## Ergebnis

Alle Handbuch-Oberflächen verwenden die neuen Werte einheitlich: Karten, Navigation, Inhaltsverzeichnis, Abzeichen und Feedback. Die wichtigsten Variablen:

| Variable | Steuert |
|---|---|
| `--lh-surface`, `--lh-surface-text` | Flächen- und Textfarbe der Karten und Kästen |
| `--lh-accent`, `--lh-on-accent` | Akzentfarbe und Textfarbe auf Akzentflächen |
| `--lh-ok`, `--lh-due`, `--lh-overdue` | Die drei Prüfstatus-Farben (Geprüft, fällig, überfällig) |
| `--lh-sticky-top` | Oberer Abstand der fixierten Navigation und des Inhaltsverzeichnisses |

<details>
<summary>Stolpersteine: Was du beim Umfärben beachten solltest</summary>

* **Halte die drei Prüfstatus-Farben unterscheidbar**, am besten nicht nur über den Farbton. Die Formen (Kreis, abgerundetes Quadrat, Raute) helfen zusätzlich, verlass dich aber nicht allein darauf.
* **Prüfe den Kontrast**, wenn du Grautöne aufhellst; die Voreinstellungen erfüllen die Anforderungen der Barrierefreiheit (WCAG AA).
* **Entferne die Fokus-Ringe nicht.** Sie machen die Bedienung per Tastatur sichtbar; wer sie umgestaltet, sollte einen klar sichtbaren Ersatz behalten.

</details>

<details>
<summary>Hintergrund: Alle Variablen und Klassennamen</summary>

Jeder Block bietet unter **Erweitert** zusätzlich eine eigene CSS-Klasse und einen HTML-Anker, um einzelne Instanzen zu stylen oder zu verlinken. Die vollständige Referenz aller `--lh-`-Variablen und stabilen Klassennamen steht in der [Entwickler-Dokumentation zur Gestaltung](https://github.com/rfluethi/living-handbook/blob/main/docs/customization.md).

</details>

## Verwandte Seiten

* [Die drei Oberflächen](die-drei-oberflaechen.md)
* [Der Prüfzyklus](../pflege/der-pruefzyklus.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Reihenfolge: 3
* Textauszug: Die Handbuch-Seiten folgen den Farben deines Themes; über CSS-Variablen mit dem Präfix --lh- passt du sie gezielt an.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 365 Tage
