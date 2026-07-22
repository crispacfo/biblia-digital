# Auditoria Técnica Inicial

## 1. Arquivos principais

- `biblia-digital70.php`: arquivo principal do plugin, cabeçalho, constantes, includes, hooks de ativação/desativação e bootstrap.
- `includes/class-bdwp70-plugin.php`: classe principal, shortcodes, assets, rewrites, SEO, admin, uploads e handlers.
- `includes/class-bdwp70-activator.php`: criação de tabelas, ativação, importação inicial e importação CSV.
- `includes/class-bdwp70-random-verse-widget.php`: widget de versículo aleatorio.
- `includes/class-bdwp70-search-widget.php`: widget de busca.
- `includes/view-shortcode.php`: template de renderizacao do shortcode principal.
- `uninstall.php`: rotina de remocao de dados.

## 2. Shortcodes

- `[biblia-digital]`
- `[biblia-wp-estudobiblico]`
- `[bibliawp-estudobiblico]`
- `[biblia-versículo-aleatorio]`
- `[biblia-versículo]`
- `[biblia-capítulo]`
- `[biblia-busca]`
- `[estudo_biblico_busca]`
- `[biblia-busca-pagina]`
- `[biblia-digital-busca]`

## 3. Widgets

- `BDWP70_Random_Verse_Widget`
- `BDWP70_Search_Widget`

## 4. Blocos

- `blocks/search/block.json`
- `blocks/search/index.js`

## 5. Opcoes salvas no banco

- `bdwp70_version`
- `bdwp70_flush_rewrite`
- `bdwp70_import_status`
- `bdwp70_import_progress`
- `bdwp70_import_error`
- `bdwp70_imported_at`
- `bdwp70_active_bible`
- Opcoes de interface/SEO identificadas em `BDWP70_Plugin`, incluindo titulo, imagem de titulo, cards rapidos, base de SEO, credito e URL do Estudo Biblico.

## 6. Tabelas criadas

Identificadas em `includes/class-bdwp70-activator.php`:

- tabela de versoes/bíblias;
- tabela de livros;
- tabela de versículos.

Os nomes finais sao montados por helpers internos com prefixo do WordPress.

## 7. Hooks

- `plugins_loaded`
- `wp_initialize_site`
- `wpmu_new_blog`
- `widgets_init`
- `init`
- `template_redirect`
- `wp_head`
- `wp_enqueue_scripts`
- `admin_menu`
- `admin_enqueue_scripts`
- `admin_notices`
- `admin_post_bdwp70_reimport`
- `admin_post_bdwp70_save_settings`
- `admin_post_bdwp70_upload_bible`
- `admin_post_bdwp70_upload_translation`
- `wp_footer`

## 8. Filtros

- `query_vars`
- `document_title_parts`
- `pre_get_document_title`
- `get_canonical_url`
- `the_content`
- `widget_text`
- `widget_text_content`
- `body_class`

## 9. Acoes admin_post

- `bdwp70_reimport`
- `bdwp70_save_settings`
- `bdwp70_upload_bible`
- `bdwp70_upload_translation`

## 10. Rotinas de upload

- Upload ZIP de Bíblia em `handle_upload_bible()`.
- Upload de traducoes `.pot`, `.po` e `.mo` em `handle_upload_translation()`.

## 11. Rotinas de importação

- Importação inicial por SQL removida do pacote público; o plugin depende do importador ZIP/CSV com `books.csv` e `verses.csv`.
- Importacao por ZIP com `books.csv` e `verses.csv`.
- Status e mensagens gravados em opções `bdwp70_*`.

## 12. Rotinas de uninstall

- `uninstall.php` deve ser revisado antes de qualquer pacote enterprise, pois a orientacao do projeto exige preservar dados por padrao e apagar somente com opcao explicita.

## 13. Pontos de multisite

- Ativacao em rede.
- Preparacao de novo site via `wp_initialize_site` e `wpmu_new_blog`.
- Uso esperado de `switch_to_blog()` e `restore_current_blog()` nas rotinas de rede.

## 14. URLs e rewrite rules

- Rewrites registrados em `BDWP70_Plugin::register_rewrite()`.
- Query vars registradas em `BDWP70_Plugin::query_vars()`.
- Flush controlado por opcao `bdwp70_flush_rewrite`.

## 15. Strings i18n

- Text Domain atual: `biblia-digital`.
- Domain Path: `/languages`.
- Arquivo POT: `languages/biblia-digital.pot`.
- Traducoes existentes: `pt_BR`, `en_US` e `es_ES`.

## 16. Assets e licencas

- `assets/css/frontend.css`
- `assets/js/frontend.js`
- `assets/images/logo-estudo-biblico.webp`
- `assets/images/open-bible-hero.svg`
- `assets/images/share-icon.png`

E necessario documentar origem, autoria e licenca em `docs/ASSETS-LICENSES.md`.

## 17. Riscos de seguranca

- Entradas por `$_GET`, `$_POST` e `$_FILES` concentradas em `BDWP70_Plugin`.
- Upload ZIP e traducoes exigem validacao forte de extensao, MIME, tamanho, conteudo e path traversal.
- SQL com dados variaveis deve permanecer sempre protegido por `$wpdb->prepare()`.
- Saidas administrativas e frontend devem manter escaping contextual.

## 18. Riscos de desempenho

- Classe principal grande, com muitas responsabilidades.
- Importacao inicial e importação CSV podem ser pesadas em sites grandes ou multisite.
- Busca biblica precisa de indices e paginacao para evitar leitura integral de tabelas.

## 19. Riscos de perda de dados

- Uninstall destrutivo sem opt-in explicito e o principal risco.
- Reimportação/substituicao de Bíblias precisa de confirmacao clara.
- Network uninstall pode afetar todos os sites se não houver politica segura.

## 20. Plano de saneamento

1. Preservar shortcodes, tabelas, opções, URLs e compatibilidade multisite.
2. Implementar politica de uninstall com `bdwp70_delete_data_on_uninstall` padrao `0`.
3. Endurecer uploads com validacao de MIME, tamanho, conteudo e limpeza de temporarios.
4. Modularizar gradualmente `BDWP70_Plugin` mantendo wrappers publicos.
5. Criar documentacao enterprise solicitada.
6. Validar com `php -l`, PHPCS WordPress Coding Standards e Plugin Check.

