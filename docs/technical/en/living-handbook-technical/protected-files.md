# Protected files

Files of pages in handbooks that are not public live in a folder of their own, which a server rule sends to WordPress. The plugin hands them out after the same access check as the page. This page describes where they live, how they move, how they are delivered, the server rules and the limits (decision E-0076).

## Where files live

A file belongs in the protected folder `wp-content/uploads/living-handbook-protected/YYYY/MM/` when its parent is a guarded post type (handbook page, learning path, see `AccessController::guarded_post_types()`) and its handbook is not public. A page without a handbook counts as protected, because it does not yet say who may read it. `ProtectedFiles::protects()` decides. Every other file stays where WordPress puts it.

The protected folder holds the main file, every image size and, for large images, the original next to the `-scaled` version. `_wp_attached_file` and `meta['file']` carry the path with the prefix `living-handbook-protected/`, so `wp_get_attachment_url()` returns the address in the protected folder.

## Moving

Files move in these cases:

- **On upload** to a page of a handbook that is not public, in the `wp_generate_attachment_metadata` filter, after WordPress made the image sizes. Not earlier: WordPress makes the sizes from the path it uploaded to and writes that path into the metadata. A file without metadata is checked at the end of the request (`shutdown`), not through the queue, so no pass beside a running upload takes the main file away.
- **When a file gets another parent** (`attachment_updated`).
- **When a page moves to another handbook** (`set_object_terms`). Up to 20 files at once, more through the queue.
- **When a handbook's visibility changes** (`added_term_meta`, `updated_term_meta`): every file of its pages through the queue.
- **After the update to 0.79.0**, once over every file of handbook pages.

The queue (`living_handbook_protect_queue`) works in cron passes under a lock (`living_handbook_protect_lock`), like the GitHub sync. When a pass runs out of time, it schedules the next one in 30 seconds.

`ProtectedFiles::move()` renames all files of an attachment together. When one fails, it undoes the others and changes nothing. Then it updates the addresses in the content: in every handbook page and learning path whose content holds the old address, in `srcset` and in root-relative addresses too. This change is written straight to the database, without a revision and without "Last updated", because the address changed, not the text. For content outside the handbooks the plugin keeps the old address (`living_handbook_moved_files`, at most 2000 entries) and replaces it on output through `the_content`.

A new file name is kept free in the twin folder too (filter `wp_unique_filename`): when `plan.jpg` already lies in the protected folder, a second upload is called `plan-1.jpg`, although WordPress checks only the normal folder.

The bundle import attaches each imported file to the first imported page that shows it. So it lands in the protected folder when the target handbook is not public.

## Delivery

The server rule rewrites every request for the protected folder to `index.php?lh_file=YYYY/MM/name`. `FileDelivery` answers it on `parse_request` and ends the request.

- **Path:** taken from `REQUEST_URI` where possible, because nginx copies the path into the query unescaped. Empty segments, `.`, `..`, backslash and null byte are refused. After `realpath()` the file has to lie in the protected folder; a symlink pointing outside gives 404. Only a file that belongs to an attachment (main file, size or original) is delivered.
- **Access:** whoever may edit the attachment (`edit_post`) gets it. Otherwise `AccessController::can_view_post()` of the parent page decides. A page that is not published also needs `read_post`; a page with a password does not hand out its files. A file whose page no longer needs protection is delivered until the queue has moved it back.
- **Refused:** a guest opening the file directly (`Sec-Fetch-Dest: document`, in older browsers `Accept: text/html`) gets 302 to the login. An embedded image and every signed-in person without access get 403.
- **Headers:** `Content-Type` from `wp_check_filetype()`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, max-age=0, must-revalidate`, `Last-Modified`, `ETag` with 304, `Range` with 206 and 416. SVG, HTML and XML come with `Content-Security-Policy: … sandbox`, so embedded script does not run. Images, video, audio, PDF and text come `inline`, everything else as a download.

### Handing off to the web server

By default PHP reads the file in blocks of 1 MB. For large files the hand-off to the web server pays off; switch it on with the filter `living_handbook_protected_files_handoff` (see [Hooks](hooks.md)):

| Value | Server | What it needs |
| --- | --- | --- |
| `x-accel-redirect` | nginx | The `internal` location from the nginx block below. |
| `x-sendfile` | Apache | The module `mod_xsendfile` with `XSendFilePath` set to the protected folder. |
| `x-litespeed-location` | LiteSpeed | Untested. The internal request may pass through the `.htaccess` again and so back to WordPress. Check on a real LiteSpeed before switching it on. |

The hand-off is not automatic: a header the server does not understand gives an empty file, and PHP cannot see whether nginx knows the internal location.

## Server rules

**Apache and LiteSpeed:** the plugin writes `.htaccess`, `index.php` and the test file `lh-selftest.txt` into the protected folder, on activation, on update and on the first move. At its core the `.htaccess` holds this rule:

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

Without `mod_rewrite` the rule locks the folder entirely. That is safe, but nobody gets a file.

**nginx** reads no `.htaccess`. The block goes into the site's `server` block, before the general `location` block. The plugin shows it on the **Access** tab with the site's paths:

```nginx
location ^~ /wp-content/uploads/living-handbook-protected/ {
    rewrite ^/wp\-content/uploads/living\-handbook\-protected/(.*)$ /index.php?lh_file=$1 last;
}
# Only with the X-Accel-Redirect hand-off:
location /lh-protected-internal/ {
    internal;
    alias /path/to/wp-content/uploads/living-handbook-protected/;
}
```

### Self-test

**Test the protection** on the **Access** tab requests `lh-selftest.txt` without cookies through `wp_remote_get()` (`ServerRules::selftest()`):

| Result | Recognised by | Meaning |
| --- | --- | --- |
| `protected` | Header `X-Living-Handbook-Protected` | The plugin answered. |
| `open` | The content of the test file came back | The server hands out the folder directly. |
| `locked` | 403 without the header | The server refuses, WordPress does not answer (Apache without `mod_rewrite`). |
| `unknown` | An error or another status | The site cannot reach itself. |

Checked on Apache 2.4 with and without `mod_rewrite` and with `AllowOverride None`, and on nginx with and without the block, each with PHP-FPM. Not yet checked on LiteSpeed.

## WP-CLI

```bash
wp living-handbook protect-files --dry-run
wp living-handbook protect-files
```

The first lists every file that is not where it belongs, with ID, direction (`protect` or `release`) and path. The second moves them in passes of 60 seconds. If a cron pass is running, the command stops with a note.

## Deactivating and uninstalling

- **Deactivating:** the rule stays, the files stay locked (fail-closed). Without the plugin nothing answers the rule, so images of internal handbooks are missing everywhere. An admin notice is no longer possible then; the **Access** tab says so beforehand.
- **Uninstalling:** queue, address map, self-test and lock always go. With "Remove everything" every file first moves back to the normal folder (`ProtectedFiles::release_all()`), because the media library stays (E-0074), and the rule and empty folders go. Without the box, files and rule stay.

## Limits

- **One file, one handbook.** The protection follows the parent page. A protected image on a public page is seen there only by people who read the internal handbook.
- **Offload plugins and CDN copies** bypass the folder. The **Access** tab warns when the uploads come from another host than the site.
- **Page caches and CDNs** must not keep the responses. `Cache-Control: private` says so; a cache that is set up wrongly can ignore it.
- **Addresses passed on** lead nowhere after a move. That is intended.
- **Image sizes** another plugin makes later land next to the original, and so in the protected folder.

## Transport-Metadaten
* Seitentyp: Background / Concept
* Zielgruppe: Tech
* Reihenfolge: 14
* Letzte Aktualisierung: 2026-11-09
