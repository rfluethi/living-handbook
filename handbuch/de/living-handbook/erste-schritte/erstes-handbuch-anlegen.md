# Erstes Handbuch anlegen

Ein Handbuch ist der Behälter für deine Seiten. Es bestimmt, wer die Seiten lesen darf. Es bekommt außerdem eine eigene Einstiegsseite mit Suche und Filtern. Diese Anleitung legt dein erstes Handbuch an.

<details>
<summary>Konzept: Warum zuerst der Behälter kommt</summary>

Jede Handbuch-Seite gehört zu genau einem Handbuch. Wer etwas lesen darf, wird pro Handbuch geregelt, nicht pro Seite. Eine Seite ohne Handbuch hat darum keine Regel, die das Lesen erlauben könnte. Sie bleibt auf der Website unsichtbar. Deshalb legst du erst das Handbuch an und dann die Seiten. Mehr dazu unter [Zugriff verstehen](../zugriff/zugriff-verstehen.md).

</details>

## Schritte

1. Öffne **Handbuch → Handbücher** und lege ein neues Handbuch an.
2. Gib einen **Namen** ein, zum Beispiel „Allgemein“. Ergänze eine kurze **Beschreibung**. Sie erscheint später auf der Übersicht und der Einstiegsseite.
3. Setze auf derselben Seite die **Sichtbarkeit**. Es gibt drei Stufen: **Öffentlich** für alle Besucher, **Alle Mitglieder (angemeldet)** für ein internes Handbuch, oder **Eingeschränkt** auf bestimmte Benutzerrollen und Personen.
4. Entscheide unter **Kommentare**, ob die Seiten dieses Handbuchs Kommentare erlauben. **Jede Seite entscheidet selbst** ist die Voreinstellung und ändert nichts. **Offen** und **Geschlossen** gelten für alle Seiten des Handbuchs auf einmal und übergehen die Einstellung der einzelnen Seite.
5. Speichere.

![Das Formular „Handbuch anlegen“ mit Name, Beschreibung und den drei Sichtbarkeits-Stufen.](../assets/handbuch-anlegen.webp)

## Ergebnis

Das neue Handbuch erscheint in der Liste unter **Handbuch → Handbücher**. Es erscheint auch auf der Übersichts-Seite „Handbuch“, sofern die aktuelle Besucherin es sehen darf. Seine Einstiegsseite liegt automatisch unter `/handbook-set/<name>/`. Sie füllt sich, sobald du Seiten anlegst.

<details>
<summary>Stolpersteine: Die Sichtbarkeit ist bewusst streng</summary>

Ein neues Handbuch steht auf **Alle Mitglieder (angemeldet)**. Ausgeloggt siehst du also nichts. Das ändert sich erst, wenn du es auf **Öffentlich** stellst oder Rollen und Personen freigibst. Das ist Absicht: Das Plugin verbirgt eine Seite lieber, als sie versehentlich zu veröffentlichen. Siehst du beim Testen nichts, prüfe zuerst die Sichtbarkeit. Nutze dafür ein privates Browserfenster. Mehr unter [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md).

</details>

<details>
<summary>Konzept: Kommentare für ein ganzes Handbuch</summary>

WordPress schaltet Kommentare pro Seite. Für ein Handbuch mit zweihundert Seiten ist das keine Antwort, denn niemand öffnet zweihundert Seiten. Die Einstellung am Handbuch ist deshalb kein Vorschlagswert, sondern eine Übersteuerung: Steht sie auf **Offen** oder **Geschlossen**, gilt sie, egal was an der einzelnen Seite steht. Ein Vorschlagswert müsste beim Setzen auf jede vorhandene Seite geschrieben werden und wäre für jede später importierte Seite wieder falsch.

**Geschlossen** blendet das Kommentarformular aus, genau wie das Schließen der Kommentare an einer einzelnen Seite. Bereits geschriebene Kommentare bleiben lesbar und werden nicht gelöscht. Das Löschen bleibt ein eigener, bewusster Schritt unter **Kommentare** in der WordPress-Verwaltung.

</details>

## Verwandte Seiten

* [Erste Seite anlegen](erste-seite-anlegen.md)
* [Sichtbarkeit einstellen](../zugriff/sichtbarkeit-einstellen.md)

## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Einstieg
* Zielgruppe: Alle Mitglieder
* Reihenfolge: 2
* Textauszug: Ein Handbuch ist der Behälter, in dem deine Seiten liegen; diese Anleitung legt dein erstes Handbuch an und setzt seine Sichtbarkeit.
* Letzte Prüfung: 2026-08-05
* Prüfintervall: 180 Tage
