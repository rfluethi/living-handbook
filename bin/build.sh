#!/usr/bin/env bash
#
# Build an installable plugin zip.
#
# Uses `git archive`, which respects the export-ignore rules in .gitattributes,
# so development files (tests, CI config, tooling) are left out. The archive is
# prefixed with living-handbook/ so it unpacks into the correct plugin folder.
#
# Usage: run from the repository root after committing your changes.
#   bash bin/build.sh

set -euo pipefail

version="$(grep -oE "Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+" living-handbook.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
output="living-handbook-${version}.zip"

git archive --format=zip --prefix=living-handbook/ --output="${output}" HEAD

echo "Built ${output} from the current HEAD commit."
