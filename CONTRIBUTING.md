# Contributing

## Set up

```bash
composer install
composer prefix      # copies the libraries into vendor-prefixed/ under BiztechEG\EasyPdfWordWP\Vendor\
```

The plugin loads its libraries only from `vendor-prefixed/`, so run `composer prefix` again after `composer update`.

## Tests

The tests run inside WordPress's own test suite, so they need an empty MySQL or MariaDB database. They use `wp_tests` on `localhost` with user `wp` and password `wp` unless you set:

```bash
export WP_TESTS_DB_NAME=wp_tests WP_TESTS_DB_USER=wp WP_TESTS_DB_PASSWORD=wp WP_TESTS_DB_HOST=127.0.0.1
```

Then:

```bash
composer test
```

The WooCommerce tests need WooCommerce: unzip a release from https://github.com/woocommerce/woocommerce/releases and point `WC_DIR` at its folder. Without it they are skipped.

```bash
WC_DIR=/path/to/woocommerce composer test
```

The database is emptied on every run, so never point it at a site's database.

## The plugin zip

```bash
composer build
```

writes `build/easy-pdf-word.zip`: the plugin's files and the prefixed libraries, without development files and without mPDF's fonts the plugin does not use.
