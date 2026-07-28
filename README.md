# Biblia Digital

WordPress plugin to publish, read, search, and import Bible texts using plugin-owned
database tables, friendly URLs, shortcodes, widgets, and a Gutenberg block.

[![CI](https://github.com/crispacfo/biblia-digital/actions/workflows/ci.yml/badge.svg)](https://github.com/crispacfo/biblia-digital/actions/workflows/ci.yml)

- **Requires WordPress:** 6.6+
- **Requires PHP:** 7.4+
- **License:** GPLv2 or later

## About the Bible text

**This plugin ships no Bible text.** No SQL dumps, no translations, no scripture
content of any kind is distributed in this package.

The site administrator imports the text themselves through a ZIP file containing
`books.csv` and `verses.csv`, and is responsible for confirming that the translation
is in the public domain, properly licensed, or used with the rights holder's
permission. See [`docs/IMPORTACAO-BIBLIAS.md`](docs/IMPORTACAO-BIBLIAS.md) for the
CSV format.

## Features

- Frontend reader by book, chapter, and verse
- Search by word, phrase, exact phrase, any word, or all words
- Friendly, version-aware URLs with backward compatibility for legacy query strings
- SEO metadata, canonical URLs, and a dedicated sitemap that also integrates with
  the native WordPress Sitemaps API
- Gutenberg search block, search widget, random verse widget
- Shortcodes for the reader, search, random verses, specific verses, and chapters
- Multiple Bible versions per site, multisite-compatible
- Optional upload of `.pot`, `.po`, and `.mo` files for interface translation
- **No required calls to external APIs** — nothing leaves the server

## Installation

1. Download the latest release ZIP.
2. In WordPress, go to *Plugins → Add New → Upload Plugin*.
3. Activate, then open *Settings → Biblia Digital* to import a Bible.

## Development

```bash
composer install     # installs PHPCS + WordPress Coding Standards
composer run lint    # php -l across every file
composer run phpcs   # WordPress Coding Standards
composer run phpcbf  # auto-fix the fixable violations
composer run test    # PHPUnit against the WordPress test suite
```

`bin/install-wp-tests.sh` provisions the official WordPress PHPUnit library before
`composer run test`. `bin/build-zip.sh` produces the distributable ZIP from
`.distignore`.

CI runs syntax linting on PHP 7.4–8.5, PHPCS, PHPUnit against WordPress 6.6 and
7.0.x, a distributable ZIP build, and the official WordPress.org Plugin Check on
every push and pull request.

## Documentation

| Document | Contents |
| --- | --- |
| [`docs/SECURITY.md`](docs/SECURITY.md) | Security decisions and constraints |
| [`docs/IMPORTACAO-BIBLIAS.md`](docs/IMPORTACAO-BIBLIAS.md) | CSV import format |
| [`docs/PRIVACY.md`](docs/PRIVACY.md) | What the plugin stores and sends |
| [`docs/ASSETS-LICENSES.md`](docs/ASSETS-LICENSES.md) | Bundled asset licensing |
| [`docs/AUDITORIA-TECNICA.md`](docs/AUDITORIA-TECNICA.md) | Technical audit notes |

## Security

To report a vulnerability, please open a private security advisory through the
GitHub *Security* tab rather than a public issue.

## License

GPLv2 or later. See [`license.txt`](license.txt).
