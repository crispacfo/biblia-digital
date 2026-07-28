<?php
/**
 * Activation, database creation and Bible import routines.
 *
 * @package BibliaDigitalWP
 */

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Legacy activation/import API keeps stable public method names.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Activation and import routines own the custom Bible tables they create.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema updates are limited to plugin-owned tables during activation/import.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress multisite hooks require the complete callback signature.
// phpcs:disable Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Streaming CSV/SQL readers intentionally assign each row in loop conditions.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local bundled import files, not remote URLs.
// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Internal CSV helper keeps the existing parameter contract.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'BDWP70_Activator', false ) ) {
	return;
}

class BDWP70_Activator {
	const OPTION_VERSION                  = 'bdwp70_db_version';
	const OPTION_IMPORTED                 = 'bdwp70_imported_at';
	const OPTION_STATUS                   = 'bdwp70_import_status';
	const OPTION_ERROR                    = 'bdwp70_last_error';
	const OPTION_PROGRESS                 = 'bdwp70_import_progress';
	const OPTION_ACTIVE_BIBLE             = 'bdwp70_active_bible_id';
	const OPTION_DELETE_DATA_ON_UNINSTALL = 'bdwp70_delete_data_on_uninstall';

	/**
	 * Activates the plugin. In multisite network activation, each site receives
	 * its own tables and site-local options, using that site's database prefix.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			self::for_each_site( array( __CLASS__, 'activate_single_site' ) );
			update_site_option( 'bdwp70_network_activated_at', current_time( 'mysql', true ) );
			return;
		}

		self::activate_single_site();
	}

	/**
	 * Creates the database structure for the current site only.
	 */
	public static function activate_single_site() {
		self::create_tables();
		update_option( self::OPTION_VERSION, BDWP70_VERSION );
		add_option( 'bdwp70_seo_base', 'biblia-digital' );
		add_option( self::OPTION_ACTIVE_BIBLE, 0 );
		add_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, 0 );
		update_option( 'bdwp70_flush_rewrite', 1 );
		update_option( self::OPTION_STATUS, 'pending' );
		update_option( self::OPTION_PROGRESS, 'Bíblia Digital foi ativado. Para começar, importe uma Bíblia em formato ZIP contendo books.csv e verses.csv.' );

		// Opções padrão do sitemap — add_option não sobrescreve se já existirem.
		add_option( 'bdwp70_sitemap_enabled', 1 );
		add_option( 'bdwp70_sitemap_include_verses', 1 );
		add_option( 'bdwp70_sitemap_per_page', 2000 );
		add_option( 'bdwp70_sitemap_lastmod', current_time( 'Y-m-d' ) );
	}

	/**
	 * Ensures the database structure matches the running plugin version.
	 *
	 * WordPress does not re-run activation hooks on plugin updates, so schema
	 * changes shipped in a new version would never reach existing installs.
	 * This also covers sites activated through the legacy loader filenames
	 * (biblia-digital-wp.php / biblia-digital-wp70.php), where the activation
	 * hook registered against biblia-digital.php never fires.
	 *
	 * Runs on every request but short-circuits on a single autoloaded option
	 * read when the stored version already matches.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored = (string) get_option( self::OPTION_VERSION, '' );

		if ( defined( 'BDWP70_VERSION' ) && BDWP70_VERSION === $stored ) {
			return;
		}

		// Prevents concurrent requests from running dbDelta simultaneously.
		$lock = 'bdwp70_upgrade_lock';
		if ( get_transient( $lock ) ) {
			return;
		}
		set_transient( $lock, 1, MINUTE_IN_SECONDS );

		self::create_tables();

		// Stale count/bounds/version transients from the previous version are
		// dropped so cached data reflects the upgraded schema (1.1.66/1.1.67).
		self::clear_runtime_caches();

		// Defaults are only added when absent, so administrator settings survive.
		add_option( 'bdwp70_seo_base', 'biblia-digital' );
		add_option( self::OPTION_ACTIVE_BIBLE, 0 );
		add_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, 0 );
		add_option( 'bdwp70_sitemap_enabled', 1 );
		add_option( 'bdwp70_sitemap_include_verses', 1 );
		add_option( 'bdwp70_sitemap_per_page', 2000 );
		add_option( 'bdwp70_sitemap_lastmod', current_time( 'Y-m-d' ) );

		// A fresh install reached here without activation (legacy loader path).
		if ( '' === $stored ) {
			add_option( self::OPTION_STATUS, 'pending' );
			add_option( self::OPTION_PROGRESS, 'Bíblia Digital foi ativado. Para começar, importe uma Bíblia em formato ZIP contendo books.csv e verses.csv.' );
		}

		update_option( 'bdwp70_flush_rewrite', 1 );
		update_option( self::OPTION_VERSION, defined( 'BDWP70_VERSION' ) ? BDWP70_VERSION : '0' );

		delete_transient( $lock );
	}

	/**
	 * Deactivates the plugin. Tables are preserved intentionally.
	 *
	 * @param bool $network_wide Whether the plugin is being network-deactivated.
	 */
	public static function deactivate( $network_wide = false ) {
		unset( $network_wide );
		// Tables and options are preserved intentionally. Use uninstall.php to remove data only when the explicit opt-in option is enabled.
	}

	/**
	 * Prepares tables/options for sites created after network activation.
	 *
	 * @param WP_Site|int $new_site New site object or blog ID.
	 * @param array       $args     Site creation arguments.
	 */
	public static function activate_new_site( $new_site, $args = array() ) {
		if ( ! is_multisite() ) {
			return;
		}

		if (
			function_exists( 'is_plugin_active_for_network' )
			&& ! is_plugin_active_for_network( plugin_basename( BDWP70_FILE ) )
			&& ! is_plugin_active_for_network( 'biblia-digital70/biblia-digital70.php' )
		) {
			return;
		}

		$blog_id = is_object( $new_site ) && isset( $new_site->blog_id ) ? (int) $new_site->blog_id : (int) $new_site;
		if ( $blog_id < 1 ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::activate_single_site();
		restore_current_blog();
	}

	/**
	 * Backward-compatible callback for the legacy multisite site-creation action.
	 *
	 * No longer registered against wpmu_new_blog: that action is deprecated since
	 * WP 5.1 and the plugin requires WP 6.6, so site creation is handled by
	 * activate_new_site() on wp_initialize_site. Kept public so integrations that
	 * still hook the legacy action themselves keep working.
	 *
	 * @param int $blog_id New blog ID.
	 */
	public static function activate_new_blog( $blog_id ) {
		self::activate_new_site( (int) $blog_id );
	}

	/**
	 * Runs a callback once for every site in the network.
	 *
	 * @param callable $callback Callback to run after switch_to_blog().
	 */
	public static function for_each_site( $callback ) {
		if ( ! is_multisite() ) {
			call_user_func( $callback );
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			call_user_func( $callback );
			restore_current_blog();
		}
	}

	public static function books_table() {
		global $wpdb;
		return $wpdb->prefix . 'bdwp70_livros';
	}

	public static function verses_table() {
		global $wpdb;
		return $wpdb->prefix . 'bdwp70_versiculos';
	}

	public static function versions_table() {
		global $wpdb;
		return $wpdb->prefix . 'bdwp70_biblias';
	}

	public static function get_active_bible_id() {
		static $cached_id = null;

		if ( null !== $cached_id ) {
			return (int) $cached_id;
		}

		$id = absint( get_option( self::OPTION_ACTIVE_BIBLE, 0 ) );
		if ( $id > 0 && self::bible_version_exists( $id ) ) {
			$cached_id = $id;
			return (int) $cached_id;
		}

		$cached_id = self::ensure_default_bible_version();
		return (int) $cached_id;
	}

	public static function count_books( $bible_id = null ) {
		global $wpdb;

		$table = self::books_table();
		$key   = 'bdwp70_books_count_' . ( null === $bible_id ? 'all' : absint( $bible_id ) );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		if ( null === $bible_id ) {
			$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );
		} else {
			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d', absint( $bible_id ) ) );
		}

		set_transient( $key, $count, HOUR_IN_SECONDS );
		return $count;
	}

	public static function count_verses( $bible_id = null ) {
		global $wpdb;

		$table = self::verses_table();
		$key   = 'bdwp70_verses_count_' . ( null === $bible_id ? 'all' : absint( $bible_id ) );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		if ( null === $bible_id ) {
			$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );
		} else {
			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE bible_id = %d', absint( $bible_id ) ) );
		}

		set_transient( $key, $count, HOUR_IN_SECONDS );
		return $count;
	}

	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$versions_table  = self::versions_table();
		$books_table     = self::books_table();
		$verses_table    = self::verses_table();

		$versions_sql = "CREATE TABLE {$versions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(120) NOT NULL,
            language_code varchar(16) NOT NULL DEFAULT 'pt-BR',
            source varchar(120) NOT NULL DEFAULT '',
            is_builtin tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY language_code (language_code)
        ) {$charset_collate};";

		$books_sql = "CREATE TABLE {$books_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bible_id bigint(20) unsigned NOT NULL DEFAULT 1,
            livro varchar(20) NOT NULL,
            livro_desc varchar(120) NOT NULL,
            livro_seq smallint(3) NOT NULL,
            published tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY bible_livro_seq (bible_id, livro_seq),
            KEY livro_seq (livro_seq),
            KEY livro (livro)
        ) {$charset_collate};";

		$verses_sql = "CREATE TABLE {$verses_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bible_id bigint(20) unsigned NOT NULL DEFAULT 1,
            testamento varchar(12) NOT NULL,
            livroseq smallint(3) NOT NULL DEFAULT 0,
            livro varchar(60) NOT NULL,
            capitulo smallint(3) NOT NULL DEFAULT 0,
            versiculo smallint(3) NOT NULL DEFAULT 0,
            palavra text NOT NULL,
            published tinyint(1) NOT NULL DEFAULT 1,
            hits int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY bible_livroseq_capitulo (bible_id, livroseq, capitulo),
            KEY bible_ref (bible_id, livroseq, capitulo, versiculo),
            KEY bible_published_ref (bible_id, published, livroseq, capitulo, versiculo),
            KEY testamento (testamento),
            KEY capitulo_versiculo (capitulo, versiculo),
            KEY livro (livro),
            FULLTEXT KEY palavra_fulltext (palavra)
        ) {$charset_collate};";

		dbDelta( $versions_sql );
		dbDelta( $books_sql );
		dbDelta( $verses_sql );

		self::maybe_add_column( $books_table, 'bible_id' );
		self::maybe_add_column( $verses_table, 'bible_id' );
		self::maybe_add_index( $verses_table, 'bible_published_ref', 'KEY `bible_published_ref` (`bible_id`, `published`, `livroseq`, `capitulo`, `versiculo`)' );
		self::maybe_add_index( $verses_table, 'bible_published_id', 'KEY `bible_published_id` (`bible_id`, `published`, `id`)' );
		self::maybe_add_index( $verses_table, 'bible_published_book_id', 'KEY `bible_published_book_id` (`bible_id`, `published`, `livroseq`, `id`)' );
		self::maybe_add_index( $verses_table, 'palavra_fulltext', 'FULLTEXT KEY `palavra_fulltext` (`palavra`)' );
	}

	private static function maybe_add_column( $table, $column ) {
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s', $column ) );
		if ( ! $exists ) {
			if ( self::books_table() === $table && 'bible_id' === $column ) {
				$wpdb->query( 'ALTER TABLE `' . esc_sql( $table ) . '` ADD `bible_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`' );
			} elseif ( self::verses_table() === $table && 'bible_id' === $column ) {
				$wpdb->query( 'ALTER TABLE `' . esc_sql( $table ) . '` ADD `bible_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`' );
			}
		}
	}

	/**
	 * Adds an index to a plugin-owned table when missing.
	 *
	 * @param string $table Database table name.
	 * @param string $index_name Index name.
	 * @param string $definition SQL index definition without ALTER TABLE ADD.
	 * @return void
	 */
	private static function maybe_add_index( $table, $index_name, $definition ) {
		global $wpdb;
		$index_name = sanitize_key( $index_name );
		if ( '' === $index_name ) {
			return;
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW INDEX FROM `' . esc_sql( $table ) . '` WHERE Key_name = %s',
				$index_name
			)
		);

		if ( $exists ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Definition is an internal constant string.
		$wpdb->query( 'ALTER TABLE `' . esc_sql( $table ) . '` ADD ' . $definition );
	}

	public static function ensure_default_bible_version() {
		global $wpdb;
		static $cached_default = null;

		if ( null !== $cached_default ) {
			return (int) $cached_default;
		}

		self::create_tables_safe_for_version_lookup();
		$table = self::versions_table();

		$active_id = absint( get_option( self::OPTION_ACTIVE_BIBLE, 0 ) );
		if ( $active_id > 0 && self::bible_version_exists( $active_id ) ) {
			$cached_default = $active_id;
			return (int) $cached_default;
		}

		$transient = get_transient( 'bdwp70_default_bible_version_id' );
		if ( false !== $transient ) {
			$cached_default = absint( $transient );
			return (int) $cached_default;
		}

		$id             = (int) $wpdb->get_var( 'SELECT id FROM `' . esc_sql( $table ) . '` ORDER BY id ASC LIMIT 1' );
		$cached_default = $id > 0 ? $id : 0;
		set_transient( 'bdwp70_default_bible_version_id', $cached_default, 12 * HOUR_IN_SECONDS );
		return (int) $cached_default;
	}

	private static function create_tables_safe_for_version_lookup() {
		global $wpdb;
		static $checked = false;

		if ( $checked ) {
			return;
		}

		$table = self::versions_table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			self::create_tables();
		}

		$checked = true;
	}

	public static function import_books( $bible_id = 1 ) {
		unset( $bible_id );
		self::create_tables();
		update_option( self::OPTION_ERROR, 'A base bíblica nativa não é distribuída neste pacote. Importe uma Bíblia em formato ZIP contendo books.csv e verses.csv.' );
		return false;
	}

	public static function import_data( $force = false, $bible_id = 1 ) {
		unset( $force, $bible_id );
		self::create_tables();
		update_option( self::OPTION_STATUS, 'pending' );
		update_option( self::OPTION_ERROR, 'A base bíblica nativa não é distribuída neste pacote. Importe uma Bíblia em formato ZIP contendo books.csv e verses.csv.' );
		update_option( self::OPTION_PROGRESS, 'Importação disponível apenas por ZIP/CSV enviado pelo administrador.' );
		return false;
	}

	public static function clear_bible_data( $bible_id ) {
		global $wpdb;
		$bible_id = absint( $bible_id );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . esc_sql( self::books_table() ) . '` WHERE bible_id = %d', $bible_id ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . esc_sql( self::verses_table() ) . '` WHERE bible_id = %d', $bible_id ) );
		self::clear_runtime_caches();
	}

	public static function get_bible_versions() {
		global $wpdb;
		static $cached_rows = null;

		if ( null !== $cached_rows ) {
			return $cached_rows;
		}

		$transient = get_transient( 'bdwp70_bible_versions_rows' );
		if ( false !== $transient && is_array( $transient ) ) {
			$cached_rows = $transient;
			return $cached_rows;
		}

		self::create_tables_safe_for_version_lookup();
		self::ensure_default_bible_version();
		$rows        = $wpdb->get_results( 'SELECT * FROM `' . esc_sql( self::versions_table() ) . '` ORDER BY is_builtin DESC, id ASC' );
		$cached_rows = is_array( $rows ) ? $rows : array();
		set_transient( 'bdwp70_bible_versions_rows', $cached_rows, 12 * HOUR_IN_SECONDS );
		return $cached_rows;
	}

	public static function bible_version_exists( $bible_id ) {
		global $wpdb;
		static $exists_cache = array();

		$bible_id = absint( $bible_id );
		if ( $bible_id < 1 ) {
			return false;
		}

		if ( array_key_exists( $bible_id, $exists_cache ) ) {
			return (bool) $exists_cache[ $bible_id ];
		}

		$key       = 'bdwp70_bible_exists_' . $bible_id;
		$transient = get_transient( $key );
		if ( false !== $transient ) {
			$exists_cache[ $bible_id ] = '1' === (string) $transient;
			return (bool) $exists_cache[ $bible_id ];
		}

		self::create_tables_safe_for_version_lookup();
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . esc_sql( self::versions_table() ) . '` WHERE id = %d LIMIT 1', $bible_id ) );
		$exists_cache[ $bible_id ] = $exists;
		set_transient( $key, $exists ? '1' : '0', 12 * HOUR_IN_SECONDS );
		return $exists;
	}

	public static function create_bible_version( $name, $language_code, $source = 'Upload do usuário', $is_builtin = 0 ) {
		global $wpdb;
		self::create_tables();
		$name          = sanitize_text_field( $name );
		$language_code = sanitize_text_field( $language_code );
		if ( '' === $name ) {
			$name = 'Bíblia importada';
		}
		if ( '' === $language_code ) {
			$language_code = 'und';
		}
		$ok = $wpdb->insert(
			self::versions_table(),
			array(
				'name'          => $name,
				'language_code' => $language_code,
				'source'        => sanitize_text_field( $source ),
				'is_builtin'    => $is_builtin ? 1 : 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);
		if ( $ok ) {
			self::clear_runtime_caches();
			return (int) $wpdb->insert_id;
		}
		return 0;
	}

	public static function delete_bible_version( $bible_id ) {
		global $wpdb;
		$bible_id = absint( $bible_id );
		if ( $bible_id < 1 ) {
			return false;
		}
		$was_active = absint( get_option( self::OPTION_ACTIVE_BIBLE, 0 ) ) === $bible_id;
		self::clear_bible_data( $bible_id );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . esc_sql( self::versions_table() ) . '` WHERE id = %d AND is_builtin = 0', $bible_id ) );
		self::clear_runtime_caches();
		if ( $was_active ) {
			update_option( self::OPTION_ACTIVE_BIBLE, self::ensure_default_bible_version() );
		}
		return true;
	}

	private static function get_verse_sql_files() {
		return array();
	}

	private static function parse_books_sql_file( $file ) {
		$contents = (string) file_get_contents( $file );
		$rows     = array();

		foreach ( preg_split( '/\R/', $contents ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, 'INSERT INTO' ) ) {
				continue;
			}

			$row = self::parse_book_sql_row( $line );
			if ( false !== $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	private static function parse_book_sql_row( $line ) {
		$line    = rtrim( $line, ",; \t\n\r\0\x0B" );
		$pattern = "/^\(\s*null\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*\)$/i";

		if ( ! preg_match( $pattern, $line, $m ) ) {
			return false;
		}

		return array(
			'livro'      => self::decode_sql_string( $m[1] ),
			'livro_desc' => self::decode_sql_string( $m[2] ),
			'livro_seq'  => (int) $m[3],
			'published'  => (int) $m[4],
		);
	}

	private static function insert_book_batch( $rows, $bible_id = 1 ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return true;
		}

		$table        = self::books_table();
		$bible_id     = absint( $bible_id );
		$placeholders = array();
		$values       = array();

		foreach ( $rows as $row ) {
			$placeholders[] = '( %d, %s, %s, %d, %d )';
			$values[]       = $bible_id;
			$values[]       = (string) $row['livro'];
			$values[]       = (string) $row['livro_desc'];
			$values[]       = (int) $row['livro_seq'];
			$values[]       = (int) $row['published'];
		}

		$sql = 'INSERT INTO `' . esc_sql( $table ) . '` (`bible_id`, `livro`, `livro_desc`, `livro_seq`, `published`) VALUES ' . implode( ', ', $placeholders );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders and values are built above.
		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		return false === $result ? false : count( $rows );
	}

	private static function import_verse_sql_file( $file, $bible_id = 1 ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming large legacy SQL files keeps imports memory-safe.
		global $wpdb;

		if ( ! is_readable( $file ) ) {
			update_option( self::OPTION_ERROR, basename( $file ) . ': arquivo não legível.' );
			return false;
		}

		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			update_option( self::OPTION_ERROR, basename( $file ) . ': não foi possível abrir o arquivo.' );
			return false;
		}

		$batch    = array();
		$inserted = 0;
		$line_no  = 0;

		while ( false !== ( $line = fgets( $handle ) ) ) {
			++$line_no;
			$line = trim( $line );

			if ( '' === $line || 0 === strpos( $line, 'INSERT INTO' ) ) {
				continue;
			}

			$row = self::parse_verse_sql_row( $line );
			if ( false === $row ) {
				if ( false !== strpos( $line, '(' ) ) {
					fclose( $handle );
					update_option( self::OPTION_ERROR, basename( $file ) . ': linha ' . $line_no . ' não pôde ser interpretada.' );
					return false;
				}
				continue;
			}

			$batch[] = $row;
			if ( count( $batch ) >= 250 ) {
				$ok = self::insert_verse_batch( $batch, $bible_id );
				if ( false === $ok ) {
					fclose( $handle );
					update_option( self::OPTION_ERROR, basename( $file ) . ': ' . $wpdb->last_error );
					return false;
				}
				$inserted += count( $batch );
				$batch     = array();
			}
		}

		fclose( $handle );

		if ( ! empty( $batch ) ) {
			$ok = self::insert_verse_batch( $batch, $bible_id );
			if ( false === $ok ) {
				update_option( self::OPTION_ERROR, basename( $file ) . ': ' . $wpdb->last_error );
				return false;
			}
			$inserted += count( $batch );
		}

		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $inserted;
	}

	private static function parse_verse_sql_row( $line ) {
		$line = rtrim( $line, ",; \t\n\r\0\x0B" );

		$pattern = "/^\(\s*NULL\s*,\s*'([^']*)'\s*,\s*([0-9]+)\s*,\s*'([^']*)'\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*'(.*)'\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*\)$/s";
		if ( ! preg_match( $pattern, $line, $m ) ) {
			return false;
		}

		return array(
			'testamento' => self::decode_sql_string( $m[1] ),
			'livroseq'   => (int) $m[2],
			'livro'      => self::decode_sql_string( $m[3] ),
			'capitulo'   => (int) $m[4],
			'versiculo'  => (int) $m[5],
			'palavra'    => self::decode_sql_string( $m[6] ),
			'published'  => (int) $m[7],
			'hits'       => (int) $m[8],
		);
	}

	private static function decode_sql_string( $value ) {
		$value = str_replace( "''", "'", $value );
		return stripcslashes( $value );
	}

	private static function insert_verse_batch( $rows, $bible_id = 1 ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return true;
		}

		$table        = self::verses_table();
		$bible_id     = absint( $bible_id );
		$placeholders = array();
		$values       = array();

		foreach ( $rows as $row ) {
			$placeholders[] = '( %d, %s, %d, %s, %d, %d, %s, %d, %d )';
			$values[]       = $bible_id;
			$values[]       = (string) $row['testamento'];
			$values[]       = (int) $row['livroseq'];
			$values[]       = (string) $row['livro'];
			$values[]       = (int) $row['capitulo'];
			$values[]       = (int) $row['versiculo'];
			$values[]       = (string) $row['palavra'];
			$values[]       = (int) $row['published'];
			$values[]       = (int) $row['hits'];
		}

		$sql = 'INSERT INTO `' . esc_sql( $table ) . '` (`bible_id`, `testamento`, `livroseq`, `livro`, `capitulo`, `versiculo`, `palavra`, `published`, `hits`) VALUES ' . implode( ', ', $placeholders );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders and values are built above.
		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		return false === $result ? false : count( $rows );
	}

	public static function import_uploaded_bible_from_csv( $books_file, $verses_file, $name, $language_code, $source = 'Upload CSV' ) {
		global $wpdb;

		self::create_tables();
		delete_option( self::OPTION_ERROR );
		update_option( self::OPTION_STATUS, 'importing' );

		$books = self::parse_books_csv( $books_file );
		if ( empty( $books ) ) {
			update_option( self::OPTION_STATUS, 'error' );
			update_option( self::OPTION_ERROR, 'O arquivo books.csv está vazio ou fora do padrão.' );
			return false;
		}

		$bible_id = self::create_bible_version( $name, $language_code, $source, 0 );
		if ( $bible_id < 1 ) {
			update_option( self::OPTION_STATUS, 'error' );
			update_option( self::OPTION_ERROR, 'Não foi possível criar o cadastro da Bíblia.' );
			return false;
		}

		$ok = self::insert_book_batch( $books, $bible_id );
		if ( false === $ok ) {
			self::delete_bible_version( $bible_id );
			update_option( self::OPTION_STATUS, 'error' );
			update_option( self::OPTION_ERROR, 'Erro ao importar books.csv: ' . $wpdb->last_error );
			return false;
		}

		$count = self::import_verses_csv( $verses_file, $bible_id );
		if ( false === $count || $count < 1 ) {
			self::delete_bible_version( $bible_id );
			update_option( self::OPTION_STATUS, 'error' );
			if ( ! get_option( self::OPTION_ERROR ) ) {
				update_option( self::OPTION_ERROR, 'O arquivo verses.csv está vazio ou fora do padrão.' );
			}
			return false;
		}

		update_option( self::OPTION_ACTIVE_BIBLE, $bible_id );
		update_option( self::OPTION_IMPORTED, current_time( 'mysql' ) );
		update_option( self::OPTION_STATUS, 'done' );
		update_option( self::OPTION_PROGRESS, 'Upload concluído: ' . number_format_i18n( $count ) . ' versículos importados.' );
		// Atualiza lastmod do sitemap após importação bem-sucedida e invalida caches leves.
		update_option( 'bdwp70_sitemap_lastmod', current_time( 'Y-m-d' ) );
		self::clear_runtime_caches();
		return $bible_id;
	}

	private static function parse_books_csv( $file ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming uploaded CSV files keeps imports memory-safe.
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$rows   = array();
		$seen   = array();
		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return array();
		}
		$header = null;
		while ( false !== ( $data = self::csv_get_row( $handle ) ) ) {
			if ( null === $header ) {
				$header = self::normalize_csv_header( $data );
				if ( ! self::csv_has_required_columns( $header, array( 'livro_seq', 'livro', 'livro_desc' ) ) ) {
					fclose( $handle );
					return array();
				}
				continue;
			}
			if ( count( array_filter( $data, 'strlen' ) ) < 1 ) {
				continue;
			}
			$row  = self::csv_assoc( $header, $data );
			$seq  = self::csv_value( $row, array( 'livro_seq', 'livroseq', 'seq', 'book_seq' ) );
			$abbr = self::csv_value( $row, array( 'livro', 'abrev', 'abbreviation', 'book' ) );
			$desc = self::csv_value( $row, array( 'livro_desc', 'nome', 'name', 'book_name' ) );
			if ( '' === $seq || '' === $desc ) {
				continue;
			}
			$seq = absint( $seq );
			if ( $seq < 1 || $seq > 66 ) {
				continue;
			}

			if ( isset( $seen[ $seq ] ) ) {
				continue;
			}
			$seen[ $seq ] = true;

			$abbr = sanitize_text_field( $abbr );
			$desc = sanitize_text_field( $desc );

			$rows[] = array(
				'livro'      => '' !== $abbr ? $abbr : $desc,
				'livro_desc' => $desc,
				'livro_seq'  => $seq,
				'published'  => 1,
			);
		}
		fclose( $handle );
		if ( 66 !== count( $seen ) ) {
			return array();
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}

	private static function import_verses_csv( $file, $bible_id ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming uploaded CSV files keeps imports memory-safe.
		global $wpdb;
		if ( ! is_readable( $file ) ) {
			update_option( self::OPTION_ERROR, 'Arquivo verses.csv não encontrado ou não legível.' );
			return false;
		}
		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			update_option( self::OPTION_ERROR, 'Não foi possível abrir verses.csv.' );
			return false;
		}
		$header  = null;
		$batch   = array();
		$count   = 0;
		$line_no = 0;
		while ( false !== ( $data = self::csv_get_row( $handle ) ) ) {
			++$line_no;
			if ( null === $header ) {
				$header = self::normalize_csv_header( $data );
				if ( ! self::csv_has_required_columns( $header, array( 'testamento', 'livroseq', 'livro', 'capitulo', 'versiculo', 'palavra' ) ) ) {
					update_option( self::OPTION_ERROR, 'verses.csv: cabeçalho inválido.' );
					fclose( $handle );
					return false;
				}
				continue;
			}
			if ( count( array_filter( $data, 'strlen' ) ) < 1 ) {
				continue;
			}
			$row      = self::csv_assoc( $header, $data );
			$livroseq = self::csv_value( $row, array( 'livroseq', 'livro_seq', 'book_seq', 'seq' ) );
			$chapter  = self::csv_value( $row, array( 'capitulo', 'chapter', 'cap' ) );
			$verse    = self::csv_value( $row, array( 'versiculo', 'verse', 'ver' ) );
			$text     = self::csv_value( $row, array( 'palavra', 'texto', 'text', 'verse_text' ) );
			if ( '' === $livroseq || '' === $chapter || '' === $verse || '' === $text ) {
				update_option( self::OPTION_ERROR, 'verses.csv: linha ' . $line_no . ' sem livroseq, capitulo, versiculo ou texto.' );
				fclose( $handle );
				return false;
			}
			$livroseq = absint( $livroseq );
			$chapter  = absint( $chapter );
			$verse    = absint( $verse );

			if ( $livroseq < 1 || $livroseq > 66 || $chapter < 1 || $verse < 1 ) {
				update_option( self::OPTION_ERROR, 'verses.csv: linha ' . $line_no . ' com referência bíblica inválida.' );
				fclose( $handle );
				return false;
			}

			$text = self::limit_csv_verse_text( $text );
			if ( '' === $text ) {
				update_option( self::OPTION_ERROR, 'verses.csv: linha ' . $line_no . ' com texto vazio.' );
				fclose( $handle );
				return false;
			}

			$batch[] = array(
				'testamento' => self::normalize_testament( self::csv_value( $row, array( 'testamento', 'testament' ), '' ), $livroseq ),
				'livroseq'   => $livroseq,
				'livro'      => sanitize_text_field( self::csv_value( $row, array( 'livro', 'book', 'abrev' ), '' ) ),
				'capitulo'   => $chapter,
				'versiculo'  => $verse,
				'palavra'    => sanitize_textarea_field( $text ),
				'published'  => 1,
				'hits'       => 0,
			);
			if ( count( $batch ) >= 250 ) {
				$ok = self::insert_verse_batch( $batch, $bible_id );
				if ( false === $ok ) {
					update_option( self::OPTION_ERROR, 'verses.csv: ' . $wpdb->last_error );
					fclose( $handle );
					return false;
				}
				$count += count( $batch );
				update_option( self::OPTION_PROGRESS, 'Importados ' . number_format_i18n( $count ) . ' versículos.' );
				$batch = array();
			}
		}
		fclose( $handle );
		if ( ! empty( $batch ) ) {
			$ok = self::insert_verse_batch( $batch, $bible_id );
			if ( false === $ok ) {
				update_option( self::OPTION_ERROR, 'verses.csv: ' . $wpdb->last_error );
				return false;
			}
			$count += count( $batch );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $count;
	}

	public static function clear_runtime_caches() {
		global $wpdb;
		if ( is_object( $wpdb ) ) {
			$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_bdwp70_qv_%' OR `option_name` LIKE '_transient_timeout_bdwp70_qv_%' OR `option_name` LIKE '_transient_bdwp70_rand_%' OR `option_name` LIKE '_transient_timeout_bdwp70_rand_%' OR `option_name` LIKE '_transient_bdwp70_chapters_%' OR `option_name` LIKE '_transient_timeout_bdwp70_chapters_%' OR `option_name` LIKE '_transient_bdwp70_sitemap_%' OR `option_name` LIKE '_transient_timeout_bdwp70_sitemap_%' OR `option_name` LIKE '_transient_bdwp70_bible_%' OR `option_name` LIKE '_transient_timeout_bdwp70_bible_%' OR `option_name` LIKE '_transient_bdwp70_default_bible_version_id' OR `option_name` LIKE '_transient_timeout_bdwp70_default_bible_version_id' OR `option_name` LIKE '_transient_bdwp70_books_count_%' OR `option_name` LIKE '_transient_timeout_bdwp70_books_count_%' OR `option_name` LIKE '_transient_bdwp70_verses_count_%' OR `option_name` LIKE '_transient_timeout_bdwp70_verses_count_%'" );
		}
	}

	private static function csv_has_required_columns( $header, $required ) {
		foreach ( $required as $column ) {
			if ( ! in_array( sanitize_key( remove_accents( $column ) ), $header, true ) ) {
				return false;
			}
		}

		return true;
	}

	private static function normalize_testament( $value, $book_seq ) {
		$value = strtoupper( sanitize_key( (string) $value ) );
		if ( in_array( $value, array( 'OT', 'NT' ), true ) ) {
			return $value;
		}
		if ( 'O' === $value ) {
			return 'OT';
		}
		if ( 'N' === $value ) {
			return 'NT';
		}

		return absint( $book_seq ) <= 39 ? 'OT' : 'NT';
	}

	private static function limit_csv_verse_text( $text ) {
		$text = sanitize_textarea_field( $text );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, 5000, 'UTF-8' );
		}

		return substr( $text, 0, 5000 );
	}

	private static function csv_get_row( $handle ) {
		$line = fgets( $handle );
		if ( false === $line ) {
			return false;
		}
		$delimiter = substr_count( $line, ';' ) > substr_count( $line, ',' ) ? ';' : ',';
		// PHP 8.4 deprecates calling str_getcsv() without an explicit $escape.
		// The historical default ('\\') is passed to preserve parsing behavior
		// across PHP 7.4–8.5.
		return str_getcsv( $line, $delimiter, '"', '\\' );
	}

	private static function normalize_csv_header( $data ) {
		$header = array();
		foreach ( $data as $item ) {
			$item     = trim( (string) $item );
			$item     = preg_replace( '/^\xEF\xBB\xBF/', '', $item );
			$header[] = sanitize_key( remove_accents( $item ) );
		}
		return $header;
	}

	private static function csv_assoc( $header, $data ) {
		$row = array();
		foreach ( $header as $i => $key ) {
			$row[ $key ] = isset( $data[ $i ] ) ? trim( (string) $data[ $i ] ) : '';
		}
		return $row;
	}

	private static function csv_value( $row, $keys, $default = '' ) {
		foreach ( $keys as $key ) {
			$key = sanitize_key( remove_accents( $key ) );
			if ( isset( $row[ $key ] ) && '' !== $row[ $key ] ) {
				return $row[ $key ];
			}
		}
		return $default;
	}
}
