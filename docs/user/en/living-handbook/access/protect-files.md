# Protect files

Images, PDFs and other files of a handbook that is not public reach only those who may read the handbook. That holds for someone who knows the address of a file or was sent it, too. This guide shows how to check that the protection works on your server.

<details>
<summary>Concept: How the protection works</summary>

Normally the web server hands out every file in `wp-content/uploads` directly, without asking WordPress. So Living Handbook keeps the files of pages in handbooks that are not public in a folder of their own, `wp-content/uploads/living-handbook-protected`, with every image size. A rule for the web server sends each request for this folder to WordPress. The plugin then checks the same access as for the page and hands out the file only when it matches.

* **Whoever may read the page** gets the file.
* **Whoever is signed in and may not read the page** gets an error.
* **Whoever is not signed in** and opens the file directly goes to the login. An embedded image simply stays empty for them.

A file follows the page it was uploaded to. When a page moves to another handbook or a handbook's visibility changes, the plugin moves the files in the background and updates the addresses in the handbook pages. Files of public handbooks and files that belong to no handbook page stay where they are, as fast as before.

</details>

## Before you start

* You are signed in as an administrator.
* On Apache and LiteSpeed nothing else is needed: the plugin writes the rule itself, as long as the server allows rules in `.htaccess`.
* On nginx the rule has to go into the server configuration. The plugin shows it ready to copy. If you cannot change the configuration yourself, give it to your host.
* In a network of several sites, every site has its own folder and its own rule. On nginx, add the block of every site that runs the plugin; each site's **Access** tab shows its own.

## Steps

1. **Open the settings.** Go to **Handbook → Settings** and choose the **Access** tab.
2. **Test the protection.** Under **Protected files**, click **Test the protection**. The plugin requests a test file in the protected folder without signing in.
3. **Read the result.**
	* **Green, "The protection holds":** everything is set up.
	* **Red, "The protection does not hold":** the folder hands out its files to anyone. On nginx the rule is shown below. Add it to the `server` block of your site and reload nginx. On Apache the server ignores the `.htaccess`. Ask your host whether `AllowOverride` can be allowed for the uploads folder.
	* **Red, "The folder is locked, but the files do not reach anyone":** the server locks the folder but does not let WordPress answer. On Apache `mod_rewrite` is usually missing. Nobody sees the files, not even those who may.
	* **Yellow:** the test could not reach the site. Some servers cannot call themselves. Ask your host in that case.
4. **Test again after every change to the server.** Until the test is green, treat the protection as not set up.
5. **Move waiting files if you do not want to wait.** After the update to this version and after a change of visibility, the plugin moves the files in the background. While files wait, the tab shows how many and the **Move now** button.

## Result

Images and files of your internal handbooks reach only the people who may read the handbook. For them nothing changes: images appear as before, links to files work.

<details>
<summary>Note: What you should know</summary>

* **One file, one handbook.** The protection follows the page a file was uploaded to. If you show a protected image on a public page too, only people who may read the internal handbook see it there.
* **Addresses passed on.** When a file moves, its address changes. An address passed on earlier then leads nowhere. That is intended.
* **Caches and CDNs.** Protected files come with the note that only the browser may keep them. A page cache or CDN that is set up wrongly can ignore that anyway. Exclude the `living-handbook-protected` folder there.
* **Files at another service.** If a plugin serves your uploads from another service, such as cloud storage, the folder does not protect those copies. The **Access** tab points this out.
* **Deactivating and deleting.** While the plugin is deactivated, the protected files stay locked: the rule stays, and nothing answers it. If you delete the plugin with the box that removes all content, the files first move back to the normal folder, because the media library stays.

</details>

<details>
<summary>Background: For the technical team</summary>

The rule for nginx, moving through WP-CLI (`wp living-handbook protect-files`) and handing the file to the web server are described in the [developer documentation on protected files](https://github.com/rfluethi/living-handbook/blob/main/docs/technical/en/living-handbook-technical/protected-files.md).

</details>

## Related pages

* [Understanding access](understanding-access.md)
* [Set visibility](set-visibility.md)
* [The settings](../the-settings.md)

## Transport-Metadaten
* Seitentyp: Guide
* Slug: protect-files
* Verantwortliche Rolle: Handbook editors
* Thema: Access
* Zielgruppe: Tech
* Eltern-Seite: Access
* Reihenfolge: 4
* Textauszug: How Living Handbook protects the files of internal handbooks, and how to check that the protection works on your server.
* Letzte Aktualisierung: 2026-11-10
* Letzte Prüfung: 2026-11-09
* Prüfintervall: 180 Tage
