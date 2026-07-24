# Gestaltung anpassen

Die Handbuch-Seiten übernehmen Schrift, Abstände und Farben von deinem Theme. Meist musst du also gar nichts tun. Diese Anleitung zeigt, wie du die Farben trotzdem gezielt änderst. Dafür brauchst du ein paar Zeilen CSS. CSS ist die Sprache, mit der Websites gestaltet werden. Du kannst das Beispiel unten aber auch einfach kopieren und die Farbwerte austauschen.

<details>
<summary>Konzept: Warum das Umfärben so gelöst ist</summary>

Alle Farben des Plugins hängen an zentralen Stellwerten, sogenannten CSS-Variablen. Ihre Namen beginnen mit `--lh-`. Änderst du einen Stellwert, ändert sich die Farbe überall zugleich. Ohne eigene Werte folgen die Farben deinem Theme. Ein dunkles Theme macht die Handbuch-Seiten automatisch dunkel. Ein Plugin-Update überschreibt deine Werte nicht.

</details>

## Schritte

1. Öffne die [Einstellungen](../die-einstellungen.md) unter **Handbuch → Einstellungen** und suche das Feld **Eigenes CSS**. Es wirkt nur auf den Handbuch-Seiten. Beim Löschen des Plugins wird es mit entfernt.
2. Trage die Variablen ein, die du ändern willst. Dieses Beispiel färbt sichtbar um: Flächen auf einen warmen Papierton, Akzente auf Bordeaux:

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
     --lh-surface: #f8f4ec;      /* Flächen der Karten und Kästen: warmes Papier */
     --lh-surface-text: #33302b; /* Text auf diesen Flächen: dunkles Braun */
     --lh-accent: #7a1f3d;       /* Links, Knöpfe, Markierungen: Bordeaux */
   }
   ```

3. Speichere und öffne eine Handbuch-Seite. Die Wirkung ist sofort zu sehen: Karten, Navigation und Inhaltsverzeichnis stehen auf dem Papierton, Links und Knöpfe sind bordeauxrot. Gefällt es nicht, leere das Feld wieder; dann gelten erneut die Farben des Themes.

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

* **Halte die drei Prüfstatus-Farben unterscheidbar**, am besten nicht nur über den Farbton. Die Formen (Kreis, abgerundetes Quadrat, Raute) helfen zusätzlich. Verlass dich aber nicht allein darauf.
* **Prüfe den Kontrast, wenn du Grautöne aufhellst.** Die Voreinstellungen erfüllen die Anforderungen der Barrierefreiheit (WCAG AA).
* **Entferne die Fokus-Ringe nicht.** Sie machen die Bedienung per Tastatur sichtbar. Wer sie umgestaltet, sollte einen klar sichtbaren Ersatz behalten.

</details>

<details>
<summary>Hintergrund: Alle Variablen und Klassennamen</summary>

Jeder Block bietet unter **Erweitert** zusätzlich eine eigene CSS-Klasse und einen HTML-Anker. Damit stylst oder verlinkst du einzelne Instanzen. Die vollständige Referenz aller `--lh-`-Variablen und stabilen Klassennamen steht in der [Entwickler-Dokumentation zur Gestaltung](https://github.com/rfluethi/living-handbook/blob/main/docs/customization.md).

</details>

## Verwandte Seiten

* [Die drei Oberflächen](die-drei-oberflaechen.md)
* [Der Prüfzyklus](../pflege/der-pruefzyklus.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Gestaltung
* Zielgruppe: Technik
* Reihenfolge: 4
* Textauszug: Die Handbuch-Seiten folgen den Farben deines Themes; über CSS-Variablen mit dem Präfix --lh- passt du sie gezielt an.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 365 Tage
