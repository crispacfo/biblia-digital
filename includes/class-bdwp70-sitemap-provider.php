<?php
/**
 * WP Sitemap API provider para a Bíblia Digital — v1.1.53
 *
 * Integra as URLs da Bíblia ao /wp-sitemap.xml do WordPress.
 *
 * Paginação lógica por blocos:
 *   Bloco A – URL raiz da Bíblia  (1 URL)
 *   Bloco B – URLs dos livros     (N URLs)
 *   Bloco C – URLs dos capítulos  (K URLs)
 *   Bloco D – URLs dos versículos (M URLs, se include_verses = true)
 *
 * Resolução segura do bible_id: usa a Bíblia ativa se ela tiver conteúdo;
 * caso contrário, busca fallback com livros e versículos publicados reais.
 *
 * @package BibliaDigital
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'BDWP70_Sitemap_Provider', false ) ) {
	return;
}

if ( ! class_exists( 'WP_Sitemaps_Provider' ) ) {
	return;
}

class BDWP70_Sitemap_Provider extends WP_Sitemaps_Provider {

	const CACHE_GROUP = 'bdwp70_sitemap';
	const CACHE_TTL   = 43200; // 12 h

	/** @var BDWP70_Plugin */
	private $plugin;

	public function __construct( $plugin ) {
		$this->plugin      = $plugin;
		$this->name        = 'biblia-digital';
		$this->object_type = 'biblia-digital';
	}

	// =========================================================================
	// WP Sitemaps API — métodos obrigatórios
	// =========================================================================

	/**
	 * Retorna as URLs da página solicitada usando paginação lógica por blocos.
	 *
	 * @param int    $page_num       Página atual (1-indexed).
	 * @param string $object_subtype Não utilizado.
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( ! BDWP70_Sitemap::is_enabled() ) {
			return array();
		}

		$bible_id = $this->sitemap_bible_id();
		if ( $bible_id < 1 ) {
			return array();
		}

		$page_num    = max( 1, absint( $page_num ) );
		$per_page    = BDWP70_Sitemap::per_page();
		$incl_verses = BDWP70_Sitemap::include_verses();
		$lastmod     = $this->get_lastmod();
		$books       = $this->get_sitemap_books( $bible_id );
		$seo_base    = $this->plugin->seo_base();

		$offset    = absint( ( $page_num - 1 ) * $per_page );
		$remaining = absint( $per_page );
		$entries   = array();

		// ── Bloco A: URL raiz (1 URL) ─────────────────────────────────────
		$a_total = 1;
		if ( $offset < $a_total ) {
			if ( $remaining > 0 ) {
				$entries[] = array(
					'loc'     => esc_url_raw( $this->bible_root_url( $bible_id ) ),
					'lastmod' => $lastmod,
				);
				$remaining--;
			}
			$offset = 0;
		} else {
			$offset -= $a_total;
		}

		// ── Bloco B: livros (array pequeno, máx 66) ───────────────────────
		$book_slugs = array();
		foreach ( $books as $book ) {
			$slug = $this->plugin->book_slug_from_seq( (int) $book->livro_seq, $books );
			if ( $slug ) {
				$book_slugs[] = array( 'seq' => (int) $book->livro_seq, 'slug' => $slug );
			}
		}
		$b_total = count( $book_slugs );

		if ( $remaining > 0 && $b_total > 0 ) {
			if ( $offset < $b_total ) {
				$b_limit = min( $remaining, $b_total - $offset );
				$slice   = array_slice( $book_slugs, $offset, $b_limit );
				foreach ( $slice as $bk ) {
					$book_url  = home_url( user_trailingslashit( $seo_base . '/' . $bk['slug'] ) );
					$book_url  = $this->plugin->maybe_add_bible_version_arg_to_url( $book_url, $bible_id );
					$entries[] = array(
						'loc'     => esc_url_raw( $book_url ),
						'lastmod' => $lastmod,
					);
					$remaining--;
				}
				$offset = 0;
			} else {
				$offset -= $b_total;
				$offset  = max( 0, $offset );
			}
		} elseif ( $b_total > 0 ) {
			$offset -= $b_total;
			$offset  = max( 0, $offset );
		}

		// ── Bloco C: capítulos via LIMIT/OFFSET ───────────────────────────
		$c_total = $this->count_chapters_total( $bible_id );

		if ( $remaining > 0 && $c_total > 0 ) {
			if ( $offset < $c_total ) {
				$c_limit = min( $remaining, $c_total - $offset );
				$c_rows  = $this->get_chapters_paged( $bible_id, $c_limit, $offset );
				foreach ( $c_rows as $row ) {
					$entries[] = array(
						'loc'     => esc_url_raw( $this->plugin->chapter_url(
							(int) $row->livroseq,
							(int) $row->capitulo,
							$books,
							$bible_id
						) ),
						'lastmod' => $lastmod,
					);
					$remaining--;
				}
				$offset = 0;
			} else {
				$offset -= $c_total;
				$offset  = max( 0, $offset );
			}
		} elseif ( $c_total > 0 ) {
			$offset -= $c_total;
			$offset  = max( 0, $offset );
		}

		// ── Bloco D: versículos via LIMIT/OFFSET ──────────────────────────
		if ( $incl_verses && $remaining > 0 ) {
			$d_total = $this->count_verses_total( $bible_id );

			if ( $d_total > 0 && $offset < $d_total ) {
				$d_limit = min( $remaining, $d_total - $offset );
				$d_rows  = $this->get_verses_paged( $bible_id, $d_limit, $offset );
				foreach ( $d_rows as $row ) {
					$entries[] = array(
						'loc'     => esc_url_raw( $this->plugin->verse_url(
							(int) $row->livroseq,
							(int) $row->capitulo,
							(int) $row->versiculo,
							$books,
							$bible_id
						) ),
						'lastmod' => $lastmod,
					);
					$remaining--;
				}
			}
		}

		return $entries;
	}

	/**
	 * Número máximo de páginas — mesmo universo que get_url_list().
	 *
	 * @param string $object_subtype Não utilizado.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		if ( ! BDWP70_Sitemap::is_enabled() ) {
			return 0;
		}

		$bible_id = $this->sitemap_bible_id();
		if ( $bible_id < 1 ) {
			return 0;
		}

		$incl_verses = BDWP70_Sitemap::include_verses();
		$per_page    = BDWP70_Sitemap::per_page();
		$books       = $this->get_sitemap_books( $bible_id );

		// Bloco A
		$total = 1;

		// Bloco B
		foreach ( $books as $book ) {
			if ( $this->plugin->book_slug_from_seq( (int) $book->livro_seq, $books ) ) {
				$total++;
			}
		}

		// Bloco C
		$total += $this->count_chapters_total( $bible_id );

		// Bloco D
		if ( $incl_verses ) {
			$total += $this->count_verses_total( $bible_id );
		}

		return max( 1, (int) ceil( $total / $per_page ) );
	}

	// =========================================================================
	// Resolução segura do bible_id
	// =========================================================================

	/**
	 * Retorna o bible_id a usar no sitemap.
	 *
	 * Tenta a Bíblia ativa; se vazia, busca fallback com conteúdo real.
	 *
	 * @return int 0 se nenhuma Bíblia com conteúdo existir.
	 */
	private function sitemap_bible_id() {
		$key    = $this->cache_key( 'sitemap_bid' );
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$active_id = absint( $this->plugin->site_active_bible_id() );

		if ( $active_id > 0 && $this->bible_has_content( $active_id ) ) {
			wp_cache_set( $key, $active_id, self::CACHE_GROUP, self::CACHE_TTL );
			return $active_id;
		}

		$fallback_id = $this->find_first_bible_with_content();
		wp_cache_set( $key, $fallback_id, self::CACHE_GROUP, self::CACHE_TTL );
		return $fallback_id;
	}

	/**
	 * Verifica se um bible_id tem livros e versículos publicados.
	 *
	 * @param int $bible_id
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
	 * Busca o primeiro bible_id com livros e versículos publicados.
	 *
	 * @return int 0 se nenhum encontrado.
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

	// =========================================================================
	// Contagens	// =========================================================================
	// Contagens com cache versionado pelo lastmod
	// =========================================================================

	/**
	 * Chave de cache versionada: expira automaticamente quando lastmod muda.
	 *
	 * @param string $base Prefixo da chave.
	 * @return string
	 */
	private function cache_key( $base ) {
		$lastmod = get_option( BDWP70_Sitemap::OPTION_LASTMOD, 'x' );
		return $base . '_' . substr( md5( $lastmod ), 0, 8 );
	}

	private function count_books_for_bible( $bible_id ) {
		$bible_id = absint( $bible_id );
		$key      = $this->cache_key( 'prov_bkcnt_' . $bible_id );
		$cached   = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$count = count( $this->get_sitemap_books( $bible_id ) );

		wp_cache_set( $key, $count, self::CACHE_GROUP, self::CACHE_TTL );
		return $count;
	}

	private function count_chapters_total( $bible_id ) {
		global $wpdb;

		$bible_id = absint( $bible_id );
		$key      = $this->cache_key( 'prov_chtotal_' . $bible_id );
		$cached   = wp_cache_get( $key, self::CACHE_GROUP );
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

		wp_cache_set( $key, $count, self::CACHE_GROUP, self::CACHE_TTL );
		return $count;
	}

	private function count_verses_total( $bible_id ) {
		global $wpdb;

		$bible_id = absint( $bible_id );
		$key      = $this->cache_key( 'prov_vtotal_' . $bible_id );
		$cached   = wp_cache_get( $key, self::CACHE_GROUP );
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

		wp_cache_set( $key, $count, self::CACHE_GROUP, self::CACHE_TTL );
		return $count;
	}

	// =========================================================================
	// Queries	// =========================================================================
	// Queries paginadas com LIMIT/OFFSET reais
	// =========================================================================

	/**
	 * Capítulos distintos de toda a Bíblia, paginados.
	 *
	 * @return object[]  Cada objeto: livroseq, capitulo.
	 */
	private function get_chapters_paged( $bible_id, $limit, $offset ) {
		global $wpdb;

		$bible_id       = absint( $bible_id );
		$limit          = absint( $limit );
		$offset         = absint( $offset );
		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id );
		$key            = $this->cache_key( 'prov_chpaged_' . $bible_id . '_' . $limit . '_' . $offset . '_' . ( $published_only ? 'p' : 'a' ) );
		$cached         = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT DISTINCT livroseq, capitulo FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0 AND published = 1
					 ORDER BY livroseq ASC, capitulo ASC
					 LIMIT %d OFFSET %d',
					$bible_id,
					$limit,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT DISTINCT livroseq, capitulo FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0
					 ORDER BY livroseq ASC, capitulo ASC
					 LIMIT %d OFFSET %d',
					$bible_id,
					$limit,
					$offset
				)
			);
		}

		$rows = is_array( $rows ) ? $rows : array();
		wp_cache_set( $key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
		return $rows;
	}

	/**
	 * Versículos de toda	/**
	 * Versículos de toda a Bíblia, paginados.
	 *
	 * @return object[]  Cada objeto: livroseq, capitulo, versiculo.
	 */
	private function get_verses_paged( $bible_id, $limit, $offset ) {
		global $wpdb;

		$bible_id       = absint( $bible_id );
		$limit          = absint( $limit );
		$offset         = absint( $offset );
		$table          = BDWP70_Activator::verses_table();
		$published_only = $this->should_filter_published( $table, $bible_id );
		$key            = $this->cache_key( 'prov_vpaged_' . $bible_id . '_' . $limit . '_' . $offset . '_' . ( $published_only ? 'p' : 'a' ) );
		$cached         = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		if ( $published_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT livroseq, capitulo, versiculo FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0 AND published = 1
					 ORDER BY livroseq ASC, capitulo ASC, versiculo ASC
					 LIMIT %d OFFSET %d',
					$bible_id,
					$limit,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT livroseq, capitulo, versiculo FROM `' . esc_sql( $table ) . '`
					 WHERE bible_id = %d AND livroseq BETWEEN 1 AND 66 AND capitulo > 0 AND versiculo > 0
					 ORDER BY livroseq ASC, capitulo ASC, versiculo ASC
					 LIMIT %d OFFSET %d',
					$bible_id,
					$limit,
					$offset
				)
			);
		}

		$rows = is_array( $rows ) ? $rows : array();
		wp_cache_set( $key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
		return $rows;
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

		$key    = $this->cache_key( 'prov_books_' . $bible_id );
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		$books = $this->plugin->get_books( $bible_id );
		if ( ! empty( $books ) ) {
			$books = $this->normalize_sitemap_books( $books );
			wp_cache_set( $key, $books, self::CACHE_GROUP, self::CACHE_TTL );
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
			wp_cache_set( $key, $books, self::CACHE_GROUP, self::CACHE_TTL );
			return $books;
		}

		$verses_table    = BDWP70_Activator::verses_table();
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
		wp_cache_set( $key, $books, self::CACHE_GROUP, self::CACHE_TTL );
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

		$key    = $this->cache_key( 'prov_pub_' . md5( $table . ':' . $bible_id . ':' . $book_seq ) );
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
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
		wp_cache_set( $key, $use_published, self::CACHE_GROUP, self::CACHE_TTL );
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

	// =========================================================================
	// URL raiz padronizada com versão
	// =========================================================================

	/**
	 * URL raiz da Bíblia normalizada com /versao/{slug}/.
	 *
	 * @param int $bible_id ID da Bíblia.
	 * @return string
	 */
	private function bible_root_url( $bible_id ) {
		$url = home_url( user_trailingslashit( $this->plugin->seo_base() ) );
		if ( method_exists( $this->plugin, 'maybe_add_bible_version_arg_to_url' ) ) {
			$url = $this->plugin->maybe_add_bible_version_arg_to_url( $url, $bible_id );
		}
		return $url;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private function get_lastmod() {
		$lastmod = get_option( BDWP70_Sitemap::OPTION_LASTMOD, '' );
		if ( ! $lastmod || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $lastmod ) ) {
			return current_time( 'Y-m-d' );
		}
		return $lastmod;
	}
}
