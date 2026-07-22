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
	public function is_bible_seo_context() {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
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
		if ( ! $this->is_bible_seo_context() ) {
			return false;
		}

		$state     = $this->read_request_state();
		$books     = $this->get_books( ! empty( $state['bible_id'] ) ? (int) $state['bible_id'] : null );
		$book_name = $this->book_name_from_seq( $books, (int) $state['book'], '' );

		if ( ! $book_name || empty( $state['chapter'] ) ) {
			if ( $book_name && ! empty( $state['book'] ) ) {
				return array(
					'title'       => $book_name . ' - ' . $this->display_title(),
					'description' => 'Leia os capitulos de ' . $book_name . ' em ' . $this->display_title() . '.',
					'canonical'   => $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() . '/' . $this->book_slug_from_seq( (int) $state['book'], $books ) ) ), (int) $state['bible_id'] ),
				);
			}
			if ( is_page( $this->seo_base() ) || get_query_var( 'bdwp_bible' ) ) {
				return array(
					'title'       => 'Livros da Biblia - ' . $this->display_title(),
					'description' => 'Lista dos livros da Biblia, organizada em Antigo Testamento e Novo Testamento, com acesso aos capitulos e versiculos.',
					'canonical'   => $this->maybe_add_bible_version_arg_to_url( home_url( user_trailingslashit( $this->seo_base() ) ), (int) $state['bible_id'] ),
				);
			}
			return false;
		}

		$canonical   = $this->chapter_url( (int) $state['book'], (int) $state['chapter'], $books, (int) $state['bible_id'] );
		$title_ref   = $book_name . ' ' . (int) $state['chapter'];
		$description = 'Leia ' . $book_name . ' capitulo ' . (int) $state['chapter'] . ' completo em ' . $this->display_title() . '.';

		if ( ! empty( $state['verse'] ) ) {
			$verse     = $this->get_single_verse( (int) $state['book'], (int) $state['chapter'], (int) $state['verse'], (int) $state['bible_id'] );
			$title_ref = $book_name . ' ' . (int) $state['chapter'] . ':' . (int) $state['verse'];
			$canonical = $this->verse_url( (int) $state['book'], (int) $state['chapter'], (int) $state['verse'], $books, (int) $state['bible_id'] );
			if ( $verse ) {
				$description = $title_ref . ' - ' . trim( wp_strip_all_tags( (string) $verse->palavra ) );
			} else {
				$description = 'Leia ' . $title_ref . ' em ' . $this->display_title() . '.';
			}
		} else {
			$first = $this->get_single_verse( (int) $state['book'], (int) $state['chapter'], 1, (int) $state['bible_id'] );
			if ( $first ) {
				$description = $book_name . ' capitulo ' . (int) $state['chapter'] . ' - ' . trim( wp_strip_all_tags( (string) $first->palavra ) );
			}
		}

		return array(
			'title'       => $title_ref . ' - ' . $this->display_title(),
			'description' => wp_trim_words( $description, 34, '...' ),
			'canonical'   => $canonical,
		);
	}

	public function document_title_parts( $parts ) {
		$seo = $this->build_seo_context();
		if ( $seo && ! empty( $seo['title'] ) ) {
			$parts['title'] = $seo['title'];
			unset( $parts['site'], $parts['tagline'] );
		}
		return $parts;
	}

	public function pre_get_document_title( $title ) {
		$seo = $this->build_seo_context();
		return $seo && ! empty( $seo['title'] ) ? $seo['title'] : $title;
	}

	public function canonical_filter( $canonical_url, $post = null ) {
		$seo = $this->build_seo_context();
		return $seo && ! empty( $seo['canonical'] ) ? $seo['canonical'] : $canonical_url;
	}

	public function seo_head() {
		$seo = $this->build_seo_context();
		if ( ! $seo ) {
			return;
		}

		echo "\n" . '<link rel="manifest" href="' . esc_url( home_url( '/manifest.json' ) ) . '">' . "\n";
		echo '<meta name="description" content="' . esc_attr( $seo['description'] ) . '">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( $seo['canonical'] ) . '">' . "\n";
	}
}
