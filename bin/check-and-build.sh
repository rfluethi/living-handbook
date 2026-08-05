#!/usr/bin/env bash
#
# Run all quality checks and, if they pass, build the plugin zip.
#
# Runs PHPCS (coding standards), PHPStan (static analysis) and the unit tests,
# then bin/build.sh. Stops at the first failure, so a zip is only produced from
# code that passes every check. This mirrors what the CI runs, so a green run
# here means a green run on GitHub.
#
# Usage: bash bin/check-and-build.sh

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

echo "==> Version consistency (header, constant, readme Stable tag)"
header_version="$(grep -oE "Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+" living-handbook.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
const_version="$(grep -oE "LIVING_HANDBOOK_VERSION', '[0-9]+\.[0-9]+\.[0-9]+" living-handbook.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
readme_version="$(grep -oE "Stable tag:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+" readme.txt | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
if [ "$header_version" != "$const_version" ] || [ "$header_version" != "$readme_version" ]; then
	echo "Version mismatch: header=$header_version constant=$const_version readme=$readme_version" >&2
	echo "Align all three before building." >&2
	exit 1
fi
echo "All three read $header_version."

echo "==> Coding standards (phpcs)"
composer lint

echo "==> Static analysis (phpstan)"
composer analyze

echo "==> Unit tests (phpunit)"
composer test

echo "==> Translations: update .pot, refresh .po, compile .l10n.php and JS JSON"

# make-pot and msgmerge stamp POT-Creation-Date on every run, so a rebuild that
# found nothing new still rewrites the file. That single line is enough to make
# a working tree differ from the commit it was built at, which is exactly what
# a release must be able to rule out. Keep the previous file whenever the
# content apart from that stamp is unchanged.
keep_if_only_the_stamp_moved() {
	local file="$1" before="$2"
	[ -f "$before" ] || return 0
	if diff -q <(grep -v '^"POT-Creation-Date' "$before") <(grep -v '^"POT-Creation-Date' "$file") >/dev/null 2>&1; then
		cp "$before" "$file"
	fi
}

if command -v wp >/dev/null 2>&1; then
	pot="languages/living-handbook.pot"
	pot_before="$(mktemp)"
	[ -f "$pot" ] && cp "$pot" "$pot_before"
	wp i18n make-pot . "$pot" --exclude=vendor,node_modules,tests --slug=living-handbook
	keep_if_only_the_stamp_moved "$pot" "$pot_before"
	rm -f "$pot_before"
	if command -v msgmerge >/dev/null 2>&1; then
		# Refresh the German .po from the template so it carries the current
		# source references (make-json routes JS strings by these) and any new
		# strings. Keeps existing translations; needs gettext.
		for po in languages/*.po; do
			[ -e "$po" ] || continue
			po_before="$(mktemp)"
			cp "$po" "$po_before"
			msgmerge --update --backup=none --no-fuzzy-matching "$po" "$pot"
			keep_if_only_the_stamp_moved "$po" "$po_before"
			rm -f "$po_before"
		done
	else
		echo "msgmerge (gettext) not found; the .po keeps its current references."
		echo "Install gettext so the .po gains JS source references and the JS JSON gets the German strings."
	fi
	# Per-script JSON for wp_set_script_translations, generated from the .po.
	wp i18n make-json languages/ --no-purge
	wp i18n make-php languages/
else
	echo "wp-cli not found. A release must not ship translation files that were" >&2
	echo "not regenerated from source: the .pot would miss new strings and the" >&2
	echo "German .po would silently fall behind. Install wp-cli (and gettext)," >&2
	echo "or run bin/build.sh directly if you knowingly want a zip without it." >&2
	exit 1
fi

echo "==> Build"
bash bin/build.sh

version="$(grep -oE "Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+" living-handbook.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
zip="living-handbook-${version}.zip"

echo "==> Plugin Check (wordpress.org guidelines)"
if wp plugin list >/dev/null 2>&1 && wp plugin check --help >/dev/null 2>&1; then
	wp plugin check "$zip" || echo "Plugin Check reported findings; see above." >&2
else
	echo "wp plugin check is not available (needs a WordPress install and the"
	echo "plugin-check plugin). Skipped: run it before submitting to wordpress.org."
fi

echo ""
echo "All checks passed. The zip is ready to install in WordPress."
