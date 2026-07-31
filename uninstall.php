<?php
/**
 * Uninstall handler.
 *
 * Data is preserved by default. Tables, options and imported Bibles are removed
 * only when the administrator explicitly enables the
 * `bdwp70_delete_data_on_uninstall` option before deleting the plugin.
 *
 * @package BibliaDigitalWP
 */

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Uninstall helpers are guarded by function_exists and documented above those guards.

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Returns whether destructive uninstall is enabled for the current site.
 *
 * @return bool
 */
if ( ! function_exists( 'bdwp70_uninstall_should_delete_current_site_data' ) ) {
	function bdwp70_uninstall_should_delete_current_site_data() {
		return 1 === (int) get_option( 'bdwp70_delete_data_on_uninstall', 0 );
	}
}

/**
 * Drops plugin tables for the current site.
 */
if ( ! function_exists( 'bdwp70_uninstall_drop_current_site_tables' ) ) {
	function bdwp70_uninstall_drop_current_site_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'bdwp70_livros',
			$wpdb->prefix . 'bdwp70_versiculos',
			$wpdb->prefix . 'bdwp70_biblias',
		);

		foreach ( $tables as $table ) {
			if ( preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicit uninstall cleanup removes only plugin-owned tables after opt-in.
				$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
			}
		}
	}
}

/**
 * Deletes plugin options for the current site after explicit opt-in.
 */
if ( ! function_exists( 'bdwp70_uninstall_delete_current_site_options' ) ) {
	function bdwp70_uninstall_delete_current_site_options() {
		$options = array(
			'bdwp70_db_version',
			'bdwp70_imported_at',
			'bdwp70_import_status',
			'bdwp70_last_error',
			'bdwp70_import_progress',
			'bdwp70_seo_base',
			'bdwp70_flush_rewrite',
			'bdwp70_show_credit',
			'bdwp70_bible_title',
			'bdwp70_title_image_id',
			'bdwp70_active_bible_id',
			'bdwp70_quick_cards',
			'bdwp70_bible_studio_url',
			'bdwp70_delete_data_on_uninstall',
			'bdwp70_sitemap_enabled',
			'bdwp70_sitemap_include_verses',
			'bdwp70_sitemap_per_page',
			'bdwp70_sitemap_lastmod',
			// Legado: controlavam o índice físico removido na 1.1.70. Continuam
			// listados para limpar instalações que atualizaram de versões antigas.
			'bdwp70_sitemap_static_index_signature',
			'bdwp70_sitemap_static_index_info',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		delete_metadata( 'user', 0, 'bdwp70_dismissed_setup_notice', '', true );
	}
}

/**
 * Removes data for the current site only when explicitly allowed.
 */
if ( ! function_exists( 'bdwp70_uninstall_current_site' ) ) {
	function bdwp70_uninstall_current_site() {
		if ( ! bdwp70_uninstall_should_delete_current_site_data() ) {
			return;
		}

		bdwp70_uninstall_drop_current_site_tables();
		bdwp70_uninstall_delete_current_site_options();
	}
}

if ( is_multisite() ) {
	$bdwp70_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $bdwp70_site_ids as $bdwp70_site_id ) {
		switch_to_blog( (int) $bdwp70_site_id );
		bdwp70_uninstall_current_site();
		restore_current_blog();
	}

	// Network-level metadata is preserved to avoid global cleanup during uninstall.
} else {
	bdwp70_uninstall_current_site();
}
