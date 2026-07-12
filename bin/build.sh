#!/usr/bin/env bash
#
# Build an installable plugin zip from the current working tree.
#
# The runtime files ship: the main file, src, assets, languages, the readme and
# LICENSE, plus the production Composer dependencies (vendor/, without dev
# packages) that the import and GitHub sync need. Development files (tests,
# tooling, CI config) are left out. The archive is prefixed with living-handbook/
# so it unpacks into the correct plugin folder. Nothing is read from git, so you
# can build, install in WordPress and test before committing.
#
# Usage: bash bin/build.sh

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

# Restore the full dev install on exit, whatever happens, so local tooling
# (phpcs, phpstan, phpunit) keeps working after the build.
restore_dev() {
	composer install --no-interaction --quiet >/dev/null 2>&1 || true
}
trap restore_dev EXIT

version="$(grep -oE "Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+" living-handbook.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
output="living-handbook-${version}.zip"

# Install only the runtime dependencies (commonmark, symfony/yaml) so vendor/
# is production-clean before it ships.
composer install --no-dev --optimize-autoloader --no-interaction --quiet

stage="$(mktemp -d)"
dest="${stage}/living-handbook"
mkdir -p "$dest"

# Runtime files that ship inside the plugin.
cp living-handbook.php "$dest"/
cp -R src "$dest"/
cp -R assets "$dest"/
cp -R languages "$dest"/
cp -R vendor "$dest"/
if [ -f readme.txt ]; then cp readme.txt "$dest"/; fi
if [ -f README.md ]; then cp README.md "$dest"/; fi
if [ -f LICENSE ]; then cp LICENSE "$dest"/; fi

rm -f "$output"
( cd "$stage" && zip -rq "${root}/${output}" living-handbook )
rm -rf "$stage"

echo "Built ${output} (version ${version}) with production vendor from the working tree."
