# AGENTS.md — Easy PDF & Word for WordPress

Guide for coding agents working in this repository.

## What this repo is

The WordPress plugin of Easy PDF & Word (slug `easy-pdf-word`, text domain
`easy-pdf-word`), for the wordpress.org plugin directory. It bundles the PHP
core [BiztechEG/easy-pdf-word](https://github.com/BiztechEG/easy-pdf-word)
with mPDF and PhpWord, and adds WooCommerce documents and a settings page.
License GPL-2.0-or-later (wordpress.org requires GPL, and mPDF is GPL).

- Docs site (built from the Laravel repo's `docs/`): https://easypdf.biztech-eg.com
- Template, engine, Arabic and language changes belong in the core: land them
  there, release it, then raise the core version here.

## Layout

- `easy-pdf-word.php` — plugin header and the PHP version check. Keep it free of
  PHP 8 syntax so old PHP shows a notice instead of a fatal error.
- `src/bootstrap.php` — loads `vendor-prefixed/autoload.php` and the plugin's
  autoloader for `BiztechEG\EasyPdfWordWP\`.
- `src/functions.php` — public functions (`easy_pdf_word()`).
- `src/Documents.php` — builds the core's factory from WordPress settings.
- `src/Settings.php` — the plugin's settings (option `easy_pdf_word_settings`) with defaults.
- `src/WooCommerce/` — loaded only when WooCommerce is active:
  `OrderData` (order → template data), `Numbers` (sequential invoice and
  credit note numbers in order meta), `OrderDocument` (type → template),
  `Downloads` (nonce + permission checked links via admin-post.php),
  `Emails`, `AdminOrders` (order box, list button, bulk ZIP), `MyAccount`.
- `vendor-prefixed/` — built by Strauss (`composer prefix`), not committed.
  Every bundled namespace is under `BiztechEG\EasyPdfWordWP\Vendor\`, so other
  plugins' copies of mPDF or PhpWord never clash with ours.
- `bin/build-zip.sh` — the release zip.

## Running tests

See CONTRIBUTING.md: `composer install && composer prefix`, a MySQL/MariaDB
database, then `composer test`. Tests use WordPress's own test suite
(`wp-phpunit/wp-phpunit` with `johnpbloch/wordpress-core`). The WooCommerce
tests run when `WC_DIR` names a WooCommerce folder (CI downloads the release
zip from GitHub); without it they are skipped.

## Rules from the maintainer

- **Commits are authored as BizTech**: before committing run
  `git config user.name "BizTech"` and
  `git config user.email "28879302+amrsoft13@users.noreply.github.com"`.
  No `Co-Authored-By` or session trailers in commit messages, and no mention of
  agents or sessions in commits, PRs or comments.
- **Many small commits**, one per logical change.
- **Branch every PR from `main`**; no stacked PRs.
- **Review after each phase** before starting the next.
- **Releases**: agents can't create tags or releases, or upload to
  wordpress.org. Open a PR that dates the changelog in `readme.txt`, write
  release notes for BizTech, who publishes.
- Repo settings are changed by BizTech, not agents.
- Repo files in English; user-facing strings go through `__()` with the
  `easy-pdf-word` text domain and get an Arabic translation in `languages/`.

## WordPress rules (keep them)

- WordPress coding style (tabs, `array()`, spaces inside parentheses).
- Every output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Every form and download link checks a nonce and a capability. A customer can
  only download their own order's documents.
- Prefix everything global with `easy_pdf_word_` / `EASY_PDF_WORD_`.
- The core's security defaults still apply: images only through
  `DocContext::image()`, remote images off, colours through `Support\Color`.
  Images may only come from the uploads folder.
