<?php
/**
 * Tests for the Open Graph data of the virtual Bible pages.
 *
 * The pages are not posts, so an SEO plugin cannot resolve a title or an image for
 * them and falls back to an unrelated post. These filters hand it the right values.
 *
 * @package BibliaDigital
 */

class Test_BDWP70_OpenGraph extends WP_UnitTestCase {

	private function plugin() {
		return BDWP70_Plugin::instance();
	}

	public function test_social_image_falls_back_to_the_site_icon() {
		add_filter( 'bdwp70_social_image', static fn() => 'https://example.org/capa.png' );
		$this->assertSame( 'https://example.org/capa.png', $this->plugin()->social_image_url() );
		remove_all_filters( 'bdwp70_social_image' );
	}

	public function test_social_image_can_be_emptied_by_filter() {
		add_filter( 'bdwp70_social_image', '__return_empty_string' );
		$this->assertSame( '', $this->plugin()->social_image_url() );
		remove_all_filters( 'bdwp70_social_image' );
	}

	public function test_og_image_keeps_the_seo_plugin_value_outside_bible_pages() {
		// Sem contexto de Bíblia, o valor do plugin de SEO não é tocado.
		$this->assertSame( 'https://example.org/post.png', $this->plugin()->og_image_seo_plugin( 'https://example.org/post.png' ) );
	}

	public function test_filters_are_registered_for_rank_math_and_yoast() {
		$plugin = $this->plugin();
		$plugin->init();

		foreach ( array(
			'rank_math/opengraph/facebook/og_title'       => 'og_title_seo_plugin',
			'rank_math/opengraph/facebook/og_description' => 'og_description_seo_plugin',
			'rank_math/opengraph/facebook/og_url'         => 'og_url_seo_plugin',
			'rank_math/opengraph/facebook/og_image'       => 'og_image_seo_plugin',
			'wpseo_opengraph_title'                       => 'og_title_seo_plugin',
			'wpseo_opengraph_image'                       => 'og_image_seo_plugin',
		) as $hook => $metodo ) {
			$this->assertNotFalse(
				has_filter( $hook, array( $plugin, $metodo ) ),
				"O filtro {$hook} precisa estar registrado."
			);
		}
	}
}
