=== EstudoBiblico Bíblia Digital ===
Contributors: crispaorg
Tags: bible, scripture, search, shortcode, gutenberg
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.80
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

= 1.1.80 =
* PUBLIC_BIBLE_CACHE_HEADERS: as páginas públicas da Bíblia deixam de enviar `nocache_headers()`. O HTML de um capítulo é igual para todo visitante anônimo, e o no-cache em toda página virtual atrapalhava cache de página, proxy e CDN justamente nas URLs mais visitadas. O no-cache continua para quem está logado, e o filtro `bdwp70_bible_page_nocache` ajusta a regra.
* INVALID_BIBLE_ROUTE_404: livro, capítulo e versículo são validados antes de responder 200. As regras de reescrita aceitam qualquer número, então /romanos/3/999/ e /romanos/999/1/ respondiam 200 com o capítulo mais próximo — um soft 404 que multiplicava o espaço de URLs rastreáveis. Agora respondem 404 e o tema renderiza a própria página de erro. Os redirecionamentos 301 de versão removida e de slug antigo continuam vindo antes da validação, então nenhuma URL que antes redirecionava passa a dar 404. Ajustável pelo filtro `bdwp70_bible_route_not_found`.
* O manifest.json, pedido por todo navegador que carrega uma página da Bíblia, também deixa de enviar no-cache.
* Em uma resposta 404, o plugin deixa de gerar título, description e canonical do trecho inexistente.
* Canonical: o plugin imprime o seu apenas quando nenhum plugin de SEO gerou canonical para a página, evitando duas tags no <head>. A detecção é pela execução do filtro do próprio plugin de SEO (Rank Math, Yoast, AIOSEO, SEOPress), e não pela simples presença dele — nestas páginas virtuais, que não são posts, o Rank Math não emite canonical, e suprimir o do plugin por presença deixaria a página sem nenhum. Filtro `bdwp70_print_canonical`.
* get_single_verse() passa a memorizar o resultado por requisição: a validação da rota e a description pediam o mesmo versículo duas vezes.

= 1.1.79 =
* Importação: o separador do CSV passa a ser detectado uma única vez, no cabeçalho. Antes era decidido linha a linha, e um versículo com muitas vírgulas num arquivo separado por ponto e vírgula (uma genealogia, por exemplo) abortava a importação.
* Importação: a leitura usa fgetcsv(), que aceita quebra de linha dentro do texto entre aspas.
* Importação: arquivos que não estão em UTF-8 (o "CSV" comum do Excel em português, em Windows-1252) são recusados com o número da linha, em vez de perderem os acentos em silêncio.
* Importação: mensagens específicas para cabeçalho ausente, cabeçalho inválido, arquivo vazio e livros faltando. O books.csv incompleto informa quantos e quais números de livro faltam.
* ZIP: books.csv e verses.csv podem estar na raiz ou dentro de uma única pasta, como gera o "Compactar pasta" do Windows e do macOS. Entradas de sistema (__MACOSX/, ._arquivo, .DS_Store, Thumbs.db, desktop.ini) são ignoradas. Arquivo inesperado ou duplicado é informado pelo nome.
* Instruções de importação reescritas no painel e no readme: passo a passo, exemplos com cabeçalho, aspas, codificação no Excel e no LibreOffice, e os efeitos de livro_desc na URL. Modelos books.csv (66 livros) e verses.csv (exemplos) para baixar.
* Versões: nova opção para excluir uma versão importada na aba Versões, com confirmação obrigatória. Apaga cadastro, livros e versículos. A Bíblia ativa não pode ser excluída diretamente. A exclusão limpa os caches por chave (inclusive em object cache) e pede a purga ao LiteSpeed Cache.
* URLs de uma versão excluída passam a responder com 301 para o mesmo livro, capítulo e versículo na Bíblia ativa.

= 1.1.78 =
* Nomes de livros corrigidos de forma nativa, sem ferramenta avulsa: na atualização para esta versão, cada site da rede corrige uma única vez a grafia errada dos nomes em versões pt-* (Genesis → Gênesis, Exodo → Êxodo, Levitico → Levítico, Deuteronomio → Deuteronômio, Juizes → Juízes, Cântares → Cantares, Oséias → Oseias, Miquéias → Miqueias, 1 e 2 Corintios → Coríntios, Efesios → Efésios, Colosenses → Colossenses, 1 e 2 Tesalonicenses → Tessalonicenses, 2 Timoteo → 2 Timóteo). Só a grafia errada exata é trocada, então nomes já corretos e outras versões não mudam.
* A mesma correção roda ao fim de toda importação, para que um CSV com a grafia antiga não traga os erros de volta.
* Depois da correção o plugin limpa os próprios caches e pede ao LiteSpeed Cache a purga das páginas, e mostra um aviso único no painel com as trocas feitas.
* Correções ajustáveis pelo filtro `bdwp70_book_name_corrections`.
* Livro 22 entre versões: "Cantares" (ACF, slug `cantares`) e "Cânticos" (Almeida 1911, ARC, ARA, slug `canticos`) passam a se redirecionar com 301 quando o slug pedido não existe na versão escolhida. Ao trocar de versão lendo esse livro, a URL deixa de levar a uma página inexistente.
* O mapa de slugs antigos fica de mão dupla também para Colossenses e 1/2 Tessalonicenses, cobrindo a ACF enquanto os nomes antigos ainda estiverem no banco.

= 1.1.77 =
* Português: títulos e descrições gerados para os buscadores passam a ter acentuação correta ("Livros da Bíblia", "capítulos", "versículos", "capítulo").
* Cartões de acesso rápido: os ícones padrão estavam gravados como "?" no código, resto de emoji perdido. Passam a usar símbolos do plano básico Unicode, que sobrevivem mesmo em bancos sem utf8mb4. Um ícone já salvo como "?" volta ao padrão.
* Mapa de nomes do sitemap: "Cantares" (grafia da ACF), "Oseias" e "Miqueias" (Acordo Ortográfico).
* Exemplo de books.csv no painel com "Gênesis" acentuado.
* Os nomes dos livros exibidos vêm do banco, gravados na importação; esta versão não os altera.
* URLs de livro com grafia corrigida: /colosenses/, /1-tesalonicenses/ e /2-tesalonicenses/ passam a responder com 301 para /colossenses/, /1-tessalonicenses/ e /2-tessalonicenses/, com capítulo e versículo preservados. O redirecionamento só age quando o slug pedido não existe na versão: enquanto o nome antigo estiver no banco, ou numa versão em que ele seja a grafia correta, nada muda. Shortcodes com a grafia antiga continuam resolvendo. Mapa ajustável pelo filtro `bdwp70_legacy_book_slugs`.

= 1.1.76 =
* SEO: a URL de versiculo (/livro/capitulo/versiculo/) passa a declarar a URL do capitulo como canonical. Ela entrega o capitulo inteiro com o versiculo destacado, e antes se declarava canonica de si mesma, o que multiplicava duplicatas por versiculo e por traducao.
* SEO: o noindex das URLs de versiculo introduzido na 1.1.74 fica desligado. noindex combinado com canonical para outra pagina e sinal contraditorio. O filtro `bdwp70_noindex_verse_urls` ainda religa a estrategia antiga por inteiro, e nesse caso o canonical volta a ser a propria URL.
* SEO: o canonical do capitulo tambem e aplicado ao Rank Math (`rank_math/frontend/canonical`) e ao Yoast (`wpseo_canonical`), caso emitam o proprio.
* Sitemap: URLs de versiculo nao entram mais, independente da opcao gravada, porque deixaram de ser canonicas. A caixa correspondente no painel fica desativada com a explicacao; o filtro `bdwp70_sitemap_include_verses` ainda pode forcar a inclusao.
* Deep link: ao abrir uma URL de versiculo, a pagina rola ate o versiculo destacado, sem sobrescrever uma ancora explicita na URL.

= 1.1.75 =
* Correcao do cabecalho `X-Robots-Tag` introduzido na 1.1.74: ele estava registrado em `template_redirect` na prioridade 1, mas o plugin renderiza a pagina virtual na prioridade 0 e encerra com `exit`, de modo que o callback nunca rodava. Passou para `send_headers`.
* A meta robots `noindex, follow` da 1.1.74 ja funcionava e nao muda; o cabecalho e redundancia para instalacoes sem plugin de SEO.

= 1.1.74 =
* As URLs de versiculo individual passam a ser marcadas como `noindex, follow`. Elas sao quase-duplicatas da pagina do capitulo, que ja traz cada versiculo com ancora propria. `follow` e mantido de proposito: os links seguem transmitindo sinal, apenas a pagina sai do indice.
* A marcacao cobre quatro caminhos, para valer com ou sem plugin de SEO: o `wp_robots` do WordPress, o filtro do Rank Math, o do Yoast e um cabecalho `X-Robots-Tag` na resposta.
* Funciona por requisicao, sem opcao no banco, entao vale automaticamente em todos os sites de uma rede multisite assim que o plugin e atualizado.
* Desligavel pelo filtro `bdwp70_noindex_verse_urls`.

= 1.1.73 =
* A opcao "Incluir versiculos individuais no sitemap" passa a vir DESMARCADA em instalacoes novas. Ligada, ela publica cerca de 30.000 URLs contra ~1.200 de capitulos; como a pagina do capitulo ja traz cada versiculo com ancora propria, sao quase-duplicatas, e cada visita de rastreador a uma delas custa uma renderizacao completa.
* A descricao da opcao no painel deixa de recomenda-la e passa a explicar o custo.
* Instalacoes existentes nao sao alteradas: quem ja tem a opcao marcada continua com ela marcada. Para desligar, use Configuracoes > Biblia Digital > SEO e URLs, ou o filtro `bdwp70_sitemap_include_verses`.

= 1.1.72 =
* Leitura: o texto biblico passa a ter medida controlada e centrada (80ch, cerca de 690px e 63 caracteres por linha no desktop). A pagina continua em largura inteira; so o texto recebe limite.
* Leitura no celular: o plugin deixa de somar o proprio recuo lateral ao do tema, e o texto volta a ocupar a largura disponivel.
* Leitura no celular: o tamanho de fonte so e gravado quando o leitor escolhe um. Gravar sempre anulava o passo menor que o CSS ja define para telas estreitas, e o texto ficava em 22px numa coluna estreita.
* Toque: a linha inteira do versiculo vira area de selecao, com realce suave. Selecionar e copiar o texto continua funcionando, e cliques em links seguem o proprio destino.
* Seletor de capitulos: no celular abre como folha inferior rolavel, com fundo escurecido. Antes era um painel absoluto que ultrapassava a borda da tela e deixava capitulos inalcancaveis.
* Barra fixa no topo do capitulo com livro, numero e navegacao; no celular ela some ao rolar para baixo e volta ao rolar para cima.
* Teclado: setas esquerda e direita mudam de capitulo, sem interferir quando o foco esta num campo de texto.

= 1.1.71 =
* Desempenho: a busca passa a usar o indice FULLTEXT `palavra_fulltext`, que ja existia no esquema e nao era consultado por nenhuma query. O caminho anterior, com `LIKE '%termo%'`, tinha curinga a esquerda e varria a tabela inteira duas vezes por busca.
* A busca por indice casa palavras e prefixos: procurar "amor" continua encontrando "amoroso", mas nao "desamor". Quando o indice nao devolve resultado, a busca anterior roda como antes, de modo que nenhuma consulta passa a terminar em zero por causa da mudanca.
* Termos com palavras menores que o token minimo do indice, buscas por frase exata com palavras curtas e instalacoes sem o indice continuam no caminho antigo. Filtros `bdwp70_use_fulltext_search` e `bdwp70_fulltext_min_token` permitem ajustar ou desligar.
* Desempenho: a lista de livros e a contagem de capitulos por livro passam a ficar em cache de 12 horas com memo por requisicao, no mesmo padrao ja usado pelas versoes biblicas. A contagem de capitulos era um agregado GROUP BY sobre a tabela de versiculos, refeito a cada pagina.
* Desempenho: o cache de resultados de busca sobe de 5 minutos para 12 horas, ajustavel pelo filtro `bdwp70_search_cache_ttl`. A importacao ja limpa esses caches.

= 1.1.70 =
* Nova identidade pública: nome de exibição `EstudoBiblico Bíblia Digital`, slug e text domain `estudobiblico-biblia-digital`, e `Contributors: crispaorg`.
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
