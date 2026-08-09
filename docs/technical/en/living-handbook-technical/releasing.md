# Releasing

A release is a tag and a published GitHub release. The zip is built for you, by
CI, from the tagged commit. You do not upload one.

This page exists because the rules are enforced by scripts that fail with a
short message and no explanation, and because the first thing anybody gets wrong
is the version number, which lives in three files.

## The version lives in three places, and they must agree

| File | Line |
| --- | --- |
| `living-handbook.php` | the `Version:` header |
| `living-handbook.php` | the `LIVING_HANDBOOK_VERSION` constant |
| `readme.txt` | `Stable tag:` |

`bin/check-and-build.sh` compares all three before it does anything else and
stops with `Version mismatch` if they differ. A unit test in `tests/Unit/SmokeTest.php`
checks the same thing, plus that the newest changelog entry in `readme.txt` is
the entry for that version. So a forgotten changelog entry turns the test suite
red rather than shipping quietly.

`Stable tag` is the line wp.org reads to decide which version it serves, so it
has to name a version that is actually released. It does, and by construction:
the version is raised in the release commit itself, not while work is in
progress, so between two releases `main` carries the last released version.
Checked over v0.53.0 to v0.58.0: at every tag the header, the constant and
`Stable tag` all read the tag's own number.

That is the whole rule, and it only holds as long as nobody raises the version
in a feature branch. If that ever becomes convenient, `Stable tag` has to stop
following the header and start naming the last published version instead, and
the three-way check has to learn the difference. Until then, do not raise the
version except in the commit that is released.

One thing does change at the wordpress.org submission: there `Stable tag` must
name a version that exists in the SVN `tags/` directory. Publishing the first
version to SVN is what satisfies that; no change here is needed for it.

## Steps

1. **Decide the number.** The project is in 0.x and stays there until it has
   been validated in real use. Every feature step raises the minor version;
   0.x.Y is for fixes.
2. **Write the changelog, both of them.** The short user-facing entry in
   `readme.txt` (see the note on the two changelogs in `CONTRIBUTING.md`), the
   full reasoning in `CHANGELOG.md`. If you add an upgrade notice in
   `readme.txt`, keep it under 300 characters: wp.org enforces that, Plugin Check
   reports it, and a test measures it.
3. **Raise the version** in the three places above.
4. **Run the checks and build:**

   ```bash
   bash bin/check-and-build.sh
   ```

   This runs PHPCS, PHPStan and the unit tests, regenerates the translation
   files, builds `living-handbook-<version>.zip` and, if a WordPress with the
   Plugin Check plugin is reachable, runs Plugin Check on the zip. Install that
   zip and try the change before going further. Run `composer test:integration`
   too; `check-and-build.sh` does not.

   Every `wp` call in the script is filtered: on PHP 8.4, wp-cli 2.12 raises a
   deprecation inside its own `php-cli-tools` on every table it prints, and
   those lines are dropped by file path, so the build's own output stays
   readable. Warnings and errors from anywhere else, this plugin included, come
   through untouched. Set `WP_CLI_PHP_ARGS` yourself to see all of it.
5. **Check that nothing moved.** `git status` must be clean after the build. If
   it is not, the translation files changed and belong in the commit: the point
   of a release is that the zip and the tag are the same code, and that claim
   only holds if the working tree matches the commit.
6. **Commit and push** to `main`.
7. **Tag and push the tag:**

   ```bash
   git tag -a v<version> -m "Living Handbook <version>"
   git push origin v<version>
   ```

8. **Publish the release** on GitHub against that tag, with notes. A draft or a
   prerelease deliberately does not get a zip.

## What happens on its own

`.github/workflows/release.yml` runs when a release is *published*, not when the
tag is pushed. It:

- refuses to continue if the tag does not match the `Version:` header, so a zip
  can never claim a version it is not,
- runs PHPCS, PHPStan and the unit tests again, so a release cannot come from a
  commit that would fail CI, however the tag was made,
- installs PHP-Scoper at a pinned version, builds the zip with `bin/build.sh`,
  and attaches it to the release.

Give it a couple of minutes, then check the release page for the zip:

```bash
gh release view v<version> --json assets --jq '.assets[].name'
```

An empty answer means the workflow is still running or it failed; the Actions
tab says which.

## Why the zip comes from CI

Because it is the only version of "the zip matches the tag" that can be checked
rather than believed. A zip built on a laptop is built from a working tree, and
a working tree is only the tagged commit if nobody looked at it in between. The
one built by the workflow starts from a fresh checkout of the tag.

`bin/build.sh` also needs PHP-Scoper, which moves the bundled Composer libraries
into the plugin's own namespace so a second plugin shipping the same library
cannot decide which copy this one uses. The workflow pins the PHP-Scoper version
for the same reason it pins everything else: a build tool that changes under a
release is a release nobody can reproduce.
