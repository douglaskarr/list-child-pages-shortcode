#!/usr/bin/env bash
# Build a clean, installable zip of the plugin using .distignore.
# Usage: bin/build.sh [output-dir]   (default: ../ relative to the plugin folder)
set -euo pipefail

SLUG="list-child-pages-shortcode"
MAIN="dklcp-shortcode.php"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$(dirname "$ROOT")}"
VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$ROOT/$MAIN" | head -1 | tr -d '[:space:]')"
STABLE="$(sed -n 's/^Stable tag:[[:space:]]*//p' "$ROOT/readme.txt" | tr -d '[:space:]')"

if [ "$VERSION" != "$STABLE" ]; then
	echo "Version mismatch: plugin header $VERSION vs readme Stable tag $STABLE" >&2
	exit 1
fi

BUILD="$ROOT/build"
rm -rf "$BUILD"
mkdir -p "$BUILD/$SLUG"

EXCLUDES=()
while IFS= read -r line || [ -n "$line" ]; do
	[ -z "$line" ] && continue
	case "$line" in \#*) continue ;; esac
	EXCLUDES+=("--exclude=$line")
done < "$ROOT/.distignore"

# COPYFILE_DISABLE stops macOS from creating ._ AppleDouble files.
COPYFILE_DISABLE=1 rsync -a "${EXCLUDES[@]}" "$ROOT/" "$BUILD/$SLUG/"
find "$BUILD/$SLUG" \( -name '._*' -o -name '.DS_Store' -o -name 'AGENTS.md' \) -delete

ZIP="$OUT_DIR/$SLUG-$VERSION.zip"
rm -f "$ZIP"
( cd "$BUILD" && COPYFILE_DISABLE=1 zip -rqX "$ZIP" "$SLUG" )
echo "Built $ZIP"
unzip -l "$ZIP"
