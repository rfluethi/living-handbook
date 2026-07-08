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

echo "==> Build"
bash bin/build.sh

echo ""
echo "All checks passed. The zip is ready to install in WordPress."
