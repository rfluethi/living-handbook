#!/usr/bin/env bash
#
# Build an installable plugin zip from the last commit.
#
# The archive is created with `git archive`, which packages the committed tree
# (HEAD), not the working directory, and respects the export-ignore rules in
# .gitattributes so development files are left out. The archive is prefixed with
# living-handbook/ so it unpacks into the correct plugin folder.
#
# Usage: run from the repository root after committing your changes.
#   bash bin/build.sh

set -euo pipefail

if [ -n "$(git status --porcelain)" ]; then
	echo "There are uncommitted or untracked changes." >&2
	echo "The zip is built from the last commit (git archive HEAD), so commit and" >&2
	echo "push your changes first, then run this script again." >&2
	exit 1
fi

version="$(grep -oE "Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+" living-handbook.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
output="living-handbook-${version}.zip"

git archive --format=zip --prefix=living-handbook/ --output="${output}" HEAD

echo "Built ${output} from the current HEAD commit."
