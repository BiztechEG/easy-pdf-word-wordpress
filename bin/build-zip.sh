#!/usr/bin/env bash
# Builds build/easy-pdf-word.zip, the file uploaded to wordpress.org.
#
# The libraries are copied into vendor-prefixed/ under BiztechEG\EasyPdfWordWP\Vendor\
# by Strauss and bin/copy-assets.php. Development files stay out of the zip.
#   composer install && composer build
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
slug="easy-pdf-word"
work="$root/build/work"
dist="$root/build/$slug"

if [ ! -x "$root/vendor/bin/strauss" ]; then
	echo "Run composer install first." >&2
	exit 1
fi

rm -rf "$root/build"
mkdir -p "$work" "$dist"

# The committed files only, so local changes stay out.
git -C "$root" archive HEAD | tar -x -C "$dist"

# The libraries from `composer install`, prefixed. Strauss copies only the
# packages under "require", so development packages stay out.
cp "$root/composer.json" "$root/composer.lock" "$work/"
cp -r "$root/vendor" "$work/vendor"
(cd "$work" && vendor/bin/strauss --no-interaction > /dev/null)
php "$root/bin/copy-assets.php" "$work" > /dev/null
mv "$work/vendor-prefixed" "$dist/vendor-prefixed"
rm -rf "$work"

cd "$dist"

# Files that are only for development.
rm -rf AGENTS.md CONTRIBUTING.md README.md composer.json composer.lock phpunit.xml.dist phpcs.xml.dist tests bin .github .gitignore

# Development files of the bundled packages.
find vendor-prefixed -type d \( -iname tests -o -iname docs -o -iname samples -o -name .github \) -prune -exec rm -rf {} +
find vendor-prefixed -type f \( -iname '*.md' -o -name 'phpstan*' -o -name 'phpunit*' -o -name '.editorconfig' \
	-o -name '.gitattributes' -o -name '.gitignore' -o -name 'composer.json' -o -name 'composer.lock' \) -delete

cd "$root/build"
zip -qr "$slug.zip" "$slug"
echo "build/$slug.zip: $(du -h "$slug.zip" | cut -f1)"
