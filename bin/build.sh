#!/usr/bin/env bash
#
# Build an installable plugin zip from the current working tree.
#
# The runtime files ship: the main file, uninstall.php, src, assets, languages,
# the readme and LICENSE, plus the production Composer dependencies (vendor/,
# without dev packages) that the import and GitHub sync need. composer.json
# ships alongside vendor/, because Plugin Check flags a vendor directory without
# it. Those dependencies are moved into the plugin's own namespace on the way in,
# so they cannot collide with the same library in another plugin; that step needs
# PHP-Scoper and is checked afterwards, see below. Development files (tests, tooling, CI config, docs) are left out. Hidden
# files are stripped: macOS drops .DS_Store into every folder it displays, and
# Plugin Check rejects hidden files outright. The archive is prefixed with
# living-handbook/ so it unpacks into the correct plugin folder. Nothing is read
# from git, so you can build, install in WordPress and test before committing.
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
cp uninstall.php "$dest"/
cp composer.json "$dest"/
cp -R src "$dest"/
cp -R assets "$dest"/
if [ -d blocks ]; then cp -R blocks "$dest"/; fi
cp -R languages "$dest"/
# The app handbook ships with the plugin: its Markdown and images are imported
# from here by the "App handbook" tab, so it always matches the installed version.
if [ -d handbuch ]; then cp -R handbuch "$dest"/; fi
# The technical documentation ships for the same two reasons, decided 2026-08-08:
# the version always matches the installed plugin, and no installation depends on a
# repository staying reachable. Measured before deciding: docs/ is 184 KB against
# the 1.6 MB handbuch/ already contributes.
if [ -d docs ]; then cp -R docs "$dest"/; fi
if [ -d docs-de ]; then cp -R docs-de "$dest"/; fi
cp -R vendor "$dest"/
if [ -f readme.txt ]; then cp readme.txt "$dest"/; fi
if [ -f README.md ]; then cp README.md "$dest"/; fi
if [ -f LICENSE ]; then cp LICENSE "$dest"/; fi

# Move the bundled libraries into a namespace of their own, so a second plugin
# shipping the same library in another version cannot decide which copy this one
# uses. Only the staged vendor/ is prefixed, never src/ and never the working
# tree: see scoper.inc.php for why, and src/Support/Vendored.php for how the
# plugin finds the libraries afterwards. LH_SKIP_SCOPER=1 builds without it, for
# a quick local test; such a zip must not be released.
if [ "${LH_SKIP_SCOPER:-0}" = "1" ]; then
	echo "!! LH_SKIP_SCOPER=1: the bundled libraries keep their global names."
	echo "!! Fine for a local test, not shippable."
else
	scoper=""
	if command -v php-scoper >/dev/null 2>&1; then
		scoper="php-scoper"
	elif [ -f "${root}/tools/php-scoper.phar" ]; then
		scoper="php ${root}/tools/php-scoper.phar"
	else
		echo "php-scoper not found. A release must not ship libraries under their" >&2
		echo "global names: another plugin with the same library in a different" >&2
		echo "version would decide which copy the import uses." >&2
		echo "Install it (https://github.com/humbug/php-scoper), or put the phar in" >&2
		echo "tools/php-scoper.phar. To build without it anyway, set LH_SKIP_SCOPER=1." >&2
		exit 1
	fi

	$scoper add-prefix "${dest}/vendor" --output-dir="${stage}/vendor-scoped" \
		--config="${root}/scoper.inc.php" --force --quiet
	rm -rf "${dest}/vendor"
	mv "${stage}/vendor-scoped" "${dest}/vendor"

	# The classes moved, the rules that say where they live did not. Composer
	# drops a class from the classmap when it does not match its package's PSR-4
	# rule, so a stale rule turns a scoped tree into an autoloader that finds
	# almost nothing, without an error anywhere. Move the rules first.
	php "${root}/bin/prefix-autoload-rules.php" "$dest" 'LivingHandbook\Vendor'

	# The autoloader has to be rebuilt from the prefixed files: Composer reads the
	# class names out of the files themselves, so an authoritative classmap comes
	# out carrying the new names. The lock file is only there for this step and
	# does not ship.
	cp composer.lock "$dest"/ 2>/dev/null || true
	composer dump-autoload --classmap-authoritative --no-dev --no-interaction --quiet --working-dir="$dest"
	rm -f "${dest}/composer.lock"

	# A prefix that quietly did not take would produce a zip that looks right and
	# collides exactly as before, so the build proves it took and that the
	# libraries still work under the new name.
	php "${root}/bin/verify-vendor-prefix.php" "$dest"
fi

# Strip every hidden file. Plugin Check rejects them outright: macOS scatters
# .DS_Store and ._* resource forks, and a network or FUSE mount (Nextcloud)
# leaves .fuse_hidden* orphans when an open file is deleted. Removed from the
# staged copy, so a locked hidden file in the working tree never reaches the zip.
find "$dest" -name '.*' -type f -delete

rm -f "$output"
( cd "$stage" && zip -rq "${root}/${output}" living-handbook -x '*/.*' -x '__MACOSX/*' )
rm -rf "$stage"

echo "Built ${output} (version ${version}) with production vendor from the working tree."
