#!/usr/bin/env bash
# Local/CI gate for this plugin. Does not talk to WordPress.org SVN.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

fail() { echo "ERROR: $*" >&2; exit 1; }

command -v php >/dev/null 2>&1 || fail "php is required"

php -l dklcp-shortcode.php >/dev/null

header_version="$(php -r '
$src = file_get_contents("dklcp-shortcode.php");
if (!preg_match("/^\s*\*\s*Version:\s*(.+)$/m", $src, $m)) {
  fwrite(STDERR, "Missing Version header in dklcp-shortcode.php\n");
  exit(1);
}
echo trim($m[1]);
')"

readme_version="$(php -r '
$src = file_get_contents("readme.txt");
if (!preg_match("/^Version:\s*(.+)$/m", $src, $m)) {
  fwrite(STDERR, "Missing Version in readme.txt\n");
  exit(1);
}
echo trim($m[1]);
')"

stable_tag="$(php -r '
$src = file_get_contents("readme.txt");
if (!preg_match("/^Stable tag:\s*(.+)$/m", $src, $m)) {
  fwrite(STDERR, "Missing Stable tag in readme.txt\n");
  exit(1);
}
echo trim($m[1]);
')"

if [[ "$header_version" != "$readme_version" ]]; then
	fail "Plugin header Version ($header_version) does not match readme.txt Version ($readme_version)"
fi

echo "Version: $header_version"
echo "Stable tag: $stable_tag"

if [[ "$stable_tag" != "$header_version" ]]; then
	echo "WARNING: Stable tag ($stable_tag) differs from Version ($header_version)."
	echo "WordPress.org will serve tags/$stable_tag until Stable tag is updated and tagged."
fi

if [[ -x vendor/bin/phpunit ]]; then
	vendor/bin/phpunit
else
	echo "Skipping PHPUnit (run: composer install)"
fi

if [[ -x vendor/bin/phpcs ]]; then
	vendor/bin/phpcs
else
	echo "Skipping PHPCS (run: composer install)"
fi

echo "ci_check: ok"
