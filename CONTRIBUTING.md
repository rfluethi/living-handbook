# Contributing

This is an internal team project that is open for anyone to read and, if useful, to build on. Contributions are welcome but not actively solicited.

## Development setup

```bash
composer install
```

That is enough to run the checks. It is not enough to see the plugin: `vendor/`
is a runtime dependency, so the plugin needs it installed before WordPress loads
it.

## Running it locally

The repository ships a [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
configuration, so a working WordPress with the plugin in it is two commands.
It needs Docker running.

```bash
composer install          # vendor/ first: the plugin needs it at runtime
npx wp-env start
```

The site is at `http://localhost:8888`, the admin at `/wp-admin` with
`admin` / `password`. `npx wp-env stop` stops it, `npx wp-env destroy` throws the
database away.

Two things the configuration takes care of, because both look like a broken
plugin when they are missing:

- **Pretty permalinks.** A handbook's entry page lives at
  `/handbook-set/<slug>/`, which plain permalinks cannot serve. The `afterStart`
  script sets them.
- **A block theme.** The plugin registers block templates, which only a block
  theme uses; on a classic theme the front end falls back to the theme's own
  layout and looks wrong for a reason that is not obvious. A current WordPress
  activates a block theme by default, so nothing extra is needed, but if you
  switch themes, switch to a block theme.

`.wp-env.override.json` is ignored by git, so you can change the port or the
WordPress version for yourself without touching the shared config.

## Before you open a pull request

Run the checks locally. All four run in CI on every pull request, and a pull
request is not merged until they are green:

```bash
composer lint             # PHPCS, WordPress coding standards
composer analyze          # PHPStan
composer test             # PHPUnit, unit tests
composer test:integration # PHPUnit, integration tests against a real WordPress
```

The integration tests are the ones that need setting up, because they need a
database and a WordPress checkout; the section below does that once. They are
not optional: they are more than four fifths of the suite, and they are where a
change to access control, import or the block templates is actually caught. CI
runs them against MySQL 8 on every push to `main` and every pull request.

The unit tests additionally run on PHP 8.1, 8.2, 8.3 and 8.4, because 8.1 is the
declared minimum and 8.4 is what current hosts ship.

### Setting up the integration test suite

The integration tests run against a real WordPress test environment. They need a throwaway MySQL/MariaDB database (it is wiped on every run, so use a dedicated one), a WordPress core checkout, and a `wp-tests-config.php`. `wp-phpunit` (a dev dependency) provides the test framework itself, so no separate test-lib checkout is needed.

1. Create an empty test database, for example `wordpress_test`, and a database user that can access it.
2. Get a WordPress core checkout, for example `git clone --depth=1 https://github.com/WordPress/WordPress.git /tmp/wordpress`.
3. Create a `wp-tests-config.php` (keep it out of git; it is already in `.gitignore`) with at least:

   ```php
   <?php
   define( 'ABSPATH', '/tmp/wordpress/' );
   define( 'DB_NAME', 'wordpress_test' );
   define( 'DB_USER', 'wp' );
   define( 'DB_PASSWORD', 'wp' );
   define( 'DB_HOST', 'localhost' );
   define( 'DB_CHARSET', 'utf8' );
   define( 'DB_COLLATE', '' );
   $table_prefix = 'wptests_';
   define( 'WP_TESTS_DOMAIN', 'example.org' );
   define( 'WP_TESTS_EMAIL', 'admin@example.org' );
   define( 'WP_TESTS_TITLE', 'Test' );
   define( 'WP_PHP_BINARY', 'php' );
   ```

4. Point the suite at that config through `WP_TESTS_CONFIG_FILE_PATH` and run the tests:

   ```bash
   export WP_TESTS_CONFIG_FILE_PATH="$(pwd)/wp-tests-config.php"
   LH_INTEGRATION=1 composer test:integration
   ```

`tests/bootstrap.php` reads `WP_TESTS_CONFIG_FILE_PATH` and defines the matching constant, so the config file above is the single place that describes your local database and WordPress path.

## Measuring performance

Performance work without a measurement is guessing, and the costs that matter only appear on a handbook that is actually large. Two scripts do that, in a development site with WP-CLI (Local ships it):

```bash
wp eval-file wp-content/plugins/living-handbook/bin/seed-performance.php
wp eval-file wp-content/plugins/living-handbook/bin/measure-performance.php
```

The first creates a public handbook "Performance-Test" with 2000 pages (`LH_SEED_PAGES` changes the count, `LH_SEED_RESET=1` clears a previous run first). The second renders the handbook entry page, one single page, and the navigation tree on its own, from the plugin's own block templates with the theme's header and footer removed, and reports for each: the number of queries, the wall time, the time spent in the database, and every query that ran more than once. That last list is the point of the whole exercise. A query that repeats a few hundred times is an N+1, and it names itself.

Every view is measured twice, once with an empty object cache and once straight after. Cold is what a site without a persistent object cache pays on every request, and is the number that has to get smaller; warm shows what caching already saves. The measurement runs as a logged-out visitor, because anything slow for a guest is slow for everyone.

Do not seed into a site whose content you want to keep: the test data is a real handbook with real pages.

## Building an installable zip

To run every check and, if they all pass, build the zip in one step:

```bash
bash bin/check-and-build.sh
```

To build the zip on its own:

```bash
bash bin/build.sh
```

The build needs [PHP-Scoper](https://github.com/humbug/php-scoper): it moves the bundled Composer libraries into `LivingHandbook\Vendor\`, so a second plugin shipping the same library in a different version cannot decide which copy this one uses. Put the phar in `tools/php-scoper.phar` (ignored by git) or install it on your PATH; use 0.18.19 or newer, older releases quietly leave the PHP files unprefixed on PHP 8.5. The build refuses to run without it; `LH_SKIP_SCOPER=1 bash bin/build.sh` builds anyway for a quick local test, and such a zip must not be released. After prefixing, the build proves the prefix took and the libraries still work, with `bin/verify-vendor-prefix.php`.

The build packages the current working tree, so you can build, install and test in WordPress before committing. Only the runtime files ship (the main file, `uninstall.php`, `composer.json`, `src`, `assets`, `blocks`, `languages`, `handbuch`, the production `vendor`, `readme.txt`, `README`, `LICENSE`); development files (tests, CI config, tooling, `docs`) are left out. The result is `living-handbook-<version>.zip`.

Do not extract the zip inside the repository; that would commit a second copy of the plugin. Install it in WordPress under Plugins, Add new, Upload plugin.

## Conventions

- **English is the language of the repository.** Code, comments, commit messages, pull request and issue text, `readme.txt` and the English `docs/` are written in English, so the project stays open to contributors who do not speak German. The German files in the repository are the localisation files `languages/*-de_DE.*` and the German app handbook under `handbuch/de/`, whose pages and image names are German too. The German source of the developer docs is kept in the project's own workspace, not in this repository; the English `docs/` here is produced from it.
- Target PHP 8.1 and WordPress 6.8 or newer.
- Follow the WordPress coding standards (enforced by PHPCS).
- All user-facing strings use the `living-handbook` text domain, and English is the source language, so the plugin is translatable into any language.
- **German content that ships with the plugin uses German spelling, with `ß`.** That is the app handbook under `handbuch/de/` and the `de_DE` translation. `de_CH` is the Swiss variant of the same text with `ß` resolved to `ss`, derived from `de_DE` rather than written separately. Both follow the current rules; they differ only in that Switzerland dropped the `ß`. Neither is the pre-1996 spelling, and no file here uses it.
- Escape on output, sanitize on input, check capabilities and nonces.
- **Two changelogs, and they are not the same file.** `readme.txt` carries the
  short, user-facing entry that WordPress shows in the update dialog; wp.org
  reads it and the 300-character limit on an upgrade notice is enforced by a
  test. `CHANGELOG.md` carries the long version: what was wrong, what changed
  and why it was decided that way. A user-facing change goes in both, the short
  form in `readme.txt` and the reasoning in `CHANGELOG.md`. An internal change
  goes in `CHANGELOG.md` alone.
- Releasing is written down in [`docs/releasing.md`](docs/releasing.md). Read it
  before touching a version number: three files have to agree, and a build
  refuses to run when they do not.
- Branch off `main`, keep pull requests focused, and make sure CI is green.
