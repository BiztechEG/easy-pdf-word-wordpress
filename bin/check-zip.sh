#!/usr/bin/env bash
# Checks the plugin zip: its size, its files, that every bundled namespace is
# prefixed, and that the bundled libraries make a PDF and a Word file alone.
set -euo pipefail

zip_file="$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"
root="$(cd "$(dirname "$0")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

unzip -q "$zip_file" -d "$work"
plugin="$work/easy-pdf-word"

# wordpress.org takes zips up to 10 MB.
size=$(stat -c %s "$zip_file")
echo "Zip size: $((size / 1024)) KB"
if [ "$size" -gt $((10 * 1024 * 1024)) ]; then
	echo "The zip is over 10 MB." >&2
	exit 1
fi

for file in easy-pdf-word.php uninstall.php readme.txt LICENSE src/bootstrap.php assets/settings.js vendor-prefixed/autoload.php \
	vendor-prefixed/biztecheg/easy-pdf-word/resources/templates/invoice/template.php \
	vendor-prefixed/biztecheg/easy-pdf-word/resources/fonts; do
	if [ ! -e "$plugin/$file" ]; then
		echo "Missing from the zip: $file" >&2
		exit 1
	fi
done

for file in composer.json phpcs.xml.dist phpunit.xml.dist tests bin vendor AGENTS.md; do
	if [ -e "$plugin/$file" ]; then
		echo "Should not be in the zip: $file" >&2
		exit 1
	fi
done

unprefixed=$(grep -rlE '^namespace (Mpdf|PhpOffice|BiztechEG\\EasyPdfWord\b|chillerlan|Psr|setasign|DeepCopy|ZipStream)' "$plugin/vendor-prefixed" || true)
if [ -n "$unprefixed" ]; then
	echo "Namespaces left without the prefix:" >&2
	echo "$unprefixed" >&2
	exit 1
fi

php "$root/bin/smoke-test.php" "$plugin"
