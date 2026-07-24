# Navigation einbinden

Diese Anleitung bringt deine Handbücher dorthin, wo Besucherinnen und Besucher sie erwarten: in den Kopfbereich der Website. Der empfohlene Weg ist eine CSS-Klasse im Navigations-Block deines Themes.

<details>
<summary>Konzept: Warum die Handbuch-Liste kein normales Menü ist</summary>

Welche Handbücher eine Person sieht, hängt von ihren Leserechten ab. Die Liste ist also für jede Besucherin anders. Ein von Hand gepflegtes Menü kann das nicht abbilden. Darum hängt das Plugin die erlaubten Handbücher automatisch in dein Menü ein. Du markierst nur die Stelle, an der sie erscheinen sollen. Als Markierung dient eine CSS-Klasse: eine Art Etikett, das du einem Menüpunkt anhängst. Das Plugin sucht nach diesem Etikett.

</details>

## Schritte

1. Öffne den Website-Editor und wähle den **Navigations-Block** deines Themes.
2. Wähle darin den Menüpunkt, unter dem die Handbücher erscheinen sollen. Ein Beispiel: ein Link „Handbuch“, der auf deine Übersichts-Seite zeigt.
3. Öffne in der rechten Seitenleiste den Reiter **Einstellungen** (Zahnrad). Scrolle ganz nach unten zum zugeklappten Abschnitt **Erweitert**. Er sitzt unter allen anderen Feldern und wird leicht übersehen.
4. Trage bei **Zusätzliche CSS-Klasse(n)** exakt `has-handbook-menu` ein.
5. Speichere.

> **Screenshot folgt:** Die Seitenleiste des Navigations-Blocks mit aufgeklapptem Abschnitt „Erweitert“ und der eingetragenen Klasse `has-handbook-menu`.

## Ergebnis

Der Menüpunkt bleibt bestehen und bekommt ein Untermenü. Darin erscheinen die Handbücher, die die aktuelle Besucherin lesen darf, als einzelne Einträge. Name und Linkziel des Menüpunkts ändern sich nicht. Das Ganze passiert im Theme-Menü. Auf dem Handy wandern die Handbücher darum automatisch mit ins Hamburger-Menü.

## Die drei Orte für die Klasse

* **Auf einem einzelnen Menü-Link (empfohlen):** Der Link bekommt ein Untermenü mit den Handbüchern als Einträgen. Er behält Name und Ziel.
* **Auf einem bestehenden Untermenü:** Dessen Einträge werden durch die Handbücher ersetzt. Name und Ziel des Untermenüs bestimmst du.
* **Auf dem ganzen Navigations-Block:** Ein Untermenü „Handbücher“ wird als erster Eintrag ergänzt. Es zeigt auf die bei der Aktivierung angelegte Übersichts-Seite. Existiert diese Seite nicht mehr, wird nichts eingefügt. Ein Menüpunkt ins Leere wäre schlimmer als keiner.

<details>
<summary>Stolpersteine: Wann die Einbindung nicht greift</summary>

* **Nur der Block „Navigation“ wird unterstützt.** Der klassische Menü-Editor unter **Design → Menüs** bleibt unberührt. Eine dort eingetragene Klasse hat keine Wirkung.
* **Die Klasse muss exakt stimmen:** `has-handbook-menu`. Varianten wie `has-handbook-menu-alt` werden ignoriert.
* **Alternative ohne Theme-Menü:** Der Block **Handbuch-Menü** zeigt dieselbe Liste als eigenständigen Block. Du kannst ihn überall platzieren, etwa im Header-Template. Auf schmalen Bildschirmen klappt er hinter einem Knopf zusammen.

</details>

## Verwandte Seiten

* [Die drei Oberflächen](die-drei-oberflaechen.md)
* [Zugriff verstehen](../zugriff/zugriff-verstehen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Gestaltung
* Zielgruppe: Alle Mitglieder
* Reihenfolge: 3
* Textauszug: Diese Anleitung bringt die Handbücher ins Menü deines Themes, über die CSS-Klasse has-handbook-menu im Navigations-Block.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
