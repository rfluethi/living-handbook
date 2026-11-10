# The version of a handbook

Every handbook carries a version, such as "Handbook version 2.1.14". It shows which state of a handbook is in front of you: on the site, in an export or in a bundle. This page explains what it consists of, what moves it on and how you set it yourself, from a tag on GitHub too.

<details>
<summary>Concept: Edition and count</summary>

The version has two parts:

* **The edition**, "2.1" in the example. You set it on the handbook by hand, or it comes from a tag on GitHub. Take a new edition when you release a state on purpose, for instance after a revision.
* **The count**, "14" in the example. The plugin keeps it. It goes up by one on every day the published content of the handbook changes. A new edition starts it again at 0.

A change is a page that is published, changed in its text or title, taken back, moved to another handbook or deleted. Several changes on the same day make a single number. Drafts, a new excerpt, another order and confirming a review do not count: they do not change what readers read.

The version is not the plugin's. That one appears, for signed-in people only, as "Living Handbook 0.81.0" on a line of its own.

</details>

## Where the version appears

* **On the site:** at the bottom of the handbook's entry page and in the footer of every page, for every reader. There is no date next to it, so a handbook that stays right for a long time does not look out of date.
* **In the website export:** in the footer of every page, as it was at the moment of the export.
* **In the Markdown export:** in `EXPORT.txt` and in `mkdocs.yml`.
* **In the bundle:** an import that creates a new handbook takes the version over. An existing handbook just keeps counting.
* **In the admin:** in the **Version** column of the handbook list.

## Set the edition by hand

1. Open **Handbook → Handbooks** and click the name of the handbook.
2. Enter the new edition in the **Edition** field: one to three numbers with dots, such as `2` or `2.1`.
3. Click **Update**. The version is now `2.1.0`; the next change makes it `2.1.1`.

## Take the edition from GitHub

If the handbook has pages from GitHub, the edition can come from a tag of the repository.

1. On the handbook, under **Edition comes**, choose **from the GitHub tag**. If the pages come from several repositories, choose the one that counts next to it.
2. Click **Update**. The plugin looks for the tag right away and shows below which tag the edition comes from. From now on the **Edition** field is locked.

This is how the tag is found:

* **If the pages follow a tag**, such as `…/v2.1/docs/…` in their address, that tag counts. GitHub is not asked.
* **If the pages follow a branch**, such as `main`, the scheduled sync asks for the repository's tags at the end of each pass. The highest one that looks like a version number counts: `v2.1` becomes the edition `2.1`; tags such as `beta` are skipped.

A new tag is a new edition, and the count starts at 0. After that it counts the changes since that tag: "2.1.3" means changed on three days since `v2.1`.

<details>
<summary>Pitfalls: Tags for code and docs</summary>

* **A tag applies to the whole repository.** If the docs live next to the code, the edition also jumps on a release that only concerns the code.
* **GitHub does not always answer.** Without signing in, GitHub allows a site 60 requests per hour, for all requests together. If an answer does not come, the edition stays as it was, and the handbook shows a notice in the admin.
* **Two exports on the same day** can carry the same number, even if something changed in between.

</details>

## Related pages

* [The review cycle](the-review-cycle.md)
* [Move a handbook](../content/move-a-handbook.md)
* [Export a handbook as Markdown](../content/export-as-markdown.md)

## Transport-Metadaten
* Seitentyp: Background / Concept
* Slug: the-version-of-a-handbook
* Verantwortliche Rolle: Handbook editors
* Thema: Upkeep
* Zielgruppe: All members
* Eltern-Seite: Upkeep
* Reihenfolge: 5
* Textauszug: What the version of a handbook consists of, what moves it on, where it appears and how to set it by hand or from a GitHub tag.
* Letzte Aktualisierung: 2026-11-10
* Letzte Prüfung: 2026-11-10
* Prüfintervall: 180 Tage
