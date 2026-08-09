# Hooks

Living Handbook bietet Erweiterungspunkte, damit du das Verhalten anpassen kannst, ohne das Plugin selbst zu verändern. Die Namen der Hooks werden hier dokumentiert, sobald sie dazukommen.

## Filter

### `living_handbook_can_view_post`

Filtert die endgültige Entscheidung darüber, ob eine Person eine Handbuch-Seite sehen darf. Dies ist die eine Zugriffsentscheidung, die jeder Lesepfad im Frontend nutzt (Einzelseiten, Einstiegsseiten der Handbücher, Ergebnislisten, der Endpunkt für die Facettenfilter, der Feedback-Endpunkt, einzelne REST-Zugriffe und die Kommentar-Kanäle). Ein Filter an dieser Stelle wirkt sich deshalb überall aus.

Parameter:

- `bool $allowed` Ob die eingebauten Regeln pro Handbuch den Zugriff erlauben.
- `int $post_id` Die ID der Handbuch-Seite.
- `int $user_id` Die Benutzer-ID (0 für Gäste).

Gib einen Boolean zurück. Beispiel: Einem Servicekonto Lesezugriff auf alles geben.

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

Filtert, ob beim Löschen des Plugins auch alle Handbuch-Inhalte entfernt werden. Standardmässig behält die Deinstallation deine Inhalte und entfernt nur die eigenen Optionen und Caches des Plugins; dieselbe Wahl gibt es als Häkchen auf der Einstellungsseite. Dieser Filter wird mit dem Häkchen ODER-verknüpft, `true` erzwingt also die vollständige Entfernung auch bei ausgeschalteter Option: die Handbuch-Seiten, die Handbücher und ihre Metadaten, die vier geseedeten Vokabulare, die bei der Aktivierung angelegte Übersichtsseite und alle im Site-Editor bearbeiteten Vorlagen.

Parameter:

- `bool $remove` Ob alle Inhalte entfernt werden. Standard ist `false`.

Gib einen Boolean zurück. Da der Filter während der Deinstallation läuft, leg ihn in ein Must-use-Plugin (`wp-content/mu-plugins/`), damit er beim Löschen des Plugins geladen ist. Beispiel: Inhalte bei der Deinstallation immer entfernen.

```php
add_filter( 'living_handbook_uninstall_remove_content', '__return_true' );
```

### `living_handbook_zip_max_bytes`

Filtert die maximale unkomprimierte Gesamtgrösse eines ZIP-Imports in Bytes (Standard 100 MB). Das ist eine Sicherheitsgrenze gegen Speichererschöpfung, keine Grösse, die das Plugin zusichern kann: Die eigentliche Obergrenze für einen grossen Import ist die PHP-Konfiguration des Servers (`upload_max_filesize`, `post_max_size`, `memory_limit` und das Zeitlimit für die Ausführung), an der dieser Filter nichts ändert. Erhöhe ihn nur, wenn der Server den Speicher hat, so viel auf einmal zu lesen, und denk daran, dass die hochgeladene ZIP-Datei selbst weiterhin durch `upload_max_filesize` und `post_max_size` begrenzt ist.

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

Filtert die URL-Basis einer Handbuch-Seite. Standard ist `handbook`, eine Seite liegt also unter `/handbook/<slug>`. Sie ist bewusst englisch und fest, damit die Permalinks stabil bleiben und nicht kollidieren. Ändere sie nur mit Bedacht: Auf einer laufenden Website schreibt eine neue Basis jede Seiten-URL um, danach musst du die Permalinks erneuern (Einstellungen, Permalinks) und die alten Links weiterleiten, sonst laufen Lesezeichen und eingehende Links ins Leere.

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

Filtert die URL-Basis einer Handbuch-Gruppierung (`handbook_set`), standardmässig `/handbook-set/<slug>`. Gleicher Grund und gleicher Vorbehalt wie bei `living_handbook_post_type_slug`.

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

Filtert die maximale Grösse eines solchen Archiv-Downloads in Bytes. Dieselbe Art Sicherheitsgrenze wie `living_handbook_zip_max_bytes`, nur für den Archiv-Weg.

Parameter:

- `int $bytes` Die Standardgrenze in Bytes.

### `living_handbook_import_time_budget`

Filtert, wie viele Sekunden eine Import-Charge arbeiten darf, bevor sie den Rest ihrer Arbeitsliste sichert und den nächsten Durchgang abwartet. Standard sind 60 Prozent von `max_execution_time`.

Parameter:

- `int $seconds` Das Zeitbudget in Sekunden.

### `living_handbook_export_user_identifier`

Filtert, wie der Prüfer einer Seite im Export-Bündel benannt wird. Standard ist der Login. Damit stellst du die frühere Benennung über die E-Mail-Adresse wieder her, nach der ein Bündel am Ziel zugeordnet wurde.

Parameter:

- `string $identifier` Die Kennung des Prüfers. Standard ist der Login.
### `living_handbook_heading_anchors`

Bestimmt, ob die Überschriften h2, h3 und h4 einer Handbuch-Seite eine ID aus ihrem eigenen Text und daneben einen kleinen Link auf den Abschnitt bekommen. Standard: `true`.

Abgeschaltet bleiben die Überschriften genau so, wie der Editor sie geschrieben hat. Damit hören auch Links in einen Abschnitt auf zu funktionieren: das Inhaltsverzeichnis fällt dann auf IDs zurück, die im Browser aus der Position der Überschrift entstehen, und die taugen nicht als Adresse zum Weitergeben. Eine im Editor von Hand gesetzte ID gewinnt ohnehin gegen die erzeugte, eine einzelne Kollision löst man also besser dort als mit diesem Filter.

Parameter:

- `bool $enabled` Ob IDs und Anker gesetzt werden.

Gibt einen bool zurück.
## Actions

_Bisher keine._
