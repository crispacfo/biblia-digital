<?php
/**
 * Main plugin class.
 *
 * @package BibliaDigitalWP
 */

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing, Squiz.Commenting.FunctionComment.MissingParamTag -- Legacy public API keeps stable method names and signatures.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Frontend readers query plugin-owned Bible tables with sanitized/prepared inputs.
// phpcs:disable Squiz.PHP.DisallowMultipleAssignments.Found -- Existing request parsing keeps compact assignment where values are sanitized immediately.
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda -- Comparisons here read clearer in domain order and do not affect sanitization.
// phpcs:disable Squiz.Operators.IncrementDecrementUsage.Found -- Existing offset arithmetic is explicit for pagination/search flow.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local uploaded/imported translation files after validation.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive exposes numFiles as a PHP extension property.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'BDWP70_Plugin', false ) ) {
	return;
}

class BDWP70_Plugin {
	use BDWP70_SEO;

	private static $instance              = null;
	const OPTION_SEO_BASE                 = 'bdwp70_seo_base';
	const OPTION_FLUSH                    = 'bdwp70_flush_rewrite';
	const OPTION_CREDIT                   = 'bdwp70_show_credit';
	const OPTION_TITLE                    = 'bdwp70_bible_title';
	const OPTION_TITLE_IMAGE              = 'bdwp70_title_image_id';
	const OPTION_QUICK_CARDS              = 'bdwp70_quick_cards';
	const OPTION_BIBLE_STUDIO_URL         = 'bdwp70_bible_studio_url';
	const OPTION_ACTIVE                   = BDWP70_Activator::OPTION_ACTIVE_BIBLE;
	const OPTION_DELETE_DATA_ON_UNINSTALL = BDWP70_Activator::OPTION_DELETE_DATA_ON_UNINSTALL;
	const OPTION_SITEMAP_ENABLED          = 'bdwp70_sitemap_enabled';
	const OPTION_SITEMAP_INCL_VERSES      = 'bdwp70_sitemap_include_verses';
	const OPTION_SITEMAP_PER_PAGE         = 'bdwp70_sitemap_per_page';

	/**
	 * Text domain público, alinhado ao slug do WordPress.org.
	 */
	const TEXT_DOMAIN = 'estudobiblico-biblia-digital';

	/**
	 * Text domain usado até a 1.1.69. Só é consultado para localizar traduções
	 * personalizadas antigas; nenhuma string nova o utiliza.
	 */
	const LEGACY_TEXT_DOMAIN = 'biblia-digital';

	/**
	 * Subpasta de uploads onde ficam as traduções enviadas pelo administrador.
	 * Resolvida sempre por wp_upload_dir(), portanto já é por site em multisite.
	 */
	const CUSTOM_LANG_SUBDIR = 'estudobiblico-biblia-digital/languages';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_shortcode( 'biblia-digital', array( $this, 'shortcode' ) );
		add_shortcode( 'biblia-wp-estudobiblico', array( $this, 'shortcode' ) );
		add_shortcode( 'bibliawp-estudobiblico', array( $this, 'shortcode' ) );
		add_shortcode( 'biblia-versiculo-aleatorio', array( $this, 'random_verse_shortcode' ) );
		add_shortcode( 'biblia-versiculo', array( $this, 'verse_shortcode' ) );
		add_shortcode( 'biblia-capitulo', array( $this, 'chapter_shortcode' ) );
		add_shortcode( 'biblia-busca', array( $this, 'search_shortcode' ) );
		add_shortcode( 'estudo_biblico_busca', array( $this, 'search_shortcode' ) );
		add_shortcode( 'biblia-busca-pagina', array( $this, 'search_page_shortcode' ) );
		add_shortcode( 'biblia-digital-busca', array( $this, 'search_page_shortcode' ) );
		add_shortcode( 'biblia-capitulos-sidebar', array( $this, 'chapter_navigation_shortcode' ) );

		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );

		add_action( 'init', array( $this, 'load_custom_translations' ), 1 );
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 0 );
		add_filter( 'document_title_parts', array( $this, 'document_title_parts' ), 20 );
		add_filter( 'pre_get_document_title', array( $this, 'pre_get_document_title' ), 20 );
		// Prioridade 20: Rank Math e Yoast escrevem o <head> na prioridade 1, então
		// quando chega a vez do plugin já se sabe se algum deles gerou canonical.
		add_action( 'wp_head', array( $this, 'seo_head' ), 20 );
		add_filter( 'get_canonical_url', array( $this, 'canonical_filter' ), 20, 2 );

		// noindex nas URLs de versiculo: core, Rank Math, Yoast e cabecalho HTTP.
		add_filter( 'wp_robots', array( $this, 'robots_noindex_verse' ), 20 );
		add_filter( 'rank_math/frontend/robots', array( $this, 'robots_noindex_verse_seo_plugin' ), 20 );
		add_filter( 'wpseo_robots_array', array( $this, 'robots_noindex_verse_seo_plugin' ), 20 );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical_seo_plugin' ), 20 );
		add_filter( 'wpseo_canonical', array( $this, 'canonical_seo_plugin' ), 20 );
		add_filter( 'aioseo_canonical_url', array( $this, 'canonical_seo_plugin' ), 20 );
		add_filter( 'seopress_titles_canonical', array( $this, 'canonical_seo_plugin' ), 20 );
		// send_headers, e nao template_redirect: o proprio plugin renderiza a
		// pagina virtual em template_redirect na prioridade 0 e encerra com exit,
		// entao qualquer callback de prioridade maior nunca chega a rodar.
		add_action( 'send_headers', array( $this, 'maybe_send_verse_robots_header' ), 20 );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_init', array( $this, 'maybe_dismiss_setup_notice' ) );
		add_action( 'admin_post_bdwp70_reimport', array( $this, 'handle_reimport' ) );
		add_action( 'admin_post_bdwp70_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_bdwp70_upload_bible', array( $this, 'handle_upload_bible' ) );
		add_action( 'admin_post_bdwp70_upload_translation', array( $this, 'handle_upload_translation' ) );
		add_action( 'admin_post_bdwp70_delete_version', array( $this, 'handle_delete_version' ) );

		add_filter( 'the_content', array( $this, 'normalize_bible_shortcode_quotes' ), 7 );
		add_filter( 'widget_text', array( $this, 'normalize_bible_shortcode_quotes' ), 7 );
		add_filter( 'widget_text_content', array( $this, 'normalize_bible_shortcode_quotes' ), 7 );

		// Ajustes de compatibilidade visual com temas: a página da Bíblia deve ficar sem sidebar, mas preservando a largura do container.
		add_filter( 'body_class', array( $this, 'layout_body_classes' ) );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_theme_layout_assets' ), 1 );
	}


	/**
	 * Normaliza aspas tipográficas dentro dos shortcodes da Bíblia.
	 *
	 * Editores visuais e colagens vindas de processadores de texto podem trocar
	 * aspas simples/duplas por "aspas curvas", fazendo com que atributos como
	 * random="1" e limite="15" não sejam reconhecidos pelo parser de shortcode.
	 *
	 * @param string $content Conteúdo original.
	 * @return string
	 */
	public function normalize_bible_shortcode_quotes( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '[biblia-' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/\[(biblia-(?:capitulo|digital|busca|busca-pagina|digital-busca|versiculo-aleatorio|versiculo)[^\]]*)\]/iu',
			function ( $matches ) {
				$shortcode = str_replace(
					array( "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xE2\x80\x9F", "\xE2\x80\xB3", "\xEF\xBC\x82", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9A", "\xE2\x80\x9B", "\xE2\x80\xB2", "\xC2\xB4", '`' ),
					array( '"', '"', '"', '"', '"', '"', "'", "'", "'", "'", "'", "'", "'" ),
					$matches[1]
				);
				return '[' . $shortcode . ']';
			},
			$content
		);
	}

	/**
	 * Detecta páginas que exibem a Bíblia Digital para permitir layout sem sidebar.
	 *
	 * O método cobre URLs virtuais do plugin, a página configurada pelo slug de SEO,
	 * páginas com shortcodes e a página criada no site do usuário para a Bíblia.
	 *
	 * @return bool
	 */
	public function is_bible_layout_context() {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return false;
		}

		if ( get_query_var( 'bdwp_bible' ) || $this->is_bible_seo_context() ) {
			return true;
		}

		$slugs = array_filter(
			array_unique(
				array_map(
					'sanitize_title',
					array(
						$this->seo_base(),
						'biblia-digital',
						'biblia-digital-online',
						'biblia-sagrada-online',
						'biblia-sagrada-online-na-web',
					)
				)
			)
		);

		if ( $slugs && is_page( $slugs ) ) {
			return true;
		}

		$bdwp70_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path       = $bdwp70_request_uri ? trim( (string) wp_parse_url( $bdwp70_request_uri, PHP_URL_PATH ), '/' ) : '';
		if ( $request_path ) {
			$request_parts = explode( '/', $request_path );
			$request_slug  = sanitize_title( end( $request_parts ) );
			foreach ( $slugs as $slug ) {
				if ( $slug && ( $request_slug === $slug || $request_path === $slug || 0 === strpos( $request_path, $slug . '/' ) ) ) {
					return true;
				}
			}
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post ) {
				$post_slug    = isset( $post->post_name ) ? sanitize_title( $post->post_name ) : '';
				$post_title   = isset( $post->post_title ) ? sanitize_title( $post->post_title ) : '';
				$post_content = isset( $post->post_content ) ? (string) $post->post_content : '';
				$content_lc   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $post_content, 'UTF-8' ) : strtolower( $post_content );

				if ( in_array( $post_slug, $slugs, true ) || in_array( $post_title, $slugs, true ) ) {
					return true;
				}

				if (
					has_shortcode( $post_content, 'biblia-digital' ) ||
					has_shortcode( $post_content, 'biblia-wp-estudobiblico' ) ||
					has_shortcode( $post_content, 'bibliawp-estudobiblico' )
				) {
					return true;
				}

				foreach ( array( '[biblia-digital', '[biblia-wp-estudobiblico', '[bibliawp-estudobiblico', 'bdwp70-', 'data-bdwp70', 'bíblia digital online', 'biblia digital online' ) as $marker ) {
					if ( false !== strpos( $content_lc, $marker ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Retorna o modo visual atual da Bíblia para compatibilizar o layout do tema.
	 *
	 * @return string books|book|reader|search
	 */
	public function bible_layout_mode() {
		$state = $this->read_request_state();
		$mode  = isset( $state['mode'] ) ? sanitize_key( (string) $state['mode'] ) : 'books';

		return in_array( $mode, array( 'books', 'book', 'reader', 'search' ), true ) ? $mode : 'books';
	}

	/**
	 * A sidebar do tema deve serãocultada apenas na página inicial da Bíblia,
	 * onde aparecem os livros do Antigo e do Novo Testamento. Nas páginas de
	 * capítulos e de leitura, a sidebar direita do site pode voltar a aparecer.
	 *
	 * @return bool
	 */
	public function should_hide_theme_sidebar_for_bible() {
		return $this->is_bible_layout_context() && 'books' === $this->bible_layout_mode();
	}

	/**
	 * Indica quando a Bíblia pode ser exibida com a sidebar do tema.
	 *
	 * @return bool
	 */
	public function should_show_theme_sidebar_for_bible() {
		return $this->is_bible_layout_context() && ! $this->should_hide_theme_sidebar_for_bible();
	}

	/**
	 * Adiciona classes ao body em páginas da Bíblia.
	 *
	 * @param array $classes Classes atuais.
	 * @return array
	 */
	public function layout_body_classes( $classes ) {
		if ( $this->is_bible_layout_context() ) {
			$mode = $this->bible_layout_mode();

			$classes[] = 'bdwp70-page-contained';
			$classes[] = 'bdwp70-page-mode-' . sanitize_html_class( $mode );

			if ( 'books' === $mode ) {
				$classes[] = 'bdwp70-page-fullwidth';
				$classes[] = 'bdwp70-hide-theme-sidebar';
			} else {
				$classes[] = 'bdwp70-page-with-sidebar';
			}
		}

		return $classes;
	}

	/**
	 * CSS de compatibilidade: remove a sidebar só na página inicial da Bíblia
	 * e preserva a sidebar direita nas páginas de capítulos e leitura.
	 *
	 * O bloco é estático: não interpola nenhum valor dinâmico, por isso usa
	 * nowdoc e não requer escape. É entregue ao navegador por
	 * wp_add_inline_style() no handle bdwp70-theme-layout.
	 *
	 * @return string
	 */
	public function theme_layout_css() {
		return <<<'BDWP70_CSS'
body.bdwp70-page-contained .bdwp70__breadcrumbs,
.bdwp70__breadcrumbs{
	margin-top:.45rem!important;
	padding-top:.45rem!important;
	line-height:1.55!important;
	position:relative;
	z-index:2;
}
body.bdwp70-hide-theme-sidebar .content-layout,
body.bdwp70-hide-theme-sidebar .content-layout--archive,
body.bdwp70-hide-theme-sidebar .content-layout--single,
body.bdwp70-hide-theme-sidebar .content-layout--page,
body.bdwp70-hide-theme-sidebar .content-layout--bible{
	grid-template-columns:minmax(0,1fr)!important;
	width:100%!important;
	max-width:var(--eb-container,1128px)!important;
	margin-inline:auto!important;
}
body.bdwp70-hide-theme-sidebar .content-layout > .content-main,
body.bdwp70-hide-theme-sidebar .content-main,
body.bdwp70-hide-theme-sidebar .content-area,
body.bdwp70-hide-theme-sidebar .entry-content{
	width:100%!important;
	max-width:100%!important;
	min-width:0!important;
	margin-inline:0!important;
	flex:0 1 100%!important;
}
body.bdwp70-hide-theme-sidebar #primary.site-main,
body.bdwp70-hide-theme-sidebar .site-main.bdwp70-virtual-page,
body.bdwp70-hide-theme-sidebar .bdwp70-virtual-page{
	width:min(calc(100% - 32px),var(--eb-container,1128px))!important;
	max-width:var(--eb-container,1128px)!important;
	margin-inline:auto!important;
}
body.bdwp70-hide-theme-sidebar #secondary,
body.bdwp70-hide-theme-sidebar aside#secondary,
body.bdwp70-hide-theme-sidebar .site-sidebar,
body.bdwp70-hide-theme-sidebar .widget-area,
body.bdwp70-hide-theme-sidebar .sidebar,
body.bdwp70-hide-theme-sidebar .right-sidebar,
body.bdwp70-hide-theme-sidebar .primary-sidebar,
body.bdwp70-hide-theme-sidebar [role="complementary"]{
	display:none!important;
	visibility:hidden!important;
	width:0!important;
	max-width:0!important;
	overflow:hidden!important;
}
.content-layout:has(.bdwp70--mode-books),
.site-content:has(.bdwp70--mode-books) .content-layout{
	grid-template-columns:minmax(0,1fr)!important;
	max-width:var(--eb-container,1128px)!important;
	margin-inline:auto!important;
}
.content-layout:has(.bdwp70--mode-books) > #secondary,
.content-layout:has(.bdwp70--mode-books) > .site-sidebar,
.content-layout:has(.bdwp70--mode-books) > .widget-area{
	display:none!important;
}
body.bdwp70-page-with-sidebar .content-layout,
body.bdwp70-page-with-sidebar .content-layout--bible,
.bdwp70-virtual-content-layout{
	width:min(calc(100% - 32px),var(--eb-container,1128px))!important;
	max-width:var(--eb-container,1128px)!important;
	margin-inline:auto!important;
}
body.bdwp70-page-with-sidebar .content-main,
body.bdwp70-page-with-sidebar #primary.site-main,
.bdwp70-virtual-content-layout .content-main{
	width:100%!important;
	max-width:100%!important;
	min-width:0!important;
}
body.bdwp70-page-with-sidebar .bdwp70,
.bdwp70-virtual-content-layout .bdwp70{
	width:100%;
	max-width:100%;
}
body.bdwp70-page-with-sidebar .bdwp70__reader-layout,
.bdwp70-virtual-content-layout .bdwp70__reader-layout{
	display:block!important;
	max-width:100%!important;
}
body.bdwp70-page-with-sidebar .bdwp70__reader-main,
.bdwp70-virtual-content-layout .bdwp70__reader-main{
	max-width:100%!important;
}
BDWP70_CSS;
	}

	/**
	 * Fallback JS: ajusta classes do body quando a Bíblia vem de bloco/shortcode dinâmico.
	 *
	 * Também estático, entregue por wp_add_inline_script() no handle
	 * bdwp70-theme-layout.
	 *
	 * @return string
	 */
	public function theme_layout_js() {
		return <<<'BDWP70_JS'
(function(){
	function bdwp70ApplyLayout(){
		var bible = document.querySelector('[data-bdwp70], .bdwp70');
		if(!bible){ return; }
		document.body.classList.add('bdwp70-page-contained');
		if(document.querySelector('.bdwp70--mode-books')){
			document.body.classList.add('bdwp70-page-fullwidth','bdwp70-hide-theme-sidebar','bdwp70-page-mode-books');
			document.body.classList.remove('bdwp70-page-with-sidebar');
		}else{
			document.body.classList.add('bdwp70-page-with-sidebar');
			document.body.classList.remove('bdwp70-page-fullwidth','bdwp70-hide-theme-sidebar');
		}
	}
	if(document.readyState === 'loading'){
		document.addEventListener('DOMContentLoaded', bdwp70ApplyLayout);
	}else{
		bdwp70ApplyLayout();
	}
})();
BDWP70_JS;
	}

	/**
	 * Enfileira o CSS/JS de compatibilidade de tema quando a Bíblia está na página.
	 *
	 * Chamado a partir de register_assets() (wp_enqueue_scripts), garantindo que o
	 * CSS saia no <head>, como antes. Os handles são registrados sem src, então o
	 * WordPress imprime apenas o conteúdo inline anexado a eles.
	 *
	 * @return void
	 */
	private function enqueue_theme_layout_assets() {
		if ( ! wp_style_is( 'bdwp70-theme-layout', 'registered' ) ) {
			return;
		}

		wp_enqueue_style( 'bdwp70-theme-layout' );
		wp_enqueue_script( 'bdwp70-theme-layout' );
	}

	/**
	 * Rede de segurança para renderizações dinâmicas.
	 *
	 * Quando um bloco ou shortcode monta a Bíblia numa página que
	 * is_bible_layout_context() não reconheceu, o CSS/JS ainda precisa sair. Roda
	 * em wp_footer com prioridade 1, antes de wp_print_footer_scripts (20), e só
	 * age se o frontend do plugin realmente foi enfileirado nesta requisição.
	 *
	 * @return void
	 */
	public function maybe_enqueue_theme_layout_assets() {
		if ( wp_style_is( 'bdwp70-theme-layout', 'enqueued' ) || wp_style_is( 'bdwp70-theme-layout', 'done' ) ) {
			return;
		}

		if ( ! wp_style_is( 'bdwp70-frontend', 'enqueued' ) && ! wp_style_is( 'bdwp70-frontend', 'done' ) ) {
			return;
		}

		$this->enqueue_theme_layout_assets();
	}

	public function register_rewrite() {
		$base = $this->seo_base();

		add_rewrite_rule( '^manifest\.json$', 'index.php?bdwp_manifest=1', 'top' );

		// URLs canônica/SEO com versão explícita: /base/versao/acf-pt-br/joao/3/16/.
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/versao/([^/]+)/([^/]+)/([0-9]+)/([0-9]+)/?$', 'index.php?bdwp_bible=1&bdwp_versao_slug=$matches[1]&bdwp_livro_slug=$matches[2]&bdwp_capitulo=$matches[3]&bdwp_versiculo=$matches[4]', 'top' );
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/versao/([^/]+)/([^/]+)/([0-9]+)/?$', 'index.php?bdwp_bible=1&bdwp_versao_slug=$matches[1]&bdwp_livro_slug=$matches[2]&bdwp_capitulo=$matches[3]', 'top' );
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/versao/([^/]+)/([^/]+)/?$', 'index.php?bdwp_bible=1&bdwp_versao_slug=$matches[1]&bdwp_livro_slug=$matches[2]', 'top' );
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/versao/([^/]+)/?$', 'index.php?bdwp_bible=1&bdwp_versao_slug=$matches[1]', 'top' );

		// URLs legadas sem versão explícita continuam funcionando.
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/?$', 'index.php?bdwp_bible=1', 'top' );
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/([^/]+)/([0-9]+)/([0-9]+)/?$', 'index.php?bdwp_bible=1&bdwp_livro_slug=$matches[1]&bdwp_capitulo=$matches[2]&bdwp_versiculo=$matches[3]', 'top' );
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/([^/]+)/([0-9]+)/?$', 'index.php?bdwp_bible=1&bdwp_livro_slug=$matches[1]&bdwp_capitulo=$matches[2]', 'top' );
		add_rewrite_rule( '^' . preg_quote( $base, '/' ) . '/([^/]+)/?$', 'index.php?bdwp_bible=1&bdwp_livro_slug=$matches[1]', 'top' );

		// Schema upgrades and runtime-cache invalidation are handled centrally by
		// BDWP70_Activator::maybe_upgrade() on plugins_loaded (with a concurrency
		// lock and option preservation), so no inline upgrade check runs here.
		$this->maybe_flush_rewrite_rules_once();
	}

	/**
	 * Flushes rewrite rules only when the plugin explicitly marked them as stale.
	 *
	 * The flag is set during activation, plugin upgrades, or after changing the SEO
	 * base. It is intentionally not flushed on every request.
	 *
	 * @return void
	 */
	private function maybe_flush_rewrite_rules_once() {
		if ( ! get_option( self::OPTION_FLUSH ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( self::OPTION_FLUSH );
	}

	public function query_vars( $vars ) {
		$plugin_vars = array(
			'bdwp_bible',
			'bdwp_versao_slug',
			'bdwp_livro_slug',
			'bdwp_capitulo',
			'bdwp_versiculo',
			'bdwp_bible_id',
			'bdwp_livro',
			'bdwp_pesquisa',
			'bdwp_search_submit',
			'bdwp_exata',
			'bdwp_match',
			'bdwp_paged',
			'bdwp_manifest',
		);

		return array_values( array_unique( array_merge( $vars, $plugin_vars ) ) );
	}

	/**
	 * Slugs antigos de livros que tiveram a grafia corrigida depois de publicados.
	 *
	 * O slug e gerado a partir do nome gravado no banco. Quando o nome muda de
	 * letra (e nao so de acento), a URL muda junto; a antiga passa a responder
	 * com 301 para a nova, e shortcodes antigos continuam resolvendo.
	 *
	 * @return array slug antigo => slug atual.
	 */
	public function legacy_book_slugs() {
		/**
		 * Filtra o mapa de slugs antigos de livros.
		 *
		 * @param array $aliases slug antigo => slug atual.
		 */
		return (array) apply_filters(
			'bdwp70_legacy_book_slugs',
			array(
				// Mapa de mão dupla. Ida e volta não formam laço: o 301 só dispara
				// quando o slug pedido não existe na versão e o destino existe. A volta
				// cobre a ACF enquanto os nomes antigos ainda estiverem no banco, e
				// quem troca de versão vindo de uma que já usa a grafia correta.
				'colosenses'        => 'colossenses',
				'colossenses'       => 'colosenses',
				'1-tesalonicenses'  => '1-tessalonicenses',
				'1-tessalonicenses' => '1-tesalonicenses',
				'2-tesalonicenses'  => '2-tessalonicenses',
				'2-tessalonicenses' => '2-tesalonicenses',
				// Livro 22: "Cantares" na ACF, "Cânticos" nas demais versões.
				'cantares'          => 'canticos',
				'canticos'          => 'cantares',
			)
		);
	}

	/**
	 * Resolve um slug antigo para o numero do livro, se o destino existir aqui.
	 *
	 * @param string $slug Slug solicitado.
	 * @return array{seq:int,slug:string}|null
	 */
	private function resolve_legacy_book_slug( $slug ) {
		$slug    = sanitize_title( (string) $slug );
		$aliases = $this->legacy_book_slugs();

		if ( '' === $slug || empty( $aliases[ $slug ] ) ) {
			return null;
		}

		$novo = sanitize_title( (string) $aliases[ $slug ] );
		$seq  = $this->book_seq_from_slug( $novo );

		if ( $seq < 1 ) {
			return null;
		}

		return array(
			'seq'  => $seq,
			'slug' => $novo,
		);
	}

	/**
	 * URL de destino do 301 quando a requisicao usa um slug de livro antigo.
	 *
	 * So age quando o slug pedido nao corresponde a livro nenhum deste site:
	 * enquanto o nome antigo existir no banco, ou num site em que ele seja a
	 * grafia correta (em espanhol, "Colosenses"), nada e redirecionado.
	 *
	 * @return string URL absoluta, ou string vazia.
	 */
	private function legacy_book_slug_redirect_url() {
		$pedido = sanitize_title( (string) get_query_var( 'bdwp_livro_slug' ) );

		if ( '' === $pedido || $this->book_seq_from_slug( $pedido ) > 0 ) {
			return '';
		}

		$destino = $this->resolve_legacy_book_slug( $pedido );
		if ( ! $destino ) {
			return '';
		}

		global $wp;
		$caminho = isset( $wp->request ) ? (string) $wp->request : '';
		if ( '' === $caminho ) {
			return '';
		}

		$partes = explode( '/', $caminho );
		$trocou = false;
		foreach ( $partes as $i => $parte ) {
			if ( $i > 0 && sanitize_title( $parte ) === $pedido ) {
				$partes[ $i ] = $destino['slug'];
				$trocou       = true;
				break;
			}
		}

		if ( ! $trocou ) {
			return '';
		}

		$url = home_url( user_trailingslashit( implode( '/', $partes ) ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repassado como query string, sem interpretar.
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
		if ( '' !== $query ) {
			$url .= '?' . $query;
		}

		return $url;
	}

	/**
	 * URL de destino do 301 quando a requisição usa o slug de uma versão que não
	 * existe mais (excluída pelo painel).
	 *
	 * Sem isso a URL antiga continuaria respondendo 200 com o texto de outra
	 * tradução. O destino é o mesmo livro, capítulo e versículo na Bíblia ativa.
	 *
	 * @return string URL absoluta, ou string vazia.
	 */
	private function unknown_version_slug_redirect_url() {
		$pedido = sanitize_title( (string) get_query_var( 'bdwp_versao_slug' ) );
		if ( '' === $pedido || $this->bible_id_from_version_slug( $pedido ) > 0 ) {
			return '';
		}

		$ativa = $this->site_active_bible_id();
		if ( $ativa < 1 || ! BDWP70_Activator::bible_version_exists( $ativa ) ) {
			return '';
		}

		$destino = sanitize_title( $this->bible_version_slug( $ativa ) );
		if ( '' === $destino || $destino === $pedido ) {
			return '';
		}

		global $wp;
		$partes = explode( '/', isset( $wp->request ) ? (string) $wp->request : '' );
		$trocou = false;
		foreach ( $partes as $i => $parte ) {
			if ( $i > 0 && 'versao' === $partes[ $i - 1 ] && sanitize_title( $parte ) === $pedido ) {
				$partes[ $i ] = $destino;
				$trocou       = true;
				break;
			}
		}

		if ( ! $trocou ) {
			return '';
		}

		$url = home_url( user_trailingslashit( implode( '/', $partes ) ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repassado como query string, sem interpretar.
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';

		return '' !== $query ? $url . '?' . $query : $url;
	}

	/**
	 * Informa se a requisição atual deve enviar cabeçalhos de no-cache.
	 *
	 * Páginas públicas da Bíblia não enviam: o HTML é o mesmo para todo
	 * visitante anônimo, e a política de cache fica com o WordPress, com o
	 * plugin de cache e com a CDN.
	 *
	 * @return bool
	 */
	private function bible_page_sends_nocache() {
		/**
		 * Filtra o envio de no-cache nas páginas da Bíblia.
		 *
		 * @param bool $nocache True para enviar. Padrão: apenas para quem está logado.
		 */
		return (bool) apply_filters( 'bdwp70_bible_page_nocache', is_user_logged_in() );
	}

	/**
	 * Informa se a rota pedida aponta para livro, capítulo ou versículo que não existe.
	 *
	 * As regras de reescrita aceitam qualquer número, então /romanos/3/999/ e
	 * /romanos/999/1/ chegavam até a renderização e respondiam 200 com o
	 * capítulo mais próximo — um soft 404 que multiplica indefinidamente o
	 * espaço de URLs rastreáveis.
	 *
	 * A verificação só vale para as rotas com slug de livro; a busca e a lista
	 * de livros seguem intactas.
	 *
	 * @return bool
	 */
	private function bible_route_not_found() {
		$book_slug = (string) get_query_var( 'bdwp_livro_slug' );
		if ( '' === trim( $book_slug ) ) {
			return false;
		}

		$bible_id = $this->active_bible_id();
		$book_seq = $this->book_seq_from_slug( $book_slug );

		if ( $book_seq < 1 ) {
			return (bool) apply_filters( 'bdwp70_bible_route_not_found', true, 'book', $book_slug );
		}

		$chapter = absint( get_query_var( 'bdwp_capitulo' ) );
		if ( $chapter < 1 ) {
			return false;
		}

		$counts   = $this->get_chapter_counts( $bible_id );
		$chapters = isset( $counts[ $book_seq ] ) ? (int) $counts[ $book_seq ] : 0;

		if ( $chapters < 1 || $chapter > $chapters ) {
			return (bool) apply_filters( 'bdwp70_bible_route_not_found', true, 'chapter', $book_slug );
		}

		$verse = absint( get_query_var( 'bdwp_versiculo' ) );
		if ( $verse < 1 ) {
			return false;
		}

		if ( ! $this->get_single_verse( $book_seq, $chapter, $verse, $bible_id ) ) {
			return (bool) apply_filters( 'bdwp70_bible_route_not_found', true, 'verse', $book_slug );
		}

		return false;
	}

	public function template_redirect() {
		$seo = $this->build_seo_context();
		if ( $seo ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}

		if ( get_query_var( 'bdwp_bible' ) ) {
			$versao_removida = $this->unknown_version_slug_redirect_url();
			if ( '' !== $versao_removida ) {
				wp_safe_redirect( $versao_removida, 301 );
				exit;
			}

			$legado = $this->legacy_book_slug_redirect_url();
			if ( '' !== $legado ) {
				wp_safe_redirect( $legado, 301 );
				exit;
			}

			global $wp_query;

			// Referência inexistente responde 404 antes de qualquer 200.
			if ( $this->bible_route_not_found() ) {
				if ( $wp_query ) {
					$wp_query->set_404();
				}
				status_header( 404 );
				nocache_headers();

				// Sem exit: o tema renderiza o próprio 404.
				return;
			}

			if ( $wp_query ) {
				$wp_query->is_404      = false;
				$wp_query->is_page     = true;
				$wp_query->is_singular = true;
			}
			status_header( 200 );

			/*
			 * O HTML de um capítulo é público e determinístico, e enviar
			 * no-cache em toda página virtual impedia cache de página, CDN e
			 * proxy — justamente nas URLs mais visitadas do site. O no-cache
			 * fica para quem está logado, onde a página pode variar.
			 */
			if ( $this->bible_page_sends_nocache() ) {
				nocache_headers();
			}

			$this->render_virtual_bible_page();
			exit;
		}

		if ( get_query_var( 'bdwp_manifest' ) ) {
			$manifest = array(
				'name'             => $this->display_title(),
				'short_name'       => wp_trim_words( $this->display_title(), 3, '' ),
				/* translators: %s: Plugin display title. */
				'description'      => sprintf( __( '%s with books, chapters, verses, and Bible search.', 'estudobiblico-biblia-digital' ), $this->display_title() ),
				'start_url'        => esc_url_raw( home_url( '/' . $this->seo_base() . '/' ) ),
				'scope'            => esc_url_raw( home_url( '/' ) ),
				'display'          => 'standalone',
				'background_color' => '#ffffff',
				'theme_color'      => '#1e73be',
				'lang'             => str_replace( '_', '-', determine_locale() ),
			);

			/*
			 * O manifest é pedido por todo navegador que carrega uma página da
			 * Bíblia; é público e muda apenas quando o título ou o idioma do site
			 * mudam, então não faz sentido torná-lo não cacheável.
			 */
			if ( $this->bible_page_sends_nocache() ) {
				nocache_headers();
			}
			header( 'Content-Type: application/manifest+json; charset=' . get_option( 'blog_charset' ) );
			echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			exit;
		}
	}

	public function render_virtual_bible_page() {
		$mode        = $this->bible_layout_mode();
		$use_sidebar = 'books' !== $mode && function_exists( 'is_active_sidebar' ) && is_active_sidebar( 'sidebar-1' );

		if ( function_exists( 'get_header' ) ) {
			get_header();
		}

		if ( $use_sidebar ) {
			echo '<div class="site-container page-shell page-shell--bible page-shell--bible-sidebar bdwp70-virtual-shell">';
			echo '<div class="content-layout content-layout--bible content-layout--bible-sidebar bdwp70-virtual-content-layout">';
			echo '<main id="primary" class="content-main site-main bdwp70-virtual-page bdwp70-contained bdwp70-virtual-with-sidebar" role="main">';
			echo do_shortcode( '[biblia-digital]' );
			echo '</main>';
			echo '<aside id="secondary" class="widget-area site-sidebar bdwp70-theme-sidebar" role="complementary">';
			dynamic_sidebar( 'sidebar-1' );
			echo '</aside>';
			echo '</div>';
			echo '</div>';
		} else {
			echo '<main id="primary" class="site-main bdwp70-virtual-page bdwp70-contained" role="main">';
			echo do_shortcode( '[biblia-digital]' );
			echo '</main>';
		}

		if ( function_exists( 'get_footer' ) ) {
			get_footer();
		}
	}

	public function register_assets() {
		$css_file = BDWP70_DIR . 'assets/css/frontend.css';
		$js_file  = BDWP70_DIR . 'assets/js/frontend.js';

		wp_register_style(
			'bdwp70-frontend',
			BDWP70_URL . 'assets/css/frontend.css',
			array(),
			file_exists( $css_file ) ? filemtime( $css_file ) : BDWP70_VERSION
		);

		wp_register_script(
			'bdwp70-frontend',
			BDWP70_URL . 'assets/js/frontend.js',
			array(),
			file_exists( $js_file ) ? filemtime( $js_file ) : BDWP70_VERSION,
			true
		);

		// Handles sem src: servem apenas de âncora estável para o CSS/JS de
		// compatibilidade de tema, que antes era impresso direto em wp_head/wp_footer.
		wp_register_style( 'bdwp70-theme-layout', false, array(), BDWP70_VERSION );
		wp_register_script( 'bdwp70-theme-layout', false, array(), BDWP70_VERSION, true );
		wp_add_inline_style( 'bdwp70-theme-layout', $this->theme_layout_css() );
		wp_add_inline_script( 'bdwp70-theme-layout', $this->theme_layout_js() );

		if ( $this->is_bible_layout_context() ) {
			$this->enqueue_theme_layout_assets();
		}

		if ( is_active_widget( false, false, 'bdwp70_random_verse', true ) ) {
			wp_enqueue_style( 'bdwp70-frontend' );
		}
	}

	/**
	 * Registers Bíblia Digital blocks for block themes and the block-based Widgets screen.
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$block_dir = BDWP70_DIR . 'blocks/search';
		if ( is_dir( $block_dir ) ) {
			register_block_type(
				$block_dir,
				array(
					'render_callback' => array( $this, 'render_search_block' ),
				)
			);
		}
	}

	/**
	 * Render callback for the Bíblia Digital search block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_search_block( $attributes = array() ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		wp_enqueue_style( 'bdwp70-frontend' );

		return $this->render_search_form(
			array(
				'title'       => isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : __( 'Search the Bible', 'estudobiblico-biblia-digital' ),
				'placeholder' => isset( $attributes['placeholder'] ) ? sanitize_text_field( $attributes['placeholder'] ) : __( 'Enter a word or phrase', 'estudobiblico-biblia-digital' ),
				'button'      => isset( $attributes['button'] ) ? sanitize_text_field( $attributes['button'] ) : __( 'Search', 'estudobiblico-biblia-digital' ),
				'show_book'   => array_key_exists( 'showBook', $attributes ) ? ( ! empty( $attributes['showBook'] ) ? 1 : 0 ) : 1,
			)
		);
	}

	/**
	 * Registers WordPress widgets.
	 */
	public function register_widgets() {
		if ( class_exists( 'BDWP70_Random_Verse_Widget' ) ) {
			register_widget( 'BDWP70_Random_Verse_Widget' );
		}
		if ( class_exists( 'BDWP70_Search_Widget' ) ) {
			register_widget( 'BDWP70_Search_Widget' );
		}
		if ( class_exists( 'BDWP70_Chapter_Navigation_Widget' ) ) {
			register_widget( 'BDWP70_Chapter_Navigation_Widget' );
		}
	}

	/**
	 * Shortcode para exibir o card de capítulos no sidebar, em blocos ou no conteúdo.
	 *
	 * Uso: [biblia-capitulos-sidebar] ou [biblia-capitulos-sidebar livro="Daniel" capitulo="6"].
	 *
	 * @param array $atts Atributos do shortcode.
	 * @return string
	 */
	public function chapter_navigation_shortcode( $atts = array() ) {
		wp_enqueue_style( 'bdwp70-frontend' );

		$atts = shortcode_atts(
			array(
				'book'     => '',
				'livro'    => '',
				'chapter'  => 0,
				'capitulo' => 0,
				'version'  => 0,
			),
			$atts,
			'biblia-capitulos-sidebar'
		);

		$state = $this->read_request_state();
		if ( ! empty( $atts['version'] ) ) {
			$state['bible_id'] = absint( $atts['version'] );
		}

		$books      = $this->get_books( (int) $state['bible_id'] );
		$book_value = '' !== trim( (string) $atts['livro'] ) ? $atts['livro'] : $atts['book'];
		if ( '' !== trim( (string) $book_value ) ) {
			$state['book'] = $this->resolve_shortcode_book_seq( $book_value, (int) $state['bible_id'] );
		}

		$chapter_value = ! empty( $atts['capitulo'] ) ? absint( $atts['capitulo'] ) : absint( $atts['chapter'] );
		if ( $chapter_value ) {
			$state['chapter'] = $chapter_value;
		}

		$chapter_counts = $this->get_chapter_counts( (int) $state['bible_id'] );
		$card           = $this->render_chapter_navigation_card( $state, $books, $chapter_counts );
		if ( '' === $card ) {
			return '';
		}

		return '<div class="bdwp70 bdwp70--chapter-widget-shortcode">' . $card . '</div>';
	}

	/**
	 * Renderiza somente o card de capítulos do livro atual.
	 *
	 * @param array $state Estado de leitura.
	 * @param array $books Livros da Bíblia ativa.
	 * @param array $chapter_counts Total de capítulos por livro.
	 * @return string
	 */
	public function render_chapter_navigation_card( $state, $books, $chapter_counts ) {
		$book      = ! empty( $state['book'] ) ? absint( $state['book'] ) : 0;
		$book_name = $this->book_name_from_seq( $books, $book, '' );
		if ( ! $book || ! $book_name ) {
			return '';
		}

		$chapters      = isset( $chapter_counts[ $book ] ) ? (int) $chapter_counts[ $book ] : 0;
		$chapter       = ! empty( $state['chapter'] ) ? absint( $state['chapter'] ) : 0;
		$active_bible  = ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : $this->active_bible_id();
		$version_label = $this->get_bible_version_label( $active_bible );
		$book_url      = $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() . '/' . $this->book_slug_from_seq( $book, $books ) ) ), $active_bible );

		ob_start();
		?>
		<section class="bdwp70__side-card bdwp70__side-card--chapters bdwp70__chapter-widget">
			<header class="bdwp70__chapter-widget-header">
				<h2><a href="<?php echo esc_url( $book_url ); ?>"><?php echo esc_html( $book_name ); ?></a></h2>
				<?php if ( $chapter ) : ?>
					<span><?php /* translators: %d: Chapter number. */ echo esc_html( sprintf( __( 'Chapter %d', 'estudobiblico-biblia-digital' ), $chapter ) ); ?></span>
				<?php endif; ?>
			</header>
			<?php if ( $chapters > 0 ) : ?>
				<nav class="bdwp70__side-chapters" aria-label="<?php esc_attr_e( 'Chapters of the book', 'estudobiblico-biblia-digital' ); ?>">
					<?php for ( $i = 1; $i <= $chapters; $i++ ) : ?>
						<a class="<?php echo $chapter === $i ? 'is-current' : ''; ?>" href="<?php echo esc_url( $this->chapter_url( $book, $i, $books, $active_bible ) ); ?>"
						<?php
						if ( $chapter === $i ) :
							?>
							aria-current="page"<?php endif; ?>><?php echo esc_html( (string) $i ); ?></a>
					<?php endfor; ?>
				</nav>
			<?php endif; ?>
			<div class="bdwp70__chapter-widget-meta">
				<span aria-hidden="true">📖</span>
				<strong><?php esc_html_e( 'Translation', 'estudobiblico-biblia-digital' ); ?></strong>
				<em><?php echo esc_html( $version_label ); ?></em>
			</div>
			<a class="bdwp70__chapter-widget-all" href="<?php echo esc_url( $book_url ); ?>"><?php /* translators: %s: Bible book name. */ echo esc_html( sprintf( __( 'View all chapters of %s', 'estudobiblico-biblia-digital' ), $book_name ) ); ?></a>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode for a random verse. Useful for block themes and widget-like areas.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */

	/**
	 * Shortcode for the separated Bible search form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function search_shortcode( $atts = array() ) {
		wp_enqueue_style( 'bdwp70-frontend' );

		$atts = shortcode_atts(
			array(
				'title'       => __( 'Search the Bible', 'estudobiblico-biblia-digital' ),
				'placeholder' => __( 'Enter a word or phrase', 'estudobiblico-biblia-digital' ),
				'button'      => __( 'Search', 'estudobiblico-biblia-digital' ),
			),
			$atts,
			'biblia-busca'
		);

		return $this->render_search_form( $atts );
	}

	/**
	 * Shortcode completo para uma página exclusiva de busca bíblica.
	 *
	 * Use: [biblia-busca-pagina]
	 * Alias: [biblia-digital-busca]
	 *
	 * @param array $atts Atributos do shortcode.
	 * @return string
	 */
	public function search_page_shortcode( $atts = array() ) {
		wp_enqueue_style( 'bdwp70-frontend' );
		wp_enqueue_script( 'bdwp70-frontend' );

		$atts = shortcode_atts(
			array(
				'title'       => __( 'Search in the Bible', 'estudobiblico-biblia-digital' ),
				'placeholder' => __( 'Enter a word, phrase, or reference', 'estudobiblico-biblia-digital' ),
				'button'      => __( 'Search', 'estudobiblico-biblia-digital' ),
				'per_page'    => 30,
				'show_book'   => 1,
			),
			$atts,
			'biblia-busca-pagina'
		);

		$state    = $this->read_request_state();
		$books    = $this->get_books( (int) $state['bible_id'] );
		$per_page = max( 1, min( 100, absint( $atts['per_page'] ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public search form, read-only request.
		$is_search_request = isset( $_GET['bdwp_search_submit'] );
		$results           = array(
			'items'       => array(),
			'total'       => 0,
			'per_page'    => $per_page,
			'total_pages' => 1,
			'mode'        => 'search',
		);

		if ( $is_search_request && '' !== trim( (string) $state['search'] ) && BDWP70_Activator::count_verses( (int) $state['bible_id'] ) > 0 ) {
			$results = $this->query_verses( $state, $per_page );
		}

		$action = $this->current_shortcode_page_url();

		ob_start();
		?>
		<div class="bdwp70 bdwp70-search-page" data-bdwp70-search-page>
			<?php
			echo wp_kses(
				$this->render_search_form(
					array_merge(
						$atts,
						array(
							'preserve_values' => 1,
							'form_action'     => $action,
						)
					)
				),
				function_exists( 'bdwp70_allowed_form_html' ) ? bdwp70_allowed_form_html() : wp_kses_allowed_html( 'post' )
			);
			?>

			<?php if ( $is_search_request ) : ?>
				<div class="bdwp70-search-page__summary" aria-live="polite">
					<?php if ( '' === trim( (string) $state['search'] ) ) : ?>
						<p><?php esc_html_e( 'Enter a word or phrase to start searching.', 'estudobiblico-biblia-digital' ); ?></p>
					<?php elseif ( ! empty( $results['items'] ) ) : ?>
						<p><?php /* translators: 1: number of results, 2: search term. */ echo esc_html( sprintf( _n( '%1$s result found for "%2$s".', '%1$s results found for "%2$s".', (int) $results['total'], 'estudobiblico-biblia-digital' ), number_format_i18n( (int) $results['total'] ), $state['search'] ) ); ?></p>
					<?php else : ?>
						<p><?php /* translators: %s: search term. */ echo esc_html( sprintf( __( 'No term or word was found in the Bible for "%s".', 'estudobiblico-biblia-digital' ), $state['search'] ) ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $results['items'] ) ) : ?>
					<?php echo $this->render_search_results_list( $results, $state, $books ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->render_search_pagination( $results, $state, $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the Bible search form used by widget and shortcode.
	 *
	 * @param array $args Form arguments.
	 * @return string
	 */
	public function render_search_form( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'           => __( 'Search the Bible', 'estudobiblico-biblia-digital' ),
				'placeholder'     => __( 'Enter a word or phrase', 'estudobiblico-biblia-digital' ),
				'button'          => __( 'Search', 'estudobiblico-biblia-digital' ),
				'show_book'       => 1,
				'preserve_values' => 0,
				'form_action'     => '',
				'bible_id'        => 0,
			)
		);

		$form_bible_id = ! empty( $args['bible_id'] ) ? absint( $args['bible_id'] ) : $this->site_active_bible_id();
		$state         = ! empty( $args['preserve_values'] ) ? $this->read_request_state() : array(
			'bible_id' => $form_bible_id,
			'book'     => 99,
			'search'   => '',
			'exact'    => 0,
			'match'    => 'any',
		);

		$bible_id      = ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : $form_bible_id;
		$books         = $this->get_books( $bible_id );
		$action        = ! empty( $args['form_action'] ) ? esc_url_raw( $args['form_action'] ) : home_url( user_trailingslashit( $this->seo_base() ) );
		$action        = $this->maybe_add_bible_version_arg_to_url( $action, $bible_id );
		$selected_book = isset( $state['book'] ) ? absint( $state['book'] ) : 99;
		$search_value  = isset( $state['search'] ) ? (string) $state['search'] : '';
		$exact_value   = ! empty( $state['exact'] );
		$match_value   = isset( $state['match'] ) && 'all' === $state['match'] ? 'all' : 'any';

		ob_start();
		?>
		<form class="bdwp70-search-widget" method="get" action="<?php echo esc_url( $action ); ?>">
			<input type="hidden" name="bdwp_search_submit" value="1">
			<?php if ( false === strpos( $action, '/versao/' ) ) : ?>
				<input type="hidden" name="bdwp_bible_id" value="<?php echo esc_attr( (int) $bible_id ); ?>">
			<?php endif; ?>
			<?php if ( '' !== trim( (string) $args['title'] ) ) : ?>
				<h2 class="bdwp70-search-widget__title"><?php echo esc_html( (string) $args['title'] ); ?></h2>
			<?php endif; ?>

			<?php if ( ! empty( $args['show_book'] ) ) : ?>
				<label class="bdwp70-search-widget__field">
					<span><?php esc_html_e( 'Search in', 'estudobiblico-biblia-digital' ); ?></span>
					<select name="bdwp_livro">
						<option value="99" <?php selected( $selected_book, 99 ); ?>><?php esc_html_e( 'All books', 'estudobiblico-biblia-digital' ); ?></option>
						<?php foreach ( $books as $book ) : ?>
							<option value="<?php echo esc_attr( (int) $book->livro_seq ); ?>" <?php selected( $selected_book, (int) $book->livro_seq ); ?>><?php echo esc_html( $book->livro_desc ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php else : ?>
				<input type="hidden" name="bdwp_livro" value="99">
			<?php endif; ?>

			<label class="bdwp70-search-widget__field">
				<span><?php esc_html_e( 'Word or phrase', 'estudobiblico-biblia-digital' ); ?></span>
				<input type="search" name="bdwp_pesquisa" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php echo esc_attr( (string) $args['placeholder'] ); ?>" required>
			</label>

			<div class="bdwp70-search-widget__options">
				<label><input type="checkbox" name="bdwp_exata" value="1" <?php checked( $exact_value ); ?>> <?php esc_html_e( 'Exact phrase', 'estudobiblico-biblia-digital' ); ?></label>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Search mode', 'estudobiblico-biblia-digital' ); ?></span>
					<select name="bdwp_match">
						<option value="any" <?php selected( $match_value, 'any' ); ?>><?php esc_html_e( 'Any word', 'estudobiblico-biblia-digital' ); ?></option>
						<option value="all" <?php selected( $match_value, 'all' ); ?>><?php esc_html_e( 'All words', 'estudobiblico-biblia-digital' ); ?></option>
					</select>
				</label>
			</div>

			<button type="submit" class="bdwp70-search-widget__button"><?php echo esc_html( (string) $args['button'] ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Retorna a URL da página atual sem os parâmetros de busca/paginação da Bíblia.
	 *
	 * @return string
	 */
	private function current_shortcode_page_url() {
		if ( is_singular() ) {
			$permalink = get_permalink();
			if ( $permalink ) {
				return $permalink;
			}
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		return home_url( user_trailingslashit( trim( $path, '/' ) ) );
	}

	/**
	 * Destaca o termo localizado na busca.
	 *
	 * @param string $text Texto do versículo.
	 * @param array  $state Estado atual da busca.
	 * @return string HTML seguro.
	 */
	public function highlight_search_terms( $text, $state ) {
		$text   = trim( (string) $text );
		$search = isset( $state['search'] ) ? trim( (string) $state['search'] ) : '';

		if ( '' === $text || '' === $search ) {
			return esc_html( $text );
		}

		if ( ! empty( $state['exact'] ) ) {
			$terms = array( $search );
		} else {
			$terms = preg_split( '/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY );
			$terms = is_array( $terms ) ? $terms : array();
		}

		$terms = array_values(
			array_unique(
				array_filter(
					array_map( 'trim', $terms ),
					function ( $term ) {
						return '' !== $term && function_exists( 'mb_strlen' ) ? mb_strlen( $term, 'UTF-8' ) >= 2 : strlen( $term ) >= 2;
					}
				)
			)
		);
		$terms = array_slice( $terms, 0, 12 );

		if ( empty( $terms ) ) {
			return esc_html( $text );
		}

		usort(
			$terms,
			function ( $a, $b ) {
				$la = function_exists( 'mb_strlen' ) ? mb_strlen( $a, 'UTF-8' ) : strlen( $a );
				$lb = function_exists( 'mb_strlen' ) ? mb_strlen( $b, 'UTF-8' ) : strlen( $b );
				return $lb <=> $la;
			}
		);

		$quoted        = array_map(
			function ( $term ) {
				return preg_quote( $term, '/' );
			},
			$terms
		);
		$group         = '(' . implode( '|', $quoted ) . ')';
		$pattern       = '/' . $group . '/iu';
		$whole_pattern = '/^' . $group . '$/iu';
		$parts         = preg_split( $pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) ) {
			return esc_html( $text );
		}

		$output = '';
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( preg_match( $whole_pattern, $part ) ) {
				$output .= '<mark class="bdwp70__search-highlight">' . esc_html( $part ) . '</mark>';
			} else {
				$output .= esc_html( $part );
			}
		}

		return $output;
	}

	private function default_bible_studio_url_template() {
		return 'https://www.bibliastudio.com.br/?s={reference}';
	}

	private function sanitize_url_template( $template ) {
		$template = trim( (string) $template );
		$template = wp_strip_all_tags( $template );
		$template = str_replace( array( "\r", "\n", "\t" ), '', $template );
		return $this->limit_text_length( $template, 300 );
	}

	public function bible_studio_url( $book_name, $chapter, $verse, $reference = '' ) {
		$template = get_option( self::OPTION_BIBLE_STUDIO_URL, $this->default_bible_studio_url_template() );
		$template = $this->sanitize_url_template( $template );

		if ( '' === $template ) {
			return '';
		}

		$book_name = trim( (string) $book_name );
		$chapter   = absint( $chapter );
		$verse     = absint( $verse );
		$reference = $reference ? trim( (string) $reference ) : trim( $book_name . ' ' . $chapter . ':' . $verse );

		$replacements = array(
			'{book}'      => rawurlencode( $book_name ),
			'{book_slug}' => rawurlencode( sanitize_title( $book_name ) ),
			'{chapter}'   => rawurlencode( (string) $chapter ),
			'{verse}'     => rawurlencode( (string) $verse ),
			'{reference}' => rawurlencode( $reference ),
		);

		$url = strtr( $template, $replacements );
		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}

		return esc_url_raw( $url );
	}

	public function render_search_results_list( $results, $state, $books ) {
		$search_term    = isset( $state['search'] ) ? trim( (string) $state['search'] ) : '';
		$is_search_mode = isset( $state['mode'] ) && 'search' === (string) $state['mode'];

		if ( empty( $results['items'] ) || ! is_array( $results['items'] ) ) {
			if ( ! $is_search_mode || '' === $search_term ) {
				return '';
			}

			ob_start();
			?>
			<div class="bdwp70__search-results bdwp70__search-results--empty" aria-live="polite">
				<p class="bdwp70__search-empty-message">
					<?php
					/* translators: %s: search term. */
					echo esc_html( sprintf( __( 'No term or word was found in the Bible for "%s".', 'estudobiblico-biblia-digital' ), $search_term ) );
					?>
				</p>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		ob_start();
		?>
		<div class="bdwp70__search-results">
			<?php foreach ( $results['items'] as $verse ) : ?>
				<?php
				$book_name    = $this->book_name_from_seq( $books, (int) $verse->livroseq, (string) $verse->livro );
				$verse_link   = $this->verse_url( (int) $verse->livroseq, (int) $verse->capitulo, (int) $verse->versiculo, $books );
				$reference    = $book_name . ' ' . (int) $verse->capitulo . ':' . (int) $verse->versiculo;
				$full_verse   = trim( (string) $verse->palavra );
				$anchor       = sanitize_title( $book_name ) . '-' . (int) $verse->capitulo . '-' . (int) $verse->versiculo;
				$verse_action = $this->render_search_result_verse_action( $book_name, (int) $verse->capitulo, (int) $verse->versiculo, $verse_link, $anchor, $full_verse );
				?>
				<article class="bdwp70__search-result">
					<div class="bdwp70__search-result-head">
						<a class="bdwp70__search-ref" href="<?php echo esc_url( $verse_link ); ?>#<?php echo esc_attr( $anchor ); ?>" title="<?php echo esc_attr( $full_verse ); ?>"><?php echo esc_html( $reference ); ?></a>
					</div>
					<p class="bdwp70__search-verse-text">
						<?php echo $this->highlight_search_terms( $full_verse, $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $verse_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</p>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renderiza a ação discreta exibida ao final do versículo nos resultados de busca.
	 *
	 * O link aponta para o versículo dentro da própria Bíblia Digital. Os atributos data-*
	 * deixam o elemento disponível para integrações externas, como o plugin Bíblia Online
	 * Studio, sem exibir novamente o link textual "Bíblia Studio" na lista de resultados.
	 *
	 * @param string $book_name Nome do livro.
	 * @param int    $chapter   Capítulo.
	 * @param int    $verse     Versículo.
	 * @param string $verse_url URL local do versículo.
	 * @param string $anchor     âncora do versículo.
	 * @param string $verse_text Texto do versículo, usado por integrações de compartilhamento.
	 * @return string HTML seguro.
	 */
	public function render_search_result_verse_action( $book_name, $chapter, $verse, $verse_url, $anchor, $verse_text = '' ) {
		$book_name = trim( (string) $book_name );
		$chapter   = absint( $chapter );
		$verse     = absint( $verse );
		$verse_url = esc_url_raw( (string) $verse_url );
		$anchor    = sanitize_title( (string) $anchor );

		if ( '' === $book_name || ! $chapter || ! $verse || '' === $verse_url ) {
			return '';
		}

		$reference  = $book_name . ' ' . $chapter . ':' . $verse;
		$href       = $verse_url . ( $anchor ? '#' . $anchor : '' );
		$verse_text = trim( wp_strip_all_tags( (string) $verse_text ) );

		$html = sprintf(
			'<a class="bdwp70__verse-action bdwp70__verse-action--share bdwp70__search-verse-action bvs-verse-share-button" href="%1$s" title="%2$s" aria-label="%2$s" data-bible-reference="%3$s" data-book="%4$s" data-chapter="%5$d" data-verse="%6$d" data-bvs-ref="%3$s" data-bvs-verse="%7$s" data-bvs-url="%1$s"><span class="bdwp70__share-icon" aria-hidden="true"></span><span class="screen-reader-text">%2$s</span></a>',
			esc_url( $href ),
			/* translators: %s: Bible verse reference. */
			esc_attr( sprintf( __( 'Share %s', 'estudobiblico-biblia-digital' ), $reference ) ),
			esc_attr( $reference ),
			esc_attr( $book_name ),
			(int) $chapter,
			(int) $verse,
			esc_attr( $verse_text )
		);

		/**
		 * Permite que outro plugin substitua o Ícone/link exibido ao final do versículo.
		 *
		 * @param string $html      HTML do link.
		 * @param string $reference Referência bíblica.
		 * @param string $book_name Nome do livro.
		 * @param int    $chapter   Capítulo.
		 * @param int    $verse     Versículo.
		 * @param string $href       URL local do versículo.
		 * @param string $verse_text Texto limpo do versículo.
		 */
		return (string) apply_filters( 'bdwp70_search_result_verse_action', $html, $reference, $book_name, $chapter, $verse, $href, $verse_text );
	}

	public function render_search_pagination( $results, $state, $base_url ) {
		if ( empty( $results['total_pages'] ) || (int) $results['total_pages'] < 2 ) {
			return '';
		}

		$base_args = array(
			'bdwp_livro'         => $state['book'],
			'bdwp_pesquisa'      => $state['search'],
			'bdwp_exata'         => $state['exact'],
			'bdwp_match'         => $state['match'],
			'bdwp_search_submit' => 1,
		);
		if ( false === strpos( (string) $base_url, '/versao/' ) ) {
			$base_args['bdwp_bible_id'] = $state['bible_id'];
		}

		$current_page = max( 1, (int) $state['paged'] );

		ob_start();
		?>
		<nav class="bdwp70__pagination" aria-label="<?php esc_attr_e( 'Bible search pagination', 'estudobiblico-biblia-digital' ); ?>">
			<?php for ( $i = 1; $i <= (int) $results['total_pages']; $i++ ) : ?>
				<?php
				if ( $i > 2 && $i < $current_page - 2 ) {
					if ( 3 === $i ) {
						echo '<span class="bdwp70__dots">...</span>';
					}
					continue;
				}
				if ( $i < (int) $results['total_pages'] - 1 && $i > $current_page + 2 ) {
					if ( $i === $current_page + 3 ) {
						echo '<span class="bdwp70__dots">...</span>';
					}
					continue;
				}
				$url = add_query_arg( array_merge( $base_args, array( 'bdwp_paged' => $i ) ), $base_url );
				?>
				<a class="bdwp70__page <?php echo $i === $current_page ? 'is-current' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) $i ); ?></a>
			<?php endfor; ?>
		</nav>
		<?php
		return (string) ob_get_clean();
	}


	/**
	 * Shortcode para exibir um capítulo bíblico.
	 *
	 * Exemplos:
	 * [biblia-capitulo random="1" limite="15"]
	 * [biblia-capitulo livro="joao" capitulo="3" limite="21"]
	 * [biblia-capitulo livro="43" capitulo="3"]
	 *
	 * @param array $atts Atributos do shortcode.
	 * @return string
	 */
	public function chapter_shortcode( $atts = array() ) {
		wp_enqueue_style( 'bdwp70-frontend' );

		$atts = shortcode_atts(
			array(
				'title'       => '',
				'livro'       => '',
				'book'        => '',
				'capitulo'    => 0,
				'chapter'     => 0,
				'random'      => 0,
				'limite'      => 0,
				'limit'       => 0,
				'version'     => 0,
				'show_title'  => 1,
				'show_button' => 1,
				'button_text' => __( 'Read the chapter', 'estudobiblico-biblia-digital' ),
			),
			is_array( $atts ) ? $atts : array(),
			'biblia-capitulo'
		);

		$bible_id = ! empty( $atts['version'] ) ? absint( $atts['version'] ) : $this->site_active_bible_id();
		if ( $bible_id < 1 || ! BDWP70_Activator::bible_version_exists( $bible_id ) ) {
			$bible_id = $this->site_active_bible_id();
		}

		$book_seq = $this->resolve_shortcode_book_seq( ! empty( $atts['livro'] ) ? $atts['livro'] : $atts['book'], $bible_id );
		$chapter  = ! empty( $atts['capitulo'] ) ? absint( $atts['capitulo'] ) : absint( $atts['chapter'] );
		$random   = ! empty( $atts['random'] ) && '0' !== (string) $atts['random'];
		$limit    = ! empty( $atts['limite'] ) ? absint( $atts['limite'] ) : absint( $atts['limit'] );
		$limit    = $limit > 0 ? min( 176, $limit ) : 0;

		if ( $random || $book_seq < 1 || $chapter < 1 ) {
			$picked = $this->get_random_chapter( $bible_id, $book_seq );
			if ( $picked ) {
				$book_seq = (int) $picked->livroseq;
				$chapter  = (int) $picked->capitulo;
			}
		}

		if ( $book_seq < 1 || $chapter < 1 ) {
			return '<div class="bdwp70-chapter-shortcode"><p class="bdwp70-random-widget__empty">' . esc_html__( 'No chapters available. Make sure the Bible data has been imported.', 'estudobiblico-biblia-digital' ) . '</p></div>';
		}

		$books       = $this->get_books( $bible_id );
		$book_name   = $this->book_name_from_seq( $books, $book_seq, '' );
		$verses      = $this->get_chapter_verses( $book_seq, $chapter, $bible_id, $limit );
		$chapter_url = $this->chapter_url( $book_seq, $chapter, $books );

		if ( empty( $verses ) ) {
			return '<div class="bdwp70-chapter-shortcode"><p class="bdwp70-random-widget__empty">' . esc_html__( 'No verses available for this chapter.', 'estudobiblico-biblia-digital' ) . '</p></div>';
		}

		$title = trim( (string) $atts['title'] );
		if ( '' === $title ) {
			$title = trim( $book_name . ' ' . $chapter );
		}

		$button_text = trim( wp_strip_all_tags( (string) $atts['button_text'] ) );
		if ( '' === $button_text ) {
			$button_text = __( 'Read the chapter', 'estudobiblico-biblia-digital' );
		}

		ob_start();
		?>
		<div class="bdwp70-chapter-shortcode" data-bdwp70-chapter-shortcode>
			<?php if ( ! empty( $atts['show_title'] ) ) : ?>
				<h2 class="bdwp70-chapter-shortcode__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<div class="bdwp70-chapter-shortcode__verses">
				<?php foreach ( $verses as $verse ) : ?>
					<p class="bdwp70-chapter-shortcode__verse" id="<?php echo esc_attr( sanitize_title( $book_name ) . '-' . (int) $verse->capitulo . '-' . (int) $verse->versiculo ); ?>">
						<sup><?php echo esc_html( (string) (int) $verse->versiculo ); ?></sup>
						<?php echo esc_html( trim( (string) $verse->palavra ) ); ?>
					</p>
				<?php endforeach; ?>
			</div>
			<?php if ( ! empty( $atts['show_button'] ) ) : ?>
				<p class="bdwp70-chapter-shortcode__more"><a href="<?php echo esc_url( $chapter_url ); ?>"><?php echo esc_html( $button_text ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}


	/**
	 * Shortcode para exibir um versículo específico ou aleatório.
	 *
	 * Compatibilidade restaurada:
	 * [biblia-versiculo livro="random" capitulo="random" versiculo="random"]
	 *
	 * Também aceita:
	 * [biblia-versiculo livro="joao" capitulo="3" versiculo="16"]
	 * [biblia-versiculo book="43" chapter="3" verse="16"]
	 * [biblia-versiculo livro="salmos" capitulo="23" versiculo="random"]
	 *
	 * @param array $atts Atributos do shortcode.
	 * @return string
	 */
	public function verse_shortcode( $atts = array() ) {
		wp_enqueue_style( 'bdwp70-frontend' );

		$atts = shortcode_atts(
			array(
				'title'          => '',
				'livro'          => 'random',
				'book'           => '',
				'capitulo'       => 'random',
				'chapter'        => '',
				'versiculo'      => 'random',
				'verse'          => '',
				'version'        => 0,
				'show_reference' => 1,
				'link_reference' => 1,
				'show_button'    => 0,
				'button_text'    => __( 'Read the chapter', 'estudobiblico-biblia-digital' ),
			),
			is_array( $atts ) ? $atts : array(),
			'biblia-versiculo'
		);

		$bible_id = ! empty( $atts['version'] ) ? absint( $atts['version'] ) : $this->site_active_bible_id();
		if ( $bible_id < 1 || ! BDWP70_Activator::bible_version_exists( $bible_id ) ) {
			$bible_id = $this->site_active_bible_id();
		}

		$book_value    = '' !== trim( (string) $atts['book'] ) ? $atts['book'] : $atts['livro'];
		$chapter_value = '' !== trim( (string) $atts['chapter'] ) ? $atts['chapter'] : $atts['capitulo'];
		$verse_value   = '' !== trim( (string) $atts['verse'] ) ? $atts['verse'] : $atts['versiculo'];

		$book_random    = $this->is_shortcode_random_value( $book_value );
		$chapter_random = $this->is_shortcode_random_value( $chapter_value );
		$verse_random   = $this->is_shortcode_random_value( $verse_value );

		$book_seq = $book_random ? 0 : $this->resolve_shortcode_book_seq( $book_value, $bible_id );
		$chapter  = $chapter_random ? 0 : absint( $chapter_value );
		$verse_no = $verse_random ? 0 : absint( $verse_value );

		if ( ! $book_random && $book_seq < 1 ) {
			return '<div class="bdwp70-random-widget bdwp70-random-widget--shortcode"><p class="bdwp70-random-widget__empty">' . esc_html__( 'Book not found for this shortcode.', 'estudobiblico-biblia-digital' ) . '</p></div>';
		}

		$verse = null;
		if ( ! $book_random && ! $chapter_random && ! $verse_random && $book_seq > 0 && $chapter > 0 && $verse_no > 0 ) {
			$verse = $this->get_single_verse( $book_seq, $chapter, $verse_no, $bible_id );
		} else {
			$verse = $this->get_shortcode_random_verse( $bible_id, $book_seq, $chapter, $verse_no );
		}

		ob_start();
		echo '<div class="bdwp70-random-widget bdwp70-random-widget--shortcode bdwp70-verse-shortcode">';

		if ( '' !== trim( (string) $atts['title'] ) ) {
			echo '<h2 class="bdwp70-random-widget__title">' . esc_html( (string) $atts['title'] ) . '</h2>';
		}

		if ( ! $verse ) {
			echo '<p class="bdwp70-random-widget__empty">' . esc_html__( 'No verses available for this shortcode.', 'estudobiblico-biblia-digital' ) . '</p>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		$books       = $this->get_books( $bible_id );
		$book_name   = $this->book_name_from_seq( $books, (int) $verse->livroseq, (string) $verse->livro );
		$reference   = $book_name . ' ' . (int) $verse->capitulo . ':' . (int) $verse->versiculo;
		$url         = $this->verse_url( (int) $verse->livroseq, (int) $verse->capitulo, (int) $verse->versiculo, $books );
		$chapter_url = $this->chapter_url( (int) $verse->livroseq, (int) $verse->capitulo, $books );

		echo '<blockquote class="bdwp70-random-widget__quote">';
		echo '<p>' . esc_html( trim( (string) $verse->palavra ) ) . '</p>';

		if ( ! empty( $atts['show_reference'] ) ) {
			echo '<footer class="bdwp70-random-widget__ref">';
			if ( ! empty( $atts['link_reference'] ) ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $reference ) . '</a>';
			} else {
				echo esc_html( $reference );
			}
			echo '</footer>';
		}
		echo '</blockquote>';

		if ( ! empty( $atts['show_button'] ) ) {
			$button_text = trim( wp_strip_all_tags( (string) $atts['button_text'] ) );
			if ( '' === $button_text ) {
				$button_text = __( 'Read the chapter', 'estudobiblico-biblia-digital' );
			}
			echo '<p class="bdwp70-random-widget__more"><a href="' . esc_url( $chapter_url ) . '">' . esc_html( $button_text ) . '</a></p>';
		}

		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Verifica se um atributo do shortcode pede valor aleatório.
	 *
	 * @param mixed $value Valor do atributo.
	 * @return bool
	 */
	private function is_shortcode_random_value( $value ) {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return '' === $value || in_array( $value, array( 'random', 'aleatorio', 'aleatório', 'rand', '*' ), true );
	}

	/**
	 * Seleciona um versículo aleatório sem ordenação randômica no banco.
	 *
	 * @param int $bible_id Bíblia.
	 * @param int $book_seq Livro opcional.
	 * @param int $chapter Capítulo opcional.
	 * @param int $verse_no Versículo opcional.
	 * @return object|null
	 */
	private function get_random_grouped_verse( $bible_id, $book_seq = 0, $chapter = 0, $verse_no = 0 ) {
		global $wpdb;

		$table    = BDWP70_Activator::verses_table();
		$bible_id = absint( $bible_id );
		$book_seq = ( $book_seq > 0 && $book_seq <= 66 ) ? (int) $book_seq : 0;
		$chapter  = max( 0, (int) $chapter );
		$verse_no = max( 0, (int) $verse_no );

		if ( $bible_id < 1 ) {
			return null;
		}

		$where  = array( 'published = 1', 'bible_id = %d', 'livroseq BETWEEN 1 AND 66', 'capitulo > 0', 'versiculo > 0' );
		$params = array( $bible_id );

		if ( $book_seq > 0 ) {
			$where[]  = 'livroseq = %d';
			$params[] = $book_seq;
		}
		if ( $chapter > 0 ) {
			$where[]  = 'capitulo = %d';
			$params[] = $chapter;
		}
		if ( $verse_no > 0 ) {
			$where[]  = 'versiculo = %d';
			$params[] = $verse_no;
		}

		$where_sql = implode( ' AND ', $where );

		// Seleção direta quando a referência foi completamente informada.
		if ( $book_seq > 0 && $chapter > 0 && $verse_no > 0 ) {
			$direct_sql = 'SELECT id, testamento, livroseq, livro, capitulo, versiculo, palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE ' . $where_sql . ' ORDER BY id ASC LIMIT 1';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from internal placeholders.
			return $wpdb->get_row( $wpdb->prepare( $direct_sql, $params ) );
		}

		$bounds_key = 'bdwp70_rand_bounds_' . md5( $table . '|' . $where_sql . '|' . implode( '|', array_map( 'strval', $params ) ) );
		$bounds     = get_transient( $bounds_key );
		if ( false === $bounds || ! is_array( $bounds ) ) {
			$bounds_sql = 'SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM `' . esc_sql( $table ) . '` WHERE ' . $where_sql;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from internal placeholders.
			$row    = $wpdb->get_row( $wpdb->prepare( $bounds_sql, $params ), ARRAY_A );
			$bounds = array(
				'min' => isset( $row['min_id'] ) ? (int) $row['min_id'] : 0,
				'max' => isset( $row['max_id'] ) ? (int) $row['max_id'] : 0,
			);
			set_transient( $bounds_key, $bounds, 12 * HOUR_IN_SECONDS );
		}

		$min_id = isset( $bounds['min'] ) ? (int) $bounds['min'] : 0;
		$max_id = isset( $bounds['max'] ) ? (int) $bounds['max'] : 0;
		if ( $min_id < 1 || $max_id < $min_id ) {
			return null;
		}

		$select_sql = 'SELECT id, testamento, livroseq, livro, capitulo, versiculo, palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE ' . $where_sql . ' AND id >= %d ORDER BY id ASC LIMIT 1';
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$random_id      = wp_rand( $min_id, $max_id );
			$query_params   = $params;
			$query_params[] = $random_id;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from internal placeholders.
			$verse = $wpdb->get_row( $wpdb->prepare( $select_sql, $query_params ) );
			if ( $verse ) {
				return $verse;
			}
		}

		$fallback_sql = 'SELECT id, testamento, livroseq, livro, capitulo, versiculo, palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE ' . $where_sql . ' ORDER BY id ASC LIMIT 1';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from internal placeholders.
		return $wpdb->get_row( $wpdb->prepare( $fallback_sql, $params ) );
	}

	/**
	 * Seleciona um capítulo aleatório sem ordenação randômica no banco.
	 *
	 * @param int $bible_id Bíblia.
	 * @param int $book_seq Livro opcional.
	 * @return object|null
	 */
	private function get_random_grouped_chapter( $bible_id, $book_seq = 0 ) {
		global $wpdb;

		$table    = BDWP70_Activator::verses_table();
		$bible_id = absint( $bible_id );
		$book_seq = ( $book_seq > 0 && $book_seq <= 66 ) ? (int) $book_seq : 0;

		if ( $bible_id < 1 ) {
			return null;
		}

		$where  = array( 'published = 1', 'bible_id = %d', 'livroseq BETWEEN 1 AND 66', 'capitulo > 0', 'versiculo > 0' );
		$params = array( $bible_id );

		if ( $book_seq > 0 ) {
			$where[]  = 'livroseq = %d';
			$params[] = $book_seq;
		}

		$key      = 'bdwp70_rand_chapters_' . md5( $table . '|' . implode( ' AND ', $where ) . '|' . implode( '|', array_map( 'strval', $params ) ) );
		$chapters = get_transient( $key );
		if ( false === $chapters || ! is_array( $chapters ) ) {
			$where_sql = implode( ' AND ', $where );
			$sql       = 'SELECT livroseq, MIN(livro) AS livro, capitulo FROM `' . esc_sql( $table ) . '` WHERE ' . $where_sql . ' GROUP BY livroseq, capitulo ORDER BY livroseq ASC, capitulo ASC';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from internal placeholders.
			$chapters = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
			$chapters = is_array( $chapters ) ? $chapters : array();
			set_transient( $key, $chapters, 12 * HOUR_IN_SECONDS );
		}

		if ( empty( $chapters ) ) {
			return null;
		}

		$index = array_rand( $chapters );
		return isset( $chapters[ $index ] ) ? $chapters[ $index ] : null;
	}

	/**
	 * Seleciona versículo aleatório respeitando filtros opcionais.
	 *
	 * @param int $bible_id Bíblia.
	 * @param int $book_seq Livro opcional.
	 * @param int $chapter Capítulo opcional.
	 * @param int $verse_no Versículo opcional.
	 * @return object|null
	 */
	public function get_shortcode_random_verse( $bible_id = null, $book_seq = 0, $chapter = 0, $verse_no = 0 ) {
		$bible_id = $bible_id ? absint( $bible_id ) : $this->site_active_bible_id();
		$book_seq = ( $book_seq > 0 && $book_seq <= 66 ) ? (int) $book_seq : 0;
		$chapter  = max( 0, (int) $chapter );
		$verse_no = max( 0, (int) $verse_no );

		return $this->get_random_grouped_verse( $bible_id, $book_seq, $chapter, $verse_no );
	}

	public function random_verse_shortcode( $atts = array() ) {
		wp_enqueue_style( 'bdwp70-frontend' );

		$atts = shortcode_atts(
			array(
				'title'          => __( 'Verse of the moment', 'estudobiblico-biblia-digital' ),
				'book'           => 0,
				'version'        => 0,
				'show_reference' => 1,
				'link_reference' => 1,
				'show_button'    => 0,
				'button_text'    => __( 'Read the chapter', 'estudobiblico-biblia-digital' ),
			),
			$atts,
			'biblia-versiculo-aleatorio'
		);

		$book_seq = absint( $atts['book'] );
		$bible_id = ! empty( $atts['version'] ) ? absint( $atts['version'] ) : $this->site_active_bible_id();
		$verse    = $this->get_random_verse( $book_seq, $bible_id );
		$books    = $this->get_books( $bible_id );

		ob_start();
		echo '<div class="bdwp70-random-widget bdwp70-random-widget--shortcode">';
		if ( '' !== trim( (string) $atts['title'] ) ) {
			echo '<h2 class="bdwp70-random-widget__title">' . esc_html( (string) $atts['title'] ) . '</h2>';
		}

		if ( ! $verse ) {
			echo '<p class="bdwp70-random-widget__empty">' . esc_html__( 'No verses available. Make sure the Bible data has been imported.', 'estudobiblico-biblia-digital' ) . '</p>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		$book_name = $this->book_name_from_seq( $books, (int) $verse->livroseq, (string) $verse->livro );
		$reference = $book_name . ' ' . (int) $verse->capitulo . ':' . (int) $verse->versiculo;
		$url       = $this->verse_url( (int) $verse->livroseq, (int) $verse->capitulo, (int) $verse->versiculo, $books );

		echo '<blockquote class="bdwp70-random-widget__quote">';
		echo '<p>' . esc_html( trim( (string) $verse->palavra ) ) . '</p>';

		if ( ! empty( $atts['show_reference'] ) ) {
			echo '<footer class="bdwp70-random-widget__ref">';
			if ( ! empty( $atts['link_reference'] ) ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $reference ) . '</a>';
			} else {
				echo esc_html( $reference );
			}
			echo '</footer>';
		}
		echo '</blockquote>';

		if ( ! empty( $atts['show_button'] ) ) {
			$button_text = trim( wp_strip_all_tags( (string) $atts['button_text'] ) );
			if ( '' === $button_text ) {
				$button_text = __( 'Read the chapter', 'estudobiblico-biblia-digital' );
			}
			echo '<p class="bdwp70-random-widget__more"><a href="' . esc_url( $url ) . '">' . esc_html( $button_text ) . '</a></p>';
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	public function shortcode( $atts = array() ) {
		wp_enqueue_style( 'bdwp70-frontend' );
		wp_enqueue_script( 'bdwp70-frontend' );

		$atts = shortcode_atts(
			array(
				'per_page' => 30,
				'title'    => $this->display_title(),
				'version'  => 0,
			),
			$atts,
			'biblia-digital'
		);

		$state = $this->read_request_state();
		if ( ! empty( $atts['version'] ) ) {
			$state['bible_id'] = absint( $atts['version'] );
		}
		$bdwp70_versions = $this->get_bible_versions();
		$books           = $this->get_books( $state['bible_id'] );
		$bdwp70_books    = $books;
		$results         = array(
			'items'       => array(),
			'total'       => 0,
			'per_page'    => max( 1, (int) $atts['per_page'] ),
			'total_pages' => 1,
			'mode'        => 'chapter',
		);

		if ( BDWP70_Activator::count_verses( $state['bible_id'] ) > 0 && in_array( $state['mode'], array( 'reader', 'search' ), true ) ) {
			$results = $this->query_verses( $state, max( 1, (int) $atts['per_page'] ) );
		}

		ob_start();
		include BDWP70_DIR . 'includes/view-shortcode.php';
		return (string) ob_get_clean();
	}

	public function read_request_state() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public reader/search query vars are read-only and sanitized below.
		$is_search_request = isset( $_GET['bdwp_search_submit'] );
		$search            = $is_search_request && isset( $_GET['bdwp_pesquisa'] ) ? sanitize_text_field( wp_unslash( $_GET['bdwp_pesquisa'] ) ) : '';
		$search            = $this->limit_text_length( $search, 120 );
		$book_slug         = get_query_var( 'bdwp_livro_slug' );
		$book_from_slug    = $book_slug ? $this->book_seq_from_slug( sanitize_title( $book_slug ) ) : 0;

		$default_book = $is_search_request ? 99 : 0;
		$book         = $book_from_slug ? $book_from_slug : ( isset( $_GET['bdwp_livro'] ) ? absint( wp_unslash( $_GET['bdwp_livro'] ) ) : $default_book );

		if ( $is_search_request && $book < 1 ) {
			$book = 99;
		}

		$chapter = 0;
		if ( ! $is_search_request && get_query_var( 'bdwp_capitulo' ) ) {
			$chapter = absint( get_query_var( 'bdwp_capitulo' ) );
		} elseif ( ! $is_search_request && isset( $_GET['bdwp_capitulo'] ) ) {
			$chapter = absint( wp_unslash( $_GET['bdwp_capitulo'] ) );
		}

		$verse = 0;
		if ( ! $is_search_request && get_query_var( 'bdwp_versiculo' ) ) {
			$verse = absint( get_query_var( 'bdwp_versiculo' ) );
		} elseif ( ! $is_search_request && isset( $_GET['bdwp_versiculo'] ) ) {
			$verse = absint( wp_unslash( $_GET['bdwp_versiculo'] ) );
		}

		$mode = 'books';
		if ( $is_search_request ) {
			$mode = 'search';
		} elseif ( $book > 0 && $chapter > 0 ) {
			$mode = 'reader';
		} elseif ( $book > 0 ) {
			$mode = 'book';
		}

		$state = array(
			'bible_id'  => $this->active_bible_id(),
			'book'      => $book,
			'book_slug' => $book_slug ? sanitize_title( $book_slug ) : '',
			'chapter'   => $chapter,
			'verse'     => $verse,
			'search'    => $search,
			'exact'     => $is_search_request && isset( $_GET['bdwp_exata'] ) ? absint( wp_unslash( $_GET['bdwp_exata'] ) ) : 0,
			'match'     => $is_search_request && isset( $_GET['bdwp_match'] ) && 'all' === sanitize_key( wp_unslash( $_GET['bdwp_match'] ) ) ? 'all' : 'any',
			'paged'     => isset( $_GET['bdwp_paged'] ) ? max( 1, absint( wp_unslash( $_GET['bdwp_paged'] ) ) ) : 1,
			'mode'      => $mode,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $state;
	}

	/**
	 * Limita textos vindos de requisições públicas antes de usá-los em consultas.
	 *
	 * @param string $value Texto de entrada.
	 * @param int    $max   Tamanho máximo.
	 * @return string
	 */
	private function limit_text_length( $value, $max = 120 ) {
		$value = (string) $value;
		$max   = max( 1, absint( $max ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max, 'UTF-8' );
		}

		return substr( $value, 0, $max );
	}

	public function display_title() {
		$title = get_option( self::OPTION_TITLE, __( 'Online Bible', 'estudobiblico-biblia-digital' ) );
		$title = is_string( $title ) ? trim( wp_strip_all_tags( $title ) ) : '';
		return $title ? $title : __( 'Online Bible', 'estudobiblico-biblia-digital' );
	}

	/**
	 * Cards padrão de acesso rápido da página inicial da Bíblia.
	 *
	 * @return array
	 */
	public function default_quick_cards() {
		return array(
			array(
				'enabled'     => 1,
				'title'       => __( 'Search chapter', 'estudobiblico-biblia-digital' ),
				'description' => __( 'Find chapters, words, or specific topics.', 'estudobiblico-biblia-digital' ),
				'url'         => '#bdwp70-livros',
				'icon'        => '⌕',
				'style'       => 'search',
			),
			array(
				'enabled'     => 1,
				'title'       => __( 'Daily reading', 'estudobiblico-biblia-digital' ),
				'description' => __( 'A daily reading suggestion to build up your faith.', 'estudobiblico-biblia-digital' ),
				'url'         => '#bdwp70-livros',
				'icon'        => '☀',
				'style'       => 'reading',
			),
			array(
				'enabled'     => 1,
				'title'       => __( 'Verse of the day', 'estudobiblico-biblia-digital' ),
				'description' => __( 'Daily inspiration from the Word of God.', 'estudobiblico-biblia-digital' ),
				'url'         => '#bdwp70-livros',
				'icon'        => '❝',
				'style'       => 'verse',
			),
			array(
				'enabled'     => 1,
				'title'       => __( 'Latest studies', 'estudobiblico-biblia-digital' ),
				'description' => __( 'Read the most recent Bible studies.', 'estudobiblico-biblia-digital' ),
				'url'         => home_url( '/' ),
				'icon'        => '✎',
				'style'       => 'studies',
			),
		);
	}

	/**
	 * Retorna os cards de acesso rápido configurados no admin.
	 *
	 * @param bool $include_disabled Inclui cards desativados para edição no admin.
	 * @return array
	 */
	public function quick_cards( $include_disabled = false ) {
		$cards = $this->sanitize_quick_cards( get_option( self::OPTION_QUICK_CARDS, array() ), true );

		if ( $include_disabled ) {
			return $cards;
		}

		return array_values(
			array_filter(
				$cards,
				function ( $card ) {
					return ! empty( $card['enabled'] ) && '' !== trim( (string) $card['title'] );
				}
			)
		);
	}

	/**
	 * Sanitiza e normaliza os cards de acesso rápido.
	 *
	 * @param array $raw Dados brutos vindos do banco ou do formulário.
	 * @param bool  $merge_defaults Mescla com os valores padrão.
	 * @return array
	 */
	private function sanitize_quick_cards( $raw, $merge_defaults = false ) {
		$defaults       = $this->default_quick_cards();
		$allowed_styles = array( 'search', 'reading', 'verse', 'studies' );
		$cards          = array();

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		for ( $i = 0; $i < 4; $i++ ) {
			$source = isset( $raw[ $i ] ) && is_array( $raw[ $i ] ) ? $raw[ $i ] : array();
			$base   = $merge_defaults && isset( $defaults[ $i ] ) ? $defaults[ $i ] : array();

			$title       = isset( $source['title'] ) ? sanitize_text_field( $source['title'] ) : ( isset( $base['title'] ) ? $base['title'] : '' );
			$description = isset( $source['description'] ) ? sanitize_text_field( $source['description'] ) : ( isset( $base['description'] ) ? $base['description'] : '' );
			$icon        = isset( $source['icon'] ) ? sanitize_text_field( $source['icon'] ) : ( isset( $base['icon'] ) ? $base['icon'] : '*' );
			// "?" e o que sobra de um emoji gravado num banco sem utf8mb4; volta ao icone padrao.
			if ( ( '' === $icon || preg_match( '/^\?+$/', $icon ) ) && ! empty( $base['icon'] ) ) {
				$icon = $base['icon'];
			}
			$style = isset( $source['style'] ) ? sanitize_key( $source['style'] ) : ( isset( $base['style'] ) ? $base['style'] : 'search' );
			if ( ! in_array( $style, $allowed_styles, true ) ) {
				$style = isset( $base['style'] ) && in_array( $base['style'], $allowed_styles, true ) ? $base['style'] : 'search';
			}

			$url = isset( $source['url'] ) ? trim( (string) $source['url'] ) : ( isset( $base['url'] ) ? (string) $base['url'] : '#bdwp70-livros' );
			if ( '' === $url ) {
				$url = isset( $base['url'] ) ? (string) $base['url'] : '#bdwp70-livros';
			}
			if ( 0 === strpos( $url, '#' ) || 0 === strpos( $url, '/' ) ) {
				$url = sanitize_text_field( $url );
			} else {
				$url = esc_url_raw( $url );
			}
			if ( '' === $url ) {
				$url = '#bdwp70-livros';
			}

			$cards[] = array(
				'enabled'     => isset( $source['enabled'] ) ? ( ! empty( $source['enabled'] ) ? 1 : 0 ) : ( isset( $base['enabled'] ) ? (int) $base['enabled'] : 0 ),
				'title'       => $title,
				'description' => $description,
				'url'         => $url,
				'icon'        => $icon ? $icon : '*',
				'style'       => $style,
			);
		}

		return $cards;
	}



	public function title_image_url() {
		$image_id = absint( get_option( self::OPTION_TITLE_IMAGE, 0 ) );
		if ( ! $image_id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $image_id, 'full' );
		return $url ? esc_url_raw( $url ) : '';
	}

	public function seo_base() {
		$base = get_option( self::OPTION_SEO_BASE, 'biblia-digital' );
		$base = sanitize_title( $base );
		return $base ? $base : 'biblia-digital';
	}

	public function get_books( $bible_id = null ) {
		global $wpdb;
		$table    = BDWP70_Activator::books_table();
		$bible_id = $bible_id ? absint( $bible_id ) : $this->active_bible_id();

		/*
		 * A lista de 66 livros nao muda entre importacoes e e pedida em varios
		 * pontos da mesma pagina. Memo estatico para a requisicao, transient para
		 * as seguintes, no mesmo padrao ja usado em get_bible_versions().
		 * Invalidado por BDWP70_Activator::clear_runtime_caches().
		 */
		static $memo = array();
		if ( isset( $memo[ $bible_id ] ) ) {
			return $memo[ $bible_id ];
		}

		$chave  = 'bdwp70_bookslist_' . $bible_id;
		$cached = get_transient( $chave );
		if ( is_array( $cached ) ) {
			$memo[ $bible_id ] = $cached;
			return $cached;
		}

		$books = $wpdb->get_results( $wpdb->prepare( 'SELECT livro_seq, livro, livro_desc FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d AND published = 1 AND livro_seq BETWEEN 1 AND 66 ORDER BY livro_seq ASC', $bible_id ) );
		$books = is_array( $books ) ? $books : array();

		if ( ! empty( $books ) ) {
			set_transient( $chave, $books, 12 * HOUR_IN_SECONDS );
		}

		$memo[ $bible_id ] = $books;

		return $books;
	}

	/**
	 * Retorna a URL pública atual preservando caminho e query string segura.
	 *
	 * @return string
	 */
	private function current_public_request_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $request_uri ) {
			return home_url( user_trailingslashit( $this->seo_base() ) );
		}

		return home_url( $request_uri );
	}

	/**
	 * Retorna um slug público e estável para uma versão bíblica.
	 *
	 * @param int $bible_id ID da Bíblia.
	 * @return string
	 */
	public function bible_version_slug( $bible_id ) {
		$bible_id = absint( $bible_id );
		if ( $bible_id < 1 ) {
			return '';
		}

		foreach ( $this->get_bible_versions() as $version ) {
			if ( (int) $version->id !== $bible_id ) {
				continue;
			}

			$name          = isset( $version->name ) ? trim( (string) $version->name ) : '';
			$language_code = isset( $version->language_code ) ? trim( (string) $version->language_code ) : '';
			$raw_slug      = trim( $name . '-' . $language_code, '-' );
			$slug          = sanitize_title( $raw_slug );

			return $slug ? $slug : 'biblia-' . $bible_id;
		}

		return '';
	}

	/**
	 * Resolve um slug público para o ID da versão bíblica.
	 *
	 * @param string $slug Slug vindo da URL.
	 * @return int
	 */
	public function bible_id_from_version_slug( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return 0;
		}

		foreach ( $this->get_bible_versions() as $version ) {
			$version_id = isset( $version->id ) ? absint( $version->id ) : 0;
			if ( $version_id < 1 ) {
				continue;
			}

			$candidates = array(
				$this->bible_version_slug( $version_id ),
				sanitize_title( isset( $version->name ) ? (string) $version->name : '' ),
				sanitize_title( isset( $version->language_code ) ? (string) $version->language_code : '' ),
				'biblia-' . $version_id,
				(string) $version_id,
			);

			if ( in_array( $slug, array_filter( array_unique( $candidates ) ), true ) ) {
				return $version_id;
			}
		}

		return 0;
	}

	/**
	 * Monta uma URL pública com a versão bíblica no caminho para melhorar SEO.
	 *
	 * @param string $url URL base.
	 * @param int    $bible_id Bíblia selecionada.
	 * @return string
	 */
	public function maybe_add_bible_version_arg_to_url( $url, $bible_id = null ) {
		$url      = $url ? (string) $url : $this->current_public_request_url();
		$bible_id = $bible_id ? absint( $bible_id ) : $this->active_bible_id();
		$slug     = $this->bible_version_slug( $bible_id );

		$url = remove_query_arg( array( 'bdwp_bible_id', 'bdwp_paged' ), $url );

		if ( '' === $slug ) {
			return esc_url_raw( $url );
		}

		$parsed_url = wp_parse_url( $url );
		$path       = isset( $parsed_url['path'] ) ? trim( (string) $parsed_url['path'], '/' ) : '';
		$query      = isset( $parsed_url['query'] ) ? (string) $parsed_url['query'] : '';
		$home_path  = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$base       = trim( $this->seo_base(), '/' );

		if ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = trim( substr( $path, strlen( $home_path ) ), '/' );
		} elseif ( '' !== $home_path && $path === $home_path ) {
			$path = '';
		}

		if ( $base && ( $path === $base || 0 === strpos( $path, $base . '/' ) ) ) {
			$tail = trim( substr( $path, strlen( $base ) ), '/' );

			if ( preg_match( '#^versao/[^/]+/?(.*)$#', $tail, $matches ) ) {
				$tail = isset( $matches[1] ) ? trim( (string) $matches[1], '/' ) : '';
			}

			$new_path = trim( $base . '/versao/' . $slug . ( $tail ? '/' . $tail : '' ), '/' );
			$new_url  = home_url( user_trailingslashit( $new_path ) );

			if ( '' !== $query ) {
				parse_str( $query, $query_args );
				unset( $query_args['bdwp_bible_id'], $query_args['bdwp_paged'] );
				$safe_query_args = array();

				foreach ( wp_unslash( $query_args ) as $query_key => $query_value ) {
					if ( is_scalar( $query_value ) ) {
						$safe_query_args[ sanitize_key( $query_key ) ] = sanitize_text_field( (string) $query_value );
					}
				}

				if ( ! empty( $safe_query_args ) ) {
					$new_url = add_query_arg( $safe_query_args, $new_url );
				}
			}

			return esc_url_raw( $new_url );
		}

		// Fallback para URLs externas ao caminho da Bíblia.
		return esc_url_raw( add_query_arg( 'bdwp_bible_id', $bible_id, $url ) );
	}

	/**
	 * URL explícita para trocar a versão bíblica sem alterar a configuração global do site.
	 *
	 * @param int    $bible_id Bíblia desejada.
	 * @param string $base_url URL base.
	 * @return string
	 */
	public function bible_version_switch_url( $bible_id, $base_url = '' ) {
		$bible_id = absint( $bible_id );
		$base_url = $base_url ? (string) $base_url : $this->current_public_request_url();
		$base_url = remove_query_arg( array( 'bdwp_bible_id', 'bdwp_paged' ), $base_url );

		if ( $bible_id < 1 || ! BDWP70_Activator::bible_version_exists( $bible_id ) ) {
			$bible_id = $this->site_active_bible_id();
		}

		return $this->maybe_add_bible_version_arg_to_url( $base_url, $bible_id );
	}

	/**
	 * Renderiza o seletor público da versão da Bíblia como botão em cascata.
	 *
	 * @param int    $active_id Bíblia selecionada no contexto atual.
	 * @param string $base_url URL usada para gerar os links de troca.
	 * @param string $context Contexto visual.
	 * @return string
	 */
	public function render_version_switcher( $active_id = null, $base_url = '', $context = 'inline' ) {
		$versions = $this->get_bible_versions();
		if ( empty( $versions ) ) {
			return '';
		}

		$active_id    = $active_id ? absint( $active_id ) : $this->active_bible_id();
		$active_label = $this->get_bible_version_label( $active_id );
		$context      = sanitize_html_class( $context ? $context : 'inline' );

		if ( count( $versions ) < 2 || 'hero' === $context ) {
			$badge_label = 'hero' === $context ? __( 'Installed version', 'estudobiblico-biblia-digital' ) : __( 'Active translation', 'estudobiblico-biblia-digital' );
			return '<div class="bdwp70__version-badge bdwp70__version-badge--' . esc_attr( $context ) . '" aria-label="' . esc_attr__( 'Installed Bible version', 'estudobiblico-biblia-digital' ) . '"><span>' . esc_html( $badge_label ) . '</span><strong>' . esc_html( $active_label ) . '</strong></div>';
		}

		$base_url = $base_url ? (string) $base_url : $this->current_public_request_url();

		ob_start();
		?>
		<details class="bdwp70__version-switcher bdwp70__version-switcher--<?php echo esc_attr( $context ); ?>">
			<summary>
				<span class="bdwp70__version-switcher-label"><?php esc_html_e( 'Bible translation', 'estudobiblico-biblia-digital' ); ?></span>
				<strong class="bdwp70__version-switcher-current"><?php echo esc_html( $active_label ); ?></strong>
				<span class="bdwp70__version-switcher-caret" aria-hidden="true">▾</span>
			</summary>
			<div class="bdwp70__version-switcher-menu" role="list">
				<?php foreach ( $versions as $version ) : ?>
					<?php
					$version_id    = isset( $version->id ) ? absint( $version->id ) : 0;
					$version_name  = isset( $version->name ) ? trim( (string) $version->name ) : '';
					$language_code = isset( $version->language_code ) ? trim( (string) $version->language_code ) : '';
					$item_label    = trim( $version_name . ( $language_code ? ' (' . $language_code . ')' : '' ) );
					/* translators: %d: Bible version ID. */
					$item_label = $item_label ? $item_label : sprintf( __( 'Bible #%d', 'estudobiblico-biblia-digital' ), $version_id );
					$item_url   = $this->bible_version_switch_url( $version_id, $base_url );
					$is_current = $version_id === $active_id;
					?>
					<a class="bdwp70__version-switcher-item <?php echo $is_current ? 'is-current' : ''; ?>" href="<?php echo esc_url( $item_url ); ?>" role="listitem" <?php echo $is_current ? 'aria-current="true"' : ''; ?>>
						<span><?php echo esc_html( $version_name ? $version_name : $item_label ); ?></span>
						<?php if ( $language_code ) : ?>
							<small><?php echo esc_html( $language_code ); ?></small>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</details>
		<?php
		return (string) ob_get_clean();
	}

	public function get_bible_versions() {
		return BDWP70_Activator::get_bible_versions();
	}

	/**
	 * Returns the Bible chosen in this site settings, ignoring public URL/query parameters.
	 * This is used by widgets so a sidebar does not change language because of a page request.
	 *
	 * @return int
	 */
	public function site_active_bible_id() {
		static $cached_id = null;

		if ( null !== $cached_id ) {
			return (int) $cached_id;
		}

		$default_id = BDWP70_Activator::get_active_bible_id();
		$id         = absint( get_option( self::OPTION_ACTIVE, $default_id ) );

		if ( $id < 1 || ! BDWP70_Activator::bible_version_exists( $id ) ) {
			$id = $default_id;
			update_option( self::OPTION_ACTIVE, $id );
		}

		$cached_id = $id;
		return (int) $cached_id;
	}

	public function active_bible_id() {
		$site_active  = $this->site_active_bible_id();
		$version_slug = get_query_var( 'bdwp_versao_slug' );
		$id           = $version_slug ? $this->bible_id_from_version_slug( sanitize_title( $version_slug ) ) : 0;

		$legacy_bible_id = get_query_var( 'bdwp_bible_id' );
		if ( $id < 1 && $legacy_bible_id ) {
			$id = absint( $legacy_bible_id );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public Bible selector is read-only and sanitized.
		if ( $id < 1 && isset( $_GET['bdwp_bible_id'] ) ) {
			$id = absint( wp_unslash( $_GET['bdwp_bible_id'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $id < 1 || ! BDWP70_Activator::bible_version_exists( $id ) ) {
			$id = $site_active;
		}

		return $id;
	}

	public function get_bible_version_label( $bible_id = null ) {
		$bible_id = $bible_id ? absint( $bible_id ) : $this->active_bible_id();
		foreach ( $this->get_bible_versions() as $version ) {
			if ( (int) $version->id === $bible_id ) {
				return trim( (string) $version->name . ' (' . (string) $version->language_code . ')' );
			}
		}
		return __( 'No active Bible', 'estudobiblico-biblia-digital' );
	}

	public function book_seq_from_slug( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return 0;
		}
		$books = $this->get_books();
		foreach ( $books as $book ) {
			$desc_slug = sanitize_title( $book->livro_desc );
			$abbr_slug = sanitize_title( $book->livro );
			if ( $slug === $desc_slug || $slug === $abbr_slug ) {
				return (int) $book->livro_seq;
			}
		}
		return 0;
	}

	public function book_slug_from_seq( $seq, $books = null ) {
		if ( null === $books ) {
			$books = $this->get_books();
		}
		foreach ( $books as $book ) {
			if ( (int) $book->livro_seq === (int) $seq ) {
				return sanitize_title( $book->livro_desc );
			}
		}
		return '';
	}

	public function chapter_url( $book_seq, $chapter, $books = null, $bible_id = null ) {
		$slug = $this->book_slug_from_seq( $book_seq, $books );
		if ( ! $slug || ! $chapter ) {
			return get_permalink();
		}
		$url = home_url( user_trailingslashit( $this->seo_base() . '/' . $slug . '/' . absint( $chapter ) ) );
		return $this->maybe_add_bible_version_arg_to_url( $url, $bible_id );
	}

	public function verse_url( $book_seq, $chapter, $verse, $books = null, $bible_id = null ) {
		$slug = $this->book_slug_from_seq( $book_seq, $books );
		if ( ! $slug || ! $chapter || ! $verse ) {
			return get_permalink();
		}
		$url = home_url( user_trailingslashit( $this->seo_base() . '/' . $slug . '/' . absint( $chapter ) . '/' . absint( $verse ) ) );
		return $this->maybe_add_bible_version_arg_to_url( $url, $bible_id );
	}

	private function query_verses( $state, $per_page ) {
		global $wpdb;
		$table      = BDWP70_Activator::verses_table();
		$bible_id   = ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : $this->active_bible_id();
		$book       = 99 !== (int) $state['book'] ? absint( $state['book'] ) : 99;
		$chapter    = ! empty( $state['chapter'] ) ? absint( $state['chapter'] ) : 0;
		$search     = trim( (string) $state['search'] );
		$is_chapter = '' === $search && 99 !== $book && $chapter > 0;

		if ( $is_chapter ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT MIN(id) AS id, testamento, livroseq, livro, capitulo, versiculo, MIN(palavra) AS palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND livroseq = %d AND capitulo = %d GROUP BY livroseq, capitulo, versiculo ORDER BY livroseq ASC, capitulo ASC, versiculo ASC',
					$bible_id,
					$book,
					$chapter
				)
			);
			$total = is_array( $items ) ? count( $items ) : 0;

			return array(
				'items'       => is_array( $items ) ? $items : array(),
				'total'       => $total,
				'per_page'    => $total,
				'total_pages' => 1,
				'mode'        => 'chapter',
			);
		}

		$paged     = max( 1, (int) $state['paged'] );
		$offset    = ( $paged - 1 ) * $per_page;
		$like      = '%' . $wpdb->esc_like( $search ) . '%';
		$cache_key = 'bdwp70_qv_' . md5(
			wp_json_encode(
				array(
					'bible_id' => $bible_id,
					'book'     => $book,
					'chapter'  => $chapter,
					'search'   => $search,
					'exact'    => ! empty( $state['exact'] ) ? 1 : 0,
					'match'    => isset( $state['match'] ) ? (string) $state['match'] : 'any',
					'paged'    => $paged,
					'per_page' => $per_page,
				)
			)
		);
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['items'], $cached['total'], $cached['mode'] ) ) {
			return $cached;
		}

		/*
		 * Caminho rapido pelo indice FULLTEXT.
		 *
		 * `palavra LIKE '%termo%'` tem curinga a esquerda e por isso varre a
		 * tabela inteira, duas vezes por busca (COUNT e pagina), com ate 12
		 * condicoes encadeadas. O indice palavra_fulltext ja existe no esquema e
		 * nao era usado por consulta nenhuma.
		 *
		 * Diferenca de comportamento, assumida de proposito: o FULLTEXT casa
		 * palavras (com prefixo, via `*`), enquanto o LIKE casa qualquer trecho.
		 * Buscar "amor" continua achando "amoroso", mas nao "desamor". Quando o
		 * FULLTEXT nao devolve nada, o caminho LIKE abaixo roda como antes, de
		 * modo que nenhuma busca passa a terminar em zero resultado por causa
		 * desta mudanca.
		 */
		$usou_fulltext = false;
		$expressao_ft  = $this->fulltext_boolean_expression(
			$search,
			! empty( $state['exact'] ),
			isset( $state['match'] ) ? (string) $state['match'] : 'any'
		);

		if ( '' !== $expressao_ft && $this->verses_have_fulltext_index() ) {
			$total_ft = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT CONCAT(livroseq, ':', capitulo, ':', versiculo)) FROM `" . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND ( %d = 99 OR livroseq = %d ) AND ( %d = 0 OR capitulo = %d ) AND MATCH(palavra) AGAINST(%s IN BOOLEAN MODE)',
					$bible_id,
					$book,
					$book,
					$chapter,
					$chapter,
					$expressao_ft
				)
			);

			if ( null !== $total_ft && (int) $total_ft > 0 ) {
				$itens_ft = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT MIN(id) AS id, testamento, livroseq, livro, capitulo, versiculo, MIN(palavra) AS palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND ( %d = 99 OR livroseq = %d ) AND ( %d = 0 OR capitulo = %d ) AND MATCH(palavra) AGAINST(%s IN BOOLEAN MODE) GROUP BY livroseq, capitulo, versiculo ORDER BY livroseq ASC, capitulo ASC, versiculo ASC LIMIT %d OFFSET %d',
						$bible_id,
						$book,
						$book,
						$chapter,
						$chapter,
						$expressao_ft,
						$per_page,
						$offset
					)
				);

				if ( is_array( $itens_ft ) ) {
					$total         = (int) $total_ft;
					$items         = $itens_ft;
					$usou_fulltext = true;
				}
			}
		}

		if ( $usou_fulltext ) {
			// Resultados ja obtidos pelo indice acima; nada a fazer aqui.
			$total = (int) $total;
		} elseif ( '' !== $search && ! empty( $state['exact'] ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT CONCAT(livroseq, ':', capitulo, ':', versiculo)) FROM `" . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND ( %d = 99 OR livroseq = %d ) AND ( %d = 0 OR capitulo = %d ) AND palavra LIKE %s',
					$bible_id,
					$book,
					$book,
					$chapter,
					$chapter,
					$like
				)
			);
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT MIN(id) AS id, testamento, livroseq, livro, capitulo, versiculo, MIN(palavra) AS palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND ( %d = 99 OR livroseq = %d ) AND ( %d = 0 OR capitulo = %d ) AND palavra LIKE %s GROUP BY livroseq, capitulo, versiculo ORDER BY livroseq ASC, capitulo ASC, versiculo ASC LIMIT %d OFFSET %d',
					$bible_id,
					$book,
					$book,
					$chapter,
					$chapter,
					$like,
					$per_page,
					$offset
				)
			);
		} else {
			$words = preg_split( '/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY );
			$words = array_slice( is_array( $words ) ? $words : array(), 0, 12 );
			$likes = array();
			for ( $i = 0; $i < 12; $i++ ) {
				$likes[] = isset( $words[ $i ] ) ? '%' . $wpdb->esc_like( $words[ $i ] ) . '%' : '';
			}

			if ( 'all' === $state['match'] ) {
				$total = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT CONCAT(livroseq, ':', capitulo, ':', versiculo)) FROM `" . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND ( %d = 99 OR livroseq = %d ) AND ( %d = 0 OR capitulo = %d ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s )',
						$bible_id,
						$book,
						$book,
						$chapter,
						$chapter,
						$likes[0],
						$likes[0],
						$likes[1],
						$likes[1],
						$likes[2],
						$likes[2],
						$likes[3],
						$likes[3],
						$likes[4],
						$likes[4],
						$likes[5],
						$likes[5],
						$likes[6],
						$likes[6],
						$likes[7],
						$likes[7],
						$likes[8],
						$likes[8],
						$likes[9],
						$likes[9],
						$likes[10],
						$likes[10],
						$likes[11],
						$likes[11]
					)
				);
				$items = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT MIN(id) AS id, testamento, livroseq, livro, capitulo, versiculo, MIN(palavra) AS palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND ( %d = 99 OR livroseq = %d ) AND ( %d = 0 OR capitulo = %d ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) AND ( %s = "" OR palavra LIKE %s ) GROUP BY livroseq, capitulo, versiculo ORDER BY livroseq ASC, capitulo ASC, versiculo ASC LIMIT %d OFFSET %d',
						$bible_id,
						$book,
						$book,
						$chapter,
						$chapter,
						$likes[0],
						$likes[0],
						$likes[1],
						$likes[1],
						$likes[2],
						$likes[2],
						$likes[3],
						$likes[3],
						$likes[4],
						$likes[4],
						$likes[5],
						$likes[5],
						$likes[6],
						$likes[6],
						$likes[7],
						$likes[7],
						$likes[8],
						$likes[8],
						$likes[9],
						$likes[9],
						$likes[10],
						$likes[10],
						$likes[11],
						$likes[11],
						$per_page,
						$offset
					)
				);
			} else {
				$total = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT CONCAT(livroseq, ':', capitulo, ':', versiculo)) FROM `" . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND ( %d = 99 OR livroseq = %d ) AND ( %d = 0 OR capitulo = %d ) AND ( %s = "" OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s )',
						$bible_id,
						$book,
						$book,
						$chapter,
						$chapter,
						$search,
						$likes[0],
						$likes[1],
						$likes[2],
						$likes[3],
						$likes[4],
						$likes[5],
						$likes[6],
						$likes[7],
						$likes[8],
						$likes[9],
						$likes[10],
						$likes[11]
					)
				);
				$items = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT MIN(id) AS id, testamento, livroseq, livro, capitulo, versiculo, MIN(palavra) AS palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE published = 1 AND bible_id = %d AND ( %d = 99 OR livroseq = %d ) AND ( %d = 0 OR capitulo = %d ) AND ( %s = "" OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s OR palavra LIKE %s ) GROUP BY livroseq, capitulo, versiculo ORDER BY livroseq ASC, capitulo ASC, versiculo ASC LIMIT %d OFFSET %d',
						$bible_id,
						$book,
						$book,
						$chapter,
						$chapter,
						$search,
						$likes[0],
						$likes[1],
						$likes[2],
						$likes[3],
						$likes[4],
						$likes[5],
						$likes[6],
						$likes[7],
						$likes[8],
						$likes[9],
						$likes[10],
						$likes[11],
						$per_page,
						$offset
					)
				);
			}
		}

		$result = array(
			'items'       => is_array( $items ) ? $items : array(),
			'total'       => $total,
			'per_page'    => $per_page,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'mode'        => 'search',
		);
		set_transient( $cache_key, $result, $this->search_cache_ttl() );
		return $result;
	}

	/**
	 * Tempo de vida do cache de resultados de busca.
	 *
	 * O texto biblico so muda em importacao, e a importacao ja limpa os
	 * transients por BDWP70_Activator::clear_runtime_caches(). Cinco minutos
	 * expiravam antes de qualquer reaproveitamento util.
	 *
	 * @return int Segundos.
	 */
	private function search_cache_ttl() {
		$ttl = (int) apply_filters( 'bdwp70_search_cache_ttl', 12 * HOUR_IN_SECONDS );

		return max( 60, $ttl );
	}

	/**
	 * Informa se a tabela de versiculos tem indice FULLTEXT em `palavra`.
	 *
	 * O esquema declara palavra_fulltext, mas tabelas criadas por versoes
	 * antigas podem nao te-lo. Sem a verificacao, MATCH ... AGAINST derrubaria
	 * a busca inteira com erro de SQL.
	 *
	 * @return bool
	 */
	private function verses_have_fulltext_index() {
		static $memo = null;

		if ( null !== $memo ) {
			return $memo;
		}

		$cached = get_transient( 'bdwp70_verses_fulltext_ok' );
		if ( false !== $cached ) {
			$memo = ( '1' === (string) $cached );
			return $memo;
		}

		global $wpdb;
		$table = BDWP70_Activator::verses_table();
		$rows  = $wpdb->get_results( 'SHOW INDEX FROM `' . esc_sql( $table ) . '`' );
		$tem   = false;

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$tipo   = isset( $row->Index_type ) ? strtoupper( (string) $row->Index_type ) : '';
				$coluna = isset( $row->Column_name ) ? strtolower( (string) $row->Column_name ) : '';
				if ( 'FULLTEXT' === $tipo && 'palavra' === $coluna ) {
					$tem = true;
					break;
				}
			}
		}

		set_transient( 'bdwp70_verses_fulltext_ok', $tem ? '1' : '0', 12 * HOUR_IN_SECONDS );
		$memo = $tem;

		return $memo;
	}

	/**
	 * Monta a expressao BOOLEAN MODE da busca, ou '' quando o indice nao serve.
	 *
	 * Devolve string vazia — e o LIKE assume — quando o filtro desliga o
	 * recurso, quando o termo fica vazio depois de remover os operadores, ou
	 * quando alguma palavra e menor que o token minimo do indice (palavras
	 * abaixo dele simplesmente nao existem no FULLTEXT e sumiriam do resultado).
	 *
	 * @param string $search Termo digitado.
	 * @param bool   $exact  Busca por frase exata.
	 * @param string $match_mode 'all' para exigir todas as palavras, 'any' caso contrario.
	 * @return string Expressao para AGAINST(), ou '' para usar o LIKE.
	 */
	private function fulltext_boolean_expression( $search, $exact, $match_mode ) {
		if ( ! apply_filters( 'bdwp70_use_fulltext_search', true ) ) {
			return '';
		}

		$search = trim( (string) $search );
		if ( '' === $search ) {
			return '';
		}

		// Remove os operadores do BOOLEAN MODE: o termo e dado do visitante.
		$limpo = preg_replace( '/[+\-><\(\)~*"@]+/u', ' ', $search );
		$limpo = trim( (string) preg_replace( '/\s+/u', ' ', (string) $limpo ) );
		if ( '' === $limpo ) {
			return '';
		}

		$palavras = preg_split( '/\s+/u', $limpo, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $palavras ) || empty( $palavras ) ) {
			return '';
		}
		$palavras = array_slice( $palavras, 0, 12 );

		$minimo = (int) apply_filters( 'bdwp70_fulltext_min_token', 3 );
		foreach ( $palavras as $palavra ) {
			$tamanho = function_exists( 'mb_strlen' ) ? mb_strlen( $palavra, 'UTF-8' ) : strlen( $palavra );
			if ( $tamanho < $minimo ) {
				return '';
			}
		}

		if ( $exact ) {
			return '"' . implode( ' ', $palavras ) . '"';
		}

		$prefixo = ( 'all' === $match_mode ) ? '+' : '';
		$termos  = array();
		foreach ( $palavras as $palavra ) {
			$termos[] = $prefixo . $palavra . '*';
		}

		return implode( ' ', $termos );
	}

	public function get_chapter_counts( $bible_id = null ) {
		global $wpdb;
		$table    = BDWP70_Activator::verses_table();
		$bible_id = $bible_id ? absint( $bible_id ) : $this->active_bible_id();

		/*
		 * Agregado GROUP BY sobre a tabela inteira de versiculos para obter 66
		 * numeros que so mudam na importacao. Mesmo coberto pelo indice
		 * bible_published_ref, nao ha motivo para repeti-lo a cada pagina.
		 * Invalidado por BDWP70_Activator::clear_runtime_caches().
		 */
		static $memo = array();
		if ( isset( $memo[ $bible_id ] ) ) {
			return $memo[ $bible_id ];
		}

		$chave  = 'bdwp70_chapters_counts_' . $bible_id;
		$cached = get_transient( $chave );
		if ( is_array( $cached ) ) {
			$memo[ $bible_id ] = $cached;
			return $cached;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT livroseq, MAX(capitulo) AS chapters FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d AND published = 1 AND livroseq BETWEEN 1 AND 66 GROUP BY livroseq',
				$bible_id
			)
		);
		$map  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$map[ (int) $row->livroseq ] = (int) $row->chapters;
			}
		}

		if ( ! empty( $map ) ) {
			set_transient( $chave, $map, 12 * HOUR_IN_SECONDS );
		}

		$memo[ $bible_id ] = $map;

		return $map;
	}

	public function book_name_from_seq( $books, $seq, $fallback = '' ) {
		foreach ( $books as $book ) {
			if ( (int) $book->livro_seq === (int) $seq ) {
				return (string) $book->livro_desc;
			}
		}
		return '' !== $fallback ? $fallback : (string) $seq;
	}

	public function get_single_verse( $book_seq, $chapter, $verse, $bible_id = null ) {
		global $wpdb;
		$table    = BDWP70_Activator::verses_table();
		$bible_id = $bible_id ? absint( $bible_id ) : $this->active_bible_id();

		// A validação da rota e a descrição SEO pedem o mesmo versículo na mesma
		// requisição; sem memo seriam duas consultas idênticas.
		static $memo = array();
		$chave       = $bible_id . ':' . (int) $book_seq . ':' . (int) $chapter . ':' . (int) $verse;
		if ( array_key_exists( $chave, $memo ) ) {
			return $memo[ $chave ];
		}

		return $memo[ $chave ] = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d AND published = 1 AND livroseq = %d AND capitulo = %d AND versiculo = %d ORDER BY id ASC LIMIT 1',
				$bible_id,
				(int) $book_seq,
				(int) $chapter,
				(int) $verse
			)
		);
	}

	/**
	 * Gets one random published verse.
	 *
	 * @param int $book_seq Optional book sequence. Zero means all books.
	 * @return object|null
	 */
	public function get_random_verse( $book_seq = 0, $bible_id = null ) {
		$bible_id = $bible_id ? absint( $bible_id ) : $this->active_bible_id();
		$book_seq = ( $book_seq > 0 && $book_seq <= 66 ) ? (int) $book_seq : 0;

		return $this->get_random_grouped_verse( $bible_id, $book_seq );
	}


	/**
	 * Resolve o livro informado no shortcode por número, abreviação, slug ou nome.
	 *
	 * @param string|int $value Valor informado no shortcode.
	 * @param int        $bible_id Bíblia usada como base.
	 * @return int
	 */
	private function resolve_shortcode_book_seq( $value, $bible_id = null ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return 0;
		}

		if ( is_numeric( $value ) ) {
			$seq = absint( $value );
			return $seq >= 1 && $seq <= 66 ? $seq : 0;
		}

		$slug  = sanitize_title( $value );
		$books = $this->get_books( $bible_id ? absint( $bible_id ) : $this->site_active_bible_id() );
		foreach ( $books as $book ) {
			if ( $slug === sanitize_title( $book->livro_desc ) || $slug === sanitize_title( $book->livro ) ) {
				return (int) $book->livro_seq;
			}
		}

		// Shortcodes publicados com a grafia antiga continuam funcionando.
		$aliases = $this->legacy_book_slugs();
		if ( ! empty( $aliases[ $slug ] ) ) {
			$novo = sanitize_title( (string) $aliases[ $slug ] );
			foreach ( $books as $book ) {
				if ( $novo === sanitize_title( $book->livro_desc ) ) {
					return (int) $book->livro_seq;
				}
			}
		}

		return 0;
	}

	/**
	 * Seleciona um capítulo aleatório publicado.
	 *
	 * @param int $bible_id Bíblia.
	 * @param int $book_seq Livro opcional.
	 * @return object|null
	 */
	public function get_random_chapter( $bible_id = null, $book_seq = 0 ) {
		$bible_id = $bible_id ? absint( $bible_id ) : $this->site_active_bible_id();
		$book_seq = ( $book_seq > 0 && $book_seq <= 66 ) ? (int) $book_seq : 0;

		return $this->get_random_grouped_chapter( $bible_id, $book_seq );
	}


	/**
	 * Retorna os versículos de um capítulo.
	 *
	 * @param int $book_seq Livro.
	 * @param int $chapter Capítulo.
	 * @param int $bible_id Bíblia.
	 * @param int $limit Limite opcional.
	 * @return array
	 */
	public function get_chapter_verses( $book_seq, $chapter, $bible_id = null, $limit = 0 ) {
		global $wpdb;
		$table    = BDWP70_Activator::verses_table();
		$bible_id = $bible_id ? absint( $bible_id ) : $this->site_active_bible_id();
		$limit    = absint( $limit );

		if ( $limit > 0 ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT MIN(id) AS id, testamento, livroseq, livro, capitulo, versiculo, MIN(palavra) AS palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d AND published = 1 AND livroseq = %d AND capitulo = %d GROUP BY livroseq, capitulo, versiculo ORDER BY versiculo ASC LIMIT %d',
					$bible_id,
					(int) $book_seq,
					(int) $chapter,
					$limit
				)
			);

			return is_array( $items ) ? $items : array();
		}

		$items = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT MIN(id) AS id, testamento, livroseq, livro, capitulo, versiculo, MIN(palavra) AS palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d AND published = 1 AND livroseq = %d AND capitulo = %d GROUP BY livroseq, capitulo, versiculo ORDER BY versiculo ASC',
				$bible_id,
				(int) $book_seq,
				(int) $chapter
			)
		);
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Retorna um versículo diário dos Salmos, estável durante o dia e vinculado à Bíblia ativa do site.
	 *
	 * Este método foi criado para integração com widgets do tema. Ele usa site_active_bible_id()
	 * por padrão, evitando que parâmetros de URL alterem o idioma/tradução do rodapé.
	 *
	 * @param array $args Argumentos opcionais: bible_id, book_seq, option_key e date.
	 * @return array|null Dados do versículo ou null quando não houver dados.
	 */
	public function get_daily_psalm_verse( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			is_array( $args ) ? $args : array(),
			array(
				'bible_id'   => 0,
				'book_seq'   => 19,
				'option_key' => 'bdwp70_daily_psalm_verse_state',
				'date'       => '',
			)
		);

		$bible_id   = ! empty( $args['bible_id'] ) ? absint( $args['bible_id'] ) : $this->site_active_bible_id();
		$book_seq   = ! empty( $args['book_seq'] ) ? absint( $args['book_seq'] ) : 19;
		$option_key = sanitize_key( (string) $args['option_key'] );

		if ( $book_seq < 1 || $book_seq > 66 ) {
			$book_seq = 19;
		}

		if ( '' === $option_key ) {
			$option_key = 'bdwp70_daily_psalm_verse_state';
		}

		if ( $bible_id < 1 || ! BDWP70_Activator::bible_version_exists( $bible_id ) ) {
			$bible_id = $this->site_active_bible_id();
		}

		$table = BDWP70_Activator::verses_table();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT CONCAT(livroseq, ':', capitulo, ':', versiculo)) FROM `" . esc_sql( $table ) . '` WHERE bible_id = %d AND published = 1 AND livroseq = %d',
				$bible_id,
				$book_seq
			)
		);

		if ( $count < 1 ) {
			return null;
		}

		$today = ! empty( $args['date'] ) ? sanitize_text_field( (string) $args['date'] ) : current_time( 'Y-m-d' );
		$hash  = md5( $bible_id . ':' . $book_seq . ':' . $count );
		$state = get_option( $option_key, array() );

		if ( ! is_array( $state ) || empty( $state['date'] ) || $today !== $state['date'] || empty( $state['hash'] ) || $hash !== $state['hash'] ) {
			$previous_offset = isset( $state['offset'] ) ? absint( $state['offset'] ) : -1;
			$offset          = wp_rand( 0, $count - 1 );

			if ( $count > 1 && $offset === $previous_offset ) {
				$offset = ( $offset + 1 ) % $count;
			}

			$state = array(
				'date'   => $today,
				'hash'   => $hash,
				'offset' => $offset,
			);
			update_option( $option_key, $state, false );
		}

		$offset = isset( $state['offset'] ) ? absint( $state['offset'] ) : 0;
		if ( $offset >= $count ) {
			$offset = 0;
		}

		$verse = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT MIN(id) AS id, testamento, livroseq, livro, capitulo, versiculo, MIN(palavra) AS palavra, published, hits FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d AND published = 1 AND livroseq = %d GROUP BY livroseq, capitulo, versiculo ORDER BY capitulo ASC, versiculo ASC LIMIT 1 OFFSET %d',
				$bible_id,
				$book_seq,
				$offset
			)
		);

		if ( ! $verse ) {
			return null;
		}

		$books     = $this->get_books( $bible_id );
		$book_name = $this->book_name_from_seq( $books, (int) $verse->livroseq, (string) $verse->livro );
		$reference = $book_name . ' ' . (int) $verse->capitulo . ':' . (int) $verse->versiculo;
		$url       = $this->verse_url( (int) $verse->livroseq, (int) $verse->capitulo, (int) $verse->versiculo, $books );

		$data = array(
			'verse'         => $verse,
			'text'          => trim( (string) $verse->palavra ),
			'reference'     => $reference,
			'url'           => $url,
			'bible_id'      => $bible_id,
			'version_label' => $this->get_bible_version_label( $bible_id ),
		);

		/**
		 * Permite ajustar o versículo diário dos Salmos antes da exibição por temas/widgets.
		 *
		 * @param array $data Dados do versículo.
		 * @param array $args Argumentos recebidos.
		 */
		return apply_filters( 'bdwp70_daily_psalm_verse', $data, $args );
	}

	public function breadcrumb_items( $state, $books ) {
		$items = array(
			array(
				'label' => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => $this->display_title(),
				'url'   => $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() ) ), ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : null ),
			),
		);

		$book_name = $this->book_name_from_seq( $books, (int) $state['book'], '' );
		if ( $book_name ) {
			$items[] = array(
				'label' => $book_name,
				'url'   => $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() . '/' . $this->book_slug_from_seq( (int) $state['book'], $books ) ) ), ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : null ),
			);
		}
		if ( ! empty( $state['chapter'] ) && $book_name ) {
			$items[] = array(
				/* translators: %d: Chapter number. */
				'label' => sprintf( __( 'Chapter %d', 'estudobiblico-biblia-digital' ), (int) $state['chapter'] ),
				'url'   => $this->chapter_url( (int) $state['book'], (int) $state['chapter'], $books, ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : null ),
			);
		}
		if ( ! empty( $state['verse'] ) && $book_name ) {
			$items[] = array(
				/* translators: %d: verse number. */
				'label' => sprintf( __( 'Verse %d', 'estudobiblico-biblia-digital' ), (int) $state['verse'] ),
				'url'   => $this->verse_url( (int) $state['book'], (int) $state['chapter'], (int) $state['verse'], $books, ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : null ),
			);
		}
		if ( 'search' === $state['mode'] ) {
			$items[] = array(
				'label' => __( 'Search results', 'estudobiblico-biblia-digital' ),
				'url'   => '',
			);
		}
		return $items;
	}

	public function render_breadcrumbs( $state, $books ) {
		$items = $this->breadcrumb_items( $state, $books );
		if ( count( $items ) < 1 ) {
			return '';
		}
		ob_start();
		?>
		<nav class="bdwp70__breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'estudobiblico-biblia-digital' ); ?>">
			<?php foreach ( $items as $index => $item ) : ?>
				<?php
				if ( $index > 0 ) :
					?>
					<span class="bdwp70__breadcrumb-sep" aria-hidden="true">›</span><?php endif; ?>
				<?php if ( ! empty( $item['url'] ) && $index < count( $items ) - 1 ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
				<?php else : ?>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renderiza um seletor compacto de capítulos para o topo do leitor.
	 *
	 * Mantém a navegação por capítulos disponível sem criar uma coluna interna
	 * que estreite o texto bíblico em temas que já possuem sidebar própria.
	 *
	 * @param array $state Estado atual do leitor.
	 * @param array $books Lista de livros.
	 * @param array $chapter_counts Total de capítulos por livro.
	 * @return string
	 */
	public function render_chapter_picker( $state, $books, $chapter_counts ) {
		$book      = ! empty( $state['book'] ) ? absint( $state['book'] ) : 0;
		$chapter   = ! empty( $state['chapter'] ) ? absint( $state['chapter'] ) : 0;
		$book_name = $this->book_name_from_seq( $books, $book, '' );
		$chapters  = isset( $chapter_counts[ $book ] ) ? absint( $chapter_counts[ $book ] ) : 0;

		if ( ! $book || ! $book_name || $chapters < 1 ) {
			return '';
		}

		$active_bible  = ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : $this->active_bible_id();
		$book_url      = $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() . '/' . $this->book_slug_from_seq( $book, $books ) ) ), $active_bible );
		$version_label = $this->get_bible_version_label( $active_bible );
		$label         = $chapter ? sprintf( /* translators: 1: Bible book name, 2: Chapter number. */ __( 'Select a chapter of %1$s; current chapter %2$d', 'estudobiblico-biblia-digital' ), $book_name, $chapter ) : sprintf( /* translators: %s: Bible book name. */ __( 'Select a chapter of %s', 'estudobiblico-biblia-digital' ), $book_name );

		ob_start();
		?>
		<details class="bdwp70__chapter-picker">
			<summary class="bdwp70__chapter-picker-toggle" aria-label="<?php echo esc_attr( $label ); ?>">
				<span class="bdwp70__chapter-picker-icon" aria-hidden="true">▦</span>
				<span><?php esc_html_e( 'Chapters', 'estudobiblico-biblia-digital' ); ?></span>
			</summary>
			<div class="bdwp70__chapter-picker-panel">
				<header class="bdwp70__chapter-picker-head">
					<strong><?php echo esc_html( $book_name ); ?></strong>
					<?php if ( $chapter ) : ?>
						<span><?php /* translators: %d: Chapter number. */ echo esc_html( sprintf( __( 'Chapter %d', 'estudobiblico-biblia-digital' ), $chapter ) ); ?></span>
					<?php endif; ?>
				</header>
				<nav class="bdwp70__chapter-picker-grid" aria-label="<?php esc_attr_e( 'All chapters of the book', 'estudobiblico-biblia-digital' ); ?>">
					<?php for ( $i = 1; $i <= $chapters; $i++ ) : ?>
						<a class="<?php echo $chapter === $i ? 'is-current' : ''; ?>" href="<?php echo esc_url( $this->chapter_url( $book, $i, $books, $active_bible ) ); ?>"
						<?php
						if ( $chapter === $i ) :
							?>
							aria-current="page"<?php endif; ?>><?php echo esc_html( (string) $i ); ?></a>
					<?php endfor; ?>
				</nav>
				<div class="bdwp70__chapter-picker-meta">
					<span><?php esc_html_e( 'Translation', 'estudobiblico-biblia-digital' ); ?></span>
					<em><?php echo esc_html( $version_label ); ?></em>
				</div>
				<a class="bdwp70__chapter-picker-all" href="<?php echo esc_url( $book_url ); ?>"><?php /* translators: %s: Bible book name. */ echo esc_html( sprintf( __( 'View the chapter list of %s', 'estudobiblico-biblia-digital' ), $book_name ) ); ?></a>
			</div>
		</details>
		<?php
		return (string) ob_get_clean();
	}


	public function render_reader_sidebar( $state, $books, $chapter_counts ) {
		$book      = (int) $state['book'];
		$book_name = $this->book_name_from_seq( $books, $book, '' );
		if ( ! $book || ! $book_name ) {
			return '';
		}

		$chapter        = ! empty( $state['chapter'] ) ? absint( $state['chapter'] ) : 0;
		$active_bible   = ! empty( $state['bible_id'] ) ? absint( $state['bible_id'] ) : $this->active_bible_id();
		$chapters_label = $chapter ? sprintf( /* translators: 1: Bible book name, 2: Chapter number. */ __( '%1$s, chapter %2$d', 'estudobiblico-biblia-digital' ), $book_name, $chapter ) : sprintf( /* translators: %s: Bible book name. */ __( 'Chapters of %s', 'estudobiblico-biblia-digital' ), $book_name );
		$chapter_card   = $this->render_chapter_navigation_card( $state, $books, $chapter_counts );

		ob_start();
		?>
		<aside class="bdwp70__reader-sidebar" aria-label="<?php echo esc_attr( $chapters_label ); ?>">
			<?php echo $chapter_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escaped in render_chapter_navigation_card(). ?>
			<section class="bdwp70__side-card bdwp70__side-card--search">
				<?php
				echo wp_kses(
					$this->render_search_form(
						array(
							'title'       => __( 'Search the Bible', 'estudobiblico-biblia-digital' ),
							'placeholder' => __( 'Word or phrase', 'estudobiblico-biblia-digital' ),
							'button'      => __( 'Go', 'estudobiblico-biblia-digital' ),
							'show_book'   => 1,
							'bible_id'    => $active_bible,
						)
					),
					function_exists( 'bdwp70_allowed_form_html' ) ? bdwp70_allowed_form_html() : wp_kses_allowed_html( 'post' )
				);
				?>
			</section>
		</aside>
		<?php
		return (string) ob_get_clean();
	}

	public function admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_bdwp70-settings' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );

		wp_register_style( 'bdwp70-admin', false, array(), BDWP70_VERSION );
		wp_enqueue_style( 'bdwp70-admin' );
		$admin_css = <<<'CSS'
.bdwp70-admin-wrap{max-width:1240px}.bdwp70-admin-tabs{margin-top:18px}.bdwp70-admin-panel{background:#fff;border:1px solid #dcdcde;border-top:0;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.bdwp70-admin-hero{display:flex;align-items:center;justify-content:space-between;gap:20px;background:linear-gradient(135deg,#f7fbff,#fff);border:1px solid #d7e4f5;border-radius:12px;padding:22px;margin:0 0 20px}.bdwp70-admin-hero h2{margin:0 0 8px}.bdwp70-admin-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:18px 0}.bdwp70-admin-card,.bdwp70-admin-box,.bdwp70-shortcode-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.bdwp70-admin-card__label,.bdwp70-admin-card__description{display:block;color:#646970}.bdwp70-admin-card__value{display:block;font-size:22px;margin:8px 0;color:#1d2327}.bdwp70-admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.bdwp70-admin-actions{display:flex;flex-wrap:wrap;gap:8px}.bdwp70-admin-form{max-width:820px}.bdwp70-admin-form--wide{max-width:1040px}.bdwp70-admin-table{max-width:1040px;margin-top:14px}.bdwp70-admin-list{list-style:disc;margin-left:2em}.bdwp70-admin-badge{display:inline-block;background:#e7f7ed;color:#006b2d;border:1px solid #9ce0b8;border-radius:999px;padding:2px 8px;font-weight:600}.bdwp70-title-image-preview{margin-bottom:.75rem}.bdwp70-title-image-preview img{max-width:320px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff}.bdwp70-shortcode-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.bdwp70-shortcode-card__header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.bdwp70-shortcode-card__header h3{margin:0 0 10px}.bdwp70-shortcode-card pre{background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:10px;white-space:pre-wrap;overflow:auto}.bdwp70-shortcode-attributes{margin-top:12px}.bdwp70-copy-shortcode.is-copied{border-color:#008a20;color:#008a20}.bdwp70-admin-notice{max-width:980px;padding:10px 12px}@media (max-width:960px){.bdwp70-admin-cards,.bdwp70-admin-grid,.bdwp70-shortcode-grid{grid-template-columns:1fr}.bdwp70-admin-hero{display:block}.bdwp70-admin-hero .button{margin-top:12px}}
CSS;
		wp_add_inline_style( 'bdwp70-admin', $admin_css );

		$script = <<<'JS'
jQuery(function($){
  var frame;
  $('#bdwp70_select_title_image').on('click', function(e){
    e.preventDefault();
    if(frame){ frame.open(); return; }
    frame = wp.media({ title: bdwp70AdminL10n.selectImage, button: { text: bdwp70AdminL10n.useImage }, multiple: false });
    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      $('#bdwp70_title_image_id').val(parseInt(attachment.id, 10) || 0);
      $('#bdwp70_title_image_preview').empty().append(
        $('<img>', { src: attachment.url || '', alt: '' }).css({ maxWidth: '320px', height: 'auto', border: '1px solid #ccd0d4', padding: '4px', background: '#fff' })
      );
      $('#bdwp70_remove_title_image').show();
    });
    frame.open();
  });
  $('#bdwp70_remove_title_image').on('click', function(e){
    e.preventDefault();
    $('#bdwp70_title_image_id').val('0');
    $('#bdwp70_title_image_preview').empty();
    $(this).hide();
  });
  $('.bdwp70-copy-shortcode').on('click', function(e){
    e.preventDefault();
    var button = $(this);
    var text = button.attr('data-copy') || '';
    function done(){
      var original = button.data('original-text') || button.text();
      button.data('original-text', original).addClass('is-copied').text('Copiado!');
      window.setTimeout(function(){ button.removeClass('is-copied').text(original); }, 1600);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, done);
      return;
    }
    var temp = $('<textarea>').val(text).css({position:'fixed',left:'-9999px',top:'0'}).appendTo('body');
    temp[0].select();
    try { document.execCommand('copy'); } catch(err) {}
    temp.remove();
    done();
  });
});
JS;
		wp_add_inline_script(
			'jquery',
			'var bdwp70AdminL10n = ' . wp_json_encode(
				array(
					'selectImage' => __( 'Select Bible image', 'estudobiblico-biblia-digital' ),
					'useImage'    => __( 'Use this image', 'estudobiblico-biblia-digital' ),
				)
			) . ';'
		);
		wp_add_inline_script( 'jquery', $script );
	}

	public function admin_menu() {
		add_options_page(
			__( 'Bíblia Digital', 'estudobiblico-biblia-digital' ),
			__( 'Bíblia Digital', 'estudobiblico-biblia-digital' ),
			'manage_options',
			'bdwp70-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Telas em que o aviso de configuração pendente pode aparecer.
	 *
	 * Diretriz 11: o aviso é restrito às telas onde o administrador pode agir
	 * sobre ele — a própria página do plugin e a lista de plugins, logo após a
	 * ativação. Nas demais telas do painel nada é exibido.
	 *
	 * @return bool
	 */
	private function screen_allows_setup_notice() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->id ) ) {
			return false;
		}

		return in_array( $screen->id, array( 'settings_page_bdwp70-settings', 'plugins', 'plugins-network' ), true );
	}

	/**
	 * Chave do user meta que registra a dispensa do aviso de configuração.
	 */
	const META_DISMISSED_SETUP = 'bdwp70_dismissed_setup_notice';

	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 1.2.0: a língua de origem passou a ser inglês. Sem pacote de idioma nem
		// tradução personalizada, o site passa a exibir o plugin em inglês.
		$locale = determine_locale();
		if ( get_option( 'bdwp70_source_strings_en' ) && ! self::has_translation_for_locale( $locale ) && $this->screen_allows_setup_notice() ) {
			echo '<div class="notice notice-info"><p>';
			echo wp_kses(
				sprintf(
					/* translators: 1: locale code, 2: link to translate.wordpress.org, 3: link to the Frontend Translation tab. */
					__( 'Bíblia Digital is written in English, and translations come from translate.wordpress.org. There is no translation for %1$s yet, so the plugin is shown in English. You can %2$s or upload your own translation on the %3$s tab.', 'estudobiblico-biblia-digital' ),
					'<code>' . esc_html( $locale ) . '</code>',
					'<a href="' . esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . self::TEXT_DOMAIN . '/' ) . '">' . esc_html__( 'help translate it', 'estudobiblico-biblia-digital' ) . '</a>',
					'<a href="' . esc_url( add_query_arg( 'tab', 'translation', admin_url( 'options-general.php?page=bdwp70-settings' ) ) ) . '">' . esc_html__( 'Frontend Translation', 'estudobiblico-biblia-digital' ) . '</a>'
				),
				array(
					'a'    => array( 'href' => array() ),
					'code' => array(),
				)
			);
			echo '</p></div>';
		}

		// Aviso único da correção de nomes de livros feita na atualização.
		$nomes_corrigidos = get_option( 'bdwp70_book_names_fixed_notice' );
		if ( is_array( $nomes_corrigidos ) && $nomes_corrigidos ) {
			delete_option( 'bdwp70_book_names_fixed_notice' );
			$pares = array();
			foreach ( $nomes_corrigidos as $troca ) {
				if ( isset( $troca['de'], $troca['para'] ) ) {
					$pares[ $troca['de'] . ' → ' . $troca['para'] ] = true;
				}
			}
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Bíblia Digital:', 'estudobiblico-biblia-digital' ) . '</strong> ';
			/* translators: %s: list of corrected book names. */
			echo esc_html( sprintf( __( 'book name spelling corrected: %s. The old Colossians and Thessalonians URLs redirect to the new ones.', 'estudobiblico-biblia-digital' ), implode( ', ', array_keys( $pares ) ) ) );
			echo '</p></div>';
		}

		if ( ! $this->screen_allows_setup_notice() ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), self::META_DISMISSED_SETUP, true ) ) {
			return;
		}

		if ( 'pending' !== get_option( BDWP70_Activator::OPTION_STATUS ) || BDWP70_Activator::count_verses( $this->site_active_bible_id() ) >= 1 ) {
			return;
		}

		$url = admin_url( 'options-general.php?page=bdwp70-settings' );

		$dismiss_url = wp_nonce_url(
			add_query_arg( 'bdwp70_dismiss_notice', 'setup', $url ),
			'bdwp70_dismiss_setup_notice'
		);

		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a> &middot; <a href="%5$s">%6$s</a></p></div>',
			esc_html__( 'Bíblia Digital:', 'estudobiblico-biblia-digital' ),
			esc_html__( 'has been activated. To get started, import a Bible as a ZIP file containing books.csv and verses.csv. The end user/site administrator is responsible for the license of the imported Bible version.', 'estudobiblico-biblia-digital' ),
			esc_url( $url ),
			esc_html__( 'Import now', 'estudobiblico-biblia-digital' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'estudobiblico-biblia-digital' )
		);
	}

	/**
	 * Registra a dispensa do aviso de configuração para o usuário atual.
	 *
	 * Altera estado, portanto exige nonce e capability. A dispensa é por usuário,
	 * de modo que um administrador não silencia o aviso para os demais.
	 *
	 * @return void
	 */
	public function maybe_dismiss_setup_notice() {
		if ( ! isset( $_GET['bdwp70_dismiss_notice'] ) ) {
			return;
		}

		if ( 'setup' !== sanitize_key( wp_unslash( $_GET['bdwp70_dismiss_notice'] ) ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'bdwp70_dismiss_setup_notice' );

		update_user_meta( get_current_user_id(), self::META_DISMISSED_SETUP, 1 );

		// Destino explícito: nunca derivado de REQUEST_URI.
		$referer  = wp_get_referer();
		$fallback = admin_url( 'options-general.php?page=bdwp70-settings' );

		wp_safe_redirect( $referer ? remove_query_arg( array( 'bdwp70_dismiss_notice', '_wpnonce' ), $referer ) : $fallback );
		exit;
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'overview'    => __( 'Overview', 'estudobiblico-biblia-digital' ),
			'import'      => __( 'Import Bible', 'estudobiblico-biblia-digital' ),
			'versions'    => __( 'Bible Versions', 'estudobiblico-biblia-digital' ),
			'translation' => __( 'Frontend Translation', 'estudobiblico-biblia-digital' ),
			'shortcodes'  => __( 'Shortcodes', 'estudobiblico-biblia-digital' ),
			'seo'         => __( 'SEO and URLs', 'estudobiblico-biblia-digital' ),
			'diagnostic'  => __( 'Diagnostics', 'estudobiblico-biblia-digital' ),
		);

		$current_tab_raw = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$current_tab     = is_string( $current_tab_raw ) ? sanitize_key( wp_unslash( $current_tab_raw ) ) : 'overview';
		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'overview';
		}

		$active_bible = $this->site_active_bible_id();
		$versions     = $this->get_bible_versions();
		$books        = BDWP70_Activator::count_books( $active_bible );
		$verses       = BDWP70_Activator::count_verses( $active_bible );
		$status       = get_option( BDWP70_Activator::OPTION_STATUS, 'pending' );
		$error        = get_option( BDWP70_Activator::OPTION_ERROR, '' );
		$progress     = get_option( BDWP70_Activator::OPTION_PROGRESS, '' );
		$base         = $this->seo_base();
		?>
		<div class="wrap bdwp70-admin-wrap">
			<h1><?php esc_html_e( 'Bíblia Digital', 'estudobiblico-biblia-digital' ); ?></h1>
			<?php $this->render_admin_page_notices( $error ); ?>

			<h2 class="nav-tab-wrapper bdwp70-admin-tabs" aria-label="<?php esc_attr_e( 'Bíblia Digital plugin sections', 'estudobiblico-biblia-digital' ); ?>">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<?php
					$tab_url = add_query_arg(
						array(
							'page' => 'bdwp70-settings',
							'tab'  => $tab_key,
						),
						admin_url( 'options-general.php' )
					);
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div class="bdwp70-admin-panel bdwp70-admin-panel--<?php echo esc_attr( $current_tab ); ?>">
				<?php
				switch ( $current_tab ) {
					case 'import':
						$this->render_admin_tab_import();
						break;
					case 'versions':
						$this->render_admin_tab_versions( $versions, $active_bible );
						break;
					case 'translation':
						$this->render_admin_tab_translation();
						break;
					case 'shortcodes':
						$this->render_admin_tab_shortcodes();
						break;
					case 'seo':
						$this->render_admin_tab_seo( $versions, $active_bible, $base );
						break;
					case 'diagnostic':
						$this->render_admin_tab_diagnostic( $versions, $active_bible, $books, $verses, $status, $progress, $error, $base );
						break;
					case 'overview':
					default:
						$this->render_admin_tab_overview( $versions, $active_bible, $books, $verses, $status, $progress, $error, $base );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_admin_page_notices( $error = '' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin status query args only display notices after nonce-protected redirects.
		if ( isset( $_GET['bdwp70_reimported'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['bdwp70_reimported'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Bible data imported successfully.', 'estudobiblico-biblia-digital' ) . '</p></div>';
		} elseif ( isset( $_GET['bdwp70_reimported'] ) && '0' === sanitize_text_field( wp_unslash( $_GET['bdwp70_reimported'] ) ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The import could not be completed.', 'estudobiblico-biblia-digital' ) . ' ' . esc_html( $error ) . '</p></div>';
		}

		if ( isset( $_GET['bdwp70_version_deleted'] ) ) {
			$resultado = sanitize_key( wp_unslash( $_GET['bdwp70_version_deleted'] ) );
			$nome      = isset( $_GET['bdwp70_version_name'] ) ? sanitize_text_field( wp_unslash( $_GET['bdwp70_version_name'] ) ) : '';
			$avisos    = array(
				/* translators: %s: Bible version name. */
				'ok'       => array( 'success', sprintf( __( 'Version "%s" was deleted, along with its books and verses. Its URLs now redirect to the active Bible.', 'estudobiblico-biblia-digital' ), $nome ) ),
				'active'   => array( 'error', __( 'The site\'s active Bible cannot be deleted. Activate another version above, then delete this one.', 'estudobiblico-biblia-digital' ) ),
				'confirm'  => array( 'error', __( 'Check the confirmation box next to the button to delete the version.', 'estudobiblico-biblia-digital' ) ),
				'builtin'  => array( 'error', __( 'This version is part of the plugin and cannot be deleted.', 'estudobiblico-biblia-digital' ) ),
				'notfound' => array( 'error', __( 'Version not found. It may have already been deleted.', 'estudobiblico-biblia-digital' ) ),
			);
			if ( isset( $avisos[ $resultado ] ) ) {
				echo '<div class="notice notice-' . esc_attr( $avisos[ $resultado ][0] ) . ' is-dismissible"><p>' . esc_html( $avisos[ $resultado ][1] ) . '</p></div>';
			}
		}

		if ( isset( $_GET['bdwp70_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['bdwp70_saved'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved. Permalinks have been refreshed.', 'estudobiblico-biblia-digital' ) . '</p></div>';
		}

		if ( isset( $_GET['bdwp70_translation_uploaded'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['bdwp70_translation_uploaded'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Translation file(s) uploaded successfully.', 'estudobiblico-biblia-digital' ) . '</p></div>';
		} elseif ( isset( $_GET['bdwp70_translation_uploaded'] ) && '0' === sanitize_text_field( wp_unslash( $_GET['bdwp70_translation_uploaded'] ) ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The translation files could not be uploaded.', 'estudobiblico-biblia-digital' ) . ' ' . esc_html( $error ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Builds settings-page URLs for the given tabs.
	 *
	 * Keeps add_query_arg() out of the markup so each attribute stays a single
	 * short PHP block. The returned URLs are unescaped on purpose: escaping is
	 * applied with esc_url() at the point of output.
	 *
	 * @param string[] $tabs Tab slugs.
	 * @return array<string,string> Tab slug => URL.
	 */
	private function admin_tab_urls( $tabs ) {
		$urls = array();

		foreach ( $tabs as $tab ) {
			$urls[ $tab ] = add_query_arg(
				array(
					'page' => 'bdwp70-settings',
					'tab'  => $tab,
				),
				admin_url( 'options-general.php' )
			);
		}

		return $urls;
	}

	private function render_admin_tab_overview( $versions, $active_bible, $books, $verses, $status, $progress, $error, $base ) {
		$active_label = $this->admin_bible_version_label( $active_bible, $versions );

		// Settings-tab URLs are built here so the markup below keeps one short
		// PHP block per attribute. Escaping stays late, at the output point.
		$tab_urls = $this->admin_tab_urls( array( 'import', 'versions', 'shortcodes', 'translation' ) );
		?>
		<div class="bdwp70-admin-hero">
			<div>
				<h2><?php esc_html_e( 'Bíblia Digital enterprise dashboard', 'estudobiblico-biblia-digital' ); ?></h2>
				<p><?php esc_html_e( 'Manage import, Bible versions, frontend translation, and shortcodes in separate sections. The plugin handles persistent features; the theme should handle only the visual presentation.', 'estudobiblico-biblia-digital' ); ?></p>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( $tab_urls['import'] ); ?>"><?php esc_html_e( 'Import Bible', 'estudobiblico-biblia-digital' ); ?></a>
		</div>

		<div class="bdwp70-admin-cards bdwp70-admin-cards--summary">
			<?php $this->render_admin_metric_card( __( 'Active Bible', 'estudobiblico-biblia-digital' ), $active_label, __( 'Used as the site default.', 'estudobiblico-biblia-digital' ) ); ?>
			<?php $this->render_admin_metric_card( __( 'Imported versions', 'estudobiblico-biblia-digital' ), number_format_i18n( is_array( $versions ) ? count( $versions ) : 0 ), __( 'Translations available in the database.', 'estudobiblico-biblia-digital' ) ); ?>
			<?php $this->render_admin_metric_card( __( 'Books in the active Bible', 'estudobiblico-biblia-digital' ), number_format_i18n( $books ), __( 'Expected: 66, in the traditional Protestant order.', 'estudobiblico-biblia-digital' ) ); ?>
			<?php $this->render_admin_metric_card( __( 'Verses in the active Bible', 'estudobiblico-biblia-digital' ), number_format_i18n( $verses ), __( 'Bible text stored locally.', 'estudobiblico-biblia-digital' ) ); ?>
		</div>

		<div class="bdwp70-admin-grid">
			<div class="bdwp70-admin-box">
				<h3><?php esc_html_e( 'Quick links', 'estudobiblico-biblia-digital' ); ?></h3>
				<p><?php esc_html_e( 'Go straight to the most used areas of the plugin.', 'estudobiblico-biblia-digital' ); ?></p>
				<p class="bdwp70-admin-actions">
					<a class="button" href="<?php echo esc_url( $tab_urls['import'] ); ?>"><?php esc_html_e( 'Import Bible', 'estudobiblico-biblia-digital' ); ?></a>
					<a class="button" href="<?php echo esc_url( $tab_urls['versions'] ); ?>"><?php esc_html_e( 'Versions', 'estudobiblico-biblia-digital' ); ?></a>
					<a class="button" href="<?php echo esc_url( $tab_urls['shortcodes'] ); ?>"><?php esc_html_e( 'Shortcodes', 'estudobiblico-biblia-digital' ); ?></a>
					<a class="button" href="<?php echo esc_url( $tab_urls['translation'] ); ?>"><?php esc_html_e( 'Frontend Translation', 'estudobiblico-biblia-digital' ); ?></a>
				</p>
			</div>
			<div class="bdwp70-admin-box">
				<h3><?php esc_html_e( 'Technical summary', 'estudobiblico-biblia-digital' ); ?></h3>
				<table class="widefat striped">
					<tbody>
						<tr><th><?php esc_html_e( 'Status', 'estudobiblico-biblia-digital' ); ?></th><td><?php echo esc_html( $status ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Base/slug', 'estudobiblico-biblia-digital' ); ?></th><td><code><?php echo esc_html( $base ); ?></code></td></tr>
						<tr><th><?php esc_html_e( 'Last import', 'estudobiblico-biblia-digital' ); ?></th><td><?php echo esc_html( get_option( BDWP70_Activator::OPTION_IMPORTED, __( 'not recorded', 'estudobiblico-biblia-digital' ) ) ); ?></td></tr>
						<?php
						if ( $progress ) :
							?>
							<tr><th><?php esc_html_e( 'Progress', 'estudobiblico-biblia-digital' ); ?></th><td><?php echo esc_html( $progress ); ?></td></tr><?php endif; ?>
						<?php
						if ( $error ) :
							?>
							<tr><th><?php esc_html_e( 'Last error', 'estudobiblico-biblia-digital' ); ?></th><td><?php echo esc_html( $error ); ?></td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function render_admin_metric_card( $label, $value, $description = '' ) {
		?>
		<div class="bdwp70-admin-card">
			<span class="bdwp70-admin-card__label"><?php echo esc_html( $label ); ?></span>
			<strong class="bdwp70-admin-card__value"><?php echo esc_html( $value ); ?></strong>
			<?php if ( $description ) : ?>
				<span class="bdwp70-admin-card__description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_admin_tab_import() {
		?>
		<h2><?php esc_html_e( 'Import Bible', 'estudobiblico-biblia-digital' ); ?></h2>
		<div class="notice notice-info inline bdwp70-admin-notice">
			<p><strong><?php esc_html_e( 'Import the Bible data first.', 'estudobiblico-biblia-digital' ); ?></strong> <?php esc_html_e( 'Upload a ZIP file containing books.csv and verses.csv in UTF-8. The plugin creates a new Bible and makes it active after the import.', 'estudobiblico-biblia-digital' ); ?></p>
			<p><?php esc_html_e( 'The public package does not distribute copyrighted Bible texts. The site administrator is responsible for the license of the imported translation.', 'estudobiblico-biblia-digital' ); ?></p>
		</div>

		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bdwp70-admin-form">
			<?php wp_nonce_field( 'bdwp70_upload_bible' ); ?>
			<input type="hidden" name="action" value="bdwp70_upload_bible">
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="bdwp70_bible_name"><?php esc_html_e( 'Bible name', 'estudobiblico-biblia-digital' ); ?></label></th><td><input type="text" class="regular-text" id="bdwp70_bible_name" name="bdwp70_bible_name" placeholder="<?php echo esc_attr__( 'E.g.: King James Version', 'estudobiblico-biblia-digital' ); ?>" required></td></tr>
				<tr><th scope="row"><label for="bdwp70_bible_lang"><?php esc_html_e( 'Language', 'estudobiblico-biblia-digital' ); ?></label></th><td><input type="text" class="regular-text" id="bdwp70_bible_lang" name="bdwp70_bible_lang" placeholder="<?php echo esc_attr__( 'E.g.: pt-BR, en-US, es-ES', 'estudobiblico-biblia-digital' ); ?>" required></td></tr>
				<tr><th scope="row"><label for="bdwp70_bible_file"><?php esc_html_e( 'ZIP file', 'estudobiblico-biblia-digital' ); ?></label></th><td><input type="file" id="bdwp70_bible_file" name="bdwp70_bible_file" accept=".zip,application/zip" required></td></tr>
			</table>
			<?php submit_button( __( 'Upload and import Bible', 'estudobiblico-biblia-digital' ), 'primary', 'submit', false ); ?>
		</form>

		<div class="bdwp70-admin-box bdwp70-admin-box--spec">
			<h3><?php esc_html_e( 'How to prepare the files', 'estudobiblico-biblia-digital' ); ?></h3>

			<p>
				<strong><?php esc_html_e( 'Ready-made templates:', 'estudobiblico-biblia-digital' ); ?></strong>
				<a class="button button-secondary" href="<?php echo esc_url( BDWP70_URL . 'assets/templates/books.csv' ); ?>" download="books.csv"><?php esc_html_e( 'Download books.csv', 'estudobiblico-biblia-digital' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( BDWP70_URL . 'assets/templates/verses.csv' ); ?>" download="verses.csv"><?php esc_html_e( 'Download verses.csv', 'estudobiblico-biblia-digital' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'The books.csv template already lists the 66 books with their Portuguese names and abbreviations; replace them with the names from your translation if it is in another language. The verses.csv template has only sample rows: replace them with the full text.', 'estudobiblico-biblia-digital' ); ?></p>

			<ol class="bdwp70-admin-list">
				<li>
					<strong>books.csv</strong> — <?php esc_html_e( 'the first line is the header, followed by one book per line, all 66 books, numbered from 1 (Genesis) to 66 (Revelation) in the traditional Protestant order:', 'estudobiblico-biblia-digital' ); ?>
					<pre>livro_seq,livro,livro_desc
1,Gen,Genesis
2,Exod,Exodus
…
66,Rev,Revelation</pre>
				</li>
				<li>
					<strong>verses.csv</strong> — <?php esc_html_e( 'the first line is the header, followed by one verse per line. Put the verse text in quotes; quotes inside the text are written doubled.', 'estudobiblico-biblia-digital' ); ?>
					<pre>testamento,livroseq,livro,capitulo,versiculo,palavra
OT,1,Gen,1,1,"Text of Genesis 1:1."
NT,43,John,3,16,"Text with commas, and ""doubled"" quotes."</pre>
				</li>
				<li><?php esc_html_e( 'Save both files in UTF-8, with a comma as the separator. In Excel, use "CSV UTF-8 (Comma delimited)". In LibreOffice, choose the Unicode (UTF-8) character set, the comma separator, and quotes as the text delimiter.', 'estudobiblico-biblia-digital' ); ?></li>
				<li><?php esc_html_e( 'Compress both files into a ZIP. They can be at the root of the ZIP or inside a single folder.', 'estudobiblico-biblia-digital' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'Before importing, check', 'estudobiblico-biblia-digital' ); ?></h4>
			<ul class="bdwp70-admin-list">
				<li><?php esc_html_e( 'The name in livro_desc is shown on the site and also becomes the book URL: "Colossians" produces /colossians/. Review the spelling before importing, because correcting it later changes the URL.', 'estudobiblico-biblia-digital' ); ?></li>
				<li><?php esc_html_e( 'Each import creates a new version and makes it active. To replace a translation, import the new one and delete the old one on the Versions tab.', 'estudobiblico-biblia-digital' ); ?></li>
				<li><?php esc_html_e( 'The text is imported without formatting (HTML is removed), with up to 5,000 characters per verse.', 'estudobiblico-biblia-digital' ); ?></li>
				<li><?php esc_html_e( 'The testamento column can hold AT/NT, OT/NT, or be empty: the testament is derived from the book number.', 'estudobiblico-biblia-digital' ); ?></li>
				<li><?php esc_html_e( 'Enter the language in the pt-BR, en-US, or es-ES format.', 'estudobiblico-biblia-digital' ); ?></li>
				<li><?php esc_html_e( 'Deuterocanonical books (Tobit, Judith, Maccabees, and others) are not supported.', 'estudobiblico-biblia-digital' ); ?></li>
				<li><?php esc_html_e( 'Limits: ZIP up to 50 MB and up to 25 MB uncompressed. The server needs the PHP zip extension.', 'estudobiblico-biblia-digital' ); ?></li>
			</ul>
		</div>
		<?php
	}

	private function render_admin_tab_versions( $versions, $active_bible ) {
		?>
		<h2><?php esc_html_e( 'Bible Versions', 'estudobiblico-biblia-digital' ); ?></h2>
		<p><?php esc_html_e( 'Manage the site\'s active Bible and review the imported versions. A visitor selecting a version on the frontend does not change this global active Bible.', 'estudobiblico-biblia-digital' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bdwp70-admin-form">
			<?php wp_nonce_field( 'bdwp70_save_settings' ); ?>
			<input type="hidden" name="action" value="bdwp70_save_settings">
			<input type="hidden" name="bdwp70_settings_context" value="versions">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bdwp70_active_bible_id"><?php esc_html_e( 'Site\'s active Bible', 'estudobiblico-biblia-digital' ); ?></label></th>
					<td>
						<?php if ( ! empty( $versions ) ) : ?>
							<select id="bdwp70_active_bible_id" name="bdwp70_active_bible_id">
								<?php foreach ( $versions as $version ) : ?>
									<option value="<?php echo esc_attr( (int) $version->id ); ?>" <?php selected( $active_bible, (int) $version->id ); ?>><?php echo esc_html( $this->admin_version_option_label( $version ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Used as the default in shortcodes, widgets, search, and URLs when the visitor does not select another version.', 'estudobiblico-biblia-digital' ); ?></p>
						<?php else : ?>
							<p class="description"><strong><?php esc_html_e( 'No Bible imported.', 'estudobiblico-biblia-digital' ); ?></strong> <?php esc_html_e( 'Use the Import Bible tab to upload a ZIP containing books.csv and verses.csv.', 'estudobiblico-biblia-digital' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save active Bible', 'estudobiblico-biblia-digital' ), 'primary', 'submit', false ); ?>
		</form>

		<p class="description"><strong><?php esc_html_e( 'Deleting a version', 'estudobiblico-biblia-digital' ); ?></strong> — <?php esc_html_e( 'removes its record, books, and verses, and cannot be undone. URLs of the deleted version redirect to the same passage in the active Bible. To replace a translation, import the new one, activate it, and then delete the old one.', 'estudobiblico-biblia-digital' ); ?></p>

		<table class="widefat striped bdwp70-admin-table">
			<thead><tr><th><?php esc_html_e( 'ID', 'estudobiblico-biblia-digital' ); ?></th><th><?php esc_html_e( 'Name', 'estudobiblico-biblia-digital' ); ?></th><th><?php esc_html_e( 'Language', 'estudobiblico-biblia-digital' ); ?></th><th><?php esc_html_e( 'Books', 'estudobiblico-biblia-digital' ); ?></th><th><?php esc_html_e( 'Verses', 'estudobiblico-biblia-digital' ); ?></th><th><?php esc_html_e( 'Status', 'estudobiblico-biblia-digital' ); ?></th><th><?php esc_html_e( 'Delete', 'estudobiblico-biblia-digital' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! empty( $versions ) ) : ?>
					<?php foreach ( $versions as $version ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) (int) $version->id ); ?></code></td>
							<td><?php echo esc_html( (string) $version->name ); ?></td>
							<td><?php echo esc_html( (string) $version->language_code ); ?></td>
							<td><?php echo esc_html( number_format_i18n( BDWP70_Activator::count_books( (int) $version->id ) ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( BDWP70_Activator::count_verses( (int) $version->id ) ) ); ?></td>
							<td><?php echo ( (int) $version->id === (int) $active_bible ) ? '<span class="bdwp70-admin-badge">' . esc_html__( 'Active', 'estudobiblico-biblia-digital' ) . '</span>' : esc_html__( 'Imported', 'estudobiblico-biblia-digital' ); ?></td>
							<td>
								<?php if ( ! empty( $version->is_builtin ) ) : ?>
									&mdash;
								<?php elseif ( (int) $version->id === (int) $active_bible ) : ?>
									<span class="description"><?php esc_html_e( 'Activate another version to be able to delete this one.', 'estudobiblico-biblia-digital' ); ?></span>
								<?php else : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'bdwp70_delete_version_' . (int) $version->id ); ?>
										<input type="hidden" name="action" value="bdwp70_delete_version">
										<input type="hidden" name="bdwp70_bible_id" value="<?php echo esc_attr( (string) (int) $version->id ); ?>">
										<label><input type="checkbox" name="bdwp70_confirm_delete" value="1" required> <?php esc_html_e( 'I confirm', 'estudobiblico-biblia-digital' ); ?></label>
										<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete version', 'estudobiblico-biblia-digital' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No version imported yet.', 'estudobiblico-biblia-digital' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'The groundwork for public labels, public visibility, and multiple versions in the same language stays in the plugin. This step does not change rewrites/permalinks.', 'estudobiblico-biblia-digital' ); ?></p>
		<?php
	}

	private function render_admin_tab_translation() {
		?>
		<h2><?php esc_html_e( 'Frontend Translation', 'estudobiblico-biblia-digital' ); ?></h2>
		<div class="notice notice-info inline bdwp70-admin-notice">
			<p><strong><?php esc_html_e( 'This step comes after importing the Bible.', 'estudobiblico-biblia-digital' ); ?></strong> <?php esc_html_e( '.pot, .po, and .mo files translate the plugin strings on the frontend. They do not replace the imported Bible text.', 'estudobiblico-biblia-digital' ); ?></p>
		</div>
		<?php $this->render_translation_upload_form( 'settings' ); ?>
		<?php $this->render_translation_files_status(); ?>
		<?php
	}

	private function render_admin_tab_shortcodes() {
		$groups = $this->admin_shortcodes_reference();
		?>
		<h2><?php esc_html_e( 'Shortcodes', 'estudobiblico-biblia-digital' ); ?></h2>
		<p><?php esc_html_e( 'Use this section as a quick reference for pages, widgets, and HTML blocks. The old aliases have been kept for compatibility.', 'estudobiblico-biblia-digital' ); ?></p>
		<div class="bdwp70-shortcode-grid">
			<?php foreach ( $groups as $group ) : ?>
				<section class="bdwp70-shortcode-card">
					<header class="bdwp70-shortcode-card__header">
						<h3><?php echo esc_html( $group['title'] ); ?></h3>
						<code><?php echo esc_html( $group['shortcode'] ); ?></code>
					</header>
					<p><?php echo esc_html( $group['description'] ); ?></p>
					<?php if ( ! empty( $group['aliases'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Aliases:', 'estudobiblico-biblia-digital' ); ?></strong> <?php echo wp_kses_post( implode( ' ', array_map( array( $this, 'admin_code_inline' ), $group['aliases'] ) ) ); ?></p>
					<?php endif; ?>
					<div class="bdwp70-shortcode-examples">
						<p><strong><?php esc_html_e( 'Basic example:', 'estudobiblico-biblia-digital' ); ?></strong></p>
						<pre><code><?php echo esc_html( $group['basic'] ); ?></code></pre>
						<button type="button" class="button bdwp70-copy-shortcode" data-copy="<?php echo esc_attr( $group['basic'] ); ?>"><?php esc_html_e( 'Copy shortcode', 'estudobiblico-biblia-digital' ); ?></button>
						<p><strong><?php esc_html_e( 'Advanced example:', 'estudobiblico-biblia-digital' ); ?></strong></p>
						<pre><code><?php echo esc_html( $group['advanced'] ); ?></code></pre>
						<button type="button" class="button bdwp70-copy-shortcode" data-copy="<?php echo esc_attr( $group['advanced'] ); ?>"><?php esc_html_e( 'Copy advanced example', 'estudobiblico-biblia-digital' ); ?></button>
					</div>
					<table class="widefat striped bdwp70-shortcode-attributes">
						<thead><tr><th><?php esc_html_e( 'Attribute', 'estudobiblico-biblia-digital' ); ?></th><th><?php esc_html_e( 'Description', 'estudobiblico-biblia-digital' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $group['attributes'] as $attribute => $description ) : ?>
								<tr><td><code><?php echo esc_html( $attribute ); ?></code></td><td><?php echo esc_html( $description ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			<?php endforeach; ?>
		</div>
		<div class="bdwp70-admin-box">
			<h3><?php esc_html_e( 'Accepted values for random', 'estudobiblico-biblia-digital' ); ?></h3>
			<p><code>random</code> <code>aleatorio</code> <code>aleatório</code> <code>rand</code> <code>*</code></p>
		</div>
		<?php
	}

	private function render_admin_tab_seo( $versions, $active_bible, $base ) {
		$site_title        = $this->display_title();
		$title_image_id    = absint( get_option( self::OPTION_TITLE_IMAGE, 0 ) );
		$title_image_url   = $title_image_id ? wp_get_attachment_image_url( $title_image_id, 'medium' ) : '';
		$quick_cards       = $this->quick_cards( true );
		$bible_studio_url  = get_option( self::OPTION_BIBLE_STUDIO_URL, $this->default_bible_studio_url_template() );
		$quick_card_styles = array(
			'search'  => __( 'Blue / search', 'estudobiblico-biblia-digital' ),
			'reading' => __( 'Yellow / reading', 'estudobiblico-biblia-digital' ),
			'verse'   => __( 'Green / verse', 'estudobiblico-biblia-digital' ),
			'studies' => __( 'Purple / studies', 'estudobiblico-biblia-digital' ),
		);
		?>
		<h2><?php esc_html_e( 'SEO, URLs, and landing elements', 'estudobiblico-biblia-digital' ); ?></h2>
		<p><?php esc_html_e( 'The friendly URL rules were revised to keep old URLs working and to accept URLs with the version in the path. After changing the slug/base, save the Permalinks once.', 'estudobiblico-biblia-digital' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bdwp70-admin-form bdwp70-admin-form--wide">
			<?php wp_nonce_field( 'bdwp70_save_settings' ); ?>
			<input type="hidden" name="action" value="bdwp70_save_settings">
			<input type="hidden" name="bdwp70_settings_context" value="seo">
			<input type="hidden" name="bdwp70_general_settings" value="1">
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="bdwp70_bible_title"><?php esc_html_e( 'Bible title', 'estudobiblico-biblia-digital' ); ?></label></th><td><input type="text" class="regular-text" id="bdwp70_bible_title" name="bdwp70_bible_title" value="<?php echo esc_attr( $site_title ); ?>"><p class="description"><?php esc_html_e( 'Sets the title shown on the frontend and used in the Bible\'s SEO metadata.', 'estudobiblico-biblia-digital' ); ?></p></td></tr>
				<tr>
					<th scope="row"><label for="bdwp70_title_image_id"><?php esc_html_e( 'Image above the title', 'estudobiblico-biblia-digital' ); ?></label></th>
					<td>
						<input type="hidden" id="bdwp70_title_image_id" name="bdwp70_title_image_id" value="<?php echo esc_attr( $title_image_id ); ?>">
						<div id="bdwp70_title_image_preview" class="bdwp70-title-image-preview">
							<?php
							if ( $title_image_url ) :
								?>
								<img src="<?php echo esc_url( $title_image_url ); ?>" alt=""><?php endif; ?>
						</div>
						<button type="button" class="button" id="bdwp70_select_title_image"><?php esc_html_e( 'Select image', 'estudobiblico-biblia-digital' ); ?></button>
						<button type="button" class="button" id="bdwp70_remove_title_image" <?php echo $title_image_url ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove image', 'estudobiblico-biblia-digital' ); ?></button>
						<p class="description"><?php esc_html_e( 'Image shown above the Bible title on the frontend. Not to be confused with the theme logo.', 'estudobiblico-biblia-digital' ); ?></p>
					</td>
				</tr>
				<tr><th scope="row"><label for="bdwp70_seo_base"><?php esc_html_e( 'Bible slug/base', 'estudobiblico-biblia-digital' ); ?></label></th><td><input type="text" class="regular-text" id="bdwp70_seo_base" name="bdwp70_seo_base" value="<?php echo esc_attr( $base ); ?>"><p class="description"><?php esc_html_e( 'Create a page with this slug and add [biblia-digital] to it. After changing this field, go to Settings > Permalinks and click Save Changes once.', 'estudobiblico-biblia-digital' ); ?> <code><?php echo esc_html( home_url( '/' . $base . '/joao/3/16/' ) ); ?></code></p></td></tr>
				<tr><th scope="row"><label for="bdwp70_bible_studio_url"><?php esc_html_e( 'Bíblia Studio URL', 'estudobiblico-biblia-digital' ); ?></label></th><td><input type="text" class="regular-text" id="bdwp70_bible_studio_url" name="bdwp70_bible_studio_url" value="<?php echo esc_attr( $bible_studio_url ); ?>"><p class="description"><?php esc_html_e( 'Legacy external URL template kept for compatibility. It can use {book}, {book_slug}, {chapter}, {verse}, and {reference}.', 'estudobiblico-biblia-digital' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Public credit', 'estudobiblico-biblia-digital' ); ?></th><td><label for="bdwp70_show_credit"><input type="checkbox" id="bdwp70_show_credit" name="bdwp70_show_credit" value="1" <?php checked( 1, (int) get_option( self::OPTION_CREDIT, 0 ) ); ?>> <?php esc_html_e( 'Show a credit linking to Estudo Bíblico on the frontend.', 'estudobiblico-biblia-digital' ); ?></label></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Data on uninstall', 'estudobiblico-biblia-digital' ); ?></th><td><label for="bdwp70_delete_data_on_uninstall"><input type="checkbox" id="bdwp70_delete_data_on_uninstall" name="bdwp70_delete_data_on_uninstall" value="1" <?php checked( 1, (int) get_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, 0 ) ); ?>> <?php esc_html_e( 'Delete tables, options, and imported Bibles when the plugin is deleted.', 'estudobiblico-biblia-digital' ); ?></label><p class="description"><strong><?php esc_html_e( 'Note:', 'estudobiblico-biblia-digital' ); ?></strong> <?php esc_html_e( 'deactivating the plugin never removes data. When the plugin is deleted, data is also kept by default.', 'estudobiblico-biblia-digital' ); ?></p></td></tr>
				<?php
				$sitemap_enabled   = (int) get_option( self::OPTION_SITEMAP_ENABLED, 1 );
				$sitemap_incl_v    = (int) get_option( BDWP70_Sitemap::OPTION_INCL_VERSES, 1 );
				$sitemap_per_page  = absint( get_option( BDWP70_Sitemap::OPTION_PER_PAGE, 2000 ) );
				$sitemap_base      = $this->seo_base();
				$sitemap_index_url = home_url( '/' . $sitemap_base . '-sitemap.xml' );
				$wp_sitemap_url    = home_url( '/wp-sitemap.xml' );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'XML sitemap', 'estudobiblico-biblia-digital' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'XML sitemap', 'estudobiblico-biblia-digital' ); ?></legend>

							<p>
								<label for="bdwp70_sitemap_enabled">
									<input type="hidden" name="bdwp70_sitemap_enabled" value="0">
									<input
										type="checkbox"
										id="bdwp70_sitemap_enabled"
										name="bdwp70_sitemap_enabled"
										value="1"
										<?php checked( 1, $sitemap_enabled ); ?>
									>
									<strong><?php esc_html_e( 'Enable the automatic Bible XML sitemap', 'estudobiblico-biblia-digital' ); ?></strong>
								</label>
							</p>
							<p class="description">
								<?php esc_html_e( 'Generates a dedicated sitemap with the Bible URLs and also integrates with the native WordPress sitemap (/wp-sitemap.xml). It does not include other pages of the site.', 'estudobiblico-biblia-digital' ); ?>
								<?php if ( $sitemap_enabled ) : ?>
									<br><br>
									<strong><?php esc_html_e( 'Dedicated sitemap:', 'estudobiblico-biblia-digital' ); ?></strong>
									<a href="<?php echo esc_url( $sitemap_index_url ); ?>" target="_blank" rel="noopener noreferrer">
										<code><?php echo esc_html( $sitemap_index_url ); ?></code>
									</a>
									<br>
									<strong><?php esc_html_e( 'Native WordPress sitemap:', 'estudobiblico-biblia-digital' ); ?></strong>
									<a href="<?php echo esc_url( $wp_sitemap_url ); ?>" target="_blank" rel="noopener noreferrer">
										<code><?php echo esc_html( $wp_sitemap_url ); ?></code>
									</a>
									<br><em><?php esc_html_e( 'After enabling it for the first time, go to Settings → Permalinks and click Save.', 'estudobiblico-biblia-digital' ); ?></em>
								<?php else : ?>
									<br><?php esc_html_e( 'When enabled, the sitemap will be at:', 'estudobiblico-biblia-digital' ); ?>
									<code><?php echo esc_html( $sitemap_index_url ); ?></code>
								<?php endif; ?>
							</p>

							<br>

							<p>
								<label for="bdwp70_sitemap_include_verses">
									<input type="hidden" name="bdwp70_sitemap_include_verses" value="0">
									<input
										type="checkbox"
										id="bdwp70_sitemap_include_verses"
										name="bdwp70_sitemap_include_verses"
										value="1"
										<?php checked( 1, $sitemap_incl_v ); ?>
										disabled
									>
									<?php esc_html_e( 'Include individual verses in the sitemap', 'estudobiblico-biblia-digital' ); ?>
								</label>
								<span class="description">
									— <?php esc_html_e( 'No effect: verse URLs declare the chapter as canonical and are therefore left out of the sitemap. They keep working as direct links to the verse.', 'estudobiblico-biblia-digital' ); ?>
								</span>
							</p>

							<p>
								<label for="bdwp70_sitemap_per_page">
									<?php esc_html_e( 'Maximum URLs per sitemap file:', 'estudobiblico-biblia-digital' ); ?>
									<input
										type="number"
										id="bdwp70_sitemap_per_page"
										name="bdwp70_sitemap_per_page"
										value="<?php echo esc_attr( $sitemap_per_page ? $sitemap_per_page : 2000 ); ?>"
										min="100"
										max="50000"
										step="100"
										class="small-text"
									>
								</label>
								<span class="description">— <?php esc_html_e( 'Minimum: 100. Maximum: 50,000. Default: 2,000.', 'estudobiblico-biblia-digital' ); ?></span>
							</p>

						</fieldset>

						<?php
						// Diagnóstico do sitemap (Correção 4).
						if ( $sitemap_enabled ) :
							$diag_active_id  = absint( $this->site_active_bible_id() );
							$diag_sitemap_id = 0;

							// Reutiliza a lógica de resolução: ativa com conteúdo ou fallback.
							if ( $diag_active_id > 0 ) {
								$has_books       = (int) BDWP70_Activator::count_books( $diag_active_id ) > 0;
								$has_verses      = (int) BDWP70_Activator::count_verses( $diag_active_id ) > 0;
								$diag_sitemap_id = ( $has_books && $has_verses ) ? $diag_active_id : 0;
							}

							global $wpdb;
							if ( 0 === $diag_sitemap_id ) {
								$vt = BDWP70_Activator::verses_table();
								$bt = BDWP70_Activator::books_table();
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
								$diag_sitemap_id = (int) $wpdb->get_var(
									// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
									'SELECT v.bible_id FROM `' . esc_sql( $vt ) . '` v
									 INNER JOIN `' . esc_sql( $bt ) . '` b ON b.bible_id = v.bible_id
									 WHERE v.published = 1 AND b.published = 1
									   AND v.livroseq BETWEEN 1 AND 66 AND b.livro_seq BETWEEN 1 AND 66
									 GROUP BY v.bible_id
									 HAVING COUNT(DISTINCT b.livro_seq) > 0 AND COUNT(v.id) > 0
									 ORDER BY v.bible_id ASC LIMIT 1'
								);
								$diag_sitemap_id = absint( $diag_sitemap_id );
							}

							$diag_books    = $diag_sitemap_id > 0 ? (int) BDWP70_Activator::count_books( $diag_sitemap_id ) : 0;
							$diag_verses   = $diag_sitemap_id > 0 ? (int) BDWP70_Activator::count_verses( $diag_sitemap_id ) : 0;
							$vt_diag       = BDWP70_Activator::verses_table();
							$diag_chapters = 0;
							if ( $diag_sitemap_id > 0 ) {
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
								$diag_chapters = (int) $wpdb->get_var(
									$wpdb->prepare(
										// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
										'SELECT COUNT(DISTINCT livroseq, capitulo) FROM `' . esc_sql( $vt_diag ) . '`
										 WHERE bible_id = %d AND published = 1',
										$diag_sitemap_id
									)
								);
							}

							$has_content = $diag_books > 0 && $diag_verses > 0;
							?>
							<div class="bdwp70-sitemap-diag" style="margin-top:16px;padding:12px 16px;background:<?php echo $has_content ? '#eaf6ea' : '#fef6e4'; ?>;border-left:4px solid <?php echo $has_content ? '#46b450' : '#ffb900'; ?>;border-radius:3px;">
								<strong><?php esc_html_e( 'XML Sitemap diagnostics', 'estudobiblico-biblia-digital' ); ?></strong>
								<ul style="margin:.6em 0 0;padding:0 0 0 1.2em;list-style:disc;">
									<li>
										<?php
										/* translators: %d: configured active Bible ID. */
										echo esc_html( sprintf( __( 'Configured active Bible: #%d', 'estudobiblico-biblia-digital' ), $diag_active_id ) );
										?>
									</li>
									<li>
										<?php
										/* translators: %d: Bible ID used by the sitemap. */
										echo esc_html( sprintf( __( 'Bible used in the sitemap: #%d', 'estudobiblico-biblia-digital' ), $diag_sitemap_id ) );
										?>
									</li>
									<li>
										<?php
										/* translators: %s: number of published books. */
										echo esc_html( sprintf( __( 'Published books: %s', 'estudobiblico-biblia-digital' ), number_format_i18n( $diag_books ) ) );
										?>
									</li>
									<li>
										<?php
										/* translators: %s: number of published chapters. */
										echo esc_html( sprintf( __( 'Published chapters: %s', 'estudobiblico-biblia-digital' ), number_format_i18n( $diag_chapters ) ) );
										?>
									</li>
									<li>
										<?php
										/* translators: %s: number of published verses. */
										echo esc_html( sprintf( __( 'Published verses: %s', 'estudobiblico-biblia-digital' ), number_format_i18n( $diag_verses ) ) );
										?>
									</li>
									<li>
										<?php esc_html_e( 'Dedicated sitemap:', 'estudobiblico-biblia-digital' ); ?>
										<a href="<?php echo esc_url( $sitemap_index_url ); ?>" target="_blank" rel="noopener noreferrer">
											<code><?php echo esc_html( $sitemap_index_url ); ?></code>
										</a>
									</li>
									<li>
										<?php esc_html_e( 'Native WordPress sitemap:', 'estudobiblico-biblia-digital' ); ?>
										<a href="<?php echo esc_url( $wp_sitemap_url ); ?>" target="_blank" rel="noopener noreferrer">
											<code><?php echo esc_html( $wp_sitemap_url ); ?></code>
										</a>
									</li>
								</ul>
								<?php if ( ! $has_content ) : ?>
									<p style="margin:.8em 0 0;color:#856404;">
										<strong><?php esc_html_e( 'Note:', 'estudobiblico-biblia-digital' ); ?></strong>
										<?php esc_html_e( 'The sitemap will not generate URLs because the active Bible has no published books or verses. Re-import the Bible or select an active Bible with content.', 'estudobiblico-biblia-digital' ); ?>
									</p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Quick access cards', 'estudobiblico-biblia-digital' ); ?></h3>
			<p><?php esc_html_e( 'Change the cards shown below the title on the Bible home page.', 'estudobiblico-biblia-digital' ); ?></p>
			<table class="widefat striped bdwp70-admin-table">
				<thead><tr><th style="width:70px;"><?php esc_html_e( 'Show', 'estudobiblico-biblia-digital' ); ?></th><th><?php esc_html_e( 'Card', 'estudobiblico-biblia-digital' ); ?></th><th style="width:180px;"><?php esc_html_e( 'Icon / color', 'estudobiblico-biblia-digital' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $quick_cards as $index => $card ) : ?>
						<tr>
							<td><input type="hidden" name="bdwp70_quick_cards[<?php echo esc_attr( $index ); ?>][enabled]" value="0"><label><input type="checkbox" name="bdwp70_quick_cards[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( 1, (int) $card['enabled'] ); ?>> <?php esc_html_e( 'Yes', 'estudobiblico-biblia-digital' ); ?></label></td>
							<td>
								<p><label><?php esc_html_e( 'Title', 'estudobiblico-biblia-digital' ); ?><br><input type="text" class="regular-text" name="bdwp70_quick_cards[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $card['title'] ); ?>"></label></p>
								<p><label><?php esc_html_e( 'Description', 'estudobiblico-biblia-digital' ); ?><br><input type="text" class="large-text" name="bdwp70_quick_cards[<?php echo esc_attr( $index ); ?>][description]" value="<?php echo esc_attr( $card['description'] ); ?>"></label></p>
								<p><label><?php esc_html_e( 'Link', 'estudobiblico-biblia-digital' ); ?><br><input type="text" class="large-text" name="bdwp70_quick_cards[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $card['url'] ); ?>" placeholder="#bdwp70-livros"></label></p>
							</td>
							<td>
								<p><label><?php esc_html_e( 'Icon', 'estudobiblico-biblia-digital' ); ?><br><input type="text" class="small-text" name="bdwp70_quick_cards[<?php echo esc_attr( $index ); ?>][icon]" value="<?php echo esc_attr( $card['icon'] ); ?>"></label></p>
								<p><label><?php esc_html_e( 'Color', 'estudobiblico-biblia-digital' ); ?><br><select name="bdwp70_quick_cards[<?php echo esc_attr( $index ); ?>][style]">
									<?php
									foreach ( $quick_card_styles as $style_key => $style_label ) :
										?>
										<option value="<?php echo esc_attr( $style_key ); ?>" <?php selected( $card['style'], $style_key ); ?>><?php echo esc_html( $style_label ); ?></option><?php endforeach; ?>
								</select></label></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save settings', 'estudobiblico-biblia-digital' ), 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	private function render_admin_tab_diagnostic( $versions, $active_bible, $books, $verses, $status, $progress, $error, $base ) {
		$report = array(
			'plugin_version'     => BDWP70_VERSION,
			'wordpress_version'  => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'permalink_enabled'  => get_option( 'permalink_structure' ) ? 'yes' : 'no',
			'active_bible_id'    => (int) $active_bible,
			'imported_versions'  => is_array( $versions ) ? count( $versions ) : 0,
			'active_books'       => (int) $books,
			'active_verses'      => (int) $verses,
			'status'             => (string) $status,
			'seo_base'           => (string) $base,
			'books_table'        => BDWP70_Activator::books_table(),
			'verses_table'       => BDWP70_Activator::verses_table(),
			'translations_dir_1' => self::custom_translations_dir(),
			'translations_dir_2' => trailingslashit( WP_LANG_DIR ) . 'plugins',
		);
		?>
		<h2><?php esc_html_e( 'Diagnostics', 'estudobiblico-biblia-digital' ); ?></h2>
		<p><?php esc_html_e( 'Technical summary without sensitive data, for environment checks and support.', 'estudobiblico-biblia-digital' ); ?></p>
		<table class="widefat striped bdwp70-admin-table">
			<tbody>
				<?php foreach ( $report as $label => $value ) : ?>
					<tr><th><code><?php echo esc_html( $label ); ?></code></th><td><?php echo esc_html( (string) $value ); ?></td></tr>
				<?php endforeach; ?>
				<?php
				if ( $progress ) :
					?>
					<tr><th><code>progress</code></th><td><?php echo esc_html( $progress ); ?></td></tr><?php endif; ?>
				<?php
				if ( $error ) :
					?>
					<tr><th><code>last_error</code></th><td><?php echo esc_html( $error ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
		<p><button type="button" class="button bdwp70-copy-shortcode" data-copy="<?php echo esc_attr( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?>"><?php esc_html_e( 'Copy technical report', 'estudobiblico-biblia-digital' ); ?></button></p>
		<?php
	}

	private function admin_bible_version_label( $bible_id, $versions ) {
		if ( ! empty( $versions ) ) {
			foreach ( $versions as $version ) {
				if ( (int) $version->id === (int) $bible_id ) {
					return (string) $version->name . ' (' . (string) $version->language_code . ')';
				}
			}
		}
		if ( $bible_id ) {
			/* translators: %d: Bible version ID. */
			return sprintf( __( 'Bible #%d', 'estudobiblico-biblia-digital' ), (int) $bible_id );
		}

		return __( 'No active Bible', 'estudobiblico-biblia-digital' );
	}

	private function admin_version_option_label( $version ) {
		return sprintf(
			/* translators: 1: Bible version name, 2: language code, 3: number of books, 4: number of verses. */
			__( '%1$s - %2$s - %3$s books / %4$s verses', 'estudobiblico-biblia-digital' ),
			(string) $version->name,
			(string) $version->language_code,
			number_format_i18n( BDWP70_Activator::count_books( (int) $version->id ) ),
			number_format_i18n( BDWP70_Activator::count_verses( (int) $version->id ) )
		);
	}

	private function admin_code_inline( $code ) {
		return '<code>' . esc_html( $code ) . '</code>';
	}

	private function admin_shortcodes_reference() {
		return array(
			array(
				'title'       => __( 'Main Bible reader', 'estudobiblico-biblia-digital' ),
				'shortcode'   => '[biblia-digital]',
				'aliases'     => array( '[biblia-wp-estudobiblico]', '[bibliawp-estudobiblico]' ),
				'description' => __( 'Displays the full Bible, including the landing page, books, chapters, verses, search, and navigation.', 'estudobiblico-biblia-digital' ),
				'basic'       => '[biblia-digital]',
				'advanced'    => '[biblia-digital per_page="30" title="Online Bible" version="1"]',
				'attributes'  => array(
					'per_page' => __( 'Number of items per page in results/listings where applicable. Default: 30.', 'estudobiblico-biblia-digital' ),
					'title'    => __( 'Title shown by the shortcode, where applicable.', 'estudobiblico-biblia-digital' ),
					'version'  => __( 'ID of the version/Bible to use. Example: version="1".', 'estudobiblico-biblia-digital' ),
				),
			),
			array(
				'title'       => __( 'Simple Bible search', 'estudobiblico-biblia-digital' ),
				'shortcode'   => '[biblia-busca]',
				'aliases'     => array( '[estudo_biblico_busca]' ),
				'description' => __( 'Displays only the Bible search form.', 'estudobiblico-biblia-digital' ),
				'basic'       => '[biblia-busca]',
				'advanced'    => '[biblia-busca title="Search the Bible" placeholder="Enter a word or phrase" button="Search"]',
				'attributes'  => array(
					'title'       => __( 'Form title.', 'estudobiblico-biblia-digital' ),
					'placeholder' => __( 'Hint text for the search field.', 'estudobiblico-biblia-digital' ),
					'button'      => __( 'Button text.', 'estudobiblico-biblia-digital' ),
					'version'     => __( 'Version/Bible ID, if applicable.', 'estudobiblico-biblia-digital' ),
				),
			),
			array(
				'title'       => __( 'Full search page', 'estudobiblico-biblia-digital' ),
				'shortcode'   => '[biblia-busca-pagina]',
				'aliases'     => array( '[biblia-digital-busca]' ),
				'description' => __( 'Displays the search form, results, and pagination. Recommended for a public search page.', 'estudobiblico-biblia-digital' ),
				'basic'       => '[biblia-busca-pagina]',
				'advanced'    => '[biblia-busca-pagina title="Search in the Bible" per_page="30" show_book="1"]',
				'attributes'  => array(
					'title'     => __( 'Title of the search page/block.', 'estudobiblico-biblia-digital' ),
					'per_page'  => __( 'Number of results per page. Suggested default: 30.', 'estudobiblico-biblia-digital' ),
					'show_book' => __( 'Show the book selector. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'version'   => __( 'Version/Bible ID, if applicable.', 'estudobiblico-biblia-digital' ),
				),
			),
			array(
				'title'       => __( 'Random verse', 'estudobiblico-biblia-digital' ),
				'shortcode'   => '[biblia-versiculo-aleatorio]',
				'aliases'     => array(),
				'description' => __( 'Displays a random verse.', 'estudobiblico-biblia-digital' ),
				'basic'       => '[biblia-versiculo-aleatorio]',
				'advanced'    => '[biblia-versiculo-aleatorio title="Verse of the moment" book="19" show_button="1" button_text="Read the chapter"]',
				'attributes'  => array(
					'title'          => __( 'Block title.', 'estudobiblico-biblia-digital' ),
					'book'           => __( 'Book ID or identifier. Example: book="19" for Psalms.', 'estudobiblico-biblia-digital' ),
					'version'        => __( 'Version/Bible ID.', 'estudobiblico-biblia-digital' ),
					'show_reference' => __( 'Show the Bible reference. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'link_reference' => __( 'Turn the reference into a link. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'show_button'    => __( 'Show a button to read the chapter. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'button_text'    => __( 'Button text.', 'estudobiblico-biblia-digital' ),
				),
			),
			array(
				'title'       => __( 'Specific or random verse', 'estudobiblico-biblia-digital' ),
				'shortcode'   => '[biblia-versiculo]',
				'aliases'     => array(),
				'description' => __( 'Displays a specific or random verse.', 'estudobiblico-biblia-digital' ),
				'basic'       => '[biblia-versiculo book="43" chapter="3" verse="16"]',
				'advanced'    => '[biblia-versiculo book="random" chapter="random" verse="random"]',
				'attributes'  => array(
					'title'          => __( 'Optional title.', 'estudobiblico-biblia-digital' ),
					'livro'          => __( 'Book name, abbreviation, or ID.', 'estudobiblico-biblia-digital' ),
					'book'           => __( 'English alias for livro.', 'estudobiblico-biblia-digital' ),
					'capitulo'       => __( 'Chapter number or random.', 'estudobiblico-biblia-digital' ),
					'chapter'        => __( 'English alias for capitulo.', 'estudobiblico-biblia-digital' ),
					'versiculo'      => __( 'Verse number or random.', 'estudobiblico-biblia-digital' ),
					'verse'          => __( 'English alias for versiculo.', 'estudobiblico-biblia-digital' ),
					'version'        => __( 'Version/Bible ID.', 'estudobiblico-biblia-digital' ),
					'show_reference' => __( 'Show the reference. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'link_reference' => __( 'Turn the reference into a link. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'show_button'    => __( 'Show the button. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'button_text'    => __( 'Button text.', 'estudobiblico-biblia-digital' ),
				),
			),
			array(
				'title'       => __( 'Specific or random chapter', 'estudobiblico-biblia-digital' ),
				'shortcode'   => '[biblia-capitulo]',
				'aliases'     => array(),
				'description' => __( 'Displays a specific chapter or a random chapter.', 'estudobiblico-biblia-digital' ),
				'basic'       => '[biblia-capitulo random="1" limit="15"]',
				'advanced'    => '[biblia-capitulo book="43" chapter="3" show_title="1" show_button="1"]',
				'attributes'  => array(
					'title'       => __( 'Optional title.', 'estudobiblico-biblia-digital' ),
					'livro'       => __( 'Book name, abbreviation, or ID.', 'estudobiblico-biblia-digital' ),
					'book'        => __( 'English alias for livro.', 'estudobiblico-biblia-digital' ),
					'capitulo'    => __( 'Chapter number.', 'estudobiblico-biblia-digital' ),
					'chapter'     => __( 'English alias for capitulo.', 'estudobiblico-biblia-digital' ),
					'random'      => __( 'Enable a random chapter. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'limite'      => __( 'Limit the number of verses displayed.', 'estudobiblico-biblia-digital' ),
					'limit'       => __( 'English alias for limite.', 'estudobiblico-biblia-digital' ),
					'version'     => __( 'Version/Bible ID.', 'estudobiblico-biblia-digital' ),
					'show_title'  => __( 'Show the chapter title. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'show_button' => __( 'Show a button to open the full chapter. Accepts 1 or 0.', 'estudobiblico-biblia-digital' ),
					'button_text' => __( 'Button text.', 'estudobiblico-biblia-digital' ),
				),
			),
		);
	}

	/**
	 * Compatibilidade: a tela separada de traduções foi removida.
	 * Mantemos este método apenas para redirecionar URLs antigas para a tela principal.
	 */
	public function translations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=bdwp70-settings' ) );
		exit;
	}

	/**
	 * Renderiza o formulário de envio de traduções.
	 *
	 * @param string $page Página de origem para redirecionamento após upload.
	 */
	private function render_translation_upload_form( $page = 'settings' ) {
		$page = 'translations' === $page ? 'translations' : 'settings';
		?>
		<h2 id="bdwp70-translation-upload"><?php esc_html_e( 'Upload translation files', 'estudobiblico-biblia-digital' ); ?></h2>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:760px;margin-bottom:2rem;">
			<?php wp_nonce_field( 'bdwp70_upload_translation' ); ?>
			<input type="hidden" name="action" value="bdwp70_upload_translation">
			<input type="hidden" name="bdwp70_translation_page" value="<?php echo esc_attr( $page ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bdwp70_translation_locale_<?php echo esc_attr( $page ); ?>"><?php esc_html_e( 'Locale', 'estudobiblico-biblia-digital' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="bdwp70_translation_locale_<?php echo esc_attr( $page ); ?>" name="bdwp70_translation_locale" placeholder="<?php esc_attr_e( 'E.g.: en_US, es_ES, pt_BR', 'estudobiblico-biblia-digital' ); ?>">
						<p class="description">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: example .po file name, 2: example .mo file name, 3: the .pot extension. */
									__( 'Optional when the file is already named %1$s or %2$s. This field is ignored for %3$s files.', 'estudobiblico-biblia-digital' ),
									'<code>' . esc_html( self::TEXT_DOMAIN . '-en_US.po' ) . '</code>',
									'<code>' . esc_html( self::TEXT_DOMAIN . '-en_US.mo' ) . '</code>',
									'<code>.pot</code>'
								),
								array( 'code' => array() )
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bdwp70_translation_files_<?php echo esc_attr( $page ); ?>"><?php esc_html_e( '.pot, .po, or .mo files', 'estudobiblico-biblia-digital' ); ?></label></th>
					<td>
						<input type="file" id="bdwp70_translation_files_<?php echo esc_attr( $page ); ?>" name="bdwp70_translation_files[]" accept=".pot,.po,.mo,text/plain,application/octet-stream" multiple required>
						<p class="description"><?php echo wp_kses( __( 'For the translation to appear on the frontend, upload the matching <code>.mo</code> file. The <code>.po</code> file stays available for human editing, and the <code>.pot</code> file serves as the translation template.', 'estudobiblico-biblia-digital' ), array( 'code' => array() ) ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Upload translation files', 'estudobiblico-biblia-digital' ), 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Mostra arquivos de tradução já existentes nos destinos reconhecidos.
	 */
	private function render_translation_files_status() {
		// O primeiro diretório é o destino atual; o segundo é apenas o legado, que
		// continua sendo lido mas nunca mais recebe gravação.
		$dirs = array_filter(
			array(
				self::custom_translations_dir(),
				trailingslashit( WP_LANG_DIR ) . 'plugins',
			)
		);
		echo '<h2>Arquivos encontrados</h2>';
		echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>Arquivo</th><th>Local</th></tr></thead><tbody>';
		$found = 0;
		foreach ( $dirs as $dir ) {
			$files = glob( trailingslashit( $dir ) . '{' . self::TEXT_DOMAIN . ',' . self::LEGACY_TEXT_DOMAIN . '}*.{pot,po,mo}', GLOB_BRACE );
			if ( ! is_array( $files ) ) {
				continue;
			}
			foreach ( $files as $file ) {
				++$found;
				echo '<tr><td><code>' . esc_html( basename( $file ) ) . '</code></td><td><code>' . esc_html( $dir ) . '</code></td></tr>';
			}
		}
		if ( ! $found ) {
			echo '<tr><td colspan="2">' . esc_html__( 'No custom translation file found yet.', 'estudobiblico-biblia-digital' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * URL de retorno após upload de tradução.
	 *
	 * @return string
	 */
	private function translation_upload_redirect_url() {
		return add_query_arg( 'tab', 'translation', admin_url( 'options-general.php?page=bdwp70-settings' ) );
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'estudobiblico-biblia-digital' ) );
		}
		check_admin_referer( 'bdwp70_save_settings' );

		$context = isset( $_POST['bdwp70_settings_context'] ) ? sanitize_key( wp_unslash( $_POST['bdwp70_settings_context'] ) ) : 'seo';

		$rewrite_needs_flush = false;

		if ( isset( $_POST['bdwp70_seo_base'] ) ) {
			$old_base = $this->seo_base();
			$base     = sanitize_title( wp_unslash( $_POST['bdwp70_seo_base'] ) );
			if ( '' === $base ) {
				$base = 'biblia-digital';
			}
			update_option( self::OPTION_SEO_BASE, $base );
			$rewrite_needs_flush = $old_base !== $base;
			// Atualiza lastmod ao mudar a base SEO (Passo 8).
			if ( $old_base !== $base ) {
				update_option( 'bdwp70_sitemap_lastmod', current_time( 'Y-m-d' ) );
			}
		}

		if ( isset( $_POST['bdwp70_bible_title'] ) ) {
			$title = sanitize_text_field( wp_unslash( $_POST['bdwp70_bible_title'] ) );
			update_option( self::OPTION_TITLE, $title ? $title : __( 'Online Bible', 'estudobiblico-biblia-digital' ) );
		}

		if ( isset( $_POST['bdwp70_title_image_id'] ) ) {
			update_option( self::OPTION_TITLE_IMAGE, absint( wp_unslash( $_POST['bdwp70_title_image_id'] ) ) );
		}

		if ( isset( $_POST['bdwp70_quick_cards'] ) && is_array( $_POST['bdwp70_quick_cards'] ) ) {
			$quick_cards = map_deep( wp_unslash( $_POST['bdwp70_quick_cards'] ), 'sanitize_text_field' );
			update_option( self::OPTION_QUICK_CARDS, $this->sanitize_quick_cards( $quick_cards, true ) );
		}

		if ( isset( $_POST['bdwp70_active_bible_id'] ) ) {
			$active_bible_id = absint( wp_unslash( $_POST['bdwp70_active_bible_id'] ) );
			if ( $active_bible_id > 0 && BDWP70_Activator::bible_version_exists( $active_bible_id ) ) {
				$old_active = absint( get_option( self::OPTION_ACTIVE, 0 ) );
				update_option( self::OPTION_ACTIVE, $active_bible_id );
				// Atualiza lastmod ao trocar Bíblia ativa (Passo 8).
				if ( $old_active !== $active_bible_id ) {
					update_option( 'bdwp70_sitemap_lastmod', current_time( 'Y-m-d' ) );
				}
			}
		}

		if ( isset( $_POST['bdwp70_bible_studio_url'] ) ) {
			$bible_studio_url = $this->sanitize_url_template( sanitize_text_field( wp_unslash( $_POST['bdwp70_bible_studio_url'] ) ) );
			update_option( self::OPTION_BIBLE_STUDIO_URL, $bible_studio_url ? $bible_studio_url : $this->default_bible_studio_url_template() );
		}

		if ( isset( $_POST['bdwp70_general_settings'] ) ) {
			update_option( self::OPTION_CREDIT, isset( $_POST['bdwp70_show_credit'] ) ? 1 : 0 );
			update_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, isset( $_POST['bdwp70_delete_data_on_uninstall'] ) ? 1 : 0 );

			// Sitemap: usa hidden input com valor 0 como fallback, então sempre estará presente.
			$sitemap_was_enabled = (int) get_option( self::OPTION_SITEMAP_ENABLED, 0 );
			$sitemap_now_enabled = isset( $_POST['bdwp70_sitemap_enabled'] ) ? absint( wp_unslash( $_POST['bdwp70_sitemap_enabled'] ) ) : 0;
			update_option( self::OPTION_SITEMAP_ENABLED, $sitemap_now_enabled ? 1 : 0 );

			// Inclui versículos — sem disabled, o hidden garante que o POST sempre tem o campo.
			$incl_verses = isset( $_POST['bdwp70_sitemap_include_verses'] ) ? absint( wp_unslash( $_POST['bdwp70_sitemap_include_verses'] ) ) : 0;
			update_option( self::OPTION_SITEMAP_INCL_VERSES, $incl_verses ? 1 : 0 );

			// Máximo de URLs por página do sitemap.
			if ( isset( $_POST['bdwp70_sitemap_per_page'] ) ) {
				$per_page = absint( wp_unslash( $_POST['bdwp70_sitemap_per_page'] ) );
				$per_page = max( 100, min( 50000, $per_page ? $per_page : 2000 ) );
				update_option( self::OPTION_SITEMAP_PER_PAGE, $per_page );
			}

			// Atualiza lastmod ao salvar opções do sitemap (Passo 8).
			update_option( 'bdwp70_sitemap_lastmod', current_time( 'Y-m-d' ) );

			// Força flush de rewrite rules ao ligar/desligar o sitemap.
			if ( (bool) $sitemap_was_enabled !== (bool) $sitemap_now_enabled ) {
				$rewrite_needs_flush = true;
			}
		}

		if ( $rewrite_needs_flush ) {
			update_option( self::OPTION_FLUSH, 1 );
		}

		$redirect_tab = in_array( $context, array( 'seo', 'versions' ), true ) ? $context : 'overview';
		wp_safe_redirect(
			add_query_arg(
				array(
					'bdwp70_saved' => '1',
					'tab'          => $redirect_tab,
				),
				admin_url( 'options-general.php?page=bdwp70-settings' )
			)
		);
		exit;
	}

	/**
	 * Exclui uma versão bíblica importada: cadastro, livros e versículos.
	 *
	 * A versão ativa do site não pode ser excluída diretamente. Excluí-la
	 * obrigaria o plugin a eleger outra Bíblia no meio da requisição, com
	 * caches ainda apontando para a removida; o administrador ativa outra
	 * versão primeiro e depois exclui.
	 */
	public function handle_delete_version() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'estudobiblico-biblia-digital' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificado logo abaixo, com o id no nome da ação.
		$bible_id = isset( $_POST['bdwp70_bible_id'] ) ? absint( wp_unslash( $_POST['bdwp70_bible_id'] ) ) : 0;
		check_admin_referer( 'bdwp70_delete_version_' . $bible_id );

		$voltar = static function ( $resultado, $nome = '' ) {
			wp_safe_redirect(
				add_query_arg(
					array_filter(
						array(
							'tab'                    => 'versions',
							'bdwp70_version_deleted' => $resultado,
							'bdwp70_version_name'    => $nome,
						)
					),
					admin_url( 'options-general.php?page=bdwp70-settings' )
				)
			);
			exit;
		};

		$versao = null;
		foreach ( BDWP70_Activator::get_bible_versions() as $item ) {
			if ( (int) $item->id === $bible_id ) {
				$versao = $item;
				break;
			}
		}

		if ( $bible_id < 1 || ! $versao ) {
			$voltar( 'notfound' );
		}

		if ( ! empty( $versao->is_builtin ) ) {
			$voltar( 'builtin' );
		}

		if ( $bible_id === (int) absint( get_option( BDWP70_Activator::OPTION_ACTIVE_BIBLE, 0 ) ) ) {
			$voltar( 'active' );
		}

		if ( empty( $_POST['bdwp70_confirm_delete'] ) ) {
			$voltar( 'confirm' );
		}

		$nome = sanitize_text_field( (string) $versao->name );
		BDWP70_Activator::delete_bible_version( $bible_id );
		update_option( 'bdwp70_sitemap_lastmod', current_time( 'Y-m-d' ) );

		// As páginas em cache da versão excluída não devem continuar no ar.
		do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook do plugin LiteSpeed Cache, não deste plugin.

		/**
		 * Disparado depois que uma versão bíblica foi excluída pelo painel.
		 *
		 * @param int    $bible_id ID da versão excluída.
		 * @param string $nome     Nome da versão.
		 */
		do_action( 'bdwp70_version_deleted', $bible_id, $nome );

		$voltar( 'ok', $nome );
	}

	public function handle_reimport() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'estudobiblico-biblia-digital' ) );
		}
		check_admin_referer( 'bdwp70_reimport' );
		update_option( BDWP70_Activator::OPTION_STATUS, 'pending' );
		update_option( BDWP70_Activator::OPTION_ERROR, __( 'The built-in SQL import was removed from the public package. Use a ZIP containing books.csv and verses.csv.', 'estudobiblico-biblia-digital' ) );
		update_option( BDWP70_Activator::OPTION_PROGRESS, __( 'The public package does not distribute Bible translations; the end user is responsible for the license of the imported package.', 'estudobiblico-biblia-digital' ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'bdwp70_reimported' => '0',
					'tab'               => 'import',
				),
				admin_url( 'options-general.php?page=bdwp70-settings' )
			)
		);
		exit;
	}


	public function handle_upload_bible() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'estudobiblico-biblia-digital' ) );
		}
		check_admin_referer( 'bdwp70_upload_bible' );

		if ( empty( $_FILES['bdwp70_bible_file'] ) || ! is_array( $_FILES['bdwp70_bible_file'] ) ) {
			$this->redirect_upload_error( __( 'No file was uploaded.', 'estudobiblico-biblia-digital' ) );
		}

		$file         = map_deep( wp_unslash( $_FILES['bdwp70_bible_file'] ), 'sanitize_text_field' );
		$upload_error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			/* translators: %d: PHP upload error code. */
			$this->redirect_upload_error( sprintf( __( 'Upload failed. Error code: %d.', 'estudobiblico-biblia-digital' ), absint( $upload_error ) ) );
		}

		$max_size = (int) apply_filters( 'bdwp70_upload_max_zip_size', 50 * MB_IN_BYTES );
		if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_size ) {
			$this->redirect_upload_error( __( 'The ZIP file exceeds the maximum allowed size.', 'estudobiblico-biblia-digital' ) );
		}

		$name = isset( $_POST['bdwp70_bible_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bdwp70_bible_name'] ) ) : __( 'Imported Bible', 'estudobiblico-biblia-digital' );
		$lang = isset( $_POST['bdwp70_bible_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['bdwp70_bible_lang'] ) ) : 'und';
		$lang = preg_match( '/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/', $lang ) ? $lang : 'und';

		$filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( empty( $filename ) || 'zip' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			$this->redirect_upload_error( __( 'Upload a ZIP file containing books.csv and verses.csv.', 'estudobiblico-biblia-digital' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'zip'  => 'application/zip',
					'xzip' => 'application/x-zip-compressed',
				),
			)
		);

		if ( empty( $upload['file'] ) ) {
			$this->redirect_upload_error( ! empty( $upload['error'] ) ? sanitize_text_field( $upload['error'] ) : 'Falha no upload.' );
		}

		$typecheck = wp_check_filetype_and_ext(
			$upload['file'],
			$filename,
			array(
				'zip'  => 'application/zip',
				'xzip' => 'application/x-zip-compressed',
			)
		);
		if ( empty( $typecheck['ext'] ) || 'zip' !== strtolower( $typecheck['ext'] ) ) {
			wp_delete_file( $upload['file'] );
			$this->redirect_upload_error( __( 'The uploaded file was not recognized as a valid ZIP.', 'estudobiblico-biblia-digital' ) );
		}

		$archive_validation = $this->validate_uploaded_zip_archive( $upload['file'] );
		if ( is_wp_error( $archive_validation ) ) {
			wp_delete_file( $upload['file'] );
			$this->redirect_upload_error( $archive_validation->get_error_message() );
		}

		$tmp_dir = trailingslashit( get_temp_dir() ) . 'bdwp70-' . wp_generate_password( 12, false, false );
		wp_mkdir_p( $tmp_dir );
		$unzipped = unzip_file( $upload['file'], $tmp_dir );
		wp_delete_file( $upload['file'] );

		if ( is_wp_error( $unzipped ) ) {
			$this->remove_directory( $tmp_dir );
			/* translators: %s: error message. */
			$this->redirect_upload_error( sprintf( __( 'The ZIP could not be extracted: %s', 'estudobiblico-biblia-digital' ), sanitize_text_field( $unzipped->get_error_message() ) ) );
		}

		$zip_validation = $this->validate_uploaded_zip_contents( $tmp_dir );
		if ( is_wp_error( $zip_validation ) ) {
			$this->remove_directory( $tmp_dir );
			$this->redirect_upload_error( $zip_validation->get_error_message() );
		}

		$books_file  = $this->find_uploaded_csv( $tmp_dir, 'books.csv' );
		$verses_file = $this->find_uploaded_csv( $tmp_dir, 'verses.csv' );
		if ( ! $books_file || ! $verses_file ) {
			$this->remove_directory( $tmp_dir );
			$this->redirect_upload_error( __( 'The ZIP must contain books.csv and verses.csv.', 'estudobiblico-biblia-digital' ) );
		}

		$id = BDWP70_Activator::import_uploaded_bible_from_csv( $books_file, $verses_file, $name, $lang, $filename );
		$this->remove_directory( $tmp_dir );
		wp_safe_redirect(
			add_query_arg(
				array(
					'bdwp70_reimported' => $id ? '1' : '0',
					'tab'               => 'import',
				),
				admin_url( 'options-general.php?page=bdwp70-settings' )
			)
		);
		exit;
	}


	/**
	 * Recebe arquivos .pot, .po e .mo pelo painel administrativo.
	 *
	 * Os arquivos são salvos em wp-content/languages/plugins quando possível,
	 * que é o local recomendado para traduções persistirem após atualizações.
	 *
	 * @return never
	 */
	/**
	 * Diretório das traduções personalizadas, dentro de uploads.
	 *
	 * Nunca retorna caminho fixo: wp_upload_dir() resolve o basedir do site atual,
	 * o que mantém o isolamento por site em multisite. Devolve string vazia quando
	 * o diretório não existe e não pôde ser criado, para que o chamador trate o
	 * erro em vez de gravar em local indevido.
	 *
	 * @param bool $create Cria o diretório quando ausente.
	 * @return string Caminho com barra final, ou '' se indisponível.
	 */
	public static function custom_translations_dir( $create = false ) {
		$uploads = wp_upload_dir( null, false );

		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::CUSTOM_LANG_SUBDIR;

		if ( ! is_dir( $dir ) ) {
			if ( ! $create ) {
				return '';
			}

			if ( ! wp_mkdir_p( $dir ) ) {
				return '';
			}

			self::protect_custom_translations_dir( $dir );
		}

		return trailingslashit( $dir );
	}

	/**
	 * Impede listagem e acesso direto aos arquivos de tradução.
	 *
	 * Best-effort: a ausência dos arquivos de proteção não impede o plugin de
	 * funcionar, e nada aqui é fatal em filesystem restrito.
	 *
	 * @param string $dir Diretório recém-criado.
	 * @return void
	 */
	private static function protect_custom_translations_dir( $dir ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return;
		}

		$dir   = trailingslashit( $dir );
		$files = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
		);

		foreach ( $files as $name => $contents ) {
			$path = $dir . $name;
			if ( ! $wp_filesystem->exists( $path ) ) {
				$wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
			}
		}
	}

	/**
	 * Caminho da tradução personalizada legada, gravada em WP_LANG_DIR até a 1.1.69.
	 *
	 * Usado apenas para leitura. A 1.1.70 nunca grava nesse caminho.
	 *
	 * @param string $locale Locale já normalizado.
	 * @param string $ext    Extensão desejada.
	 * @return string
	 */
	private static function legacy_custom_translation_file( $locale, $ext = 'mo' ) {
		if ( '' === $locale || ! defined( 'WP_LANG_DIR' ) ) {
			return '';
		}

		return trailingslashit( WP_LANG_DIR ) . 'plugins/' . self::LEGACY_TEXT_DOMAIN . '-' . $locale . '.' . $ext;
	}

	/**
	 * Carrega a tradução personalizada do administrador, se houver.
	 *
	 * Ordem: arquivo em uploads (1.1.70+) e, apenas se ele não existir, o arquivo
	 * legado em WP_LANG_DIR, em modo somente-leitura. Isso mantém funcionando as
	 * traduções de quem atualizou de uma versão anterior mesmo que a cópia para
	 * uploads não tenha sido possível.
	 *
	 * Roda em init para não disparar o aviso de carregamento antecipado de
	 * text domain introduzido no WordPress 6.7.
	 *
	 * @return void
	 */
	/**
	 * Mapa das chaves de tradução anteriores à 1.2.0.
	 *
	 * @return array md5( contexto . "\x04" . msgid antigo ) => array( msgid novo, plural novo ou null ).
	 */
	public static function legacy_msgid_map() {
		static $map = null;
		if ( null === $map ) {
			$map = include BDWP70_DIR . 'includes/legacy-msgids.php';
			$map = is_array( $map ) ? $map : array();
		}
		return $map;
	}

	/**
	 * Converte um .po ou .mo com as chaves em português (até a 1.1.80) para as chaves em inglês.
	 *
	 * Só troca msgid/msgid_plural; as traduções (msgstr) ficam como estão. Arquivo que
	 * já usa as chaves novas não é regravado.
	 *
	 * @param string $file Caminho do arquivo.
	 * @return int|false Número de entradas convertidas, ou false se o arquivo não pôde ser lido ou gravado.
	 */
	public static function remap_legacy_translation_file( $file ) {
		$ext = strtolower( pathinfo( (string) $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'po', 'mo' ), true ) || ! is_readable( $file ) || ! wp_is_writable( $file ) ) {
			return false;
		}

		require_once ABSPATH . WPINC . '/pomo/mo.php';
		require_once ABSPATH . WPINC . '/pomo/po.php';

		$origem = 'mo' === $ext ? new MO() : new PO();
		if ( ! $origem->import_from_file( $file ) ) {
			return false;
		}

		$map     = self::legacy_msgid_map();
		$destino = 'mo' === $ext ? new MO() : new PO();
		$destino->set_headers( $origem->headers );
		$convertidas = 0;

		foreach ( $origem->entries as $entry ) {
			$hash = md5( ( null === $entry->context ? '' : $entry->context ) . "\x04" . $entry->singular );
			if ( isset( $map[ $hash ] ) ) {
				$entry->singular = $map[ $hash ][0];
				if ( null !== $entry->plural && null !== $map[ $hash ][1] ) {
					$entry->plural = $map[ $hash ][1];
				}
				++$convertidas;
			}
			$destino->add_entry( $entry );
		}

		if ( 0 === $convertidas ) {
			return 0;
		}

		return $destino->export_to_file( $file ) ? $convertidas : false;
	}

	/**
	 * Converte todas as traduções personalizadas deste site para as chaves em inglês.
	 *
	 * @return int Total de entradas convertidas.
	 */
	public static function remap_custom_translations_to_english() {
		$dir = self::custom_translations_dir();
		if ( '' === $dir ) {
			return 0;
		}

		$total = 0;
		foreach ( (array) glob( trailingslashit( $dir ) . '{' . self::TEXT_DOMAIN . ',' . self::LEGACY_TEXT_DOMAIN . '}-*.{po,mo}', GLOB_BRACE ) as $file ) {
			$convertidas = self::remap_legacy_translation_file( $file );
			$total      += is_int( $convertidas ) ? $convertidas : 0;
		}

		return $total;
	}

	/**
	 * Informa se há tradução disponível para o idioma do painel.
	 *
	 * @param string $locale Locale.
	 * @return bool
	 */
	private static function has_translation_for_locale( $locale ) {
		if ( 0 === strpos( $locale, 'en_' ) ) {
			return true;
		}

		$candidatos = array( trailingslashit( WP_LANG_DIR ) . 'plugins/' . self::TEXT_DOMAIN . '-' . $locale . '.mo' );
		$dir        = self::custom_translations_dir();
		if ( '' !== $dir ) {
			$candidatos[] = trailingslashit( $dir ) . self::TEXT_DOMAIN . '-' . $locale . '.mo';
		}

		foreach ( $candidatos as $file ) {
			if ( is_readable( $file ) ) {
				return true;
			}
		}

		return false;
	}

	public function load_custom_translations() {
		$locale = determine_locale();
		if ( ! is_string( $locale ) || '' === $locale ) {
			return;
		}

		$carregou = false;
		$dir      = self::custom_translations_dir();
		if ( '' !== $dir ) {
			$file = $dir . self::TEXT_DOMAIN . '-' . $locale . '.mo';
			if ( is_readable( $file ) ) {
				$carregou = load_textdomain( self::TEXT_DOMAIN, $file );
			}
		}

		if ( ! $carregou ) {
			$legacy = self::legacy_custom_translation_file( $locale );
			if ( '' !== $legacy && is_readable( $legacy ) ) {
				$carregou = load_textdomain( self::TEXT_DOMAIN, $legacy );
			}
		}

		/*
		 * Com o domínio já carregado, o WordPress deixa de buscar o pacote de idioma
		 * do translate.wordpress.org. Carregá-lo aqui, depois do personalizado, faz o
		 * pacote completar as strings que a tradução personalizada não cobre; o
		 * arquivo carregado primeiro continua com prioridade.
		 */
		if ( $carregou ) {
			$pack = trailingslashit( WP_LANG_DIR ) . 'plugins/' . self::TEXT_DOMAIN . '-' . $locale . '.mo';
			if ( is_readable( $pack ) ) {
				load_textdomain( self::TEXT_DOMAIN, $pack );
			}
		}
	}

	public function handle_upload_translation() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'estudobiblico-biblia-digital' ) );
		}
		check_admin_referer( 'bdwp70_upload_translation' );

		if ( empty( $_FILES['bdwp70_translation_files'] ) || ! is_array( $_FILES['bdwp70_translation_files'] ) ) {
			$this->redirect_translation_error( __( 'No translation file was uploaded.', 'estudobiblico-biblia-digital' ) );
		}

		$files = $this->normalize_uploaded_files_array( map_deep( wp_unslash( $_FILES['bdwp70_translation_files'] ), 'sanitize_text_field' ) );
		if ( empty( $files ) ) {
			$this->redirect_translation_error( __( 'No valid translation file was uploaded.', 'estudobiblico-biblia-digital' ) );
		}

		$locale_field = isset( $_POST['bdwp70_translation_locale'] ) ? sanitize_text_field( wp_unslash( $_POST['bdwp70_translation_locale'] ) ) : '';
		$locale_field = $this->normalize_locale_code( $locale_field );

		// 1.1.70: o destino passa a ser uma subpasta própria em uploads, resolvida
		// por wp_upload_dir(). WP_LANG_DIR e o diretório do plugin não são mais
		// usados como destino gravável.
		$target_dir = self::custom_translations_dir( true );

		if ( '' === $target_dir || ! wp_is_writable( $target_dir ) ) {
			$this->redirect_translation_error( __( 'The translations folder in uploads could not be created or is not writable.', 'estudobiblico-biblia-digital' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$uploaded_count = 0;
		$last_error     = '';
		foreach ( $files as $file ) {
			$result = $this->store_translation_file( $file, $target_dir, $locale_field );
			if ( is_wp_error( $result ) ) {
				$last_error = $result->get_error_message();
				continue;
			}
			++$uploaded_count;
		}

		if ( $uploaded_count < 1 ) {
			$this->redirect_translation_error( $last_error ? $last_error : __( 'No translation file was saved.', 'estudobiblico-biblia-digital' ) );
		}

		update_option( BDWP70_Activator::OPTION_ERROR, '' );
		wp_safe_redirect( add_query_arg( 'bdwp70_translation_uploaded', '1', $this->translation_upload_redirect_url() ) );
		exit;
	}

	/**
	 * Normaliza a estrutura de $_FILES para múltiplos uploads.
	 *
	 * @param array $files Campo de upload.
	 * @return array
	 */
	private function normalize_uploaded_files_array( $files ) {
		$normalized = array();
		if ( ! isset( $files['name'] ) ) {
			return $normalized;
		}

		if ( is_array( $files['name'] ) ) {
			foreach ( $files['name'] as $index => $name ) {
				$normalized[] = array(
					'name'     => $name,
					'type'     => isset( $files['type'][ $index ] ) ? $files['type'][ $index ] : '',
					'tmp_name' => isset( $files['tmp_name'][ $index ] ) ? $files['tmp_name'][ $index ] : '',
					'error'    => isset( $files['error'][ $index ] ) ? $files['error'][ $index ] : UPLOAD_ERR_NO_FILE,
					'size'     => isset( $files['size'][ $index ] ) ? $files['size'][ $index ] : 0,
				);
			}
		} else {
			$normalized[] = $files;
		}

		return $normalized;
	}

	/**
	 * Salva um arquivo de tradução validado.
	 *
	 * @param array  $file Arquivo vindo de $_FILES.
	 * @param string $target_dir Diretório final.
	 * @param string $locale_field Locale informado no formulário.
	 * @return string|WP_Error Caminho salvo ou erro.
	 */
	private function store_translation_file( $file, $target_dir, $locale_field = '' ) {
		$upload_error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			/* translators: %d: PHP upload error code. */
			return new WP_Error( 'bdwp70_translation_upload_error', sprintf( __( 'Translation upload failed. Error code: %d.', 'estudobiblico-biblia-digital' ), absint( $upload_error ) ) );
		}

		$max_size = (int) apply_filters( 'bdwp70_translation_upload_max_size', 2 * MB_IN_BYTES );
		if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_size ) {
			return new WP_Error( 'bdwp70_translation_size', __( 'A translation file exceeds the maximum allowed size.', 'estudobiblico-biblia-digital' ) );
		}

		$original_name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'pot', 'po', 'mo' ), true ) ) {
			return new WP_Error( 'bdwp70_translation_ext', __( 'Upload only .pot, .po, or .mo files.', 'estudobiblico-biblia-digital' ) );
		}

		if ( in_array( $ext, array( 'pot', 'po' ), true ) && ! $this->translation_text_file_looks_safe( $file['tmp_name'] ) ) {
			return new WP_Error( 'bdwp70_translation_unsafe', __( 'The text translation file looks invalid or contains disallowed content.', 'estudobiblico-biblia-digital' ) );
		}

		if ( ! $this->translation_filetype_looks_safe( $file['tmp_name'], $original_name, $ext ) ) {
			return new WP_Error( 'bdwp70_translation_mime', __( 'The translation file did not pass the file type check.', 'estudobiblico-biblia-digital' ) );
		}

		if ( 'mo' === $ext && ! $this->translation_mo_file_looks_safe( $file['tmp_name'] ) ) {
			return new WP_Error( 'bdwp70_translation_mo', __( 'The .mo file did not pass the basic check.', 'estudobiblico-biblia-digital' ) );
		}

		$target_name = $this->target_translation_filename( $original_name, $ext, $locale_field );
		if ( is_wp_error( $target_name ) ) {
			return $target_name;
		}

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'pot|po' => 'text/plain',
					'mo'     => 'application/octet-stream',
				),
			)
		);

		if ( empty( $upload['file'] ) ) {
			return new WP_Error( 'bdwp70_translation_handle', ! empty( $upload['error'] ) ? sanitize_text_field( $upload['error'] ) : __( 'The translation upload could not be processed.', 'estudobiblico-biblia-digital' ) );
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		$destination = trailingslashit( $target_dir ) . $target_name;
		if ( $wp_filesystem && $wp_filesystem->exists( $destination ) ) {
			$backup = $destination . '.bak.' . gmdate( 'YmdHis' );
			if ( ! $wp_filesystem->copy( $destination, $backup, false, FS_CHMOD_FILE ) ) {
				wp_delete_file( $upload['file'] );
				return new WP_Error( 'bdwp70_translation_backup', __( 'The existing translation could not be backed up.', 'estudobiblico-biblia-digital' ) );
			}
		}

		$copied = $wp_filesystem ? $wp_filesystem->copy( $upload['file'], $destination, true, FS_CHMOD_FILE ) : false;
		wp_delete_file( $upload['file'] );

		if ( ! $copied ) {
			return new WP_Error( 'bdwp70_translation_copy', __( 'The translation file could not be saved to its destination.', 'estudobiblico-biblia-digital' ) );
		}

		if ( $wp_filesystem ) {
			$wp_filesystem->chmod( $destination, FS_CHMOD_FILE );
		}

		// Tradução feita para 1.1.80 ou anterior usa as chaves em português.
		self::remap_legacy_translation_file( $destination );

		return $destination;
	}

	/**
	 * Define o nome final aceito para um arquivo de tradução.
	 *
	 * @param string $original_name Nome original.
	 * @param string $ext Extensão.
	 * @param string $locale_field Locale do formulário.
	 * @return string|WP_Error
	 */
	private function target_translation_filename( $original_name, $ext, $locale_field = '' ) {
		// O nome final é sempre construído pelo plugin a partir do domínio atual e
		// de um locale normalizado, nunca a partir do nome enviado. Isso elimina
		// path traversal e mantém o arquivo localizável pelo carregador.
		if ( 'pot' === $ext ) {
			return self::TEXT_DOMAIN . '.pot';
		}

		$locale = $this->extract_locale_from_translation_filename( $original_name );
		if ( '' === $locale ) {
			$locale = $locale_field;
		}
		$locale = $this->normalize_locale_code( $locale );

		if ( '' === $locale ) {
			/* translators: %s: example file name. */
			return new WP_Error( 'bdwp70_translation_locale', sprintf( __( 'Enter the locale, such as en_US, es_ES, or pt_BR, or upload the file named as %s.', 'estudobiblico-biblia-digital' ), self::TEXT_DOMAIN . '-en_US.' . $ext ) );
		}

		return self::TEXT_DOMAIN . '-' . $locale . '.' . $ext;
	}

	/**
	 * Extrai locale do nome do arquivo.
	 *
	 * Aceita tanto o prefixo atual quanto o legado, para que quem já tinha o
	 * arquivo nomeado como biblia-digital-pt_BR.mo continue conseguindo enviá-lo.
	 *
	 * @param string $filename Nome do arquivo.
	 * @return string
	 */
	private function extract_locale_from_translation_filename( $filename ) {
		$filename = sanitize_file_name( (string) $filename );
		$prefixes = array( self::TEXT_DOMAIN, self::LEGACY_TEXT_DOMAIN );

		foreach ( $prefixes as $prefix ) {
			if ( preg_match( '/' . preg_quote( $prefix, '/' ) . '-([A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?)\.(?:po|mo)$/', $filename, $matches ) ) {
				return $matches[1];
			}
		}
		if ( preg_match( '/([A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?)\.(?:po|mo)$/', $filename, $matches ) ) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Normaliza locale para o padrão WordPress, como en_US.
	 *
	 * @param string $locale Locale bruto.
	 * @return string
	 */
	private function normalize_locale_code( $locale ) {
		$locale = trim( str_replace( '-', '_', (string) $locale ) );
		if ( '' === $locale || ! preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,8})?$/', $locale ) ) {
			return '';
		}

		$parts    = explode( '_', $locale );
		$parts[0] = strtolower( $parts[0] );
		if ( isset( $parts[1] ) ) {
			$parts[1] = strtoupper( $parts[1] );
		}

		return implode( '_', $parts );
	}

	/**
	 * Verificação simples para arquivos .po/.pot textuais.
	 *
	 * @param string $path Caminho temporário.
	 * @return bool
	 */
	/**
	 * Validates translation file extension and WordPress-detected type.
	 *
	 * @param string $path Temporary file path.
	 * @param string $original_name Original uploaded filename.
	 * @param string $ext Expected extension.
	 * @return bool
	 */
	private function translation_filetype_looks_safe( $path, $original_name, $ext ) {
		$strict_mimes = array(
			'pot' => 'text/plain',
			'po'  => 'text/plain',
			'mo'  => 'application/octet-stream',
		);

		$typecheck = wp_check_filetype_and_ext( $path, $original_name, $strict_mimes );
		if ( ! empty( $typecheck['ext'] ) && $ext === strtolower( $typecheck['ext'] ) ) {
			return true;
		}

		$filetype = wp_check_filetype( $original_name, $strict_mimes );
		return ! empty( $filetype['ext'] ) && $ext === strtolower( $filetype['ext'] );
	}

	private function translation_text_file_looks_safe( $path ) {
		if ( ! is_readable( $path ) ) {
			return false;
		}
		$sample = (string) file_get_contents( $path, false, null, 0, 4096 );
		if ( false !== stripos( $sample, '<?php' ) || false !== stripos( $sample, '<script' ) ) {
			return false;
		}
		return ( false !== strpos( $sample, 'msgid' ) || false !== strpos( $sample, 'Project-Id-Version' ) );
	}

	/**
	 * Registra erro de tradução e redireciona.
	 *
	 * @param string $message Mensagem.
	 * @return never
	 */
	/**
	 * Basic .mo signature validation.
	 *
	 * @param string $path Temporary file path.
	 * @return bool
	 */
	private function translation_mo_file_looks_safe( $path ) {
		if ( ! is_readable( $path ) ) {
			return false;
		}

		$sample = (string) file_get_contents( $path, false, null, 0, 4 );
		return in_array( bin2hex( $sample ), array( '950412de', 'de120495' ), true );
	}

	private function redirect_translation_error( $message ) {
		update_option( BDWP70_Activator::OPTION_ERROR, sanitize_text_field( $message ) );
		wp_safe_redirect( add_query_arg( 'bdwp70_translation_uploaded', '0', $this->translation_upload_redirect_url() ) );
		exit;
	}

	/**
	 * Registra erro de upload e redireciona com segurança para a tela do plugin.
	 *
	 * @param string $message Mensagem de erro.
	 * @return never
	 */
	private function redirect_upload_error( $message ) {
		update_option( BDWP70_Activator::OPTION_ERROR, sanitize_text_field( $message ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'bdwp70_reimported' => '0',
					'tab'               => 'import',
				),
				admin_url( 'options-general.php?page=bdwp70-settings' )
			)
		);
		exit;
	}

	/**
	 * Validates ZIP entries before extraction.
	 *
	 * @param string $path Uploaded ZIP path.
	 * @return true|WP_Error
	 */
	/**
	 * Entradas que sistemas operacionais acrescentam a um ZIP e que não são dados.
	 *
	 * "Compactar pasta" no macOS inclui __MACOSX/ e ._arquivo; o Windows e o
	 * Finder deixam Thumbs.db, desktop.ini e .DS_Store. Antes qualquer uma delas
	 * recusava o upload com mensagem de caminho inseguro.
	 *
	 * @param string $relative Caminho relativo dentro do ZIP.
	 * @return bool
	 */
	private function is_zip_junk_entry( $relative ) {
		$relative = str_replace( '\\', '/', (string) $relative );
		$base     = strtolower( basename( $relative ) );

		return 0 === strpos( $relative, '__MACOSX/' )
			|| false !== strpos( $relative, '/__MACOSX/' )
			|| 0 === strpos( $base, '._' )
			|| in_array( $base, array( '.ds_store', 'thumbs.db', 'desktop.ini' ), true );
	}

	private function validate_uploaded_zip_archive( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			// Without ext-zip the archive cannot be inspected before extraction,
			// so every traversal, file-count and size guard below would be
			// bypassed. Refuse the upload instead of silently trusting PclZip.
			return new WP_Error(
				'bdwp70_zip_ext_missing',
				__( 'The PHP zip extension is required to validate the uploaded file. Ask your host to enable ext-zip.', 'estudobiblico-biblia-digital' )
			);
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'bdwp70_zip_open', __( 'The uploaded file could not be opened as a valid ZIP.', 'estudobiblico-biblia-digital' ) );
		}

		$max_files = (int) apply_filters( 'bdwp70_upload_max_extracted_files', 8 );
		$max_total = (int) apply_filters( 'bdwp70_upload_max_extracted_size', 25 * MB_IN_BYTES );
		$required  = array(
			'books.csv'  => false,
			'verses.csv' => false,
		);
		$files     = 0;
		$total     = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( ! is_array( $stat ) || empty( $stat['name'] ) ) {
				$zip->close();
				return new WP_Error( 'bdwp70_zip_entry', __( 'The ZIP contains an invalid entry.', 'estudobiblico-biblia-digital' ) );
			}

			$name = str_replace( '\\', '/', (string) $stat['name'] );
			if ( '/' === substr( $name, -1 ) ) {
				continue;
			}

			if ( false !== strpos( $name, '../' ) || 0 === strpos( $name, '/' ) || preg_match( '#^[A-Za-z]:/#', $name ) ) {
				$zip->close();
				return new WP_Error( 'bdwp70_zip_traversal', __( 'The ZIP contains a potentially unsafe path.', 'estudobiblico-biblia-digital' ) );
			}

			if ( $this->is_zip_junk_entry( $name ) ) {
				continue;
			}

			// Aceita os CSVs na raiz ou dentro de uma única pasta ("Compactar pasta").
			if ( substr_count( $name, '/' ) > 1 ) {
				$zip->close();
				return new WP_Error( 'bdwp70_zip_depth', __( 'The books.csv and verses.csv files must be at the root of the ZIP or inside a single folder.', 'estudobiblico-biblia-digital' ) );
			}

			$basename = strtolower( sanitize_file_name( basename( $name ) ) );
			if ( ! array_key_exists( $basename, $required ) ) {
				$zip->close();
				/* translators: %s: unexpected file name inside the ZIP. */
				return new WP_Error( 'bdwp70_zip_unexpected_file', sprintf( __( 'The ZIP must contain only books.csv and verses.csv. Unexpected file: %s', 'estudobiblico-biblia-digital' ), $name ) );
			}

			if ( $required[ $basename ] ) {
				$zip->close();
				/* translators: %s: CSV file name. */
				return new WP_Error( 'bdwp70_zip_duplicate', sprintf( __( 'The ZIP contains more than one %s.', 'estudobiblico-biblia-digital' ), $basename ) );
			}

			++$files;
			$total                += isset( $stat['size'] ) ? (int) $stat['size'] : 0;
			$required[ $basename ] = true;

			if ( $files > $max_files ) {
				$zip->close();
				return new WP_Error( 'bdwp70_zip_file_count', __( 'The ZIP contains too many files.', 'estudobiblico-biblia-digital' ) );
			}

			if ( $total > $max_total ) {
				$zip->close();
				return new WP_Error( 'bdwp70_zip_total_size', __( 'The extracted ZIP content exceeds the allowed size.', 'estudobiblico-biblia-digital' ) );
			}
		}

		$zip->close();

		foreach ( $required as $found ) {
			if ( ! $found ) {
				return new WP_Error( 'bdwp70_zip_missing_file', __( 'The ZIP must contain books.csv and verses.csv.', 'estudobiblico-biblia-digital' ) );
			}
		}

		return true;
	}

	/**
	 * Validates extracted ZIP contents before import.
	 *
	 * @param string $dir Temporary extracted directory.
	 * @return true|WP_Error
	 */
	private function validate_uploaded_zip_contents( $dir ) {
		$root = realpath( $dir );
		if ( ! $root || ! is_dir( $root ) ) {
			return new WP_Error( 'bdwp70_zip_root', __( 'The ZIP content could not be validated.', 'estudobiblico-biblia-digital' ) );
		}

		$max_files = (int) apply_filters( 'bdwp70_upload_max_extracted_files', 8 );
		$max_total = (int) apply_filters( 'bdwp70_upload_max_extracted_size', 25 * MB_IN_BYTES );
		$files     = 0;
		$total     = 0;
		$required  = array(
			'books.csv'  => false,
			'verses.csv' => false,
		);

		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			$path = realpath( $file->getPathname() );
			if ( ! $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) ) {
				return new WP_Error( 'bdwp70_zip_path', __( 'The ZIP contains an invalid path.', 'estudobiblico-biblia-digital' ) );
			}

			if ( $file->isLink() ) {
				return new WP_Error( 'bdwp70_zip_link', __( 'The ZIP must not contain symbolic links.', 'estudobiblico-biblia-digital' ) );
			}

			if ( ! $file->isFile() ) {
				continue;
			}

			$relative = ltrim( str_replace( $root, '', $path ), DIRECTORY_SEPARATOR );
			if ( false !== strpos( $relative, '..' ) || false !== strpos( $relative, '\\' ) ) {
				return new WP_Error( 'bdwp70_zip_traversal', __( 'The ZIP contains a potentially unsafe path.', 'estudobiblico-biblia-digital' ) );
			}

			$relative_url = str_replace( DIRECTORY_SEPARATOR, '/', $relative );
			if ( $this->is_zip_junk_entry( $relative_url ) ) {
				continue;
			}

			if ( substr_count( $relative_url, '/' ) > 1 ) {
				return new WP_Error( 'bdwp70_zip_depth', __( 'The books.csv and verses.csv files must be at the root of the ZIP or inside a single folder.', 'estudobiblico-biblia-digital' ) );
			}

			$basename = strtolower( $file->getFilename() );
			if ( ! array_key_exists( $basename, $required ) ) {
				/* translators: %s: unexpected file name inside the ZIP. */
				return new WP_Error( 'bdwp70_zip_unexpected_file', sprintf( __( 'The ZIP must contain only books.csv and verses.csv. Unexpected file: %s', 'estudobiblico-biblia-digital' ), $relative_url ) );
			}

			if ( $required[ $basename ] ) {
				/* translators: %s: CSV file name. */
				return new WP_Error( 'bdwp70_zip_duplicate', sprintf( __( 'The ZIP contains more than one %s.', 'estudobiblico-biblia-digital' ), $basename ) );
			}

			++$files;
			$total                += (int) $file->getSize();
			$required[ $basename ] = true;

			if ( $files > $max_files ) {
				return new WP_Error( 'bdwp70_zip_file_count', __( 'The ZIP contains too many files.', 'estudobiblico-biblia-digital' ) );
			}

			if ( $total > $max_total ) {
				return new WP_Error( 'bdwp70_zip_total_size', __( 'The extracted ZIP content exceeds the allowed size.', 'estudobiblico-biblia-digital' ) );
			}
		}

		foreach ( $required as $name => $found ) {
			if ( ! $found ) {
				return new WP_Error( 'bdwp70_zip_missing_file', __( 'The ZIP must contain books.csv and verses.csv.', 'estudobiblico-biblia-digital' ) );
			}
		}

		return true;
	}

	private function find_uploaded_csv( $dir, $filename ) {
		$root = realpath( $dir );
		if ( ! $root || ! is_dir( $root ) ) {
			return '';
		}

		$filename = strtolower( sanitize_file_name( $filename ) );
		if ( ! in_array( $filename, array( 'books.csv', 'verses.csv' ), true ) ) {
			return '';
		}

		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || $file->isLink() ) {
				continue;
			}

			$path = realpath( $file->getPathname() );
			if ( ! $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) ) {
				continue;
			}

			if ( $this->is_zip_junk_entry( str_replace( DIRECTORY_SEPARATOR, '/', substr( $path, strlen( $root ) + 1 ) ) ) ) {
				continue;
			}

			if ( strtolower( $file->getFilename() ) === $filename && 'csv' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) && is_readable( $path ) ) {
				return $path;
			}
		}
		return '';
	}

	private function remove_directory( $dir ) {
		$root = realpath( $dir );
		$temp = realpath( get_temp_dir() );

		if ( ! $root || ! $temp || ! is_dir( $root ) || 0 !== strpos( $root, trailingslashit( $temp ) ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $root, true, 'd' );
		}
	}
}
