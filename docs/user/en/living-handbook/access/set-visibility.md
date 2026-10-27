# Set visibility

Visibility is controlled per handbook, not per page. This guide sets who may read a handbook on the website.

## Steps

1. Open **Handbook → Handbooks** and edit the handbook.
2. Choose one of the three levels:
   * **Public:** Everyone can read, even without logging in.
   * **All members (logged in):** Only people logged in to the website can read. This is the default for every new handbook.
   * **Restricted:** Only specific user roles and/or individually named people can read.
3. For **Restricted**: pick the roles. Add individual people if needed.
4. Save.

> **Important:** The levels apply to everyone except administrators. Administrators read every handbook in full, a restricted one too: the pages, the entry page, the entry in the overview and the learning paths. Everyone else, editors included, reads only what the handbook grants them. If your editors should read a restricted handbook, tick the role **Editor** or add the people individually. Leave both out and the handbook stays reserved for administrators.

![The edit screen of a handbook with the three visibility levels and the fields for roles and people.](../assets/sichtbarkeit-einstellen.webp)

## Result

The rule takes effect immediately and everywhere: on the handbook overview, on the pages, in the search, in the filters and in the menu. A handbook someone may not read appears nowhere for that person, not even as a name in a list. Someone logged in who opens a page of a handbook they may not read sees the notice "No access to this handbook" with their own name, or the page chosen as **No-access page** under [Settings → Access](../the-settings.md#access). Logged-out people are sent to the login.

The same rule holds in the WordPress admin. Other people's pages in a handbook someone may not read are missing there from the page list, their files from the media library and their comments from the comment list; they cannot be opened, edited or deleted. The handbook itself is missing from the list under **Handbook → Handbooks**, from the editor's handbook picker and from the pickers of the import, the export and the page list. Only someone who may read it can change its settings. Your own pages always stay visible and editable.

**Comments follow their page.** Whoever may read a page sees its comments and, while they are open, can write one. Whoever may not read it sees them neither on the website nor over the API. The right to edit your own page does not count here: if the handbook keeps you out, its comments are missing for you on the website. In the WordPress admin your own pages always count as well. The comment list there therefore shows the comments on exactly the pages your page list shows.

<details>
<summary>Pitfalls: Typical surprises</summary>

* **"I cannot see my handbook":** You are probably logged out, and the handbook is set to "All members". Test your readers' view in a private browser window.
* **Never test as an administrator.** With an Administrator account you see every page, including the ones your readers do not. Check the view with an account that has the same role as your readers.
* **Do not lock yourself out.** Someone who may not read a handbook can no longer open its settings either. If you set a handbook to **Restricted** without ticking your own role or yourself, it disappears from your list once you save. Only an administrator can bring it back.
* **A page only for administrators needs a handbook of its own.** Grants exist per handbook, not per page. Create a handbook set to **Restricted**, with no roles and no people, and move the page there.
* **Your own pages stay yours.** Anyone who may edit a page under the WordPress rules can open it in the block editor, save it, preview it and read it on the website. For your own pages that always holds, even when the handbook otherwise excludes you, and also for a new page that belongs to no handbook yet. Other people's pages in a handbook you may not read, on the other hand, you do not open, not even as an editor. Under the WordPress rules, authors edit only their own pages anyway, contributors only their own drafts. Lists, search and navigation follow the handbook's visibility. Someone who may not read a handbook therefore does not find even their own page in it through the navigation.
* **The counts above the page list count everything.** "All (12)" and the other counts include the pages the list below hides from you. The plugin does not adjust these numbers.
* **When moving to another website, individually allowed people do not travel along.** See [Move a handbook](../content/move-a-handbook.md).
* **Individually allowed people are personal data.** Under **Tools → Export Personal Data**, such a person appears with the handbooks that name them one by one. **Erase Personal Data** keeps the entry and reports it, since removing it would silently end an active account's access. If the person should no longer read a handbook, take them off in the handbook itself. A deleted account leaves every access list.

</details>

## Related pages

* [Understanding access](understanding-access.md)
* [Create your first handbook](../getting-started/create-your-first-handbook.md)

## Transport-Metadaten
* Seitentyp: Guide
* Verantwortliche Rolle: Handbook editors
* Thema: Access
* Zielgruppe: All members
* Eltern-Seite: Access
* Reihenfolge: 1
* Textauszug: Visibility is controlled per handbook; this guide sets who may read a handbook on the website.
* Letzte Aktualisierung: 2026-10-27
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 90 Tage
