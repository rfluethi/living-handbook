# Die Blöcke des Plugins

Living Handbook bringt eigene Blöcke mit, also Bausteine für den Editor. Du findest sie beim Einfügen unter der Kategorie **Living Handbook**. Diese Seite zeigt, welche du selbst einsetzt und welche das Plugin von allein platziert.

## Diese Blöcke setzt du selbst ein

**Mermaid** zeichnet Diagramme direkt auf der Seite: Abläufe, Entscheidungswege, Hierarchien. Du beschreibst das Diagramm als Text in der Diagramm-Sprache [Mermaid](https://mermaid.js.org/); gezeichnet wird es beim Anzeigen der Seite. Füge den Block ein und schreibe die Beschreibung in das Feld **Mermaid-Code**. Ein Titel wird zur Bildunterschrift. Die Textbeschreibung wird vorgelesen, wenn jemand die Seite mit einem Screenreader nutzt. So sieht die Beschreibung eines kleinen Ablaufs aus:

```text
graph LR;
  A["Entwurf schreiben"] --> B["Prüfen lassen"];
  B --> C["Veröffentlichen"];
```

Und so wird sie gezeichnet:

```mermaid
graph LR;
  A["Entwurf schreiben"] --> B["Prüfen lassen"];
  B --> C["Veröffentlichen"];
```

Der Block funktioniert überall, auch mitten im Seiteninhalt. Kommt eine Seite aus einer Markdown-Datei, brauchst du ihn nicht von Hand zu setzen: Ein Codeblock mit der Sprachangabe `mermaid` wird beim [Import](../inhalte/markdown-importieren.md) automatisch zu diesem Block.

**Handbuch-Übersicht** listet alle Handbücher, die die aktuelle Besucherin lesen darf, mit Name, Beschreibung und Seitenzahl. Die Aktivierung legt eine Seite mit diesem Block an. Du kannst ihn aber auf jede beliebige Seite setzen. Einstellung: Anzeige als **Liste** oder als **Karten**.

**Handbuch-Menü** zeigt dieselbe Handbuch-Liste kompakt, gedacht für den Kopfbereich der Website. Auf schmalen Bildschirmen klappt er hinter einem Knopf zusammen. Meist ist die [Einbindung ins Theme-Menü](navigation-einbinden.md) die bessere Wahl; dieser Block ist die Alternative ohne Theme-Menü.

**GitHub-Quellenhinweis** kennzeichnet eine Seite als [auf GitHub gepflegt](../inhalte/github-synchronisation.md). Der Text des Hinweises ist anpassbar. Der Block zeigt sich nur auf Seiten mit GitHub-Quelle; überall sonst bleibt er unsichtbar. Du kannst ihn also einmal im Seitenlayout platzieren und vergessen.

## Diese Blöcke platziert das Plugin für dich

Die übrigen Blöcke sitzen schon an der richtigen Stelle in den mitgelieferten Seitenlayouts. Du begegnest ihnen nur, wenn du die Layouts im Website-Editor umbaust. Die meisten zeigen zudem nur in ihrem Zusammenhang etwas an; an anderer Stelle bleiben sie leer.

| Block | Was er zeigt | Wo er wirkt |
|---|---|---|
| **Handbuch-Eintrag** | Die Einstiegsseite: Suche, Filter, Bereichs-Kacheln, zuletzt aktualisierte Seiten. | Nur auf der Einstiegsseite eines Handbuchs. |
| **Handbuch-Navigation** | Den auf- und zuklappbaren Seitenbaum. Anzeige als **Menü** (alles offen) oder **Akkordeon** (Äste klappen). | Einstiegsseite und Einzelseiten. |
| **Handbuch-Badges** | Die Abzeichen: Seitentyp, Thema, Zielgruppe. | Nur auf Einzelseiten. |
| **Inhaltsverzeichnis** | „Auf dieser Seite“, aus den Überschriften gebaut, mit Markierung beim Lesen. | Nur auf Einzelseiten. |
| **Handbuch-Suche** | Das Suchfeld mit Vorschlägen beim Tippen. | Nur auf Einzelseiten. |
| **Handbuch-Feedback** | Die Frage „War das hilfreich?“ mit Ja und Nein. | Nur auf Einzelseiten, nur für Angemeldete. |
| **Handbuch-Seiten-Meta** | Erstellt, Aktualisiert, Geprüft und die verantwortliche Rolle, mit Prüfstatus. | Nur auf Einzelseiten. |

<details>
<summary>Hinweis: Einzelne Blöcke gezielt gestalten</summary>

Jeder Block bietet in der Seitenleiste unter **Erweitert** zwei Felder: eine **zusätzliche CSS-Klasse** und einen **HTML-Anker**. Mit der Klasse gestaltest du genau diesen einen Block, mit dem Anker verlinkst du direkt auf ihn. Die vollständige technische Referenz aller Blöcke steht in der [Entwickler-Dokumentation zu den Blöcken](https://github.com/rfluethi/living-handbook/blob/main/docs/blocks.md).

</details>

## Verwandte Seiten

* [Die drei Oberflächen](die-drei-oberflaechen.md)
* [Navigation einbinden](navigation-einbinden.md)
* [Inhalte schreiben](../inhalte/inhalte-schreiben.md)

## Transport-Metadaten
* Seitentyp: Tool-Übersicht
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Gestaltung
* Zielgruppe: Alle Mitglieder
* Reihenfolge: 2
* Textauszug: Welche Blöcke des Plugins du selbst einsetzt, allen voran das Mermaid-Diagramm, und welche das Plugin von allein platziert.
* Letzte Prüfung: 2026-07-23
* Prüfintervall: 180 Tage
