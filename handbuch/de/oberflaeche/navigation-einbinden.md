# Navigation einbinden

Diese Anleitung bringt deine Handbücher dorthin, wo Besucherinnen und Besucher sie erwarten: in den Kopfbereich der Website. Der empfohlene Weg ist eine CSS-Klasse im Navigations-Block deines Themes.

<details>
<summary>Konzept: Warum die Handbuch-Liste kein normales Menü ist</summary>

Welche Handbücher eine Person sieht, hängt davon ab, was sie lesen darf. Die Liste wird darum pro Besucherin aufgebaut und kann kein statisches Menü sein, das du von Hand pflegst. Stattdessen hängt das Plugin die erlaubten Handbücher automatisch in dein Menü ein.

</details>

## Schritte

1. Öffne den Website-Editor und wähle den **Navigations-Block** deines Themes.
2. Wähle darin den Menüpunkt, unter dem die Handbücher erscheinen sollen, zum Beispiel einen Link „Handbuch“, der auf deine Übersichts-Seite zeigt.
3. Öffne in der rechten Seitenleiste den Reiter **Einstellungen** (Zahnrad) und scrolle ganz nach unten zum zugeklappten Abschnitt **Erweitert**. Er sitzt unter allen anderen Feldern und wird leicht übersehen.
4. Trage bei **Zusätzliche CSS-Klasse(n)** exakt `has-handbook-menu` ein.
5. Speichere.

> **Screenshot folgt:** Die Seitenleiste des Navigations-Blocks mit aufgeklapptem Abschnitt „Erweitert“ und der eingetragenen Klasse `has-handbook-menu`.

## Ergebnis

Der Menüpunkt wird zu einem Untermenü: Sein eigener Name und sein Linkziel bleiben erhalten, und darunter erscheinen die Handbücher, die die aktuelle Besucherin lesen darf. Weil das im Theme-Menü passiert, wandern die Handbücher auf dem Handy automatisch mit ins Hamburger-Menü.

## Die drei Orte für die Klasse

* **Auf einem einzelnen Menü-Link (empfohlen):** Der Link wird zum Untermenü mit den Handbüchern als Einträgen und behält Name und Ziel.
* **Auf einem bestehenden Untermenü:** Dessen Einträge werden durch die Handbücher ersetzt; Name und Ziel des Untermenüs bestimmst du.
* **Auf dem ganzen Navigations-Block:** Ein Untermenü „Handbücher“ wird als erster Eintrag ergänzt und zeigt auf die bei der Aktivierung angelegte Übersichts-Seite. Existiert diese Seite nicht mehr, wird nichts eingefügt; ein Menüpunkt ins Leere wäre schlimmer als keiner.

<details>
<summary>Stolpersteine: Wann die Einbindung nicht greift</summary>

* **Nur der Block „Navigation“ wird unterstützt.** Der klassische Menü-Editor unter **Design → Menüs** bleibt unberührt; eine dort eingetragene Klasse hat keine Wirkung.
* **Die Klasse muss exakt stimmen:** `has-handbook-menu`. Varianten wie `has-handbook-menu-alt` werden ignoriert.
* **Alternative ohne Theme-Menü:** Der Block **Handbuch-Menü** zeigt dieselbe Liste als eigenständigen Block, den du überall platzieren kannst, etwa im Header-Template; auf schmalen Bildschirmen klappt er hinter einem Knopf zusammen.

</details>

## Verwandte Seiten

* [Die drei Oberflächen](die-drei-oberflaechen.md)
* [Zugriff verstehen](../zugriff/zugriff-verstehen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Reihenfolge: 2
* Textauszug: Diese Anleitung bringt die Handbücher ins Menü deines Themes, über die CSS-Klasse has-handbook-menu im Navigations-Block.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
