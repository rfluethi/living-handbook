# Dateischutz

Dateien von Seiten aus Handbüchern, die nicht öffentlich sind, liegen in einem eigenen Ordner, den eine Serverregel an WordPress umleitet. Das Plugin liefert sie nach derselben Zugriffsprüfung aus wie die Seite. Diese Seite beschreibt Ablage, Verschieben, Auslieferung, Serverregeln und Grenzen (Entscheid E-0076).

## Ablage

Eine Datei gehört in den Schutzordner `wp-content/uploads/living-handbook-protected/JJJJ/MM/`, wenn ihre Elternseite ein geschützter Inhaltstyp ist (Handbuch-Seite, Lernpfad, siehe `AccessController::guarded_post_types()`) und ihr Handbuch nicht öffentlich ist. Eine Seite ohne Handbuch zählt als geschützt, weil sie noch niemandem sagt, wer sie lesen darf. Die Entscheidung trifft `ProtectedFiles::protects()`. Alle übrigen Dateien bleiben, wo WordPress sie ablegt.

Im Schutzordner liegen die Hauptdatei, jede Bildgröße und bei großen Bildern das Original neben der `-scaled`-Fassung. `_wp_attached_file` und `meta['file']` tragen den Pfad mit dem Präfix `living-handbook-protected/`, `wp_get_attachment_url()` liefert also die Adresse im Schutzordner.

## Verschieben

Verschoben wird in diesen Fällen:

- **Beim Hochladen** auf eine Seite eines nicht öffentlichen Handbuchs, im Filter `wp_generate_attachment_metadata`, nachdem WordPress die Bildgrößen erzeugt hat. Früher geht es nicht: WordPress erzeugt die Größen aus dem Pfad, in den es hochgeladen hat, und schreibt diesen Pfad in die Metadaten. Eine Datei ohne Metadaten prüft das Plugin am Ende der Anfrage (`shutdown`), nicht über die Warteschlange, damit kein Durchgang neben einem laufenden Upload die Hauptdatei wegnimmt.
- **Beim Wechsel der Elternseite** einer Datei (`attachment_updated`).
- **Beim Wechsel einer Seite** in ein anderes Handbuch (`set_object_terms`). Bis 20 Dateien sofort, mehr über die Warteschlange.
- **Beim Wechsel der Sichtbarkeit** eines Handbuchs (`added_term_meta`, `updated_term_meta`): alle Dateien seiner Seiten über die Warteschlange.
- **Nach dem Update auf 0.79.0** einmal über alle Dateien von Handbuch-Seiten.

Die Warteschlange (`living_handbook_protect_queue`) arbeitet in Cron-Durchgängen unter einer Sperre (`living_handbook_protect_lock`), wie der GitHub-Sync. Reicht die Zeit eines Durchgangs nicht, plant er den nächsten in 30 Sekunden.

`ProtectedFiles::move()` benennt alle Dateien eines Anhangs gemeinsam um. Scheitert eine, macht es die übrigen rückgängig und ändert nichts. Danach zieht es die Adressen im Inhalt mit: in allen Handbuch-Seiten und Lernpfaden, deren Inhalt die alte Adresse enthält, auch in `srcset` und in wurzelrelativen Adressen. Diese Änderung schreibt direkt in die Datenbank, ohne Revision und ohne „Letzte Aktualisierung“, weil sich nicht der Text geändert hat, sondern die Adresse. Für Inhalte außerhalb der Handbücher merkt sich das Plugin die alte Adresse (`living_handbook_moved_files`, höchstens 2000 Einträge) und ersetzt sie beim Ausgeben über `the_content`.

Ein neuer Dateiname wird auch im Zwillingsordner frei gehalten (Filter `wp_unique_filename`): Liegt `plan.jpg` schon im Schutzordner, heißt ein zweiter Upload `plan-1.jpg`, obwohl WordPress nur den normalen Ordner prüft.

Der Bündel-Import hängt jede eingespielte Datei an die erste importierte Seite, die sie zeigt. Damit landet sie im Schutzordner, wenn das Ziel-Handbuch nicht öffentlich ist.

## Auslieferung

Die Serverregel schreibt jede Anfrage an den Schutzordner auf `index.php?lh_file=JJJJ/MM/name` um. `FileDelivery` beantwortet sie auf `parse_request` und beendet die Anfrage.

- **Pfad:** Bevorzugt aus `REQUEST_URI`, weil nginx den Pfad unkodiert in die Abfrage kopiert. Abgelehnt werden leere Teile, `.`, `..`, Backslash und Nullbyte. Nach `realpath()` muss die Datei im Schutzordner liegen, ein Symlink nach draußen ergibt 404. Nur eine Datei, zu der ein Anhang gehört (Hauptdatei, Größe oder Original), wird ausgeliefert.
- **Zugriff:** Wer den Anhang bearbeiten darf (`edit_post`), bekommt ihn. Sonst entscheidet `AccessController::can_view_post()` der Elternseite. Eine Seite, die nicht veröffentlicht ist, verlangt zusätzlich `read_post`, eine Seite mit Passwort gibt ihre Dateien nicht heraus. Eine Datei, deren Seite keinen Schutz mehr braucht, wird ausgeliefert, bis die Warteschlange sie zurückgelegt hat.
- **Abgelehnt:** Ein Gast, der die Datei direkt öffnet (`Sec-Fetch-Dest: document`, bei älteren Browsern `Accept: text/html`), bekommt 302 zur Anmeldung. Ein eingebettetes Bild und jede angemeldete Person ohne Zugriff bekommen 403.
- **Kopfzeilen:** `Content-Type` aus `wp_check_filetype()`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, max-age=0, must-revalidate`, `Last-Modified`, `ETag` mit 304, `Range` mit 206 und 416. SVG, HTML und XML kommen mit `Content-Security-Policy: … sandbox`, damit eingebettetes Skript nicht läuft. Bilder, Video, Audio, PDF und Text kommen `inline`, alles andere als Download.

### Übergabe an den Webserver

Standardmäßig liest PHP die Datei in Blöcken von 1 MB. Für große Dateien lohnt die Übergabe an den Webserver, die du mit dem Filter `living_handbook_protected_files_handoff` einschaltest (siehe [Hooks](hooks.md)):

| Wert | Server | Was es braucht |
| --- | --- | --- |
| `x-accel-redirect` | nginx | Den `internal`-Ort aus dem nginx-Block unten. |
| `x-sendfile` | Apache | Das Modul `mod_xsendfile` mit `XSendFilePath` auf den Schutzordner. |
| `x-litespeed-location` | LiteSpeed | Ungeprüft. Der interne Abruf geht womöglich noch einmal durch die `.htaccess` und damit zurück an WordPress. Vor dem Einschalten auf einem echten LiteSpeed prüfen. |

Die Übergabe ist nicht automatisch: Eine Kopfzeile, die der Server nicht versteht, ergibt eine leere Datei, und von PHP aus lässt sich nicht sehen, ob nginx den internen Ort kennt.

## Serverregeln

**Apache und LiteSpeed:** Das Plugin schreibt `.htaccess`, `index.php` und die Testdatei `lh-selftest.txt` in den Schutzordner, bei der Aktivierung, beim Update und beim ersten Verschieben. Die `.htaccess` enthält im Kern diese Regel:

```apache
Options -Indexes
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^(.*)$ /index.php?lh_file=$1 [L,QSA,B]
</IfModule>
<IfModule !mod_rewrite.c>
Require all denied
</IfModule>
```

Ohne `mod_rewrite` sperrt die Regel den Ordner ganz. Das ist sicher, aber niemand bekommt eine Datei.

**nginx** liest keine `.htaccess`. Der Block gehört in den `server`-Block der Website, vor den allgemeinen `location`-Block. Das Plugin zeigt ihn im Reiter **Zugriff** mit den Pfaden der Website:

```nginx
location ^~ /wp-content/uploads/living-handbook-protected/ {
    rewrite ^/wp\-content/uploads/living\-handbook\-protected/(.*)$ /index.php?lh_file=$1 last;
}
# Nur mit der Übergabe per X-Accel-Redirect:
location /lh-protected-internal/ {
    internal;
    alias /pfad/zu/wp-content/uploads/living-handbook-protected/;
}
```

### Selbsttest

**Schutz testen** im Reiter **Zugriff** ruft `lh-selftest.txt` ohne Cookies über `wp_remote_get()` ab (`ServerRules::selftest()`):

| Ergebnis | Woran erkannt | Bedeutung |
| --- | --- | --- |
| `protected` | Kopfzeile `X-Living-Handbook-Protected` | Das Plugin hat geantwortet. |
| `open` | Der Inhalt der Testdatei kam zurück | Der Server liefert den Ordner direkt aus. |
| `locked` | 403 ohne die Kopfzeile | Der Server sperrt, WordPress antwortet nicht (Apache ohne `mod_rewrite`). |
| `unknown` | Fehler oder anderer Status | Die Website erreicht sich nicht selbst. |

Geprüft auf Apache 2.4 mit und ohne `mod_rewrite` und mit `AllowOverride None`, und auf nginx mit und ohne den Block, je mit PHP-FPM. Auf LiteSpeed ist es noch nicht geprüft.

## WP-CLI

```bash
wp living-handbook protect-files --dry-run
wp living-handbook protect-files
```

Der erste Aufruf listet jede Datei, die nicht liegt, wo sie hingehört, mit ID, Richtung (`protect` oder `release`) und Pfad. Der zweite verschiebt sie in Durchgängen von 60 Sekunden. Läuft gerade ein Cron-Durchgang, bricht der Befehl mit einem Hinweis ab.

## Deaktivieren und Deinstallieren

- **Deaktivieren:** Die Regel bleibt, die Dateien bleiben gesperrt (fail-closed). Ohne Plugin beantwortet niemand die Regel, Bilder interner Handbücher fehlen also überall. Ein Hinweis im Admin ist dann nicht mehr möglich, der Reiter **Zugriff** sagt es vorher.
- **Deinstallieren:** Warteschlange, Adresskarte, Selbsttest und Sperre gehen immer. Mit „Alles entfernen“ wandern vorher alle Dateien zurück in den normalen Ordner (`ProtectedFiles::release_all()`), weil die Mediathek bleibt (E-0074), und Regel und leere Ordner gehen. Ohne das Häkchen bleiben Dateien und Regel.

## Grenzen

- **Eine Datei, ein Handbuch.** Der Schutz folgt der Elternseite. Ein geschütztes Bild auf einer öffentlichen Seite sehen dort nur Personen, die das interne Handbuch lesen.
- **Offload-Plugins und CDN-Kopien** umgehen den Ordner. Der Reiter **Zugriff** warnt, wenn die Uploads von einem anderen Host kommen als die Website.
- **Page-Cache und CDN** dürfen die Antworten nicht zwischenspeichern. `Cache-Control: private` sagt das, ein falsch eingestellter Cache kann es übergehen.
- **Weitergegebene Adressen** zeigen nach dem Verschieben ins Leere. Das ist gewollt.
- **Bildgrößen,** die ein anderes Plugin später erzeugt, landen neben dem Original und damit im Schutzordner.

## Transport-Metadaten
* Seitentyp: Hintergrund / Konzept
* Zielgruppe: Technik
* Reihenfolge: 14
* Letzte Aktualisierung: 2026-11-09
