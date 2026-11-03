#!/usr/bin/env bash
# Builds build/easy-pdf-word.zip, the file uploaded to wordpress.org.
#
# The libraries are installed without development packages and copied into
# vendor-prefixed/ under BiztechEG\EasyPdfWordWP\Vendor\ by Strauss, then
# development files and the mPDF fonts the plugin does not use are left out.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
slug="easy-pdf-word"
work="$root/build/work"
dist="$work/$slug"

rm -rf "$root/build"
mkdir -p "$dist"

# A clean copy of the tracked files, so local changes and vendor/ stay out.
git -C "$root" archive HEAD | tar -x -C "$work"
mv "$work"/* "$dist"/ 2>/dev/null || true

cd "$work"
cp -r "$root/vendor" "$work/vendor"
composer install --no-dev --no-interaction --no-progress --quiet --working-dir="$work"
# Strauss is a development package, so it runs from the full install.
(cd "$work" && "$root/vendor/bin/strauss" --no-interaction)
mv "$work/vendor-prefixed" "$dist/vendor-prefixed"

cd "$dist"

# Files that are only for development.
rm -rf AGENTS.md CONTRIBUTING.md composer.json composer.lock phpunit.xml.dist tests bin .github .gitignore .distignore

prefixed="vendor-prefixed"

# Development files of the bundled packages.
find "$prefixed" -type d \( -iname tests -o -iname test -o -iname docs -o -iname samples -o -iname .github \) -prune -exec rm -rf {} +
find "$prefixed" -type f \( -iname '*.md' -o -name 'phpstan*' -o -name 'phpunit*' -o -name '.editorconfig' -o -name '.gitattributes' -o -name '.gitignore' -o -name 'composer.json' -o -name 'composer.lock' \) -delete

# mPDF's fonts: the plugin draws with the library's own fonts (Cairo, Noto
# Naskh Arabic, Tajawal). DejaVu Sans Condensed stays as mPDF's default font.
fonts="$prefixed/mpdf/mpdf/ttfonts"
find "$fonts" -type f ! -name 'DejaVuSansCondensed*' ! -name 'DejaVuinfo.txt' -delete
rm -rf "$prefixed/mpdf/mpdf/utils"

cd "$work"
zip -qr "$root/build/$slug.zip" "$slug"
echo "build/$slug.zip: $(du -h "$root/build/$slug.zip" | cut -f1)"
