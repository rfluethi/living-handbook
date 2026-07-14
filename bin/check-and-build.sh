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

echo "==> Coding standards (phpcs)"
composer lint

echo "==> Static analysis (phpstan)"
composer analyze

echo "==> Unit tests (phpunit)"
composer test

echo "==> Translations: update .pot template and compile .l10n.php"
if command -v wp >/dev/null 2>&1; then
	wp i18n make-pot . languages/living-handbook.pot --exclude=vendor,node_modules,tests --slug=living-handbook
	wp i18n make-php languages/
else
	echo "wp-cli not found; skipping .pot and .l10n.php generation. The committed files are used as is."
	echo "Install wp-cli so the .pot template and the .l10n.php are generated from source automatically."
fi

echo "==> Build"
bash bin/build.sh

echo ""
echo "All checks passed. The zip is ready to install in WordPress."
