# Erstes Handbuch anlegen

Ein Handbuch ist der Behälter, in dem deine Seiten liegen. Es bestimmt, wer die Seiten lesen darf, und bekommt eine eigene Einstiegsseite mit Suche und Filtern. Diese Anleitung legt dein erstes Handbuch an.

<details>
<summary>Konzept: Warum zuerst der Behälter kommt</summary>

Jede Handbuch-Seite gehört zu genau einem Handbuch, und die Sichtbarkeit wird pro Handbuch geregelt, nicht pro Seite. Eine Seite ohne Handbuch hat darum keine Sichtbarkeitsregel und bleibt im Frontend unsichtbar. Deshalb legst du erst das Handbuch an und dann die Seiten. Mehr dazu unter [Zugriff verstehen](../zugriff/zugriff-verstehen.md).

</details>

## Schritte

1. Öffne **Handbuch → Handbücher** und lege ein neues Handbuch an.
2. Gib einen **Namen** ein, zum Beispiel „Allgemein“, und eine kurze **Beschreibung**; sie erscheint später auf der Übersicht und der Einstiegsseite.
3. Setze auf derselben Seite die **Sichtbarkeit**: **Öffentlich** für alle Besucher, **Alle Mitglieder (angemeldet)** für ein internes Handbuch, oder eine Einschränkung auf bestimmte Rollen und Personen.
4. Speichere.

> **Screenshot folgt:** Das Formular „Handbuch anlegen“ mit Name, Beschreibung und den drei Sichtbarkeits-Stufen.

## Ergebnis

Das neue Handbuch erscheint in der Liste unter **Handbuch → Handbücher** und auf der Übersichts-Seite „Handbuch“, sofern die aktuelle Besucherin es sehen darf. Es hat automatisch eine eigene Einstiegsseite unter `/handbook-set/<name>/`; sie füllt sich, sobald du Seiten anlegst.

<details>
<summary>Stolpersteine: Die Sichtbarkeit ist bewusst streng</summary>

Ein neues Handbuch steht auf **Alle Mitglieder (angemeldet)**. Ausgeloggt siehst du also nichts, bis du es auf **Öffentlich** stellst oder Rollen und Personen freigibst. Das ist Absicht: Das Plugin ist fail-closed, es verbirgt eine Seite lieber, als sie versehentlich zu veröffentlichen. Wenn du beim Testen „nichts sehen“ solltest, prüfe zuerst in einem privaten Browserfenster, ob es an der Sichtbarkeit liegt. Mehr unter [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md).

</details>

## Verwandte Seiten

* [Erste Seite anlegen](erste-seite-anlegen.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Reihenfolge: 2
* Textauszug: Ein Handbuch ist der Behälter, in dem deine Seiten liegen; diese Anleitung legt dein erstes Handbuch an und setzt seine Sichtbarkeit.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
