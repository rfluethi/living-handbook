#!/usr/bin/env bash
#
# Replace the bundled mermaid.js with a release from npm, and record what it is.
#
# assets/js/mermaid.min.js is a 3.5 MB third-party bundle that the plugin ships
# and serves to every reader of a page with a diagram. 0.75.0 shipped 11.16.0,
# which carries a published KaTeX vulnerability (GHSA-238p-pmpm-9mq7, fixed in
# 11.17.2), and nothing in the repository said which version the file was or
# where it came from: the only way to tell was to grep the minified code. This
# script is the one way the file changes, so that question always has an answer.
#
# It downloads the package with `npm pack`, checks the tarball against the
# integrity hash the npm registry publishes for that version, and copies
# dist/mermaid.min.js and the LICENSE into assets/js/. It then writes
# assets/js/mermaid.version.txt (version, registry integrity, sha256 of the file
# as shipped) and regenerates assets/js/mermaid.THIRD-PARTY.txt, the licenses of
# everything bundled inside it. tests/Unit/MermaidBundleTest.php checks that the
# recorded hash matches the file, so a hand-copied mermaid.min.js fails the
# tests instead of shipping unrecorded.
#
# Nothing downloaded is executed: the tarball is unpacked into a temporary
# directory and only read.
#
# Usage: bash bin/update-mermaid.sh [version]
#        Without a version, the newest 11.x release. A new major version is a
#        deliberate step: pass it explicitly, then test the block and the export.

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

for tool in npm node tar sha256sum; do
	if ! command -v "$tool" >/dev/null 2>&1; then
		echo "$tool not found; it is needed to fetch and verify the package." >&2
		exit 1
	fi
done

version="${1:-}"
if [ -z "$version" ]; then
	# npm answers a range with one line per match; the last is the newest.
	version="$(npm view 'mermaid@>=11 <12' version --json | node -e '
		let s = ""; process.stdin.on("data", d => s += d).on("end", () => {
			const v = JSON.parse(s); console.log(Array.isArray(v) ? v[v.length - 1] : v);
		});')"
fi
if ! printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
	echo "Not a release version: '$version'." >&2
	exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

echo "==> mermaid ${version} from npm"
( cd "$work" && npm pack "mermaid@${version}" --silent >/dev/null )
tarball="${work}/mermaid-${version}.tgz"

# The registry's integrity value is a sha512 over the tarball, base64-encoded.
# Comparing against it is what ties the file to the published release rather
# than to whatever a mirror or a proxy handed over.
expected="$(npm view "mermaid@${version}" dist.integrity)"
actual="sha512-$(node -e '
	const c = require("crypto"), f = require("fs");
	process.stdout.write(c.createHash("sha512").update(f.readFileSync(process.argv[1])).digest("base64"));
' "$tarball")"
if [ "$expected" != "$actual" ]; then
	echo "Integrity mismatch for mermaid@${version}:" >&2
	echo "  registry: $expected" >&2
	echo "  tarball:  $actual" >&2
	exit 1
fi
echo "Integrity matches the registry: $expected"

mkdir "${work}/pkg"
tar -xzf "$tarball" -C "${work}/pkg"
package="${work}/pkg/package"

if [ ! -f "${package}/dist/mermaid.min.js" ] || [ ! -f "${package}/LICENSE" ]; then
	echo "The package has no dist/mermaid.min.js or LICENSE; its layout changed." >&2
	exit 1
fi

cp "${package}/dist/mermaid.min.js" assets/js/mermaid.min.js
cp "${package}/LICENSE" assets/js/mermaid.LICENSE.txt

sha="$(sha256sum assets/js/mermaid.min.js | cut -d' ' -f1)"
cat > assets/js/mermaid.version.txt <<EOF
mermaid ${version}
source: https://www.npmjs.com/package/mermaid/v/${version} (dist/mermaid.min.js)
npm integrity: ${expected}
sha256 of assets/js/mermaid.min.js: ${sha}
Updated with bin/update-mermaid.sh; do not edit the bundle by hand.
EOF

echo "==> Licenses of the bundled packages"
node "${root}/bin/mermaid-third-party.js" "$package" "$work" > assets/js/mermaid.THIRD-PARTY.txt

echo ""
echo "assets/js/mermaid.min.js is mermaid ${version} (sha256 ${sha})."
echo "Run the unit and integration tests, then try a diagram in the editor and in a static export."
