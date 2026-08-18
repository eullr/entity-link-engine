<?php
/**
 * Installation, activation and settings.
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Install / uninstall helpers and settings store.
 */
class ELE_Install {

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'post_types'      => array( 'post' ),
			'max_links'       => 3,
			'min_score'       => 2.5,
			'auto_on_publish' => 1,
			'add_link_class'  => 1,
			'link_class'      => 'ele-auto-link',
			'skip_blocks'     => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'code', 'table', 'blockquote', 'figure', 'img' ),
			'embed'           => array(
				'enabled' => 0,
				'api_url' => '',
				'api_key' => '',
				'model'   => 'text-embedding-3-small',
				'blend'   => 0.4,
			),
		);
	}

	/**
	 * Current settings merged over defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( 'ele_settings', array() );
		$saved = is_array( $saved ) ? $saved : array();
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Whether the given post type is enabled.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_post_type_enabled( $post_type ) {
		$settings = self::get_settings();
		$types    = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array( 'post' );
		return in_array( $post_type, $types, true );
	}

	/**
	 * Table names.
	 *
	 * @return array
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'index' => $wpdb->prefix . 'ele_entity_index',
			'links' => $wpdb->prefix . 'ele_links',
		);
	}

	/**
	 * Create plugin tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables    = self::tables();
		$charset   = $wpdb->get_charset_collate();

		$index_sql = "CREATE TABLE {$tables['index']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_key VARCHAR(191) NOT NULL,
			entity_label VARCHAR(191) NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'title',
			weight INT NOT NULL DEFAULT 10,
			lang VARCHAR(10) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY entity_key (entity_key),
			KEY post_id (post_id)
		) $charset;";

		$links_sql = "CREATE TABLE {$tables['links']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_id BIGINT UNSIGNED NOT NULL,
			target_id BIGINT UNSIGNED NOT NULL,
			anchor VARCHAR(255) NOT NULL DEFAULT '',
			score DECIMAL(8,3) NOT NULL DEFAULT 0,
			mode VARCHAR(10) NOT NULL DEFAULT 'auto',
			status VARCHAR(10) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY source_id (source_id),
			KEY target_id (target_id)
		) $charset;";

		dbDelta( $index_sql );
		dbDelta( $links_sql );
	}

	/**
	 * Activation: tables + defaults.
	 */
	public static function activate() {
		self::create_tables();
		if ( false === get_option( 'ele_settings' ) ) {
			add_option( 'ele_settings', self::defaults() );
		}
		update_option( 'ele_db_version', '1.0.0' );
	}

	/**
	 * Deactivation: nothing destructive.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'ele_bulk_tick' );
	}

	/**
	 * Uninstall: remove options, tables and post meta.
	 * Called from uninstall.php.
	 */
	public static function uninstall() {
		global $wpdb;

		delete_option( 'ele_settings' );
		delete_option( 'ele_entities_manual' );
		delete_option( 'ele_index_built' );
		delete_option( 'ele_db_version' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ele_links" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ele_entity_index" );

		delete_post_meta_by_key( '_ele_snapshot' );
		delete_post_meta_by_key( '_ele_inserted_links' );
		delete_post_meta_by_key( '_ele_last_run' );
		delete_post_meta_by_key( '_ele_embedding' );
		delete_post_meta_by_key( '_ele_auto_links' );
		delete_post_meta_by_key( '_ele_max_links' );
		delete_post_meta_by_key( '_ele_objectives' );
		delete_post_meta_by_key( '_ele_use_tag' );
		delete_post_meta_by_key( '_ele_lang' );
	}
}
