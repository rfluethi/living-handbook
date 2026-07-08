#!/usr/bin/env bash
#
# Build an installable plugin zip from the current working tree.
#
# Only the runtime files ship (the main file, src, assets, languages, plus
# README and LICENSE). Development files (tests, tooling, CI config) are left
# out. The archive is prefixed with living-handbook/ so it unpacks into the
# correct plugin folder. Nothing is read from git, so you can build, install in
# WordPress and test before committing.
#
# Usage: bash bin/build.sh

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

version="$(grep -oE "Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+" living-handbook.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
output="living-handbook-${version}.zip"

stage="$(mktemp -d)"
dest="${stage}/living-handbook"
mkdir -p "$dest"

# Runtime files that ship inside the plugin.
cp living-handbook.php "$dest"/
cp -R src "$dest"/
cp -R assets "$dest"/
cp -R languages "$dest"/
if [ -f README.md ]; then cp README.md "$dest"/; fi
if [ -f LICENSE ]; then cp LICENSE "$dest"/; fi

rm -f "$output"
( cd "$stage" && zip -rq "${root}/${output}" living-handbook )
rm -rf "$stage"

echo "Built ${output} (version ${version}) from the working tree."
