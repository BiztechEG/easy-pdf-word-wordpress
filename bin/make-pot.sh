#!/usr/bin/env bash
# Writes languages/easy-pdf-word.pot from the plugin's PHP files and header,
# then updates the bundled translations and compiles them.
#   bash bin/make-pot.sh       (needs gettext: xgettext, msgmerge, msgfmt)
set -euo pipefail

cd "$(dirname "$0")/.."
pot=languages/easy-pdf-word.pot
version=$(sed -n 's/^ \* Version: *//p' easy-pdf-word.php)

xgettext --language=PHP --from-code=UTF-8 --add-comments=translators: --sort-by-file \
	--keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_html_e --keyword=esc_attr__ \
	--keyword=esc_attr_e --keyword=_x:1,2c --keyword=esc_html_x:1,2c --keyword=_n:1,2 \
	--package-name="Easy PDF & Word" --package-version="$version" \
	--msgid-bugs-address="https://github.com/BiztechEG/easy-pdf-word-wordpress/issues" \
	-o "$pot.tmp" easy-pdf-word.php $(find src -name '*.php' | sort)

{
	printf '# Copyright (C) 2026 BizTech\n# This file is distributed under the GPL-2.0-or-later license.\n'
	sed -e '1,/^#, fuzzy/d' -e 's/charset=CHARSET/charset=UTF-8/' \
		-e 's/^"POT-Creation-Date: .*"$/"POT-Creation-Date: 2026-11-03 00:00+0000\\n"/' \
		-e 's/^"Language-Team: .*"$/"Language-Team: \\n"/' -e 's/^"Last-Translator: .*"$/"Last-Translator: \\n"/' \
		"$pot.tmp" | sed '1i #, fuzzy'
	# The plugin header, which WordPress translates on the Plugins screen.
	for field in "Plugin Name" Description; do
		value=$(sed -n "s/^ \* $field: *//p" easy-pdf-word.php)
		# Already in the code's strings.
		if grep -qxF "msgid \"${value//\"/\\\"}\"" "$pot.tmp"; then
			continue
		fi
		printf '\n#. %s of the plugin\n#: easy-pdf-word.php\nmsgid "%s"\nmsgstr ""\n' "$field" "${value//\"/\\\"}"
	done
} > "$pot"
rm "$pot.tmp"

for po in languages/*.po; do
	msgmerge --quiet --update --backup=none --no-fuzzy-matching "$po" "$pot"
	msgfmt --check -o "${po%.po}.mo" "$po"
done
