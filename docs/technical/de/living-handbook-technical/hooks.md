# Hooks

Living Handbook bietet Erweiterungspunkte, damit du das Verhalten anpassen kannst, ohne das Plugin selbst zu verändern. Die Namen der Hooks werden hier dokumentiert, sobald sie dazukommen. Die Daten, die das Plugin führt, stehen in [meta-felder.md](meta-felder.md), seine REST-Endpunkte in [rest-routen.md](rest-routen.md).

## Filter

### `living_handbook_can_view_post`

Filtert die endgültige Entscheidung darüber, ob eine Person eine Handbuch-Seite sehen darf. Dies ist die eine Zugriffsentscheidung, die jeder Lesepfad im Frontend nutzt (Einzelseiten, Einstiegsseiten der Handbücher, Ergebnislisten, der Endpunkt für die Facettenfilter, der Feedback-Endpunkt, einzelne REST-Zugriffe und die Kommentar-Kanäle), dazu der Website-Export und der Bündel-Export, die weglassen, was er vor der exportierenden Person verbirgt. Ein Filter an dieser Stelle wirkt sich deshalb überall aus.

Parameter:

- `bool $allowed` Ob die eingebauten Regeln den Zugriff erlauben.
- `int $post_id` Die ID der Handbuch-Seite.
- `int $user_id` Die Benutzer-ID (0 für Gäste).

Die eingebauten Regeln beginnen bei den Rechten, nicht beim Handbuch. Wer jedes Handbuch liest, standardmäßig ein Konto mit `manage_options` (Administration, siehe `living_handbook_read_all_capability`), darf jede Seite lesen, auch in eingeschränkten Handbüchern und auch ohne Handbuch; für ein solches Konto kommt `$allowed` als `true` an. Eine Seite ohne Handbuch lesen außerdem Konten mit `edit_others_posts` (Redaktion), sonst niemand. Für jede andere Seite zählen Sichtbarkeit, Rollen und Personen des Handbuchs, auch für die Redaktion: eine Redakteurin liest ein eingeschränktes Handbuch nur, wenn ihre Rolle oder sie selbst dort eingetragen ist. Gibst du hier `false` zurück, verschwindet die Seite für diese Person aus Listen, Suche, Navigation und Export. Eine Ausnahme liegt außerhalb dieses Filters: Wer eine Seite nach WordPress-Regeln bearbeiten darf (`edit_post`), also eine Autorin ihre eigenen Seiten und eine Mitarbeiterin ihre eigenen Entwürfe, bekommt diese eine Seite im Block-Editor, in der Vorschau und in der Einzelansicht, was immer der Filter sagt. Sonst könnte ein Filter jemandem die eigene Arbeit wegnehmen. Für fremde Seiten in einem Handbuch, das die Person nicht lesen darf, verweigert das Plugin `edit_post` selbst (über `map_meta_cap`); die Ausnahme öffnet ein solches Handbuch also nur für die eigenen Seiten.

Der Filter läuft für jede Seite, die die Navigation zeigt, einmal; er muss also billig sein. Die Navigation liest von den Seiten eines Handbuchs nur Titel und Adressen, nicht den Inhalt, und legt sie nicht in den Post-Cache: Ein Callback, der dort `get_post()` aufruft, kostet eine Datenbankabfrage pro Seite, bei einem großen Handbuch einige tausend. Entscheide anhand der ID, des Benutzers und von Daten, die sich cachen lassen, etwa Term-Meta.

Die Prüfung auf Handbuch-Ebene, `AccessController::can_view_term()`, die über Einstiegsseite, Übersicht, Menü, Lernpfade sowie den Such- und den Filter-Endpunkt entscheidet, läuft nicht durch diesen Filter, und ebenso wenig der Schutz im Backend, der auf ihr aufbaut (Seitenliste, Mediathek, Editor, Kommentare, Handbuch-Einstellungen, die Wahl des Handbuchs bei Import und Export). Ohne Handbuch-Freigabe lässt sie nur durch, wer jedes Handbuch liest. Das Recht, eine einzelne Seite zu bearbeiten, kennt sie nicht: es öffnet diese eine Seite, nicht das Handbuch, und Listen, Suche und Navigation folgen für eine Autorin weiter den Handbuch-Regeln.

Gib einen Boolean zurück. Beispiel: Einem Servicekonto Lesezugriff auf alle Seiten geben. Einstiegsseiten, Übersicht und Backend öffnet das nicht; soll ein Konto wirklich alles lesen, nimm `living_handbook_read_all_capability`.

```php
add_filter(
	'living_handbook_can_view_post',
	function ( bool $allowed, int $post_id, int $user_id ): bool {
		if ( user_can( $user_id, 'read_all_handbooks' ) ) {
			return true;
		}
		return $allowed;
	},
	10,
	3
);
```

Wenn du einen eigenen Lesepfad schreibst, ruf `AccessController::can_view_post()` auf, statt die Term-Metadaten selbst auszulesen. Das ist der einzige unterstützte Weg, diese Frage zu stellen, und das Ergebnis wird pro Request zwischengespeichert.

### `living_handbook_read_all_capability`

Filtert die Fähigkeit, mit der ein Konto jedes Handbuch liest, was immer dessen Sichtbarkeit sagt. Wer sie hat, liest jede Seite, jede Einstiegsseite, jeden Lernpfad und jeden Kommentar, sieht im Backend alle Seiten, Dateien und Handbücher und kann jedes Handbuch einstellen, exportieren und als Importziel wählen, soweit die übrigen WordPress-Rechte reichen. Alle anderen, die Redaktion eingeschlossen, lesen, was ein Handbuch ihnen freigibt. Die Frage wird an einer Stelle beantwortet, `AccessController::reads_everything()`; frag dort, statt die Fähigkeit selbst zu prüfen.

Parameter:

- `string $capability` Die Fähigkeit. Standard ist `'manage_options'`, also die Administration.

Gib einen String zurück. Ein leerer String heißt: niemand liest alles, auch die Administration nicht. Dann gelten die Handbuch-Freigaben für jedes Konto, auch für die Einstellungen eines Handbuchs: wer es nicht lesen darf, kann es auch nicht mehr öffnen.

Beispiel: die Redaktion wieder jedes Handbuch lesen lassen, wie vor 0.76.0.

```php
add_filter(
	'living_handbook_read_all_capability',
	function (): string {
		return 'edit_others_posts';
	}
);
```

Der Filter läuft bei jeder Zugriffsentscheidung; gib einen festen Wert zurück, keine Entscheidung pro Person. Soll nur ein einzelnes Konto alles lesen, etwa ein Dienstkonto, gib eine eigene Fähigkeit wie `'read_all_handbooks'` zurück und vergib sie diesem Konto und der Rolle Administrator, denn wer sie nicht hat, liest ab dann nur noch, was die Handbücher freigeben. Seiten ohne Handbuch hängen nicht an diesem Filter: sie liest, wer `edit_others_posts` hat, und wer jedes Handbuch liest.

### `living_handbook_sync_allowed_hosts`

Filtert die Hosts, auf die eine Markdown-Quell-URL zeigen darf. Der GitHub-Sync ruft eine URL ab, die eine redaktionelle Person eingetippt hat. Deshalb ist der Host auf eine Positivliste beschränkt. Ohne diese Beschränkung könnte jemand mit Bearbeitungsrechten den Server auf eine interne Adresse richten (Server-Side Request Forgery).

Parameter:

- `string[] $hosts` Erlaubte Hostnamen. Standard ist `array( 'raw.githubusercontent.com' )`.

Gib ein Array von Hostnamen zurück. Beispiel: zusätzlich einen selbst gehosteten Git-Dienst erlauben.

```php
add_filter(
	'living_handbook_sync_allowed_hosts',
	function ( array $hosts ): array {
		$hosts[] = 'git.example.com';
		return $hosts;
	}
);
```

Nimm nur Hosts auf, die du selbst kontrollierst oder denen du vertraust. Jeder zusätzliche Host ist ein Host, von dem dein Server Daten abrufen kann, und er gilt von da an als vertrauenswürdige Bezugsquelle: Das Plugin holt von dort Markdown und legt das Ergebnis als Seiteninhalt ab. Das zweite Sicherheitsnetz bleibt in beiden Fällen bestehen, denn `wp_safe_remote_get` verweigert weiterhin interne und private Adressen; ein öffentlicher Host unter fremder Kontrolle ist aber erreichbar, sobald du ihn einträgst.

### `living_handbook_nav_label`

Filtert die Beschriftung des Handbuch-Untermenüs, das in einen Core-Navigation-Block mit der Klasse `has-handbook-menu` eingefügt wird. Der Filter gilt nur für genau diesen Fall: Wenn du die Klasse auf einen Navigationslink oder auf ein Untermenü setzt, behält der Eintrag seine eigene Beschriftung und der Filter kommt nicht zum Einsatz. Siehe [bloecke.md](bloecke.md).

Parameter:

- `string $label` Die Menü-Beschriftung. Standard ist das übersetzte „Handbücher".

Gib einen String zurück. Beispiel:

```php
add_filter(
	'living_handbook_nav_label',
	function ( string $label ): string {
		return 'Wissensdatenbank';
	}
);
```

### `living_handbook_uninstall_remove_content`

Filtert, ob beim Löschen des Plugins auch alle Handbuch-Inhalte entfernt werden. Standardmäßig behält die Deinstallation deine Inhalte und entfernt nur die eigenen Optionen und Caches des Plugins; dieselbe Wahl gibt es als Häkchen auf der Einstellungsseite. Dieser Filter wird mit dem Häkchen ODER-verknüpft, `true` erzwingt also die vollständige Entfernung auch bei ausgeschalteter Option: die Handbuch-Seiten, die Handbücher und ihre Metadaten, die vier geseedeten Einordnungs-Taxonomien, die bei der Aktivierung angelegte Übersichtsseite und alle im Site-Editor bearbeiteten Vorlagen. Dateien der Mediathek bleiben in jedem Fall stehen, auch die zu Handbuch-Seiten hochgeladenen.

Parameter:

- `bool $remove` Ob alle Inhalte entfernt werden. Standard ist `false`.

Gib einen Boolean zurück. Da der Filter während der Deinstallation läuft, leg ihn in ein Must-use-Plugin (`wp-content/mu-plugins/`), damit er beim Löschen des Plugins geladen ist. Beispiel: Inhalte bei der Deinstallation immer entfernen.

```php
add_filter( 'living_handbook_uninstall_remove_content', '__return_true' );
```

### `living_handbook_zip_max_bytes`

Filtert die maximale unkomprimierte Gesamtgröße eines ZIP-Imports in Bytes (Standard 100 MB). Das ist eine Sicherheitsgrenze gegen Speichererschöpfung, keine Größe, die das Plugin zusichern kann: Die eigentliche Obergrenze für einen großen Import ist die PHP-Konfiguration des Servers (`upload_max_filesize`, `post_max_size`, `memory_limit` und das Zeitlimit für die Ausführung), an der dieser Filter nichts ändert. Erhöhe ihn nur, wenn der Server den Speicher hat, so viel auf einmal zu lesen, und denk daran, dass die hochgeladene ZIP-Datei selbst weiterhin durch `upload_max_filesize` und `post_max_size` begrenzt ist.

Parameter:

- `int $bytes` Die Standardgrenze in Bytes.

Gib eine Ganzzahl zurück. Beispiel: die unkomprimierte Grenze auf 250 MB anheben.

```php
add_filter(
	'living_handbook_zip_max_bytes',
	function ( int $bytes ): int {
		return 250 * MB_IN_BYTES;
	}
);
```

### `living_handbook_bundle_max_bytes`

Filtert, auf wie viele Bytes ein Handbuch-Bündel (**Handbuch → Import**, Reiter **Bündel**) höchstens entpacken darf (Standard 1 GB). Das Bündel hat eigene Grenzen, nicht die des ZIP-Imports: Es trägt die PDFs und Videos mit, auf die seine Seiten verweisen, und der Export setzt keine Grenze. Wie `living_handbook_zip_max_bytes` ist das ein Schutz gegen ein Archiv, das beim Hochladen klein und beim Lesen riesig ist, keine Zusage über das, was der Server trägt. Jede Datei des Bündels wird einzeln gelesen; den Speicher bestimmt also die größte Datei, nicht diese Summe, und für sie gilt `living_handbook_bundle_max_file_bytes`. Die hochgeladene Datei selbst bleibt durch `upload_max_filesize` und `post_max_size` begrenzt.

Parameter:

- `int $bytes` Die Standardgrenze in Bytes.

Gib eine Ganzzahl zurück. Beispiel: die Grenze auf 4 GB anheben.

```php
add_filter(
	'living_handbook_bundle_max_bytes',
	function ( int $bytes ): int {
		return 4 * GB_IN_BYTES;
	}
);
```

### `living_handbook_bundle_max_entries`

Filtert, wie viele Einträge ein Handbuch-Bündel höchstens haben darf (Standard 20000). Ein Bündel ist das Manifest und ein Eintrag je Mediendatei, die seine Seiten verwenden.

Parameter:

- `int $entries` Die Standardgrenze.

Gib eine Ganzzahl zurück.

### `living_handbook_bundle_max_file_bytes`

Filtert, wie groß eine einzelne Datei eines Handbuch-Bündels entpackt höchstens sein darf, damit der Import sie liest (Standard: das Kleinere aus 128 MB und einem Viertel des noch freien PHP-Speichers; ohne `memory_limit` gelten die 128 MB). Jede Datei wird beim Import ganz in den Speicher gelesen, und eine einzelne große Videodatei würde sonst den Import mitten im Lauf an `memory_limit` scheitern lassen. Eine größere Datei wird nicht gelesen, und der Bericht des Imports nennt sie. Erhöhe den Wert nur zusammen mit `memory_limit`.

Parameter:

- `int $bytes` Die Standardgrenze in Bytes.

Gib eine Ganzzahl zurück.

### `living_handbook_app_handbook_url`

Filtert, woher der Reiter **App-Handbuch** lädt. Der Standard ist ein leerer String und bedeutet „die mit dem Plugin ausgelieferte Kopie verwenden". Gib eine GitHub-tree-URL zurück, um stattdessen aus einem Repository zu laden: Ein Fork mit eigener Dokumentation richtet den Reiter so auf sein Repository, ohne das Plugin zu ändern, und jede Installation, die lieber den neuesten Stand direkt von GitHub zieht als die mitgelieferte Kopie, kann das ebenfalls.

Parameter:

- `string $default` Der Standard, ein leerer String (mitgelieferte Kopie verwenden).
- `string $locale` Die aktuelle Backend-Sprache.

Gib eine `github.com/.../tree/<branch>/<path>`-URL zurück, oder `''`, um bei der mitgelieferten Kopie zu bleiben. Beispiel: aus dem eigenen Repository laden statt aus dem Bündel.

```php
add_filter(
	'living_handbook_app_handbook_url',
	function ( string $default, string $locale ): string {
		return 0 === strpos( $locale, 'fr' )
			? 'https://github.com/me/my-docs/tree/main/handbook/fr'
			: 'https://github.com/me/my-docs/tree/main/handbook/en';
	},
	10,
	2
);
```

### `living_handbook_post_type_slug`

Filtert die URL-Basis einer Handbuch-Seite. Standard ist `handbook`, eine Seite liegt also unter `/handbook/<slug>`. Sie ist bewusst englisch und fest, damit die Permalinks stabil bleiben und nicht kollidieren. Auf `handbooks` gesetzt, legt die Aktivierung die Übersichtsseite unter `handbook-overview` statt unter `handbooks` an, damit sich die beiden nicht in die Quere kommen. Ändere sie nur mit Bedacht: Auf einer laufenden Website schreibt eine neue Basis jede Seiten-URL um, danach musst du die Permalinks erneuern (Einstellungen, Permalinks) und die alten Links weiterleiten, sonst laufen Lesezeichen und eingehende Links ins Leere.

Parameter:

- `string $slug` Die Rewrite-Basis. Standard `'handbook'`.

```php
add_filter(
	'living_handbook_post_type_slug',
	function (): string {
		return 'handbuch';
	}
);
```

### `living_handbook_taxonomy_slug`

Filtert die URL-Basis einer Handbuch-Gruppierung (`handbook_set`), standardmäßig `/handbook-set/<slug>`. Gleicher Grund und gleicher Vorbehalt wie bei `living_handbook_post_type_slug`.

Parameter:

- `string $slug` Die Rewrite-Basis. Standard `'handbook-set'`.

```php
add_filter(
	'living_handbook_taxonomy_slug',
	function (): string {
		return 'handbuecher';
	}
);
```

### `living_handbook_access_denied_title`

Filtert den Titel der Seite, die eine Person zu sehen bekommt, wenn ihr der Zugriff auf ein Handbuch fehlt.

Parameter:

- `string $title` Der Standardtitel.

### `living_handbook_access_denied_message`

Filtert den Text derselben Seite, unter dem Titel. Nützlich, um auf die Stelle zu verweisen, an der man den Zugang beantragt.

Parameter:

- `string $message` Der Standardtext.

### `living_handbook_anonymous_feedback_limit`

Filtert die Obergrenze anonymer Stimmen pro Seite und Stunde, die gilt, wenn öffentliches Feedback eingeschaltet ist. Standard ist 200; `0` schaltet die Begrenzung aus.

Parameter:

- `int $limit` Die Obergrenze. Standard `200`.

### `living_handbook_archive_allowed_hosts`

Filtert die Hosts, von denen ein Repository-Archiv geladen werden darf. Bewusst getrennt von `living_handbook_sync_allowed_hosts`: Der Archiv-Download hat seine eigene Liste, damit der dafür zusätzlich erlaubte Host nicht in die allgemeine Quellenprüfung durchschlägt.

Parameter:

- `string[] $hosts` Erlaubte Hostnamen für den Archiv-Download.

### `living_handbook_archive_max_bytes`

Filtert die maximale Größe eines solchen Archiv-Downloads in Bytes. Dieselbe Art Sicherheitsgrenze wie `living_handbook_zip_max_bytes`, nur für den Archiv-Weg.

Parameter:

- `int $bytes` Die Standardgrenze in Bytes.

### `living_handbook_import_time_budget`

Filtert, wie viele Sekunden eine Import-Charge arbeiten darf, bevor sie den Rest ihrer Arbeitsliste sichert und den nächsten Durchgang abwartet. Standard sind 60 Prozent von `max_execution_time`.

Parameter:

- `int $seconds` Das Zeitbudget in Sekunden.

### `living_handbook_export_user_identifier`

Filtert, wie der Prüfer einer Seite im Export-Bündel benannt wird. Standard ist der Login, der in einer Datei, die die Website verlässt, weniger über die Person verrät als die E-Mail-Adresse. Der Import liest beides, ein Bündel mit E-Mail-Adressen wird also ebenfalls zugeordnet. Der Preis, offen gesagt: die Zuordnung über den Login scheitert, wo dieselbe Person am Ziel einen anderen Login hat; gibst du hier die E-Mail-Adresse zurück, wird über sie zugeordnet.

Parameter:

- `string $identifier` Die Kennung des Prüfers. Standard ist der Login.
### `living_handbook_heading_anchors`

Bestimmt, ob die Überschriften h2, h3 und h4 einer Handbuch-Seite eine ID aus ihrem eigenen Text und daneben einen kleinen Link auf den Abschnitt bekommen. Standard: `true`.

Abgeschaltet bleiben die Überschriften genau so, wie der Editor sie geschrieben hat. Damit hören auch Links in einen Abschnitt auf zu funktionieren: das Inhaltsverzeichnis fällt dann auf IDs zurück, die im Browser aus der Position der Überschrift entstehen, und die taugen nicht als Adresse zum Weitergeben. Eine im Editor von Hand gesetzte ID gewinnt ohnehin gegen die erzeugte, eine einzelne Kollision löst man also besser dort als mit diesem Filter.

Parameter:

- `bool $enabled` Ob IDs und Anker gesetzt werden.

Gibt einen bool zurück.
### `living_handbook_enabled_taxonomies`

Bestimmt, welche der vier klassifizierenden Taxonomien diese Website nutzt: Seitentyp, Thema, Verantwortung, Zielgruppe. Standard ist das, was die Ankreuzfelder unter **Handbuch → Einstellungen → Einordnung** sagen, und alle vier, solange nichts gespeichert wurde.

Eine Taxonomie, die nicht in der Liste steht, verschwindet aus Spalte und Filter der Seitenliste, aus der Facette auf der Einstiegsseite, aus dem Abzeichen auf Seite und Karte, aus dem Feld in der Editor-Seitenleiste und aus der Zeile, die ein Import im Transport-Block liest. Registriert bleibt es, und gelöscht wird nichts: die Begriffe bleiben, die Seiten behalten sie, und wer es zurückstellt, bekommt jede Zuordnung unverändert wieder.

Die Handbuch-Gruppierung (`handbook_set`) steht bewusst nicht zur Wahl. An ihr hängt der Zugriff, und eine Seite ohne Handbuch ist im Frontend unsichtbar.

Parameter:

- `array<int, string> $enabled` Namen der Taxonomien, die genutzt werden.

Gibt ein Array von Taxonomie-Namen zurück; alles, was keines der vier ist, wird ignoriert.

### `living_handbook_training_enabled`

Bestimmt, ob das Modul Lernpfade aktiv ist. Standard ist das Ankreuzfeld unter **Handbuch → Einstellungen → Lernpfade**, und das ist aus.

Ausschalten blendet den Beitragstyp, seinen Menüeintrag und seine Seiten aus, gelöscht wird nichts: die Pfade, ihre Texte und ihre Lektionslisten bleiben, und Einschalten bringt sie unverändert zurück. Was es nicht berührt: die Zugriffsregeln schützen Lernpfade in beiden Fällen, denn eine Website, die das Modul ausschaltet, hat ihre Pfade weiterhin in der Datenbank.

Parameter:

- `bool $enabled` Ob das Modul eingeschaltet ist.

Gibt einen Wahrheitswert zurück.

```php
add_filter( 'living_handbook_training_enabled', '__return_true' );
```

### `living_handbook_training_slug`

Bestimmt die URL-Basis eines Lernpfads, standardmäßig `/learning-path/<slug>`. Gleicher Grund und gleicher Vorbehalt wie bei `living_handbook_post_type_slug`.

Parameter:

- `string $slug` Die Rewrite-Basis. Standard `'learning-path'`.

```php
add_filter(
	'living_handbook_training_slug',
	function (): string {
		return 'lernpfad';
	}
);
```

### `living_handbook_static_export_time_budget`

Bestimmt, wie viele Sekunden ein Durchgang des Website-Exports rendern darf, bevor er seinen Stand sichert und den nächsten Durchgang abwartet. Standard sind 15 Sekunden, begrenzt auf 60 Prozent von `max_execution_time`, falls gesetzt. Auf einem Server, der Anfragen früh abschneidet, senken; höher setzen als der Server erlaubt macht aus einem pausierten Export einen fehlgeschlagenen.

Parameter:

- `float $budget` Das Zeitbudget in Sekunden.

Gibt einen float zurück.

### `living_handbook_static_export_page`

Filtert das vollständige HTML-Dokument einer Seite im Website-Export, nachdem Links und Bilder darin auf die Dateien im ZIP zeigen. Hier hängt eine Website einen eigenen Kopf, einen Vertraulichkeitshinweis, ein Druck-Stylesheet oder eine eigene Fußzeile an einen Export, ohne das Plugin anzufassen.

Das Dokument wird genau so ins Archiv geschrieben, wie es zurückkommt: Was hier dazukommt, wird danach nicht mehr escaped, bereinigt oder geprüft.

Parameter:

- `string $html` Das vollständige Dokument.
- `WP_Post $post` Die Seite, aus der es gebaut wurde.
- `string $path` Sein Pfad im Export, zum Beispiel `pflege/seiten-pruefen.html`.

Gibt einen String zurück.

### `living_handbook_static_export_themes`

Filtert die Gestaltungen, die der Export-Bildschirm für eine statische Website anbietet. Jeder Eintrag hat einen Schlüssel, ein `label` und einen `css`-Block, der hinter das eigene Stylesheet des Exports gehängt wird. So ergänzt eine Website eine eigene Gestaltung, benennt die mitgelieferten um oder wirft weg, was sie nicht braucht.

Es genügt, die `--lh-user-*`-Eigenschaften zu setzen, um alles umzufärben, was das Plugin zeichnet: Abzeichen, Karten, Prüfstatus-Farben und Metadaten-Fußzeile lesen sie über eine Fallback-Kette. Eine Gestaltung, die stattdessen einzelne Bausteine umstylt, muss bei jeder Änderung an diesen Bausteinen nachgezogen werden.

Der Eintrag `site` lässt sich nicht entfernen. Auf ihn fällt eine unbekannte oder fehlende Wahl zurück, und ein Bildschirm ohne gültige Auswahl wäre schlimmer als eine ungewollte.

Parameter:

- `array<string, array{label: string, css: string}> $themes` Die verfügbaren Gestaltungen.

Gibt ein Array derselben Form zurück; ein Eintrag ohne Label fällt weg.

## Actions

_Keine. Ein Hook ist eine Zusage, deren Signatur sich später nicht ändern lässt, ohne zu brechen, wer ihn nutzt; einer kommt also dazu, wenn etwas ihn wirklich braucht, nicht von einer Liste. Drei Kandidaten, nach einem Import, an den Metadaten und an der Aktualitätsbewertung, liegen als Notiz mit Signatur und Zweck im eigenen Arbeitsbereich des Projekts._

## Transport-Metadaten
* Seitentyp: Tool-Übersicht
* Zielgruppe: Technik
* Reihenfolge: 7
* Letzte Aktualisierung: 2026-10-27
