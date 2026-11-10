# Spezifikation des Bündelformats

Diese Seite legt Format und Regeln des Export- und Import-Bündels fest, damit beide Seiten mechanisch arbeiten statt nach Auslegung. Sie richtet sich an Entwicklerinnen und Entwickler, die gegen das Bündelformat arbeiten. Die Bedienung von Export und Import steht im Benutzerhandbuch unter [Handbuch umziehen](../../../user/de/living-handbook/inhalte/handbuch-umziehen.md).

## Das Bündel

Eine ZIP-Datei mit `manifest.json` im Wurzelverzeichnis und einem Ordner `media/` mit allen referenzierten Dateien. Selbstenthaltend und damit offlinefähig; ein Zugriff auf die Quellseite ist nicht nötig.

## manifest.json, Schema Version 1

```json
{
  "format": "living-handbook-bundle",
  "version": 1,
  "exported": { "site": "https://quelle.example", "plugin_version": "0.28.0", "date": "2026-07-21T12:00:00+00:00" },
  "scope": "handbook",
  "handbook": {
    "slug": "betrieb",
    "name": "Betriebshandbuch",
    "visibility": "members",
    "roles": ["editor"],
    "users": ["person@example.com"],
    "version": { "edition": "2.1", "count": 14, "version": "2.1.14" }
  },
  "pages": [
    {
      "key": "einstieg/notfall",
      "origin_id": "https://quelle.example#123",
      "parent_key": "einstieg",
      "order": 2,
      "title": "Notfall",
      "slug": "notfall",
      "status": "publish",
      "source": "github",
      "markdown_url": "https://raw.githubusercontent.com/org/repo/main/notfall.md",
      "content": "<!-- Block-Markup-Snapshot -->",
      "terms": { "handbook_type": ["howto"], "handbook_topic": ["sicherheit"], "handbook_role": ["it"], "handbook_audience": ["alle"] },
      "meta": { "last_reviewed": "2026-06-01", "review_interval": 180, "reviewer": "person@example.com", "toc_depth": 0, "ai_exclude": false },
      "media": ["media/a1b2-plan.png"]
    }
  ],
  "paths": [
    {
      "key": "path:onboarding",
      "origin_id": "https://quelle.example#456",
      "title": "Einarbeitung",
      "slug": "onboarding",
      "status": "publish",
      "content": "<!-- block markup snapshot -->",
      "excerpt": "Die erste Woche, der Reihe nach.",
      "lessons": ["getting-started", "getting-started/emergency"],
      "media": []
    }
  ],
  "media": [
    { "file": "media/a1b2-plan.png", "original_url": "https://quelle.example/wp-content/uploads/2026/06/plan.png", "alt": "Fluchtplan" }
  ]
}
```

## Identität und Abgleich

Jede Seite trägt einen `key` (stabiler Hierarchie-Pfad aus Slugs, die Identität im Bündel) und eine `origin_id` (Quellseiten-URL plus ursprüngliche Post-ID). Beim Import wird die `origin_id` als Meta-Feld `_lh_origin_id` gespeichert; so findet ein erneuter Import dieselbe Seite wieder.

Abgeglichen wird nur innerhalb des Ziel-Handbuchs: primär über `origin_id`, ersatzweise über `key`, bei GitHub-Seiten zusätzlich über `markdown_url`. Terms werden über den Slug gefunden (ersatzweise über den Namen) und bei Bedarf angelegt, der Prüfer über die E-Mail-Adresse, ersatzweise über den Login.

## Lernpfade

`paths` führt die Lernpfade des Handbuchs. Ein Pfad wird erkannt wie eine Seite: sein `key` ist `path:` plus Slug, mit angehängtem `-<ID>`, wenn zwei Pfade denselben hätten, und seine `origin_id` ist die Quellseiten-URL plus die ursprüngliche Post-ID. `lessons` ist die geordnete Liste der Seiten-Keys aus `pages`. Beim Import wird jeder Key auf die Seite aufgelöst, die im selben Lauf angelegt oder gefunden wurde; ein Key, der ins Leere zeigt, fällt weg und wird im Bericht genannt.

Ein Pfad folgt demselben Abgleich, denselben Laufregeln und demselben Schutzschalter wie eine Seite, und einen vorhandenen Pfad, den die importierende Person nicht bearbeiten darf, lässt der Import stehen. Der Ergebnisbildschirm zählt Pfade getrennt von Seiten: angelegt, aktualisiert, übersprungen, geschützt und verweigert. Der Export lässt Pfade weg, welche die exportierende Person nicht lesen darf, und ein Bereichs-Export trägt nur Pfade mit mindestens einer Lektion in diesem Bereich. Die Schema-Version bleibt 1: Ältere Plugin-Versionen ignorieren `paths`.

## Version des Handbuchs

`handbook.version` trägt Ausgabe und Zähler (E-0084), `version` ist beides zusammen zum Lesen. Legt der Import ein neues Handbuch an, übernimmt es Ausgabe und Zähler, nachdem die Seiten angelegt sind; das Anlegen veröffentlichter Seiten hätte sonst schon gezählt. Ein bestehendes Ziel-Handbuch behält seine Version und zählt das Einspielen als Änderung. Ein Bündel ohne den Schlüssel, aus einer Version vor 0.81.0, lässt ein neues Handbuch bei 1.0 beginnen. Die Schema-Version bleibt 1.

## Passwortgeschützte Seiten

Eine Seite oder ein Pfad mit Passwort trägt es in `password`; bei ungeschützten fehlt der Schlüssel, ein solches Bündel sieht also genau aus wie eines aus einer älteren Version. Der Import setzt das Passwort bei einem neuen und bei einem aktualisierten Beitrag gleichermaßen. Ein Eintrag ohne den Schlüssel lässt die Beitragsdaten in Ruhe, ein Aktualisieren behält also ein auf der Zielseite gesetztes Passwort.

Der geschützte Text reist ohnehin im Klartext im Bündel. Das Passwort macht die Datei nicht vertraulicher, als sie schon ist; es hält nur den Schutz auf der Website, auf die das Bündel geht. Behandle ein Bündel mit geschützten Seiten wie die Seiten selbst.

## Was mitreist

Die Handbuch-Konfiguration (Sichtbarkeit, Rollen, Benutzer als E-Mail oder Login, best effort, dazu die Version des Handbuchs), alle Seiten mit Inhalts-Snapshot, Hierarchie, Reihenfolge, Slug und Status, die vier Vokabular-Terms, die Metadaten (Prüfdatum, Intervall, Prüfer, Überschriftentiefe, KI-Ausschluss), die Quelle je Seite samt Markdown-URL bei GitHub-Seiten, das Passwort geschützter Seiten, die Lernpfade mit der Reihenfolge ihrer Lektionen, und die Medien.

GitHub-Seiten tragen sowohl den Snapshot, der offline funktioniert, als auch die Quell-URL, mit der die Zielseite den Sync wieder aufnimmt.

## Was nicht mitreist

Site-Einstellungen (Sync-Zeitplan, seitenweites eigenes CSS, ZIP-Grenze); die Feedback-Zähler, die den Lesern der Zielseite gehören; Sync-Status und Fehlerflags; die Editor-Sperre, die die Zielseite aus der Quelle ableitet; und alle IDs, die neu vergeben werden. Kommentare sind in Version 1 bewusst ausgeklammert.

## Konfliktbehandlung

Eine Laufregel, gewählt beim Import:

- **Überspringen (Standard):** vorhandene Seiten bleiben unberührt, nur neue werden angelegt. Überschreibt nie.
- **Aktualisieren:** bei einem Treffer werden Titel, Inhalt (bei WordPress-Seiten), Slug, Reihenfolge, Eltern und Term-Zuordnungen aus dem Bündel ersetzt.
- **Als neu anlegen:** legt immer eine neue Seite an, auch bei einem Treffer (Klonen).

Dazu kommt ein Schutzschalter pro Seite (Meta `_lh_import_protected`), der unabhängig von der Laufregel nie überschrieben wird.

Ein Aktualisieren fasst lokale Betriebsdaten nie an: die Feedback-Zähler sowie Prüfdatum, Intervall und Prüfer, die die Zielseite selbst pflegt. Beim erstmaligen Anlegen werden die Prüf-Metadaten dagegen aus dem Bündel gesetzt. Gelöscht wird nie: Seiten, die im Bündel fehlen, auf der Zielseite aber existieren, bleiben stehen.

## Sichere Voreinstellungen beim Import

Die Sichtbarkeit steht beim Anlegen auf «Mitglieder», auch wenn das Bündel «öffentlich» sagt, und der Bericht kennzeichnet das, damit niemand versehentlich etwas öffentlich schaltet. Beim Aktualisieren eines vorhandenen Handbuchs bleibt dessen Zugriffs-Konfiguration unverändert. Medien laufen über den bestehenden Sideload mit Dublettenschutz. Export und Import stehen nur Berechtigten offen (mindestens `edit_others_posts`).

## Sanitisierung: Block für Block

Ein Bündel ist eine Datei von einer anderen Website, sein Inhalt wird also behandelt wie jede andere fremde Quelle, genau wie beim Markdown-Import und beim GitHub-Sync. Durch kses am Stück kann er nicht laufen, weil das die Block-Trenner (`<!-- wp:... -->`) zerstört und die Seiten kaputtgehen. `HtmlSanitizer::clean_blocks()` zerlegt deshalb zuerst die Blöcke, reinigt nur das HTML innerhalb jedes Blocks und setzt sie wieder zusammen; die Trenner und die Block-Attribute bleiben erhalten. Das gilt für Seiten und Lernpfade gleichermaßen.

Block-Attribute werden nicht gefiltert: Ein dynamischer Block rendert aus ihnen, und die eigenen Render-Callbacks des Plugins escapen sie, während sich ein Core- oder Fremd-Block auf sein eigenes Escaping verlässt. Die Medien laufen über den bestehenden Sideload samt SVG-Reinigung, und importieren darf nur, wer die Berechtigung eines Content-Managers hat (`edit_others_posts`). Wer ein Bündel aus fremder Quelle importiert, muss dieser Quelle also weiterhin vertrauen, was die Attribute ihrer Blöcke tragen. Mehr dazu in [Import und Sync](import-und-sync.md).

## Noch nicht festgelegt

Drei Fragen sind offen und in Version 1 bewusst ausgeklammert:

- ob der Export alle Status umfasst (auch Entwürfe) oder nur veröffentlichte Seiten,
- wie das Ziel-Handbuch beim Import gewählt wird (vorhandenes zuordnen oder neu anlegen),
- ob der Export an der Handbuch-Liste sitzt oder auf einer eigenen Seite.

## Transport-Metadaten
* Seitentyp: Tool-Übersicht
* Zielgruppe: Technik
* Reihenfolge: 12
* Letzte Aktualisierung: 2026-11-10
