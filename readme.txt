=== EstudoBiblico Bíblia Digital ===
Contributors: crispaorg
Tags: bible, scripture, search, shortcode, gutenberg
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish, search, and import Bible texts in WordPress with shortcodes, widgets, a Gutenberg block, and an organized admin panel.

== Description ==

EstudoBiblico Bíblia Digital lets site administrators publish locally stored Bible content in WordPress using plugin-owned database tables, friendly URLs, shortcodes, widgets, and a search block.

This public package does not include copyrighted Bible texts, SQL dumps, or proprietary translations. The site administrator imports licensed Bible data with a ZIP file that contains `books.csv` and `verses.csv`.

Main features:

* Frontend Bible reader by book, chapter, and verse.
* Search by word, phrase, exact phrase, any word, or all words.
* Friendly URLs for books, chapters, verses, and version-aware paths.
* Backward compatibility with older query-string URLs.
* SEO metadata, canonical URL support, and local manifest endpoint.
* Gutenberg search block.
* Search widget and random verse widget.
* Shortcodes for the reader, search, random verses, specific verses, and chapters.
* Multisite-compatible structure.
* Multiple Bible versions/translations per site.
* Optional upload of `.pot`, `.po`, and `.mo` files for plugin interface translation.
* Admin tabs for overview, import, versions, frontend translation, shortcodes, SEO and URLs, and diagnostics.
* No required calls to external APIs.

== Bible Data License ==

This plugin does not distribute Bible text. The site administrator is responsible for confirming that every imported translation is public domain, properly licensed, or used with permission from the rights holder.

== Import Format ==

Bible text is imported from a ZIP file containing two CSV files: `books.csv` and `verses.csv`. Ready-made templates can be downloaded from Settings > Bíblia Digital > Import.

= books.csv =

The first line is the header. Then one book per line, all 66 books, numbered 1 (Genesis) to 66 (Revelation) in the traditional Protestant order.

`livro_seq,livro,livro_desc`
`1,Gn,Genesis`
`2,Ex,Exodus`
`...`
`66,Rev,Revelation`

* `livro_seq`: book number, 1 to 66.
* `livro`: short abbreviation.
* `livro_desc`: book name. It is shown on the site and also becomes the book URL (`Colossians` → `/colossians/`). Review the spelling before importing: changing it later changes the URL.

The import stops with a message listing the missing book numbers if any of the 66 is absent. Deuterocanonical books are not supported.

= verses.csv =

The first line is the header. Then one verse per line.

`testamento,livroseq,livro,capitulo,versiculo,palavra`
`OT,1,Gen,1,1,"Text of Genesis 1:1."`
`NT,43,John,3,16,"Text with commas, and ""doubled"" quotes."`

* `testamento`: OT/NT, AT/NT or empty. It is inferred from the book number.
* `livroseq`, `capitulo`, `versiculo`: book, chapter and verse numbers.
* `livro`: abbreviation (optional content, required column).
* `palavra`: plain verse text, up to 5,000 characters. HTML is removed.

Put the verse text in double quotes. Quotes inside the text are written doubled (`""`). Line breaks inside a quoted text are accepted.

= Saving and packaging =

* Encoding: UTF-8. In Excel, use "CSV UTF-8 (Comma delimited)". In LibreOffice, choose the Unicode (UTF-8) character set, comma separator and double quote as text delimiter. Files in another encoding are rejected with the line number.
* Separator: comma is recommended. Semicolon is also accepted; the separator is detected once, from the header line.
* ZIP: the two CSV files may be at the root of the ZIP or inside a single folder. System files added by macOS and Windows (`__MACOSX/`, `.DS_Store`, `Thumbs.db`) are ignored.
* Limits: ZIP up to 50 MB and up to 25 MB extracted. The PHP zip extension is required.
* Language: use a code such as `pt-BR`, `en-US` or `es-ES`.

= Importing again and deleting versions =

Each import creates a new Bible version and makes it active. To replace a translation, import the new file and then delete the old version in Settings > Bíblia Digital > Versions.

Deleting a version removes its record, books and verses, and cannot be undone. The active Bible cannot be deleted directly: activate another version first. URLs of a deleted version redirect (301) to the same book, chapter and verse in the active Bible.

== Shortcodes ==

All shortcodes are also documented inside the plugin admin area under Settings > Biblia Digital > Shortcodes.

= Main Bible Reader =

Shortcode:

`[biblia-digital]`

Compatibility aliases:

* `[biblia-wp-estudobiblico]`
* `[bibliawp-estudobiblico]`

Description: displays the full Bible reader, including landing area, books, chapters, verses, search, breadcrumbs, and navigation.

Examples:

`[biblia-digital]`
`[biblia-digital version="1"]`
`[biblia-digital per_page="30" title="Bible Reader" version="1"]`

Attributes:

* `per_page`: number of items per page when pagination is used. Default: 30.
* `title`: optional title displayed by the shortcode when applicable.
* `version`: Bible version ID. Example: `version="1"`.

= Simple Bible Search =

Shortcode:

`[biblia-busca]`

Alias:

* `[estudo_biblico_busca]`

Description: displays only the Bible search form.

Examples:

`[biblia-busca]`
`[biblia-busca title="Search the Bible" placeholder="Type a word or phrase" button="Search"]`

Attributes:

* `title`: search form title.
* `placeholder`: placeholder text for the search field.
* `button`: submit button text.
* `version`: Bible version ID, when applicable.

= Full Search Page =

Shortcode:

`[biblia-busca-pagina]`

Alias:

* `[biblia-digital-busca]`

Description: displays the search form, results, and pagination. This shortcode is recommended for a public search page.

Examples:

`[biblia-busca-pagina]`
`[biblia-busca-pagina title="Search the Bible" per_page="30" show_book="1"]`

Attributes:

* `title`: search page or block title.
* `per_page`: number of results per page. Suggested default: 30.
* `show_book`: show the book selector. Accepts `1` or `0`.
* `version`: Bible version ID, when applicable.

= Random Verse =

Shortcode:

`[biblia-versiculo-aleatorio]`

Description: displays a random Bible verse.

Examples:

`[biblia-versiculo-aleatorio]`
`[biblia-versiculo-aleatorio book="19"]`
`[biblia-versiculo-aleatorio title="Verse of the Moment" book="19" show_button="1" button_text="Read chapter"]`

Attributes:

* `title`: block title.
* `book`: book ID or book identifier. Example: `book="19"` for Psalms.
* `version`: Bible version ID.
* `show_reference`: show the Bible reference. Accepts `1` or `0`.
* `link_reference`: link the reference to the reader. Accepts `1` or `0`.
* `show_button`: show a chapter button. Accepts `1` or `0`.
* `button_text`: button label.

= Specific or Random Verse =

Shortcode:

`[biblia-versiculo]`

Description: displays one specific verse or a random verse.

Examples:

`[biblia-versiculo livro="john" capitulo="3" versiculo="16"]`
`[biblia-versiculo book="43" chapter="3" verse="16"]`
`[biblia-versiculo livro="random" capitulo="random" versiculo="random"]`
`[biblia-versiculo livro="psalms" capitulo="23" versiculo="random"]`

Attributes:

* `title`: optional title.
* `livro`: book name, abbreviation, or ID.
* `book`: English alias for `livro`.
* `capitulo`: chapter number or `random`.
* `chapter`: English alias for `capitulo`.
* `versiculo`: verse number or `random`.
* `verse`: English alias for `versiculo`.
* `version`: Bible version ID.
* `show_reference`: show the reference. Accepts `1` or `0`.
* `link_reference`: link the reference to the reader. Accepts `1` or `0`.
* `show_button`: show a button. Accepts `1` or `0`.
* `button_text`: button label.

Accepted random values:

* `random`
* `aleatorio`
* `aleatório`
* `rand`
* `*`

= Specific or Random Chapter =

Shortcode:

`[biblia-capitulo]`

Description: displays a specific chapter or a random chapter.

Examples:

`[biblia-capitulo random="1" limite="15"]`
`[biblia-capitulo livro="john" capitulo="3"]`
`[biblia-capitulo livro="43" capitulo="3"]`

Attributes:

* `title`: optional title.
* `livro`: book name, abbreviation, or ID.
* `book`: English alias for `livro`.
* `capitulo`: chapter number.
* `chapter`: English alias for `capitulo`.
* `random`: enable random chapter mode. Accepts `1` or `0`.
* `limite`: limit the number of displayed verses.
* `limit`: English alias for `limite`.
* `version`: Bible version ID.
* `show_title`: show the chapter title. Accepts `1` or `0`.
* `show_button`: show a button to open the full chapter. Accepts `1` or `0`.
* `button_text`: button label.

== Friendly URLs ==

Biblia Digital supports friendly URLs and keeps compatibility with older query-string URLs.

Version-aware URL example:

`/biblia-digital/versao/acf-pt-br/john/3/16/`

Legacy URL example:

`/biblia-digital/john/3/16/?bdwp_bible_id=1`

After changing the Bible URL base in the plugin settings, go to Settings > Permalinks and click Save Changes once.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the ZIP file from the WordPress admin panel.
2. Activate EstudoBiblico Bíblia Digital.
3. Go to Settings > Biblia Digital.
4. Open the Import Bible tab.
5. Import a ZIP file containing `books.csv` and `verses.csv`.
6. Confirm that you have the right to use the imported Bible translation.
7. Create a public Bible page.
8. Insert the `[biblia-digital]` shortcode.
9. If friendly URLs return 404, go to Settings > Permalinks and click Save Changes once.

== Frequently Asked Questions ==

= Does the plugin include Bible text? =

No. The public package does not include Bible text. You must import a properly licensed Bible data package.

= Why is import not automatic on activation? =

Activation creates tables and default options. Bible text import can be heavy, so it is handled from the admin panel.

= Does the plugin support multisite? =

Yes. Each site uses its own tables and options.

= Can I use different languages or translations? =

Yes. Each imported version can have its own label and language code. The active Bible is configured per site, while public visitors can choose another available version when more than one public version exists.

= Where can I find the shortcodes? =

Go to Settings > Biblia Digital > Shortcodes. The tab includes examples, attributes, and copy buttons.

= Does frontend translation replace the Bible text? =

No. `.pot`, `.po`, and `.mo` files translate only the plugin interface strings. Bible text comes from imported CSV files.

= How is the plugin interface translated? =

The plugin is written in English. Other languages come from translate.wordpress.org as language packs, which WordPress installs automatically. You can also upload your own `.po` and `.mo` files in Settings > Bíblia Digital > Frontend Translation. An uploaded translation takes priority, and the language pack fills in any strings it does not cover.

= Does the plugin collect visitor data? =

No. Reading and search are processed locally in WordPress. The plugin does not create tracking cookies or send visitor data to third parties.

== Screenshots ==

1. Bible reader with books, chapters, and search.
2. Import Bible tab for ZIP packages.
3. Shortcodes tab with examples and copy buttons.
4. Bible Versions tab with active version management.
5. Random verse widget.

== Privacy ==

Biblia Digital does not collect personal data, track visitors, or require calls to external APIs. Imported Bible content is stored locally in the WordPress database.

== License ==

This plugin is licensed under GPLv2 or later. Imported Bible data must be public domain, properly licensed, or used with permission from the rights holder.

== Upgrade Notice ==

= 1.2.2 =
Cache fixes for Bible content changes: importing a Bible or switching the active Bible now purges the Bible pages, 404 routes are purgeable, and the per-version tag matches the version the page shows.

= 1.2.1 =
Bible pages get their own LiteSpeed Cache tag, so publishing a post no longer purges every Bible URL. The plugin's own purges now clear only the Bible pages instead of the whole site cache.

= 1.2.0 =
The source language is now English; other languages come from translate.wordpress.org. Uploaded custom translations are converted automatically. Portuguese sites without a pt_BR language pack: upload a pt_BR translation before updating.

= 1.1.70 =
New public name and slug. The Bible URLs, shortcodes and stored options are unchanged. Custom translation files uploaded by the administrator move to the uploads directory automatically; the previous copies are kept and still read.

= 1.1.65 =
Removes the automatic card that narrowed the verses and adds a compact chapter selector at the top of the reader.

= 1.1.64 =
Fixes the display of the chapter side card, adds a real sidebar widget, and adjusts the Aa panel on mobile.

= 1.1.51 =
Fixes Plugin Check findings, revises rewrite/query vars, improves legacy URL compatibility, and keeps permalink flushing controlled.

= 1.1.50 =
Adds an organized admin panel with enterprise-style tabs and documents all shortcodes and attributes in the admin panel and readme.txt.

= 1.1.49 =
Keeps the functional translation selector below the book lists and improves dropdown stacking.

== Changelog ==

= 1.2.2 =
* The per-version cache tag now reflects the version the page actually shows. It was assigned before the request state was resolved, so a URL with /versao/<slug>/ or a `version` shortcode attribute was tagged with the site's active Bible instead, and a purge for one version would have missed those pages.
* Bible routes that answer 404 also carry the Bible cache tag. Without it, a URL that starts to exist after an import would keep answering 404 until the server's error page TTL expired.
* Switching the site's active Bible now purges the Bible pages. Until now the cached pages kept serving the previous translation until their TTL ran out.
* Importing a Bible purges the Bible pages as well. The imported version becomes the active one, so every cached Bible page was out of date.

= 1.2.1 =
* Bible pages get their own LiteSpeed Cache tag (`bdwp70_bible`). Until now the virtual pages only carried the tags LiteSpeed derives from the query: the page that hosts the shortcode, the blog home, and "pages". Every Bible URL shared those tags, so publishing or updating any post purged all of them at once, and the server had to render tens of thousands of pages again.
* The virtual query no longer reports itself as the blog home (`is_home`), which is where the home cache tag came from.
* The plugin's own purges (book name corrections, deleting a version) now purge only the Bible tag instead of the whole site cache. The `bdwp70_purge_all_caches` filter restores the previous site-wide purge.

= 1.2.0 =
* The plugin's source strings are now in English, as translate.wordpress.org expects. Until now they were in Portuguese: translators had no English original to start from, and sites in English could never get an English interface, since en_US has no translation project.
* Interface text that was hardcoded in Portuguese is now translatable English: import and upload error messages, the translation upload form, the search pagination label, the media picker, the Bible version labels, and the search engine titles and descriptions.
* Custom translation files uploaded before this version use the old Portuguese keys. On update they are converted once to the new English keys, and any file uploaded later with the old keys is converted on upload. The conversion map ships only hashes of the old keys, not translated text.
* When a custom translation is loaded, the translate.wordpress.org language pack for the same locale is loaded after it, filling in any strings the custom file does not cover.
* A notice on the plugin screens explains when the site's language has neither a language pack nor a custom translation.
* Reading options: the font size label ("Small", "Medium", "Large") was rewritten by frontend.js with fixed Portuguese text after each change. It now uses the translated labels rendered by PHP.
* Shortcode examples in the admin panel use the English attribute aliases (`book`, `chapter`, `verse`, `limit`) and English sample values. The Portuguese attribute names keep working.
* CSV examples in the import instructions use English sample data.
* The changelog and upgrade notices in this readme are now in English.

= 1.1.80 =
* Public Bible pages no longer send `nocache_headers()`. A chapter's HTML is the same for every anonymous visitor, and no-cache on every virtual page defeated page caching, proxies and CDNs on the most visited URLs. No-cache is still sent to logged-in users; the `bdwp70_bible_page_nocache` filter adjusts the rule.
* Book, chapter and verse are validated before responding with 200. The rewrite rules accept any number, so /romans/3/999/ and /romans/999/1/ used to respond 200 with the nearest chapter, a soft 404 that multiplied the crawlable URL space. They now respond 404 and the theme renders its own error page. The 301 redirects for removed versions and old slugs still run before the validation, so no URL that used to redirect starts returning 404. Adjustable with the `bdwp70_bible_route_not_found` filter.
* manifest.json, requested by every browser that loads a Bible page, no longer sends no-cache either.
* On a 404 response the plugin no longer outputs a title, description or canonical for the missing passage.
* Canonical: the plugin prints its own only when no SEO plugin produced one for the page, avoiding two tags in the head. Detection relies on the SEO plugin's own filter running (Rank Math, Yoast, AIOSEO, SEOPress), not on the plugin merely being active: on these virtual pages, which are not posts, Rank Math emits no canonical, and suppressing ours based on presence would leave the page without one. Filter: `bdwp70_print_canonical`.
* Search block: text domain fixed from `biblia-digital` to `estudobiblico-biblia-digital` in block.json and index.js. The 1.1.70 domain migration had missed the block, so its strings were neither translatable nor in the .pot.
* Search block: title, placeholder and button no longer have fixed English defaults in block.json. WordPress applies those defaults before the render callback, so a block inserted without changes showed "Search the Bible" on the site; it now uses the same translatable text as the widget. Blocks with custom text are unchanged.
* .pot regenerated. The 1.1.70 file had been generated before the activation notice was added and lacked its four strings.
* get_single_verse() memoizes its result per request: route validation and the meta description were asking for the same verse twice.
* Coding standards fixes, and the book name correction query now uses `$wpdb->prepare()` with `%i`.

= 1.1.79 =
* Import: the CSV separator is detected once, from the header. It used to be decided line by line, and a verse with many commas in a semicolon-separated file (a genealogy, for example) aborted the import.
* Import: reading uses fgetcsv(), which accepts line breaks inside quoted text.
* Import: files that are not UTF-8 (the usual Excel "CSV" in Windows-1252) are rejected with the line number, instead of silently losing accented characters.
* Import: specific messages for a missing header, an invalid header, an empty file and missing books. An incomplete books.csv reports how many book numbers are missing and which ones.
* ZIP: books.csv and verses.csv can be at the root or inside a single folder, as produced by "Compress folder" on Windows and macOS. System entries (__MACOSX/, ._file, .DS_Store, Thumbs.db, desktop.ini) are ignored. An unexpected or duplicated file is reported by name.
* Import instructions rewritten in the admin panel and in this readme, with a step-by-step guide, header examples, quoting, Excel and LibreOffice encoding settings, and the effect of livro_desc on the URL. Downloadable books.csv (66 books) and verses.csv (examples) templates.
* Versions: new option to delete an imported version on the Versions tab, with a required confirmation. It removes the record, books and verses. The active Bible cannot be deleted directly. Deleting clears the keyed caches (object cache included) and asks LiteSpeed Cache to purge.
* URLs of a deleted version respond with a 301 to the same book, chapter and verse in the active Bible.

= 1.1.78 =
* Book names are fixed natively, without a separate tool: on update to this version, each network site corrects once the wrong spelling of book names in pt-* versions (Genesis → Gênesis, Exodo → Êxodo, Levitico → Levítico, Deuteronomio → Deuteronômio, Juizes → Juízes, Cântares → Cantares, Oséias → Oseias, Miquéias → Miqueias, 1 and 2 Corintios → Coríntios, Efesios → Efésios, Colosenses → Colossenses, 1 and 2 Tesalonicenses → Tessalonicenses, 2 Timoteo → 2 Timóteo). Only the exact wrong spelling is replaced, so correct names and other versions do not change.
* The same correction runs at the end of every import, so a CSV with the old spelling does not bring the errors back.
* After the correction the plugin clears its own caches, asks LiteSpeed Cache to purge pages, and shows a one-time admin notice listing the changes.
* Corrections are adjustable with the `bdwp70_book_name_corrections` filter.
* Book 22 across versions: "Cantares" (ACF, slug `cantares`) and "Cânticos" (Almeida 1911, ARC, ARA, slug `canticos`) redirect to each other with a 301 when the requested slug does not exist in the chosen version, so switching versions while reading this book no longer leads to a missing page.
* The old slug map also works both ways for Colossenses and 1/2 Tessalonicenses, covering ACF while the old names are still in the database.

= 1.1.77 =
* Portuguese: titles and descriptions generated for search engines now have correct accents ("Livros da Bíblia", "capítulos", "versículos", "capítulo").
* Quick access cards: the default icons were stored as "?" in the code, left over from lost emoji. They now use Basic Multilingual Plane symbols, which survive even in databases without utf8mb4. An icon already saved as "?" falls back to the default.
* Sitemap name map: "Cantares" (ACF spelling), "Oseias" and "Miqueias" (current Portuguese orthography).
* books.csv example in the admin panel with an accented "Gênesis".
* The displayed book names come from the database, stored at import time; this version does not change them.
* Book URLs with corrected spelling: /colosenses/, /1-tesalonicenses/ and /2-tesalonicenses/ respond with a 301 to /colossenses/, /1-tessalonicenses/ and /2-tessalonicenses/, keeping chapter and verse. The redirect only applies when the requested slug does not exist in the version: while the old name is in the database, or in a version where it is the correct spelling, nothing changes. Shortcodes with the old spelling keep resolving. Map adjustable with the `bdwp70_legacy_book_slugs` filter.

= 1.1.76 =
* SEO: the verse URL (/book/chapter/verse/) declares the chapter URL as canonical. It serves the whole chapter with the verse highlighted, and it used to declare itself canonical, which multiplied duplicates per verse and per translation.
* SEO: the verse URL noindex introduced in 1.1.74 is turned off. noindex combined with a canonical pointing to another page is a contradictory signal. The `bdwp70_noindex_verse_urls` filter still turns the old strategy back on as a whole, in which case the canonical returns to the URL itself.
* SEO: the chapter canonical is also applied to Rank Math (`rank_math/frontend/canonical`) and Yoast (`wpseo_canonical`), in case they emit their own.
* Sitemap: verse URLs are no longer included, regardless of the stored option, because they are no longer canonical. The matching checkbox in the admin panel is disabled with an explanation; the `bdwp70_sitemap_include_verses` filter can still force them in.
* Deep link: opening a verse URL scrolls the page to the highlighted verse, without overriding an explicit anchor in the URL.

= 1.1.75 =
* Fix for the `X-Robots-Tag` header introduced in 1.1.74: it was registered on `template_redirect` at priority 1, but the plugin renders the virtual page at priority 0 and ends with `exit`, so the callback never ran. It moved to `send_headers`.
* The `noindex, follow` robots meta from 1.1.74 already worked and is unchanged; the header is a fallback for installs without an SEO plugin.

= 1.1.74 =
* Individual verse URLs are marked `noindex, follow`. They are near-duplicates of the chapter page, which already includes every verse with its own anchor. `follow` is kept on purpose: links keep passing signals, only the page leaves the index.
* The marking covers four paths, so it works with or without an SEO plugin: WordPress `wp_robots`, the Rank Math filter, the Yoast filter and an `X-Robots-Tag` response header.
* It works per request, without a database option, so it applies to every site in a multisite network as soon as the plugin is updated.
* Can be turned off with the `bdwp70_noindex_verse_urls` filter.

= 1.1.73 =
* The "Include individual verses in the sitemap" option is unchecked on new installs. When on, it publishes about 30,000 URLs against ~1,200 chapter URLs; since the chapter page already includes every verse with its own anchor, they are near-duplicates, and each crawler visit to one of them costs a full render.
* The option's description in the admin panel no longer recommends it and explains the cost instead.
* Existing installs are not changed: if the option was checked, it stays checked. To turn it off, use Settings > Bíblia Digital > SEO and URLs, or the `bdwp70_sitemap_include_verses` filter.

= 1.1.72 =
* Reading: the Bible text has a controlled, centered measure (80ch, about 690px and 63 characters per line on desktop). The page stays full width; only the text is limited.
* Reading on mobile: the plugin no longer adds its own side padding to the theme's, and the text uses the available width again.
* Reading on mobile: the font size is saved only when the reader picks one. Always saving it overrode the smaller step the CSS already sets for narrow screens, leaving the text at 22px in a narrow column.
* Touch: the whole verse line becomes a selection area, with a soft highlight. Selecting and copying text still works, and link clicks follow their own target.
* Chapter selector: on mobile it opens as a scrollable bottom sheet with a dimmed backdrop. It used to be an absolute panel that overflowed the screen edge and left chapters out of reach.
* Sticky bar at the top of the chapter with the book, chapter number and navigation; on mobile it hides when scrolling down and returns when scrolling up.
* Keyboard: the left and right arrow keys change chapters, without interfering when focus is in a text field.

= 1.1.71 =
* Performance: search uses the `palavra_fulltext` FULLTEXT index, which already existed in the schema but was not used by any query. The previous path, with `LIKE '%term%'`, had a leading wildcard and scanned the whole table twice per search.
* Index search matches words and prefixes: searching "amor" still finds "amoroso", but not "desamor". When the index returns nothing, the previous search runs as before, so no query ends up with zero results because of the change.
* Terms with words shorter than the index's minimum token, exact phrase searches with short words, and installs without the index keep the old path. The `bdwp70_use_fulltext_search` and `bdwp70_fulltext_min_token` filters adjust or disable it.
* Performance: the book list and the chapter count per book are cached for 12 hours with a per-request memo, following the pattern already used for Bible versions. The chapter count was a GROUP BY aggregate over the verses table, recomputed on every page.
* Performance: the search results cache goes from 5 minutes to 12 hours, adjustable with the `bdwp70_search_cache_ttl` filter. Importing already clears these caches.

= 1.1.70 =
* New public identity: display name `EstudoBiblico Bíblia Digital`, slug and text domain `estudobiblico-biblia-digital`, and `Contributors: crispaorg`.
* Changes requested in the WordPress.org review.
* Theme compatibility CSS and JavaScript are no longer printed as `<style>`/`<script>` and use `wp_register_style()`, `wp_register_script()`, `wp_add_inline_style()` and `wp_add_inline_script()`, loaded only on Bible pages.
* Sitemap: the physical index file is no longer written to the WordPress root. The public URLs stay the same, served dynamically by the existing routes. A leftover file from previous versions is deleted on update, and only when it was provably generated by the plugin.
* Custom translations uploaded by the administrator are stored in a dedicated subfolder of `wp_upload_dir()`, per site, protected against direct access. `WP_LANG_DIR` and the plugin folder are no longer used as write targets; old files are copied without being deleted and are still read as a fallback.
* Review of nonces, capabilities and sanitization in admin actions.
* Admin notices audited against Guideline 11.
* The distributed package no longer includes `.po` and `.mo` files: translations come from translate.wordpress.org.
* Public URLs, shortcodes, options, tables, hooks, handles and the BDWP70 prefix are preserved.

= 1.1.69 =
* Metadata: plugin header aligned with the official WordPress.org requirements.
* Display name changed to `Bíblia Digital`, with the accent, in the header and in the readme. The slug, text domain, folder and main file remain `biblia-digital`.
* `Plugin URI` points to the plugin page (`https://estudobiblico.org/biblia-sagrada-online/`), distinct from the `Author URI`, which stays at the site root.
* `Description` rewritten in Portuguese, aligned with the plugin's audience.
* `License` normalized to the SPDX identifier `GPL-2.0-or-later`; the license itself does not change.
* `Tested up to` removed from the PHP header: it is not a plugin header field recognized by WordPress and remains declared in readme.txt.
* No `Update URI` was added, so updates are not diverted from WordPress.org.
* `.pot` and pt_BR catalogs regenerated to reflect the name, description and version.

= 1.1.68 =
* Consolidates the 1.1.67 line (abuse fix) with schema self-repair, hardened ZIP import and the quality tooling.
* Database: adds `BDWP70_Activator::maybe_upgrade()`, which creates/updates tables and indexes on plugin updates and on activations through the legacy loaders, without requiring reactivation. The previous inline check was centralized in this method, now with a concurrency lock and runtime cache clearing.
* Security: `validate_uploaded_zip_archive()` rejects the upload with a `WP_Error` when the PHP zip extension is missing, instead of silently skipping the path traversal, count and size checks.
* Compatibility: `str_getcsv()` receives an explicit `$escape`, removing the deprecation warning on PHP 8.4/8.5; the `wpmu_new_blog` hook, deprecated since WP 5.1, is no longer registered.
* Internationalization: import and ZIP validation messages use `__()`; `.pot` catalog regenerated and pt_BR translation included.
* Quality/distribution: CI workflow (PHP 7.4–8.5 lint, PHPCS/WPCS, PHPUnit on WordPress 6.6 and 7.0.x, Plugin Check on the ZIP), `composer.json`, `phpcs.xml.dist`, `.distignore` and integration tests.
* Fixes for pre-existing defects: the versioned `.gitignore` contained the command that generated it; twelve corrupted comments in the sitemap files were restored.
* No changes to data, options, slugs, canonical book names, tables, shortcodes, hooks or the BDWP70 prefix.

= 1.1.67 =
* Abuse report hardening: removes runtime random queries with GROUP BY/OFFSET and uses indexed ID range selection.
* Performance: persistent cache for version existence, the active Bible and sitemap counts, reducing repeated SELECT id and COUNT(*) on bot traffic.
* Database: additional indexes for counts and random selection by bible_id/published/id.

= 1.1.66 =
* Performance: the physical sitemap is no longer validated on every public request; the check uses a transient and revalidates only periodically.
* Performance: random verse and random chapter no longer use random ordering in the database.
* Performance: CSV import uses real batch inserts for books and verses.
* Performance: search queries get a short transient cache and helper indexes.
* Performance: the SEO context has an internal per-request cache.

= 1.1.65 =
* Removes the reader layout's internal automatic chapter card, which narrowed the Bible text in themes with their own sidebar.
* Adds a compact "Chapters" button at the top of the chapter, with a drop-down panel containing all the book's chapters, the active translation and a link to the full list.
* Keeps the native widget and the [biblia-capitulos-sidebar] shortcode available for manual use in real sidebars.
* Improves the mobile layout of the Verse by verse/Continuous buttons, hiding the icon that took up space and avoiding truncated text.

= 1.1.64 =
* Fixes the layout rule that pushed the chapter card below the text in some themes.
* Adds the native "Bíblia Digital - Chapters of the current book" widget for use in Appearance > Widgets.
* Adds the [biblia-capitulos-sidebar] shortcode to insert the card manually in blocks or side areas.
* Adjusts the Aa panel on mobile to avoid truncated Verse by verse/Continuous buttons.

= 1.1.63 =
* Adds a compact reading options panel with font size, verse by verse/continuous layout, Default/Lexend font and reading background.
* Saves the reader's preferences in the browser.
* Improves the chapter side widget with the book title, current chapter, active translation and navigation grid.

= 1.1.62 =
* Removes the duplicated "no results" message on the dedicated search page.
* Keeps a single message in the dedicated search summary.
* Keeps the no-results fallback in the main shortcode and widgets.

= 1.1.61 =
* Definitively fixes the missing message when a search finds no term or word.
* Version 1.1.60 fixed the list function and the dedicated page, but the main template only called the list when there were items.
* The main template now also calls the no-results fallback in search mode.
* Keeps an accessible, styled message in the main shortcode, the dedicated page and the public search flows.

= 1.1.60 =
* Fixes the message shown when a search finds no term or word in the Bible.
* The message now appears both on the dedicated search page and in the main shortcode's search mode.
* Adds aria-live markup for accessibility and a subtle visual style for the empty search notice.

= 1.1.59 =
* Adds a dedicated XSL to display the Bíblia Digital sitemaps visually in the browser.
* Keeps the XML structure plain for crawlers and search engines.

= 1.1.58 =
* Fixes Plugin Check findings in the sitemap diagnostics and in the sitemap SQL queries.
* Adds translators comments to strings with placeholders.
* Removes dynamic SQL fragments for the published filter and uses prepared/escaped branches.
* Adds caching and safe declarations to the sitemap's paginated queries without changing the functional logic of version 1.1.57.

= 1.1.56 =
* Definitively fixes URL generation in the Bible XML sitemap.
* Adds a robust fallback that resolves a Bible with real content from the verses table.
* Avoids an empty sitemap when the books table is incomplete but the verses exist.
* Makes the published filter resilient for legacy databases.
* Keeps block pagination, LIMIT/OFFSET and integration with the WP Sitemap API.

= 1.1.51 =
* Fixes missing translator comments reported by Plugin Check.
* Rewrites the official readme.txt in English for repository review.
* Avoids nonce warnings for navigation-only admin tabs by reading the tab parameter safely.
* Reviews rewrite rules for version-aware Bible URLs.
* Registers public query vars used by friendly URLs and legacy query-string URLs.
* Preserves compatibility with old `?bdwp_bible_id=1` URLs.
* Flushes rewrite rules only when explicitly marked as stale, such as plugin activation, plugin upgrade, or SEO base changes.
* Adds clearer admin instructions to save permalinks once after changing the Bible URL base.

= 1.1.50 =
* Reorganizes the admin panel into tabs: Overview, Import Bible, Bible Versions, Frontend Translation, Shortcodes, SEO and URLs, and Diagnostics.
* Places Import Bible before Frontend Translation to reduce operational confusion.
* Adds a Shortcodes tab with cards, examples, attributes, and copy buttons.
* Updates readme.txt with all shortcodes, aliases, and attributes.
* Preserves compatibility with legacy shortcodes.

= 1.1.49 =
* Keeps the hero version display as a static badge and the functional translation dropdown below the book lists.
* Fixes dropdown stacking/overflow so version choices remain readable over the Bible landing layout.
* Preserves the plugin title image and prevents theme portal logos from replacing functional Bible images.

= 1.1.48 =
* Adds SEO-friendly Bible version paths such as `/biblia-digital/versao/acf-pt-br/john/3/16/`.
* Keeps backward compatibility with the previous `bdwp_bible_id` query parameter.
* Moves the public translation/version selector below the Old Testament and New Testament book containers.
* Replaces the decorative separator with a functional translation selection panel.
* Preserves the selected version across books, chapters, verses, breadcrumbs, search, and pagination.

= 1.1.46 =
* Renamed the public plugin package to Biblia Digital.
* Updated the text domain to `biblia-digital`.
* Removed the custom update header from the public package.
* Updated the search block to API version 3.
* Rewrote the WordPress.org readme in English.
* Preserved legacy classes, options, database tables, and shortcodes.

= 1.1.44 =
* Normalized UTF-8 encoding and line endings.
* Improved admin and readme text.
* Preserved the CSV importer and safe uninstall defaults.
