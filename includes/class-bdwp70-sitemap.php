<?php
/**
 * Sitemap XML dedicado da Bíblia Digital — v1.1.59
 *
 * Gera um sitemap index em /{base}-sitemap.xml e sub-sitemaps
 * paginados por livro em /{base}-sitemap-{seq}.xml.
 *
 * Paginação lógica por blocos:
 *   Bloco A – URL do livro (1 URL, apenas página 1)
 *   Bloco B – capítulos do livro
 *   Bloco C – versículos do livro (se include_verses = true)
 *
 * Resolução do bible_id (C1-C3): usa a Bíblia ativa se ela tiver
 * livros e versículos; caso contrário, busca fallback com conteúdo real.
 *
 * @package BibliaDigital
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'BDWP70_Sitemap', false ) ) {
	return;
}

class BDWP70_Sitemap {

	// -------------------------------------------------------------------------
	// Constantes
	// -------------------------------------------------------------------------

	const OPTION_ENABLED     = 'bdwp70_sitemap_enabled';
	const OPTION_INCL_VERSES = 'bdwp70_sitemap_include_verses';
	const OPTION_PER_PAGE    = 'bdwp70_sitemap_per_page';
	const OPTION_LASTMOD     = 'bdwp70_sitemap_lastmod';
	const OPTION_STATIC_SIG  = 'bdwp70_sitemap_static_index_signature';
	const OPTION_STATIC_INFO = 'bdwp70_sitemap_static_index_info';

	const QUERY_VAR      = 'bdwp_sitemap';
	const QUERY_VAR_BOOK = 'bdwp_sitemap_book';
	const QUERY_VAR_PAGE = 'bdwp_sitemap_page';

	const CACHE_GROUP = 'bdwp70_sitemap';
	const CACHE_TTL   = 43200; // 12 h

	/** @var BDWP70_Plugin */
	private $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	public function init() {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 10, 2 );

		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'init', array( $this, 'register_rewrite' ), 9 );

		// Interceptação direta e muito antecipada por REQUEST_URI.
		// Alguns plugins de SEO interceptam *-sitemap.xml no init e podem responder
		// antes do template_redirect/wp_loaded. Por isso a rota dedicada precisa ser
		// servida no init com prioridade negativa. Mantemos parse_request/wp_loaded
		// apenas como fallback para ambientes com bootstrap incomum.
		add_action( 'init', array( $this, 'maybe_serve_direct_sitemap_request' ), -9999 );
		add_action( 'parse_request', array( $this, 'maybe_serve_direct_sitemap_request' ), -9999 );
		add_action( 'wp_loaded', array( $this, 'maybe_serve_direct_sitemap_request' ), 0 );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_for_sitemap' ), 10, 2 );

		// Abordagem 1.1.58: criar também um arquivo físico de índice na raiz
		// do WordPress. Isso contorna conflitos em ambientes onde plugin de SEO, cache
		// ou regra do servidor captura /{base}-sitemap.xml antes do WordPress.
		add_action( 'init', array( $this, 'maybe_refresh_static_index_file' ), 50 );

		add_action( 'template_redirect', array( $this, 'maybe_serve_sitemap' ), 5 );
	}

	// -------------------------------------------------------------------------
	// Getters de opção
	// -------------------------------------------------------------------------

	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, 1 );
	}

	public static function include_verses() {
		$val = get_option( self::OPTION_INCL_VERSES, 1 );
		return (bool) apply_filters( 'bdwp70_sitemap_include_verses', $val );
	}

	public static function per_page() {
		$val = absint( get_option( self::OPTION_PER_PAGE, 2000 ) );
		return max( 100, min( 50000, $val ) );
	}

	public function get_lastmod() {
		$lastmod = get_option( self::OPTION_LASTMOD, '' );
		if ( ! $lastmod || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $lastmod ) ) {
			$lastmod = current_time( 'Y-m-d' );
			update_option( self::OPTION_LASTMOD, $lastmod );
		}
		return $lastmod;
	}

	// -------------------------------------------------------------------------
	// Rewrite rules
	// -------------------------------------------------------------------------

	public function register_rewrite() {
		$base = $this->sitemap_base();

		add_rewrite_rule(
			'^' . preg_quote( $base, '/' ) . '-sitemap\.xml$',
			'index.php?' . self::QUERY_VAR . '=index',
			'top'
		);

		add_rewrite_rule(
			'^' . preg_quote( $base, '/' ) . '-sitemap-([0-9]+)(?:-p([0-9]+))?\.xml$',
			'index.php?' . self::QUERY_VAR . '=book&' . self::QUERY_VAR_BOOK . '=$matches[1]&' . self::QUERY_VAR_PAGE . '=$matches[2]',
			'top'
		);
	}

	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::QUERY_VAR_BOOK;
		$vars[] = self::QUERY_VAR_PAGE;
		return $vars;
	}

	/**
	 * Intercepta diretamente as rotas do sitemap antes do fluxo normal de template.
	 *
	 * Em alguns ambientes, plugins de SEO/cache também registram padrões *-sitemap.xml.
	 * Quando isso ocorre, /biblia-digital-sitemap.xml pode ser tratado como um
	 * sitemap externo e listar apenas a própria URL. Esta interceptação usa a URI
	 * real da requisição e entrega o XML do plugin antes desses conflitos.
	 *
	 * @return void
	 */
	public function maybe_serve_direct_sitemap_request() {
		$match = $this->match_sitemap_request_uri();

		if ( ! $match ) {
			return;
		}

		// Modo debug para administradores.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['bdwp70_debug_sitemap'] ) && current_user_can( 'manage_options' ) ) {
			$this->serve_debug();
			return;
		}

		do_action( 'bdwp70_sitemap_before_output' );

		if ( 'index' === $match['type'] ) {
			$this->serve_index();
		}

		if ( 'book' === $match['type'] ) {
			$this->serve_book_sitemap( $match['book'], $match['page'] );
		}
	}

	/**
	 * Impede redirect_canonical nas rotas XML do sitemap dedicado.
	 *
	 * @param string|false $redirect_url  URL sugerida pelo WordPress.
	 * @param string       $requested_url URL requisitada.
	 * @return string|false
	 */
	public function disable_canonical_for_sitemap( $redirect_url, $requested_url ) {
		if ( $this->match_sitemap_request_uri() ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Identifica as rotas do sitemap dedicado a partir de REQUEST_URI.
	 *
	 * @return array|false
	 */
	private function match_sitemap_request_uri() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$path = rawurldecode( $path );
		$path = '/' . ltrim( $path, '/' );

		// Compatibilidade com WordPress instalado em subdiretório.
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = '/' . trim( $home_path, '/' );

		if ( '/' !== $home_path && 0 === stripos( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		$path  = trim( $path, '/' );
		$bases = array_unique( array_filter( array( $this->sitemap_base(), 'biblia-digital' ) ) );

		// Rota alternativa sem o padrão "*-sitemap.xml" para diagnóstico/escape
		// quando algum plugin de SEO captura a rota tradicional antes do plugin.
		if ( preg_match( '#^bdwp70-bible-index\.xml$#i', $path ) ) {
			return array(
				'type' => 'index',
				'book' => 0,
				'page' => 1,
			);
		}

		foreach ( $bases as $raw_base ) {
			$base = preg_quote( sanitize_title( $raw_base ), '#' );

			if ( preg_match( '#^' . $base . '-sitemap\.xml$#i', $path ) ) {
				return array(
					'type' => 'index',
					'book' => 0,
					'page' => 1,
				);
			}

			if ( preg_match( '#^' . $base . '-sitemap-([0-9]+)(?:-p([0-9]+))?\.xml$#i', $path, $matches ) ) {
				return array(
					'type' => 'book',
					'book' => absint( $matches[1] ),
					'page' => isset( $matches[2] ) ? max( 1, absint( $matches[2] ) ) : 1,
				);
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Dispatcher
	// -------------------------------------------------------------------------

	public function maybe_serve_sitemap() {
		$type = get_query_var( self::QUERY_VAR );
		if ( ! $type ) {
			return;
		}

		// Modo debug para administradores (Correção 6).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['bdwp70_debug_sitemap'] ) && current_user_can( 'manage_options' ) ) {
			$this->serve_debug();
			return;
		}

		do_action( 'bdwp70_sitemap_before_output' );

		if ( 'index' === $type ) {
			$this->serve_index();
		} elseif ( 'book' === $type ) {
			$book = absint( get_query_var( self::QUERY_VAR_BOOK ) );
			$page = max( 1, absint( get_query_var( self::QUERY_VAR_PAGE ) ) );
			$this->serve_book_sitemap( $book, $page );
		}
	}

	// -------------------------------------------------------------------------
	// Debug para administradores (Correção 6)
	// -------------------------------------------------------------------------

	private function serve_debug() {
		global $wpdb;

		$active_id      = absint( $this->plugin->site_active_bible_id() );
		$sitemap_id     = $this->sitemap_bible_id();
		$books          = $sitemap_id > 0 ? $this->count_books_for_bible( $sitemap_id ) : 0;
		$chapters       = $sitemap_id > 0 ? $this->count_chapters_total_for_book( $sitemap_id ) : 0;
		$verses         = $sitemap_id > 0 ? $this->count_verses_total( $sitemap_id ) : 0;
		$book_seqs      = $sitemap_id > 0 ? $this->get_book_sequences_with_content( $sitemap_id ) : array();
		$total_seqs     = count( $book_seqs );

		// Calcula quantos sub-sitemaps o índice terá (1 raiz + N páginas por livro).
		$expected_children = $sitemap_id > 0 ? 1 : 0; // sitemap-0.xml
		foreach ( $book_seqs as $seq ) {
			$expected_children += $this->pages_for_book( absint( $seq ), $sitemap_id );
		}

		$info = array(
			'sitemap_enabled'                   => self::is_enabled() ? 'yes' : 'no',
			'include_verses'                    => self::include_verses() ? 'yes' : 'no',
			'per_page'                          => self::per_page(),
			'lastmod'                           => $this->get_lastmod(),
			'active_bible_id'                   => $active_id,
			'sitemap_bible_id'                  => $sitemap_id,
			'count_books'                       => $books,
			'count_chapters'                    => $chapters,
			'count_verses'                      => $verses,
			'book_sequences_with_content'       => implode( ',', $book_seqs ),
			'total_book_sequences_with_content' => $total_seqs,
			'expected_index_children'           => $expected_children,
			'last_sql_error'                    => $wpdb->last_error ? $wpdb->last_error : 'none',
		);

		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
		}

		foreach ( $info as $key => $value ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo esc_html( $key ) . ': ' . esc_html( (string) $value ) . "\n";
		}
		exit;
	}

	// -------------------------------------------------------------------------
	// Sitemap index
	// -------------------------------------------------------------------------

	private function serve_index() {
		$this->send_xml( $this->build_index_xml() );
	}

	/**
	 * Monta o XML do sitemap index dedicado.
	 *
	 * Este método é usado tanto pela rota dinâmica quanto pelo arquivo físico
	 * de fallback em ABSPATH/{base}-sitemap.xml. O índice não depende de XSL,
	 * não aponta para si mesmo e lista apenas os sub-sitemaps reais.
	 *
	 * @return string
	 */
	private function build_index_xml() {
		$bible_id = $this->sitemap_bible_id();
		$lastmod  = $this->get_lastmod();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= $this->xsl_pi();
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		if ( $bible_id > 0 ) {
			// Sub-sitemap 0 = URL raiz/canônica da Bíblia ativa.
			$xml .= $this->sitemap_entry( $this->sitemap_child_url( 0, 1 ), $lastmod );

			$book_sequences = $this->get_book_sequences_with_content( $bible_id );
			foreach ( $book_sequences as $seq ) {
				$seq = absint( $seq );
				if ( $seq < 1 || $seq > 66 ) {
					continue;
				}

				$pages = $this->pages_for_book( $seq, $bible_id );
				for ( $p = 1; $p <= $pages; $p++ ) {
					$xml .= $this->sitemap_entry( $this->sitemap_child_url( $seq, $p ), $lastmod );
				}
			}
		}

		$xml .= '</sitemapindex>';
		return $xml;
	}

	/**
	 * URL absoluta de um sub-sitemap dedicado.
	 *
	 * @param int $book_seq Livro 0 = raiz; 1-66 = livro bíblico.
	 * @param int $page Página interna do sub-sitemap.
	 * @return string
	 */
	private function sitemap_child_url( $book_seq, $page = 1 ) {
		$book_seq = absint( $book_seq );
		$page     = max( 1, absint( $page ) );
		$suffix   = $page > 1 ? '-p' . $page : '';

		return home_url( '/' . $this->sitemap_base() . '-sitemap-' . $book_seq . $suffix . '.xml' );
	}

	/**
	 * Atualiza, quando possível, um arquivo físico do índice dedicado na raiz.
	 *
	 * A rota dinâmica continua existindo, mas o arquivo físico é servido pelo
	 * servidor web antes de qualquer plugin de SEO/cache. Isso resolve o caso em
	 * que /biblia-digital-sitemap.xml é capturado por outro componente e passa a
	 * listar apenas a própria URL.
	 *
	 * @return void
	 */
	public function maybe_refresh_static_index_file() {
		if ( ! self::is_enabled() ) {
			return;
		}

		/*
		 * Performance 1.1.66:
		 * A assinatura do sitemap pode acionar consultas ao banco. Antes, ela era
		 * calculada em toda requisição pública no init. Agora a verificação física
		 * é limitada por transient curto; alterações relevantes invalidam o lastmod
		 * e a rota dinâmica continua respondendo imediatamente.
		 */
		$throttle_key = 'bdwp70_sitemap_static_checked_' . md5( home_url( '/' ) . '|' . $this->sitemap_base() );
		if ( ! is_admin() && ! wp_doing_cron() && false !== get_transient( $throttle_key ) ) {
			return;
		}
		set_transient( $throttle_key, 1, HOUR_IN_SECONDS );

		$signature = $this->static_index_signature();
		$stored    = (string) get_option( self::OPTION_STATIC_SIG, '' );
		$file      = $this->static_index_path();

		if ( $stored === $signature && is_readable( $file ) && filesize( $file ) > 50 ) {
			return;
		}

		$this->write_static_index_file( $signature );
	}

	/**
	 * Assinatura simples para evitar regravar o arquivo físico a cada requisição.
	 *
	 * @return string
	 */
	private function static_index_signature() {
		$parts = array(
			BDWP70_VERSION,
			$this->get_lastmod(),
			$this->sitemap_base(),
			$this->sitemap_bible_id(),
			self::include_verses() ? '1' : '0',
			self::per_page(),
		);

		return md5( implode( '|', array_map( 'strval', $parts ) ) );
	}

	/**
	 * Caminho absoluto do arquivo físico do índice.
	 *
	 * @return string
	 */
	private function static_index_path() {
		return trailingslashit( ABSPATH ) . $this->sitemap_base() . '-sitemap.xml';
	}

	/**
	 * Grava o arquivo físico do índice, se o ambiente permitir.
	 *
	 * @param string $signature Assinatura calculada.
	 * @return bool
	 */
	private function write_static_index_file( $signature ) {
		$file = $this->static_index_path();
		$xml  = $this->build_index_xml();

		if ( '' === trim( $xml ) || false === strpos( $xml, '<sitemapindex' ) ) {
			return false;
		}

		// Evita sobrescrever arquivo suspeito que não foi criado pelo plugin.
		if ( file_exists( $file ) ) {
			$current = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_string( $current ) && '' !== $current && false === strpos( $current, 'BDWP70_STATIC_SITEMAP_INDEX' ) ) {
				update_option(
					self::OPTION_STATIC_INFO,
					'Arquivo físico não sobrescrito porque já existe e não foi identificado como índice da Bíblia Digital: ' . $file
				);
				return false;
			}
		}

		$xml = str_replace(
			'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
			"<!-- BDWP70_STATIC_SITEMAP_INDEX -->\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
			$xml
		);

		$result = file_put_contents( $file, $xml, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $result ) {
			update_option( self::OPTION_STATIC_INFO, 'Não foi possível gravar o índice físico em: ' . $file );
			return false;
		}

		update_option( self::OPTION_STATIC_SIG, $signature );
		update_option( self::OPTION_STATIC_INFO, 'Índice físico atualizado em: ' . $file );
		return true;
	}

	// -------------------------------------------------------------------------
	// Sub-sitemap por livro — paginação lógica por blocos
	// -------------------------------------------------------------------------

	private function serve_book_sitemap( $book_seq, $page = 1 ) {
		$bible_id   = $this->sitemap_bible_id();
		$lastmod    = $this->get_lastmod();
		$changefreq = (string) apply_filters( 'bdwp70_sitemap_changefreq', 'monthly' );
		$priority   = (string) apply_filters( 'bdwp70_sitemap_priority', '0.8' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= $this->xsl_pi();
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		if ( 0 === $book_seq ) {
			if ( $bible_id > 0 ) {
				$xml .= $this->url_entry(
					$this->bible_root_url( $bible_id ),
					'weekly',
					'1.0',
					$lastmod
				);
			}
			$xml .= '</urlset>';
			$this->send_xml( $xml );
			return;
		}

		if ( $bible_id < 1 ) {
			$xml .= '</urlset>';
			$this->send_xml( $xml );
			return;
		}

		$books       = $this->get_sitemap_books( $bible_id );
		$per_page    = self::per_page();
		$incl_verses = self::include_verses();
		$book_slug   = $this->plugin->book_slug_from_seq( $book_seq, $books );

		$chapter_count = $this->count_chapters( $book_seq, $bible_id );
		$verse_count   = $incl_verses ? $this->count_verses_for_book( $book_seq, $bible_id ) : 0;

		$offset    = ( $page - 1 ) * $per_page;
		$remaining = $per_page;

		// ── Bloco A: URL do livro (Correção 2: somente página 1) ────────────
		$block_a_total = ( $book_slug ) ? 1 : 0;
		if ( $block_a_total > 0 ) {
			if ( $offset === 0 && $remaining > 0 ) {
				$book_url  = home_url( user_trailingslashit( $this->plugin->seo_base() . '/' . $book_slug ) );
				$book_url  = $this->plugin->maybe_add_bible_version_arg_to_url( $book_url, $bible_id );
				$xml      .= $this->url_entry( $book_url, 'monthly', '0.9', $lastmod );
				$remaining--;
			} elseif ( $offset > 0 ) {
				$offset -= $block_a_total;
			}
		}

		// ── Bloco B: capítulos via LIMIT/OFFSET ──────────────────────────────
		if ( $remaining > 0 && $chapter_count > 0 ) {
			$b_offset = max( 0, $offset );
			if ( $b_offset < $chapter_count ) {
				$b_limit    = min( $remaining, $chapter_count - $b_offset );
				$b_chapters = $this->get_chapters_paged( $book_seq, $bible_id, $b_limit, $b_offset );
				foreach ( $b_chapters as $chapter ) {
					$chapter_url = $this->plugin->chapter_url( $book_seq, $chapter, $books, $bible_id );
					$xml        .= $this->url_entry( $chapter_url, $changefreq, $priority, $lastmod );
					$remaining--;
				}
				$offset = 0;
			} else {
				$offset -= $chapter_count;
			}
		} else {
			$offset = max( 0, $offset - $chapter_count );
		}

		// ── Bloco C: versículos via LIMIT/OFFSET ─────────────────────────────
		if ( $incl_verses && $remaining > 0 && $verse_count > 0 ) {
			$c_offset = max( 0, $offset );
			if ( $c_offset < $verse_count ) {
				$c_limit  = min( $remaining, $verse_count - $c_offset );
				$c_verses = $this->get_verses_paged( $book_seq, $bible_id, $c_limit, $c_offset );
				foreach ( $c_verses as $row ) {
					$verse_url = $this->plugin->verse_url(
						(int) $row->livroseq,
						(int) $row->capitulo,
						(int) $row->versiculo,
						$books,
						$bible_id
					);
					$xml      .= $this->url_entry( $verse_url, $changefreq, '0.6', $lastmod );
					$remaining--;
				}
			}
		}

		$xml .= '</urlset>';
		$this->send_xml( $xml );
	}

	// -------------------------------------------------------------------------
	// Resolução do bible_id (Correções 1, 2, 3)
	// -------------------------------------------------------------------------

	/**
	 * Retorna o bible_id com conteúdo real para uso no sitemap.
	 *
	 * 1. Tenta a Bíblia ativa configurada no admin.
	 * 2. Se ela não tiver livros+versículos publicados, busca fallback.
	 * 3. Se não houver nenhuma, retorna 0 (sitemap vazio válido).
	 *
	 * @return int
	 */
	private function sitemap_bible_id() {
		$key    = $this->cache_key( 'sitemap_bible_id' );
		$cached = $this->cache_get( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$active_id = absint( $this->plugin->site_active_bible_id() );

		if ( $active_id > 0 && $this->bible_has_content( $active_id ) ) {
			$this->cache_set( $key, $active_id );
			return $active_id;
		}

		$fallback_id = $this->find_first_bible_with_content();

		$this->cache_set( $key, $fallback_id );
		return $fallback_id;
	}

	/**
	 * Verifica se uma Bíblia tem livros e versículos publicados.
	 *
	 * @param int $bible_id ID da Bíblia.
	 * @return bool
	 */
	private function bible_has_content( $bible_id ) {
		$bible_id = absint( $bible_id );
		if ( $bible_id < 1 ) {
			return false;
		}
		return $this->count_books_for_bible( $bible_id ) > 0
			&& $this->count_verses_total( $bible_id ) > 0;
	}

	/**
	 * Busca a primeira Bíblia importada com livros e versículos publicados.
	 *
	 * @return int bible_id ou 0 se não houver.
	 */
	private function find_first_bible_with_content() {
		global $wpdb;

		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, 0 );

		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$bible_id = (int) $wpdb->get_var(
				'SELECT bible_id FROM `' . esc_sql( $table ) . '`
				 WHERE bible_id > 0 AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0 AND published = 1
				 GROUP BY bible_id
				 HAVING COUNT(*) > 0
				 ORDER BY bible_id ASC
				 LIMIT 1'
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$bible_id = (int) $wpdb->get_var(
				'SELECT bible_id FROM `' . esc_sql( $table ) . '`
				 WHERE bible_id > 0 AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0
				 GROUP BY bible_id
				 HAVING COUNT(*) > 0
				 ORDER BY bible_id ASC
				 LIMIT 1'
			);
		}

		return $bible_id > 0 ? $bible_id : 0;
	}

	/**
	 * Compatibilidade: active_bible_id()	/**
	 * Compatibilidade: active_bible_id() agora delega para sitemap_bible_id().
	 *
	 * @return int
	 */
	private function active_bible_id() {
		return $this->sitemap_bible_id();
	}

	// -------------------------------------------------------------------------
	// Cálculo de páginas
	// -------------------------------------------------------------------------

	private function pages_for_book( $book_seq, $bible_id ) {
		$per_page    = self::per_page();
		$incl_verses = self::include_verses();
		$books       = $this->get_sitemap_books( $bible_id );
		$book_slug   = $this->plugin->book_slug_from_seq( $book_seq, $books );

		$total  = $book_slug ? 1 : 0;
		$total += $this->count_chapters( $book_seq, $bible_id );

		if ( $incl_verses ) {
			$total += $this->count_verses_for_book( $book_seq, $bible_id );
		}

		return max( 1, (int) ceil( $total / $per_page ) );
	}

	// -------------------------------------------------------------------------
	// Contagens com cache — com chave versionada pelo lastmod (Correção 5)
	// -------------------------------------------------------------------------

	/**
	 * Chave de cache versionada pelo lastmod.
	 * Quando o lastmod muda (importação, troca de Bíblia), o cache expira.
	 */
	private function cache_key( $base ) {
		$lastmod = get_option( self::OPTION_LASTMOD, 'x' );
		return 'bdwp70_sitemap_' . $base . '_' . substr( md5( $lastmod ), 0, 8 );
	}


	/**
	 * Lê cache em duas camadas: object cache quando disponível e transient persistente.
	 * Isso reduz consultas repetidas ao sitemap em hospedagens sem object cache persistente.
	 *
	 * @param string $key Chave já versionada pelo lastmod.
	 * @return mixed
	 */
	private function cache_get( $key ) {
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$cached = get_transient( $key );
		if ( false !== $cached ) {
			wp_cache_set( $key, $cached, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return $cached;
	}

	/**
	 * Grava cache em duas camadas.
	 *
	 * @param string $key Chave já versionada pelo lastmod.
	 * @param mixed  $value Valor a armazenar.
	 * @return void
	 */
	private function cache_set( $key, $value ) {
		wp_cache_set( $key, $value, self::CACHE_GROUP, self::CACHE_TTL );
		set_transient( $key, $value, self::CACHE_TTL );
	}

	private function count_books_for_bible( $bible_id ) {
		global $wpdb;

		$bible_id = absint( $bible_id );
		$key      = $this->cache_key( 'bkcnt_' . $bible_id );
		$cached   = $this->cache_get( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$books = $this->get_sitemap_books( $bible_id );
		$count = count( $books );

		$this->cache_set( $key, $count );
		return $count;
	}

	private function count_chapters( $book_seq, $bible_id ) {
		global $wpdb;

		$book_seq = absint( $book_seq );
		$bible_id = absint( $bible_id );
		$key      = $this->cache_key( 'chapters_' . $bible_id . '_' . $book_seq );
		$cached   = $this->cache_get( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id, $book_seq );
		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT capitulo) FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq = %d AND capitulo > 0 AND versiculo > 0 AND published = 1',
					$bible_id,
					$book_seq
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT capitulo) FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq = %d AND capitulo > 0 AND versiculo > 0',
					$bible_id,
					$book_seq
				)
			);
		}

		$this->cache_set( $key, $count );
		return $count;
	}

	private function count_chapters_total_for_book( $bible_id ) {
		global $wpdb;

		$bible_id = absint( $bible_id );
		$key      = $this->cache_key( 'chtotal_' . $bible_id );
		$cached   = $this->cache_get( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id );
		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT livroseq, capitulo) FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0 AND published = 1',
					$bible_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT livroseq, capitulo) FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0',
					$bible_id
				)
			);
		}

		$this->cache_set( $key, $count );
		return $count;
	}

	private function count_verses_for_book( $book_seq, $bible_id ) {
		global $wpdb;

		$book_seq = absint( $book_seq );
		$bible_id = absint( $bible_id );
		$key      = $this->cache_key( 'verses_' . $bible_id . '_' . $book_seq );
		$cached   = $this->cache_get( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id, $book_seq );
		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq = %d AND capitulo > 0 AND versiculo > 0 AND published = 1',
					$bible_id,
					$book_seq
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq = %d AND capitulo > 0 AND versiculo > 0',
					$bible_id,
					$book_seq
				)
			);
		}

		$this->cache_set( $key, $count );
		return $count;
	}

	private function count_verses_total( $bible_id ) {
		global $wpdb;

		$bible_id = absint( $bible_id );
		$key      = $this->cache_key( 'vtotal_' . $bible_id );
		$cached   = $this->cache_get( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id );
		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0 AND published = 1',
					$bible_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0',
					$bible_id
				)
			);
		}

		$this->cache_set( $key, $count );
		return $count;
	}

	// -------------------------------------------------------------------------
	// Queries paginadas	// -------------------------------------------------------------------------
	// Queries paginadas com LIMIT/OFFSET reais
	// -------------------------------------------------------------------------

	private function get_chapters_paged( $book_seq, $bible_id, $limit, $offset ) {
		global $wpdb;

		$bible_id       = absint( $bible_id );
		$book_seq       = absint( $book_seq );
		$limit          = absint( $limit );
		$offset         = absint( $offset );
		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id, $book_seq );
		$key            = $this->cache_key( 'chpaged_' . $bible_id . '_' . $book_seq . '_' . $limit . '_' . $offset . '_' . ( $published_only ? 'p' : 'a' ) );
		$cached         = $this->cache_get( $key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? array_map( 'intval', $cached ) : array();
		}

		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT capitulo FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq = %d AND capitulo > 0 AND versiculo > 0 AND published = 1
					 ORDER BY capitulo ASC
					 LIMIT %d OFFSET %d',
					$bible_id,
					$book_seq,
					$limit,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT capitulo FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq = %d AND capitulo > 0 AND versiculo > 0
					 ORDER BY capitulo ASC
					 LIMIT %d OFFSET %d',
					$bible_id,
					$book_seq,
					$limit,
					$offset
				)
			);
		}

		$rows = is_array( $rows ) ? array_map( 'intval', $rows ) : array();
		$this->cache_set( $key, $rows );
		return $rows;
	}

	private function get_verses_paged( $book_seq, $bible_id, $limit, $offset ) {
		global $wpdb;

		$bible_id       = absint( $bible_id );
		$book_seq       = absint( $book_seq );
		$limit          = absint( $limit );
		$offset         = absint( $offset );
		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id, $book_seq );
		$key            = $this->cache_key( 'vpaged_' . $bible_id . '_' . $book_seq . '_' . $limit . '_' . $offset . '_' . ( $published_only ? 'p' : 'a' ) );
		$cached         = $this->cache_get( $key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT livroseq, capitulo, versiculo FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq = %d AND capitulo > 0 AND versiculo > 0 AND published = 1
					 ORDER BY capitulo ASC, versiculo ASC
					 LIMIT %d OFFSET %d',
					$bible_id,
					$book_seq,
					$limit,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT livroseq, capitulo, versiculo FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq = %d AND capitulo > 0 AND versiculo > 0
					 ORDER BY capitulo ASC, versiculo ASC
					 LIMIT %d OFFSET %d',
					$bible_id,
					$book_seq,
					$limit,
					$offset
				)
			);
		}

		$rows = is_array( $rows ) ? $rows : array();
		$this->cache_set( $key, $rows );
		return $rows;
	}


	/**
	 * Retorna os números	/**
	 * Retorna os números de sequência dos livros que têm versículos reais na Bíblia.
	 *
	 * Consulta diretamente a tabela de versículos — independe de get_sitemap_books()
	 * e não falha se a tabela de livros estiver incompleta ou vazia.
	 *
	 * @param int $bible_id ID da Bíblia.
	 * @return int[]  Array de livroseq (1-66), ordenados.
	 */
	private function get_book_sequences_with_content( $bible_id ) {
		global $wpdb;

		$bible_id = absint( $bible_id );
		if ( $bible_id < 1 ) {
			return array();
		}

		$key    = $this->cache_key( 'bkseqs_' . $bible_id );
		$cached = $this->cache_get( $key );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id );
		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT livroseq
					 FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d
					   AND livroseq BETWEEN 1 AND 66
					   AND capitulo > 0
					   AND versiculo > 0
					   AND published = 1
					 ORDER BY livroseq ASC',
					$bible_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT livroseq
					 FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d
					   AND livroseq BETWEEN 1 AND 66
					   AND capitulo > 0
					   AND versiculo > 0
					 ORDER BY livroseq ASC',
					$bible_id
				)
			);
		}

		$result = is_array( $rows ) ? array_map( 'absint', $rows ) : array();
		$this->cache_set( $key, $result );
		return $result;
	}

	/**
	 * Retorna livros para o sitemap	/**
	 * Retorna livros para o sitemap com fallback robusto.
	 *
	 * O sitemap não pode ficar vazio apenas porque a tabela de livros está
	 * incompleta, porque as referências também existem na tabela de versículos.
	 *
	 * @param int $bible_id ID da Bíblia.
	 * @return object[]
	 */
	private function get_sitemap_books( $bible_id ) {
		global $wpdb;

		$bible_id = absint( $bible_id );
		if ( $bible_id < 1 ) {
			return array();
		}

		$key    = $this->cache_key( 'books_' . $bible_id );
		$cached = $this->cache_get( $key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		$books = $this->plugin->get_books( $bible_id );
		if ( ! empty( $books ) ) {
			$books = $this->normalize_sitemap_books( $books );
			$this->cache_set( $key, $books );
			return $books;
		}

		$books_table = BDWP70_Activator::books_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$books = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT livro_seq, livro, livro_desc FROM `' . esc_sql( $books_table ) . '`
				 WHERE bible_id = %d AND livro_seq BETWEEN 1 AND 66
				 ORDER BY livro_seq ASC',
				$bible_id
			)
		);
		if ( ! empty( $books ) ) {
			$books = $this->normalize_sitemap_books( $books );
			$this->cache_set( $key, $books );
			return $books;
		}

		$verses_table   = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $verses_table, $bible_id );
		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT livroseq AS livro_seq, MIN(livro) AS livro, MIN(livro) AS livro_desc
					 FROM `' . esc_sql( $verses_table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0 AND published = 1
					 GROUP BY livroseq
					 ORDER BY livroseq ASC',
					$bible_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT livroseq AS livro_seq, MIN(livro) AS livro, MIN(livro) AS livro_desc
					 FROM `' . esc_sql( $verses_table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0
					 GROUP BY livroseq
					 ORDER BY livroseq ASC',
					$bible_id
				)
			);
		}

		$books = $this->normalize_sitemap_books( is_array( $rows ) ? $rows : array() );
		$this->cache_set( $key, $books );
		return $books;
	}

	/**
	 * Normaliza objetos de livros	/**
	 * Normaliza objetos de livros e preenche nomes ausentes com a ordem protestante.
	 *
	 * @param object[] $books Livros brutos.
	 * @return object[]
	 */
	private function normalize_sitemap_books( $books ) {
		$normalized = array();
		$seen       = array();

		foreach ( (array) $books as $book ) {
			$seq = isset( $book->livro_seq ) ? absint( $book->livro_seq ) : 0;
			if ( $seq < 1 || $seq > 66 || isset( $seen[ $seq ] ) ) {
				continue;
			}
			$seen[ $seq ] = true;

			$desc = isset( $book->livro_desc ) ? trim( (string) $book->livro_desc ) : '';
			$abbr = isset( $book->livro ) ? trim( (string) $book->livro ) : '';
			if ( '' === $desc ) {
				$desc = $this->canonical_book_name( $seq );
			}
			if ( '' === $abbr ) {
				$abbr = $desc;
			}

			$normalized[] = (object) array(
				'livro_seq'  => $seq,
				'livro'      => $abbr,
				'livro_desc' => $desc,
			);
		}

		usort(
			$normalized,
			static function ( $a, $b ) {
				return (int) $a->livro_seq <=> (int) $b->livro_seq;
			}
		);

		return $normalized;
	}

	/**
	 * Decide se consultas do sitemap devem restringir published = 1.
	 *
	 * Se houver linhas publicadas, usa published = 1. Se a coluna existir, mas todas
	 * as linhas antigas estiverem com 0, não bloqueia o sitemap.
	 *
	 * @param string $table Tabela de versículos.
	 * @param int    $bible_id ID da Bíblia; 0 para consulta global.
	 * @param int    $book_seq Livro; 0 para todos.
	 * @return bool
	 */
	private function should_filter_published( $table, $bible_id = 0, $book_seq = 0 ) {
		global $wpdb;

		$bible_id = absint( $bible_id );
		$book_seq = absint( $book_seq );

		if ( ! $this->table_has_column( $table, 'published' ) ) {
			return false;
		}

		$key    = $this->cache_key( 'pub_' . md5( $table . ':' . $bible_id . ':' . $book_seq ) );
		$cached = $this->cache_get( $key );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		if ( $bible_id > 0 && $book_seq > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d AND livroseq = %d AND published = 1',
					$bible_id,
					$book_seq
				)
			);
		} elseif ( $bible_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d AND published = 1',
					$bible_id
				)
			);
		} elseif ( $book_seq > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE livroseq = %d AND published = 1',
					$book_seq
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE published = 1' );
		}

		$use_published = $count > 0;
		$this->cache_set( $key, $use_published );
		return $use_published;
	}

	/**
	 * Verifica existência de coluna	/**
	 * Verifica existência de coluna com cache local.
	 *
	 * @param string $table Tabela.
	 * @param string $column Coluna.
	 * @return bool
	 */
	private function table_has_column( $table, $column ) {
		global $wpdb;
		static $cache = array();

		$key = $table . ':' . $column;
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s',
				$column
			)
		);

		$cache[ $key ] = ! empty( $exists );
		return $cache[ $key ];
	}

	/**
	 * Nome canônico dos 66 livros em ordem protestante.
	 *
	 * @param int $seq Sequência do livro.
	 * @return string
	 */
	private function canonical_book_name( $seq ) {
		$names = array(
			1 => 'Gênesis', 2 => 'Êxodo', 3 => 'Levítico', 4 => 'Números', 5 => 'Deuteronômio',
			6 => 'Josué', 7 => 'Juízes', 8 => 'Rute', 9 => '1 Samuel', 10 => '2 Samuel',
			11 => '1 Reis', 12 => '2 Reis', 13 => '1 Crônicas', 14 => '2 Crônicas', 15 => 'Esdras',
			16 => 'Neemias', 17 => 'Ester', 18 => 'Jó', 19 => 'Salmos', 20 => 'Provérbios',
			21 => 'Eclesiastes', 22 => 'Cânticos', 23 => 'Isaías', 24 => 'Jeremias', 25 => 'Lamentações',
			26 => 'Ezequiel', 27 => 'Daniel', 28 => 'Oséias', 29 => 'Joel', 30 => 'Amós',
			31 => 'Obadias', 32 => 'Jonas', 33 => 'Miquéias', 34 => 'Naum', 35 => 'Habacuque',
			36 => 'Sofonias', 37 => 'Ageu', 38 => 'Zacarias', 39 => 'Malaquias', 40 => 'Mateus',
			41 => 'Marcos', 42 => 'Lucas', 43 => 'João', 44 => 'Atos', 45 => 'Romanos',
			46 => '1 Coríntios', 47 => '2 Coríntios', 48 => 'Gálatas', 49 => 'Efésios', 50 => 'Filipenses',
			51 => 'Colossenses', 52 => '1 Tessalonicenses', 53 => '2 Tessalonicenses', 54 => '1 Timóteo', 55 => '2 Timóteo',
			56 => 'Tito', 57 => 'Filemom', 58 => 'Hebreus', 59 => 'Tiago', 60 => '1 Pedro',
			61 => '2 Pedro', 62 => '1 João', 63 => '2 João', 64 => '3 João', 65 => 'Judas', 66 => 'Apocalipse',
		);

		$seq = absint( $seq );
		return isset( $names[ $seq ] ) ? $names[ $seq ] : 'Livro ' . $seq;
	}

	// -------------------------------------------------------------------------
	// URL raiz padronizada com versão
	// -------------------------------------------------------------------------

	private function bible_root_url( $bible_id ) {
		$url = home_url( user_trailingslashit( $this->plugin->seo_base() ) );
		if ( method_exists( $this->plugin, 'maybe_add_bible_version_arg_to_url' ) ) {
			$url = $this->plugin->maybe_add_bible_version_arg_to_url( $url, $bible_id );
		}
		return $url;
	}

	// -------------------------------------------------------------------------
	// Helpers de XML — esc_url() / esc_xml()
	// -------------------------------------------------------------------------

	private function sitemap_entry( $loc, $lastmod ) {
		return "\t<sitemap>\n"
			. "\t\t<loc>" . esc_url( $loc ) . "</loc>\n"
			. "\t\t<lastmod>" . esc_xml( $lastmod ) . "</lastmod>\n"
			. "\t</sitemap>\n";
	}

	private function url_entry( $loc, $changefreq = 'monthly', $priority = '0.8', $lastmod = '' ) {
		if ( ! $lastmod ) {
			$lastmod = $this->get_lastmod();
		}
		return "\t<url>\n"
			. "\t\t<loc>" . esc_url( $loc ) . "</loc>\n"
			. "\t\t<lastmod>" . esc_xml( $lastmod ) . "</lastmod>\n"
			. "\t\t<changefreq>" . esc_xml( $changefreq ) . "</changefreq>\n"
			. "\t\t<priority>" . esc_xml( $priority ) . "</priority>\n"
			. "\t</url>\n";
	}

	private function xsl_pi() {
		/**
		 * Permite desativar a camada visual XSL sem alterar o XML.
		 *
		 * O padrão agora é ativo porque o XSL é próprio do plugin e não usa
		 * a folha visual do WordPress/Rank Math. Crawlers continuam lendo o XML
		 * normalmente, pois a processing instruction é apenas apresentação.
		 *
		 * @param bool $use_xsl Se deve imprimir xml-stylesheet.
		 */
		$use_xsl = (bool) apply_filters( 'bdwp70_sitemap_use_xsl', true );

		if ( ! $use_xsl ) {
			return '';
		}

		$xsl_url = plugins_url( 'assets/xsl/bdwp70-sitemap.xsl', BDWP70_FILE );

		return '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
	}

	private function send_xml( $xml ) {
		global $wp_query;
		if ( $wp_query ) {
			$wp_query->is_404 = false;
		}
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: application/xml; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex' );
			header( 'X-Biblia-Digital-Sitemap: 1' );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml;
		exit;
	}

	// -------------------------------------------------------------------------
	// Helpers gerais
	// -------------------------------------------------------------------------

	private function sitemap_base() {
		return $this->plugin->seo_base();
	}

	public function sitemap_url() {
		return home_url( '/' . $this->sitemap_base() . '-sitemap.xml' );
	}

	// -------------------------------------------------------------------------
	// Robots.txt
	// -------------------------------------------------------------------------

	public function robots_txt( $output, $public ) {
		if ( ! $public || ! self::is_enabled() ) {
			return $output;
		}
		$url = $this->sitemap_url();
		if ( false !== strpos( $output, $url ) ) {
			return $output;
		}
		$output .= "\n# Biblia Digital Sitemap\nSitemap: " . esc_url_raw( $url ) . "\n";
		return $output;
	}
}
