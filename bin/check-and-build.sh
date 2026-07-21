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
if command -v wp >/dev/null 2>&1; then
	wp i18n make-pot . languages/living-handbook.pot --exclude=vendor,node_modules,tests --slug=living-handbook
	if command -v msgmerge >/dev/null 2>&1; then
		# Refresh the German .po from the template so it carries the current
		# source references (make-json routes JS strings by these) and any new
		# strings. Keeps existing translations; needs gettext.
		for po in languages/*.po; do
			[ -e "$po" ] || continue
			msgmerge --update --backup=none "$po" languages/living-handbook.pot
		done
	else
		echo "msgmerge (gettext) not found; the .po keeps its current references."
		echo "Install gettext so the .po gains JS source references and the JS JSON gets the German strings."
	fi
	# Per-script JSON for wp_set_script_translations, generated from the .po.
	wp i18n make-json languages/ --no-purge
	wp i18n make-php languages/
else
	echo "wp-cli not found; skipping translation generation. The committed files are used as is."
	echo "Install wp-cli (and gettext) so the .pot, .po, JS JSON and .l10n.php are generated from source automatically."
fi

echo "==> Build"
bash bin/build.sh

echo ""
echo "All checks passed. The zip is ready to install in WordPress."
