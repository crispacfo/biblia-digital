<?php
/**
 * Plugin Name: Biblia Digital
 * Plugin URI: https://estudobiblico.org/
 * Description: Display, search, and import Bible texts using shortcodes, widgets, and a Gutenberg block.
 * Version: 1.1.65
 * Requires at least: 6.6
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Author: Claudio Crispim
 * Author URI: https://estudobiblico.org/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: biblia-digital
 * Domain Path: /languages
 *
 * @package BibliaDigital
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BDWP70_VERSION' ) ) {
	define( 'BDWP70_VERSION', '1.1.65' );
}

if ( ! defined( 'BDWP70_FILE' ) ) {
	define( 'BDWP70_FILE', __FILE__ );
}

if ( ! defined( 'BDWP70_DIR' ) ) {
	define( 'BDWP70_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BDWP70_URL' ) ) {
	define( 'BDWP70_URL', plugin_dir_url( __FILE__ ) );
}

$bdwp70_existing_file = defined( 'BDWP70_FILE' ) ? (string) BDWP70_FILE : '';
$bdwp70_current_file  = __FILE__;
$bdwp70_duplicate     = (
	class_exists( 'BDWP70_Plugin', false )
	|| class_exists( 'BDWP70_Activator', false )
	|| function_exists( 'bdwp70_bootstrap' )
	|| (
		'' !== $bdwp70_existing_file
		&& realpath( $bdwp70_existing_file )
		&& realpath( $bdwp70_current_file )
		&& realpath( $bdwp70_existing_file ) !== realpath( $bdwp70_current_file )
	)
);

if ( $bdwp70_duplicate ) {
	add_action(
		'admin_notices',
		static function () {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'An older Biblia Digital version is active. Deactivate the older version before activating this version.', 'biblia-digital' ); ?></p>
			</div>
			<?php
		}
	);

	return;
}

if ( ! class_exists( 'BDWP70_Activator', false ) ) {
	require_once BDWP70_DIR . 'includes/class-bdwp70-activator.php';
}
if ( ! trait_exists( 'BDWP70_SEO', false ) ) {
	require_once BDWP70_DIR . 'includes/class-bdwp70-seo.php';
}
if ( ! class_exists( 'BDWP70_Plugin', false ) ) {
	require_once BDWP70_DIR . 'includes/class-bdwp70-plugin.php';
}
if ( ! class_exists( 'BDWP70_Random_Verse_Widget', false ) ) {
	require_once BDWP70_DIR . 'includes/class-bdwp70-random-verse-widget.php';
}
if ( ! class_exists( 'BDWP70_Search_Widget', false ) ) {
	require_once BDWP70_DIR . 'includes/class-bdwp70-search-widget.php';
}
if ( ! class_exists( 'BDWP70_Chapter_Navigation_Widget', false ) ) {
	require_once BDWP70_DIR . 'includes/class-bdwp70-chapter-navigation-widget.php';
}
if ( ! class_exists( 'BDWP70_Sitemap', false ) ) {
	require_once BDWP70_DIR . 'includes/class-bdwp70-sitemap.php';
}
// O provider WP Sitemap API é carregado defensivamente no hook init (ver bdwp70_bootstrap).
// Não é carregado aqui para evitar erro fatal se WP_Sitemaps_Provider ainda não existir.

if ( ! class_exists( 'BDWP70_Activator', false ) || ! class_exists( 'BDWP70_Plugin', false ) ) {
	return;
}

register_activation_hook( __FILE__, array( 'BDWP70_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BDWP70_Activator', 'deactivate' ) );

// Multisite: prepare tables/options automatically when a new site is created
// while the plugin is network-active. Only wp_initialize_site is registered:
// the legacy wpmu_new_blog hook is deprecated since WP 5.1 and the plugin's
// minimum is WP 6.6, so registering it would only emit a deprecation notice on
// site creation. BDWP70_Activator::activate_new_blog() is kept as a callable
// for third-party integrations that still hook the legacy action themselves.
add_action( 'wp_initialize_site', array( 'BDWP70_Activator', 'activate_new_site' ), 10, 2 );

if ( ! function_exists( 'bdwp70_bootstrap' ) ) {
	add_action( 'plugins_loaded', 'bdwp70_bootstrap' );

	/**
	 * Starts the plugin.
	 *
	 * WordPress.org loads translations automatically when the plugin slug and
	 * text domain match, so no explicit manual text-domain loading is needed.
	 *
	 * @return void
	 */
	function bdwp70_bootstrap() {
		// Self-heals the schema on plugin updates and on legacy-loader activations,
		// where register_activation_hook() never fired for this file. Runs before
		// init() so upgraded tables/indexes are present for the rest of the request.
		BDWP70_Activator::maybe_upgrade();

		$plugin = BDWP70_Plugin::instance();
		$plugin->init();

		// Sitemap dedicado
		if ( class_exists( 'BDWP70_Sitemap', false ) ) {
			( new BDWP70_Sitemap( $plugin ) )->init();
		}

		// Integração com WP Sitemap API nativa — Correção 7: carregamento defensivo.
		add_action(
			'init',
			static function () use ( $plugin ) {
				// Guard 1: sitemap habilitado.
				if ( ! BDWP70_Sitemap::is_enabled() ) {
					return;
				}

				// Guard 2: WP Sitemap API disponível (WP 5.5+).
				if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_sitemaps_get_server' ) ) {
					return;
				}

				// Guard 3: classe do provider carregada.
				if ( ! class_exists( 'BDWP70_Sitemap_Provider', false ) ) {
					$provider_file = BDWP70_DIR . 'includes/class-bdwp70-sitemap-provider.php';
					if ( file_exists( $provider_file ) ) {
						require_once $provider_file;
					}
				}

				if ( ! class_exists( 'BDWP70_Sitemap_Provider', false ) ) {
					return;
				}

				// Guard 4: servidor de sitemaps acessível.
				$server = wp_sitemaps_get_server();
				if ( ! $server || empty( $server->registry ) ) {
					return;
				}

				$registry = $server->registry;

				// Guard 5: sem duplicata.
				if (
					! is_callable( array( $registry, 'add_provider' ) ) ||
					( is_callable( array( $registry, 'get_provider' ) ) && $registry->get_provider( 'biblia-digital' ) )
				) {
					return;
				}

				$registry->add_provider(
					'biblia-digital',
					new BDWP70_Sitemap_Provider( $plugin )
				);
			},
			20
		);
	}
}

if ( ! function_exists( 'bdwp70_allowed_form_html' ) ) {
	/**
	 * Returns the allowed HTML map for frontend forms rendered by this plugin.
	 *
	 * @return array
	 */
	function bdwp70_allowed_form_html() {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed['form'] = array(
			'action' => true,
			'class'  => true,
			'id'     => true,
			'method' => true,
			'role'   => true,
		);

		$allowed['input'] = array(
			'aria-label'  => true,
			'checked'     => true,
			'class'       => true,
			'id'          => true,
			'maxlength'   => true,
			'name'        => true,
			'placeholder' => true,
			'type'        => true,
			'value'       => true,
		);

		$allowed['select'] = array(
			'aria-label' => true,
			'class'      => true,
			'id'         => true,
			'name'       => true,
		);

		$allowed['option'] = array(
			'selected' => true,
			'value'    => true,
		);

		$allowed['button'] = array(
			'class' => true,
			'type'  => true,
		);

		$allowed['label'] = array(
			'class' => true,
			'for'   => true,
		);

		return $allowed;
	}
}

if ( ! function_exists( 'bdwp70_get_active_bible_id' ) ) {
	/**
	 * Returns the active Bible ID configured for the current site.
	 *
	 * @return int
	 */
	function bdwp70_get_active_bible_id() {
		if ( ! class_exists( 'BDWP70_Plugin' ) ) {
			return 0;
		}

		return (int) BDWP70_Plugin::instance()->site_active_bible_id();
	}
}

if ( ! function_exists( 'bdwp70_get_active_bible_label' ) ) {
	/**
	 * Returns the active Bible label configured for the current site.
	 *
	 * @return string
	 */
	function bdwp70_get_active_bible_label() {
		if ( ! class_exists( 'BDWP70_Plugin' ) ) {
			return '';
		}

		return (string) BDWP70_Plugin::instance()->get_bible_version_label( BDWP70_Plugin::instance()->site_active_bible_id() );
	}
}

if ( ! function_exists( 'bdwp70_get_daily_psalm_verse' ) ) {
	/**
	 * Returns a daily Psalm verse using the active Bible for the current site.
	 *
	 * @param array $args Optional arguments: bible_id, book_seq, and option_key.
	 * @return array|null
	 */
	function bdwp70_get_daily_psalm_verse( $args = array() ) {
		if ( ! class_exists( 'BDWP70_Plugin' ) ) {
			return null;
		}

		return BDWP70_Plugin::instance()->get_daily_psalm_verse( $args );
	}
}
