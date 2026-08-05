# Gestaltung anpassen

Die Handbuch-Seiten übernehmen Schrift, Abstände und Farben von deinem Theme. Meist musst du also gar nichts tun.

Willst du trotzdem etwas ändern, gibt es zwei Wege. Für die wichtigsten acht Farben und die Schriftgröße brauchst du kein CSS: Sie stehen als Felder unter **Handbuch → Einstellungen → Darstellung**, siehe [Die Einstellungen](../die-einstellungen.md). Alles Weitere geht über CSS, und darum geht es auf dieser Seite. CSS ist die Sprache, mit der Websites gestaltet werden. Du kannst das Beispiel unten auch einfach kopieren und die Farbwerte austauschen.

Beides zusammen ist erlaubt: Eigenes CSS gewinnt gegen die Felder, und die Felder gewinnen gegen die Voreinstellungen des Plugins.

<details>
<summary>Konzept: Warum das Umfärben so gelöst ist</summary>

Alle Farben des Plugins hängen an zentralen Stellwerten, sogenannten CSS-Variablen. Ihre Namen beginnen mit `--lh-`. Änderst du einen Stellwert, ändert sich die Farbe überall zugleich. Ohne eigene Werte folgen die Farben deinem Theme. Ein dunkles Theme macht die Handbuch-Seiten automatisch dunkel. Ein Plugin-Update überschreibt deine Werte nicht.

</details>

## Schritte

1. Öffne die [Einstellungen](../die-einstellungen.md) unter **Handbuch → Einstellungen** und suche das Feld **Eigenes CSS**. Es wirkt nur auf den Handbuch-Seiten. Beim Löschen des Plugins wird es mit entfernt.
2. Trage die Variablen ein, die du ändern willst. Dieses Beispiel wirkt genau dort, wo du gerade liest: auf der Einzelseite. Es färbt das Suchfeld, das Inhaltsverzeichnis, die Abzeichen und den aktiven Navigationseintrag dunkel. Zusätzlich verkleinert es den Fließtext ein wenig:

   ```css
   /* Dunkle Kästen auf der Einzelseite */
   .living-handbook-nav,
   .living-handbook-toc,
   .living-handbook-badge,
   .living-handbook-page-search {
     --lh-surface: #1d2733;      /* Fläche */
     --lh-surface-text: #f2f5f8; /* Schrift */
     --lh-accent: #ffb84d;       /* Akzent */
     --lh-on-accent: #1d2733;
     --lh-badge-bg: #33414f;
     --lh-badge-text: #d7dee5;
   }

   /* Fließtext der Handbuch-Seiten kleiner */
   .living-handbook-page .wp-block-post-content {
     font-size: 0.92em;
   }
   ```

   Die zweite Regel nutzt die Klasse `living-handbook-page`. Sie sitzt auf jeder Handbuch-Ansicht. Damit gestaltest du auch normale Theme-Elemente, ohne den Rest der Website zu verändern.

3. Speichere und lade eine Einzelseite neu. Du siehst sofort: Das Suchfeld oben und das Inhaltsverzeichnis sind dunkel mit heller Schrift. Die Abzeichen sind dunkle Chips. Der aktive Eintrag in der Navigation leuchtet bernsteinfarben. Der Fließtext ist eine Spur kleiner. Gefällt es nicht, leere das Feld wieder; dann gelten erneut die Farben des Themes.

4. Willst du das ganze Handbuch umfärben, ergänze vorne in der Selektor-Liste die übrigen Oberflächen: `.living-handbook-overview`, `.living-handbook-entry`, `.living-handbook-cards`, `.living-handbook-card`. Dann wechseln auch die Übersicht und die Einstiegsseite mit ihren Kacheln.

## Ergebnis

Die Selektor-Liste bestimmt, wo die neuen Werte gelten; jede aufgeführte Oberfläche übernimmt sie einheitlich. Die wichtigsten Variablen:

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
* Eltern-Seite: Oberfläche
* Reihenfolge: 4
* Textauszug: Die Handbuch-Seiten folgen den Farben deines Themes; über CSS-Variablen mit dem Präfix --lh- passt du sie gezielt an.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 365 Tage
