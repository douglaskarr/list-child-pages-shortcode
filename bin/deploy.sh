#!/usr/bin/env bash
# Deploy this Git working tree to plugins.svn.wordpress.org.
#
# Default is a dry run. Pass --commit to actually svn commit.
# Requires SVN_USERNAME and SVN_PASSWORD (or a cached SVN auth session).
#
# GitHub Releases are the preferred path (.github/workflows/deploy.yml).
# Use this script only when you need a local WordPress.org push.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SLUG="list-child-pages-shortcode"
MAINFILE="dklcp-shortcode.php"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"
ASSETS_DIR=".wordpress-org"
COMMIT=0

usage() {
	cat <<EOF
Usage: bin/deploy.sh [--commit]

Copies the distributable plugin files into a temporary SVN checkout of
${SVN_URL}, updates trunk, copies trunk to tags/<version>, and updates assets.

Without --commit this is a dry run (svn status / diff only).
EOF
}

for arg in "$@"; do
	case "$arg" in
		--commit) COMMIT=1 ;;
		-h|--help) usage; exit 0 ;;
		*) echo "Unknown argument: $arg" >&2; usage; exit 1 ;;
	esac
done

fail() { echo "ERROR: $*" >&2; exit 1; }

SVN="$(command -v svn || true)"
if [[ -z "$SVN" ]]; then
	for candidate in /opt/homebrew/bin/svn /usr/local/bin/svn; do
		if [[ -x "$candidate" ]]; then
			SVN="$candidate"
			break
		fi
	done
fi
[[ -n "$SVN" ]] || fail "svn is not installed. brew install svn"

command -v rsync >/dev/null 2>&1 || fail "rsync is required"
command -v php >/dev/null 2>&1 || fail "php is required"

if [[ -n "$(git status --porcelain)" ]]; then
	fail "Working tree is dirty. Commit or stash before deploying."
fi

bash "$ROOT/bin/ci_check.sh"

VERSION="$(php -r '
$src = file_get_contents("dklcp-shortcode.php");
preg_match("/^\s*\*\s*Version:\s*(.+)$/m", $src, $m);
echo trim($m[1]);
')"
STABLE="$(php -r '
$src = file_get_contents("readme.txt");
preg_match("/^Stable tag:\s*(.+)$/m", $src, $m);
echo trim($m[1]);
')"

[[ -n "$VERSION" ]] || fail "Could not read plugin version"
if [[ "$STABLE" != "$VERSION" ]]; then
	fail "Stable tag ($STABLE) must equal Version ($VERSION) before a release deploy"
fi

SVN_ARGS=( --non-interactive )
if [[ -n "${SVN_USERNAME:-}" ]]; then
	SVN_ARGS+=( --username "$SVN_USERNAME" )
fi
if [[ -n "${SVN_PASSWORD:-}" ]]; then
	SVN_ARGS+=( --password "$SVN_PASSWORD" --no-auth-cache )
fi

WORKDIR="$(mktemp -d /tmp/${SLUG}-svn-XXXXXX)"
cleanup() { rm -rf "$WORKDIR"; }
trap cleanup EXIT

echo "SVN checkout → $WORKDIR"
"$SVN" checkout "${SVN_ARGS[@]}" --depth immediates "$SVN_URL" "$WORKDIR"
"$SVN" update "${SVN_ARGS[@]}" --set-depth infinity "$WORKDIR/trunk"
"$SVN" update "${SVN_ARGS[@]}" --set-depth infinity "$WORKDIR/assets"
"$SVN" update "${SVN_ARGS[@]}" --set-depth empty "$WORKDIR/tags"
"$SVN" update "${SVN_ARGS[@]}" --set-depth empty "$WORKDIR/tags/$VERSION" || true

echo "Sync trunk"
rsync -rc --delete --exclude-from="$ROOT/.distignore" "$ROOT/" "$WORKDIR/trunk/"

echo "Sync assets"
rsync -rc --delete "$ROOT/$ASSETS_DIR/" "$WORKDIR/assets/"

# Stage new/removed files in trunk and assets.
(
	cd "$WORKDIR"
	"$SVN" add --force trunk assets >/dev/null
	while IFS= read -r missing; do
		[[ -n "$missing" ]] && "$SVN" delete "$missing"
	done < <("$SVN" status trunk assets | awk '/^!/ {print $2}')
)

if [[ ! -d "$WORKDIR/tags/$VERSION" ]]; then
	echo "Create tags/$VERSION from trunk"
	"$SVN" copy "$WORKDIR/trunk" "$WORKDIR/tags/$VERSION"
fi

echo "SVN status:"
"$SVN" status "$WORKDIR"

if [[ "$COMMIT" -eq 0 ]]; then
	echo
	echo "Dry run only. Re-run with --commit to publish $VERSION to WordPress.org."
	exit 0
fi

"$SVN" commit "${SVN_ARGS[@]}" "$WORKDIR" -m "Release $VERSION from git"
echo "Committed $VERSION to $SVN_URL"
