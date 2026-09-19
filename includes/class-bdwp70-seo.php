<?php
/**
 * SEO callbacks for Biblia Digital.
 *
 * @package BibliaDigitalWP
 */

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- SEO trait methods are documented by their WordPress hook usage.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress filter signature includes the post parameter.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( trait_exists( 'BDWP70_SEO', false ) ) {
	return;
}

trait BDWP70_SEO {
	/**
	 * Um plugin de SEO já produziu o canonical desta requisição.
	 *
	 * @var bool
	 */
	private $seo_plugin_canonical_done = false;

	public function is_bible_seo_context() {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return false;
		}

		/*
		 * Rota de referência inexistente responde 404 (bible_route_not_found()).
		 * Sem esta saída, a página de erro herdaria título, description e
		 * canonical de um versículo que não existe.
		 */
		if ( is_404() ) {
			return false;
		}

		if ( get_query_var( 'bdwp_bible' ) ) {
			return true;
		}

		$base = $this->seo_base();

		if ( is_page( $base ) ) {
			return true;
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post && isset( $post->post_content ) ) {
				$content = (string) $post->post_content;
				if (
					has_shortcode( $content, 'biblia-digital' ) ||
					has_shortcode( $content, 'biblia-wp-estudobiblico' ) ||
					has_shortcode( $content, 'bibliawp-estudobiblico' )
				) {
					return true;
				}
			}
		}

		return false;
	}

	public function build_seo_context() {
		static $bdwp70_seo_context_cache = null;

		if ( null !== $bdwp70_seo_context_cache ) {
			return $bdwp70_seo_context_cache;
		}

		if ( ! $this->is_bible_seo_context() ) {
			$bdwp70_seo_context_cache = false;
			return false;
		}

		$state     = $this->read_request_state();
		$books     = $this->get_books( ! empty( $state['bible_id'] ) ? (int) $state['bible_id'] : null );
		$book_name = $this->book_name_from_seq( $books, (int) $state['book'], '' );

		if ( ! $book_name || empty( $state['chapter'] ) ) {
			if ( $book_name && ! empty( $state['book'] ) ) {
				$bdwp70_seo_context_cache = array(
					'title'       => $book_name . ' - ' . $this->display_title(),
					/* translators: 1: book name, 2: Bible title. */
					'description' => sprintf( __( 'Read the chapters of %1$s on %2$s.', 'estudobiblico-biblia-digital' ), $book_name, $this->display_title() ),
					'canonical'   => $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() . '/' . $this->book_slug_from_seq( (int) $state['book'], $books ) ) ), (int) $state['bible_id'] ),
				);
				return $bdwp70_seo_context_cache;
			}
			if ( is_page( $this->seo_base() ) || get_query_var( 'bdwp_bible' ) ) {
				$bdwp70_seo_context_cache = array(
					/* translators: %s: Bible title. */
					'title'       => sprintf( __( 'Books of the Bible - %s', 'estudobiblico-biblia-digital' ), $this->display_title() ),
					'description' => __( 'List of the books of the Bible, organized into the Old Testament and the New Testament, with access to the chapters and verses.', 'estudobiblico-biblia-digital' ),
					'canonical'   => $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() ) ), (int) $state['bible_id'] ),
				);
				return $bdwp70_seo_context_cache;
			}
			$bdwp70_seo_context_cache = false;
			return false;
		}

		$canonical = $this->chapter_url( (int) $state['book'], (int) $state['chapter'], $books, (int) $state['bible_id'] );
		$title_ref = $book_name . ' ' . (int) $state['chapter'];

		/* translators: 1: book name, 2: chapter number, 3: Bible title. */
		$description = sprintf( __( 'Read %1$s chapter %2$d in full on %3$s.', 'estudobiblico-biblia-digital' ), $book_name, (int) $state['chapter'], $this->display_title() );

		if ( ! empty( $state['verse'] ) ) {
			$verse     = $this->get_single_verse( (int) $state['book'], (int) $state['chapter'], (int) $state['verse'], (int) $state['bible_id'] );
			$title_ref = $book_name . ' ' . (int) $state['chapter'] . ':' . (int) $state['verse'];

			/*
			 * A URL de versiculo entrega o capitulo inteiro, com o versiculo
			 * destacado. Declarar canonical proprio fazia cada versiculo se
			 * apresentar como pagina original de um conteudo quase identico ao do
			 * capitulo — dezenas de milhares de duplicatas por traducao. O canonical
			 * aponta para o capitulo, calculado logo acima, e a URL do versiculo
			 * segue viva como deep link.
			 *
			 * Excecao: se o modo noindex for religado pelo filtro, o canonical
			 * precisa ser o proprio. noindex combinado com canonical para outra
			 * pagina e sinal contraditorio, e o buscador pode estender o noindex
			 * ao destino.
			 */
			if ( $this->verse_urls_noindex() ) {
				$canonical = $this->verse_url( (int) $state['book'], (int) $state['chapter'], (int) $state['verse'], $books, (int) $state['bible_id'] );
			}
			if ( $verse ) {
				$description = $title_ref . ' - ' . trim( wp_strip_all_tags( (string) $verse->palavra ) );
			} else {
				/* translators: 1: Bible reference, 2: Bible title. */
				$description = sprintf( __( 'Read %1$s on %2$s.', 'estudobiblico-biblia-digital' ), $title_ref, $this->display_title() );
			}
		} else {
			$first = $this->get_single_verse( (int) $state['book'], (int) $state['chapter'], 1, (int) $state['bible_id'] );
			if ( $first ) {
				/* translators: 1: book name, 2: chapter number, 3: text of the first verse. */
				$description = sprintf( __( '%1$s chapter %2$d - %3$s', 'estudobiblico-biblia-digital' ), $book_name, (int) $state['chapter'], trim( wp_strip_all_tags( (string) $first->palavra ) ) );
			}
		}

		$bdwp70_seo_context_cache = array(
			'title'       => $title_ref . ' - ' . $this->display_title(),
			'description' => wp_trim_words( $description, 34, '...' ),
			'canonical'   => $canonical,
		);
		return $bdwp70_seo_context_cache;
	}

	public function document_title_parts( $parts ) {
		if ( is_404() ) {
			return $parts;
		}

		$seo = $this->build_seo_context();
		if ( $seo && ! empty( $seo['title'] ) ) {
			$parts['title'] = $seo['title'];
			unset( $parts['site'], $parts['tagline'] );
		}
		return $parts;
	}

	public function pre_get_document_title( $title ) {
		if ( is_404() ) {
			return $title;
		}

		$seo = $this->build_seo_context();
		return $seo && ! empty( $seo['title'] ) ? $seo['title'] : $title;
	}

	public function canonical_filter( $canonical_url, $post = null ) {
		$seo = $this->build_seo_context();
		return $seo && ! empty( $seo['canonical'] ) ? $seo['canonical'] : $canonical_url;
	}

	public function seo_head() {
		if ( is_404() ) {
			return;
		}

		$seo = $this->build_seo_context();
		if ( ! $seo ) {
			return;
		}

		echo "\n" . '<link rel="manifest" href="' . esc_url( home_url( '/manifest.json' ) ) . '">' . "\n";
		echo '<meta name="description" content="' . esc_attr( $seo['description'] ) . '">' . "\n";
		/**
		 * Filtra a impressão do canonical próprio da Bíblia Digital.
		 *
		 * @param bool $print False quando um plugin de SEO já gerou o canonical.
		 */
		if ( apply_filters( 'bdwp70_print_canonical', ! $this->seo_plugin_canonical_done ) ) {
			echo '<link rel="canonical" href="' . esc_url( $seo['canonical'] ) . '">' . "\n";
		}
	}

	/**
	 * Informa se a requisicao atual e uma URL de versiculo individual.
	 *
	 * So detecta; a politica aplicada (canonical para o capitulo, ou noindex)
	 * fica em verse_urls_noindex().
	 *
	 * @return bool
	 */
	public function is_verse_url_request() {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return false;
		}

		if ( ! get_query_var( 'bdwp_bible' ) ) {
			return false;
		}

		if ( absint( get_query_var( 'bdwp_versiculo' ) ) < 1 ) {
			return false;
		}

		return true;
	}

	/**
	 * Informa se as URLs de versiculo devem sair como noindex.
	 *
	 * Desligado por padrao desde a 1.1.76: a URL de versiculo passa a declarar o
	 * capitulo como canonical, e noindex nao pode ser combinado com canonical
	 * para outra pagina. Religar pelo filtro troca uma estrategia pela outra por
	 * inteiro — o canonical volta a ser a propria URL do versiculo.
	 *
	 * @return bool
	 */
	public function verse_urls_noindex() {
		if ( ! $this->is_verse_url_request() ) {
			return false;
		}

		/**
		 * Filtra a estrategia das URLs de versiculo.
		 *
		 * @param bool $noindex True para noindex com canonical proprio; false
		 *                      (padrao) para canonical apontando ao capitulo.
		 */
		return (bool) apply_filters( 'bdwp70_noindex_verse_urls', false );
	}

	/**
	 * Canonical do capitulo tambem para Rank Math e Yoast, caso emitam o proprio.
	 *
	 * @param string $canonical Canonical calculado pelo plugin de SEO.
	 * @return string
	 */
	public function canonical_seo_plugin( $canonical ) {
		$seo = $this->build_seo_context();
		if ( ! $seo || empty( $seo['canonical'] ) ) {
			return $canonical;
		}

		/*
		 * O filtro ter rodado significa que o plugin de SEO está montando o
		 * canonical desta página; então ele imprime a tag e a Bíblia Digital não
		 * imprime a sua, evitando duas tags no <head>. Se nenhum plugin de SEO
		 * gerar canonical — o que acontece hoje nestas páginas virtuais, que não
		 * são posts —, quem imprime continua sendo este plugin.
		 */
		$this->seo_plugin_canonical_done = true;

		return $seo['canonical'];
	}

	/**
	 * Alias mantido para quem chamava o nome anterior.
	 *
	 * @param string $canonical Canonical calculado pelo plugin de SEO.
	 * @return string
	 */
	public function canonical_verse_seo_plugin( $canonical ) {
		return $this->canonical_seo_plugin( $canonical );
	}

	/**
	 * Marca a URL de versiculo como noindex no wp_robots do WordPress.
	 *
	 * `follow` e mantido de proposito: os links para o capitulo continuam
	 * transmitindo sinal, so a pagina em si sai do indice.
	 *
	 * @param array $robots Diretivas do core.
	 * @return array
	 */
	public function robots_noindex_verse( $robots ) {
		if ( ! is_array( $robots ) || ! $this->verse_urls_noindex() ) {
			return $robots;
		}

		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'], $robots['archive'] );

		return $robots;
	}

	/**
	 * Mesma marcacao para Rank Math e Yoast, que emitem a propria meta robots
	 * e ignoram o wp_robots do core. Ambos usam array com chaves index/follow
	 * e valores textuais.
	 *
	 * @param array $robots Diretivas do plugin de SEO.
	 * @return array
	 */
	public function robots_noindex_verse_seo_plugin( $robots ) {
		if ( ! is_array( $robots ) || ! $this->verse_urls_noindex() ) {
			return $robots;
		}

		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';

		return $robots;
	}

	/**
	 * Envia X-Robots-Tag na resposta da URL de versiculo.
	 *
	 * Rede de seguranca: vale mesmo que nenhum plugin de SEO esteja ativo, ou
	 * que uma versao futura deles mude o nome do filtro. Em caso de divergencia
	 * entre cabecalho e meta, os buscadores adotam a diretiva mais restritiva.
	 */
	public function maybe_send_verse_robots_header() {
		if ( headers_sent() || ! $this->verse_urls_noindex() ) {
			return;
		}

		header( 'X-Robots-Tag: noindex, follow', true );
	}
}
