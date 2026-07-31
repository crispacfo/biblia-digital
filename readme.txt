=== EstudoBiblico Bíblia Digital ===
Contributors: crispacfo
Tags: bible, scripture, search, shortcode, gutenberg
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.70
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

Import a ZIP file containing:

* `books.csv`
* `verses.csv`

Both files must be encoded in UTF-8. Comma and semicolon separators are supported.

Required `books.csv` columns:

* `livro_seq`
* `livro`
* `livro_desc`

Required `verses.csv` columns:

* `testamento`
* `livroseq`
* `livro`
* `capitulo`
* `versiculo`
* `palavra`

Example `books.csv`:

`livro_seq,livro,livro_desc`
`1,Gn,Genesis`
`43,John,John`

Example `verses.csv`:

`testamento,livroseq,livro,capitulo,versiculo,palavra`
`OT,1,Gn,1,1,In the beginning God created the heaven and the earth.`
`NT,43,John,3,16,For God so loved the world...`

Book numbering should follow the traditional Protestant order from 1 to 66.

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

= 1.1.70 =
New public name and slug. The Bible URLs, shortcodes and stored options are unchanged. Custom translation files uploaded by the administrator move to the uploads directory automatically; the previous copies are kept and still read.

= 1.1.65 =
Remove o card automático que estreitava os versículos e adiciona seletor compacto de capítulos no topo do leitor.

= 1.1.64 =
Corrige a exibição do card lateral de capítulos, adiciona widget real para sidebar e ajusta o painel Aa no mobile.

= 1.1.51 =
Fixes Plugin Check findings, revises rewrite/query vars, improves legacy URL compatibility, and keeps permalink flushing controlled.

= 1.1.50 =
Adds an organized admin panel with enterprise-style tabs and documents all shortcodes and attributes in the admin panel and readme.txt.

= 1.1.49 =
Keeps the functional translation selector below the book lists and improves dropdown stacking.

== Changelog ==

= 1.1.70 =
* Nova identidade pública: nome de exibição `EstudoBiblico Bíblia Digital`, slug e text domain `estudobiblico-biblia-digital`, e `Contributors: crispacfo`.
* Adequações solicitadas na revisão do WordPress.org.
* CSS e JavaScript de compatibilidade de tema deixam de ser impressos como `<style>`/`<script>` e passam a usar `wp_register_style()`, `wp_register_script()`, `wp_add_inline_style()` e `wp_add_inline_script()`, carregados apenas nas páginas da Bíblia.
* Sitemap: removida a gravação do índice físico na raiz do WordPress. As URLs públicas continuam as mesmas, servidas dinamicamente pelas rotas já existentes. O arquivo remanescente de versões anteriores é apagado na atualização, e apenas quando comprovadamente gerado pelo plugin.
* Traduções personalizadas enviadas pelo administrador passam a ser gravadas em uma subpasta própria dentro de `wp_upload_dir()`, por site, protegida contra acesso direto. `WP_LANG_DIR` e a pasta do plugin não são mais usados como destino de escrita; arquivos antigos são copiados sem serem apagados e continuam sendo lidos como fallback.
* Revisão de nonces, capabilities e sanitização nas ações administrativas.
* Auditoria dos avisos administrativos quanto à Diretriz 11.
* Pacote distribuído deixa de incluir `.po` e `.mo`: as traduções passam a vir do translate.wordpress.org.
* Preservados URLs públicas, shortcodes, opções, tabelas, hooks, handles e o prefixo BDWP70.

= 1.1.69 =
* Metadados: cabeçalho do plugin adequado aos requisitos oficiais do WordPress.org.
* Nome de exibição passa a `Bíblia Digital`, com acentuação, no cabeçalho e no readme. O slug, o text domain, a pasta e o arquivo principal continuam `biblia-digital`.
* `Plugin URI` passa a apontar para a página do plugin (`https://estudobiblico.org/biblia-sagrada-online/`), distinta da `Author URI`, que permanece na raiz do site.
* `Description` reescrita em português, alinhada ao público do plugin.
* `License` normalizada para o identificador SPDX `GPL-2.0-or-later`; a licença em si não muda.
* Removido `Tested up to` do cabeçalho PHP: não é um campo de cabeçalho de plugin reconhecido pelo WordPress e permanece declarado no readme.txt.
* Nenhum `Update URI` foi adicionado, para não desviar as atualizações do WordPress.org.
* Catálogos `.pot` e pt_BR regenerados por refletirem nome, descrição e versão.

= 1.1.68 =
* Consolida a linha 1.1.67 (abuse-fix) com o auto-reparo de schema, o reforço de importação por ZIP e o ferramental de qualidade.
* Database: adiciona `BDWP70_Activator::maybe_upgrade()`, que cria/atualiza tabelas e índices em atualizações do plugin e em ativações pelos loaders legados, sem exigir reativação. A verificação inline anterior foi centralizada nesse método, agora com trava de concorrência e limpeza dos caches de runtime.
* Segurança: `validate_uploaded_zip_archive()` passa a recusar o upload com `WP_Error` quando a extensão PHP zip está ausente, em vez de ignorar silenciosamente as validações de path traversal, contagem e tamanho.
* Compatibilidade: `str_getcsv()` passa a receber `$escape` explícito, eliminando o aviso de depreciação no PHP 8.4/8.5; removido o registro do hook `wpmu_new_blog`, depreciado desde o WP 5.1.
* Internacionalização: mensagens de importação e de validação de ZIP passam a usar `__()`; catálogo `.pot` regenerado e tradução pt_BR incluída.
* Qualidade/distribuição: workflow de CI (lint PHP 7.4–8.5, PHPCS/WPCS, PHPUnit em WordPress 6.6 e 7.0.x, Plugin Check sobre o ZIP), `composer.json`, `phpcs.xml.dist`, `.distignore` e testes de integração.
* Correções de defeito preexistente: `.gitignore` versionado continha o comando que o gerou; doze comentários corrompidos nos arquivos de sitemap foram restaurados.
* Nenhuma alteração em dados, opções, slugs, nomes canônicos de livros, tabelas, shortcodes, hooks ou no prefixo BDWP70.

= 1.1.67 =
* Abuse report hardening: elimina consultas aleatórias com GROUP BY/OFFSET em runtime e usa seleção por faixa de ID indexada.
* Performance: cache persistente para existência de versões, Bíblia ativa e contagens do sitemap, reduzindo SELECT id repetido e COUNT(*) em acessos de bots.
* Database: índices complementares para contagens e seleção aleatória por bible_id/published/id.

= 1.1.66 =
* Performance: sitemap físico deixou de validar em toda requisição pública; verificação agora usa transient e só revalida periodicamente.
* Performance: versículo e capítulo aleatórios não usam mais ordenação randômica no banco.
* Performance: importação CSV usa inserção em lote real para livros e versículos.
* Performance: consultas de busca recebem cache transitório curto e índices auxiliares.
* Performance: contexto SEO possui cache interno por requisição.

= 1.1.65 =
* Remove o card automático interno de capítulos do layout do leitor, evitando que o texto bíblico seja estreitado em temas com sidebar própria.
* Adiciona botão compacto “Capítulos” no topo do capítulo, com painel suspenso contendo todos os capítulos do livro, tradução ativa e link para a lista completa.
* Mantém disponíveis o widget nativo e o shortcode [biblia-capitulos-sidebar] para uso manual em sidebars reais.
* Reforça o ajuste mobile dos botões Versículo/Corrido, ocultando o ícone que consumia espaço e evitando corte de texto.

= 1.1.64 =
* Corrige a regra de layout que empurrava o card de capítulos para depois do texto em alguns temas.
* Adiciona o widget nativo “Bíblia Digital - Capítulos do livro atual” para uso em Aparência > Widgets.
* Adiciona o shortcode [biblia-capitulos-sidebar] para inserir o card manualmente em blocos/áreas laterais.
* Ajusta o painel Aa no mobile para evitar corte nos botões Versículo/Corrido.

= 1.1.63 =
* Adiciona painel compacto de opções de leitura com tamanho da fonte, formato por versículo/corrido, fonte Padrão/Lexend e fundo de leitura.
* Adiciona persistência das preferências do leitor no navegador.
* Melhora o widget lateral de capítulos com título do livro, capítulo atual, tradução ativa e grade de navegação.

= 1.1.62 =
* Remove duplicidade da mensagem de busca sem resultados na página dedicada de busca.
* Mantém a mensagem única no resumo da busca dedicada.
* Mantém o fallback do shortcode principal e widgets quando não há resultados.


= 1.1.61 =
* Corrige definitivamente a ausência de mensagem quando a busca não encontra termo/palavra.
* A versão 1.1.60 corrigia a função de lista e a página dedicada, mas o template principal só chamava a lista quando havia itens.
* Agora o template principal também chama o fallback de sem resultados no modo de busca.
* Mantém mensagem acessível e estilizada no shortcode principal, na página dedicada e nos fluxos de busca pública.


= 1.1.60 =
* Corrige a exibição de mensagem quando uma busca não encontra termo ou palavra na Bíblia.
* A mensagem agora aparece tanto na página de busca dedicada quanto no modo de busca do shortcode principal.
* Adiciona marcação aria-live para acessibilidade e estilo visual discreto para o aviso de busca vazia.


= 1.1.59 =
* Adiciona XSL próprio para exibição visual dos sitemaps da Bíblia Digital no navegador.
* Mantém a estrutura XML pura para crawlers e buscadores.


= 1.1.58 =
* Corrige apontamentos do Plugin Check no diagnóstico do sitemap e nas consultas SQL do sitemap.
* Adiciona comentários translators em strings com placeholders.
* Remove fragmentos SQL dinâmicos de filtro published e usa ramificações preparadas/escapadas.
* Adiciona cache/declarações seguras nas consultas paginadas do sitemap sem alterar a lógica funcional da versão 1.1.57.

= 1.1.56 =
* Corrige definitivamente a geração de URLs no sitemap XML da Bíblia.
* Adiciona fallback robusto para resolver Bíblia com conteúdo real a partir da tabela de versículos.
* Evita sitemap vazio quando a tabela de livros estiver incompleta, mas os versículos existirem.
* Torna o filtro published resiliente para bases legadas.
* Mantém paginação por blocos, LIMIT/OFFSET e integração com WP Sitemap API.


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
