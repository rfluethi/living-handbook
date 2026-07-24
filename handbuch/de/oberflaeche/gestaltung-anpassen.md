# Gestaltung anpassen

Die Handbuch-Seiten übernehmen Schrift, Abstände und Farben von deinem Theme. Meist musst du also gar nichts tun. Diese Anleitung zeigt, wie du die Farben trotzdem gezielt änderst. Dafür brauchst du ein paar Zeilen CSS. CSS ist die Sprache, mit der Websites gestaltet werden. Du kannst das Beispiel unten aber auch einfach kopieren und die Farbwerte austauschen.

<details>
<summary>Konzept: Warum das Umfärben so gelöst ist</summary>

Alle Farben des Plugins hängen an zentralen Stellwerten, sogenannten CSS-Variablen. Ihre Namen beginnen mit `--lh-`. Änderst du einen Stellwert, ändert sich die Farbe überall zugleich. Ohne eigene Werte folgen die Farben deinem Theme. Ein dunkles Theme macht die Handbuch-Seiten automatisch dunkel. Ein Plugin-Update überschreibt deine Werte nicht.

</details>

## Schritte

1. Öffne die [Einstellungen](../die-einstellungen.md) unter **Handbuch → Einstellungen** und suche das Feld **Eigenes CSS**. Es wirkt nur auf den Handbuch-Seiten. Beim Löschen des Plugins wird es mit entfernt.
2. Trage die Variablen ein, die du ändern willst. Dieses Beispiel stellt das Handbuch komplett um: dunkle Kästen auf heller Website, Bernstein als Akzent, passend eingefärbte Abzeichen. So ist die Wirkung unübersehbar:

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
     /* Flächen: dunkles Blaugrau statt Weiß, helle Schrift darauf */
     --lh-surface: #1d2733;
     --lh-surface-text: #f2f5f8;

     /* Akzent: Bernstein für Links, Knöpfe und Markierungen.
        --lh-on-accent ist die Schriftfarbe auf gefüllten Knöpfen. */
     --lh-accent: #ffb84d;
     --lh-on-accent: #1d2733;

     /* Abzeichen-Etiketten: dunkler Chip mit heller Schrift */
     --lh-badge-bg: #33414f;
     --lh-badge-text: #d7dee5;
   }
   ```

   Rahmen und Nebentexte musst du nicht anfassen. Sie rechnen sich automatisch aus Fläche und Schriftfarbe und ziehen mit um.

3. Speichere und öffne die Einstiegsseite deines Handbuchs. Bereichs-Kacheln, Filterleiste und Suchfeld sind jetzt dunkel mit heller Schrift, Knöpfe und Verweise bernsteinfarben. Auf einer Einzelseite wechseln das Inhaltsverzeichnis, die Abzeichen und die aktiven Einträge in der Navigation. Gefällt es nicht, leere das Feld wieder; dann gelten erneut die Farben des Themes.

## Ergebnis

Alle Handbuch-Oberflächen verwenden die neuen Werte einheitlich: Karten, Navigation, Inhaltsverzeichnis, Abzeichen und Feedback. Die wichtigsten Variablen:

| Variable | Steuert |
|---|---|
| `--lh-surface`, `--lh-surface-text` | Flächen und Text von Karten, Inhaltsverzeichnis, Filterleiste und Suchfeld |
| `--lh-accent`, `--lh-on-accent` | Akzentfarbe und Textfarbe auf Akzentflächen |
| `--lh-badge-bg`, `--lh-badge-text` | Flächen- und Textfarbe der Abzeichen-Etiketten |
| `--lh-ok`, `--lh-due`, `--lh-overdue` | Die drei Prüfstatus-Farben (Geprüft, fällig, überfällig) |
| `--lh-sticky-top` | Oberer Abstand der fixierten Navigation und des Inhaltsverzeichnisses |

<details>
<summary>Stolpersteine: Was du beim Umfärben beachten solltest</summary>

* **Zwei Stellen ändern sich absichtlich nicht mit.** Der Kasten der Navigation hat keinen eigenen Hintergrund; er zeigt immer den Seitenhintergrund durch. Und Links im Seiteninhalt gehören dem Theme, nicht dem Plugin; ihre Farbe stellst du im Website-Editor um.
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
