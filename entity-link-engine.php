<?php
/**
 * Plugin Name:       Entity Link Engine
 * Plugin URI:        https://eullrich.com/
 * Description:       Automatic internal linking with entity mapping and fan-out query retrieval. Maps entities in your content to existing posts, generates fan-out retrieval queries and inserts score-ranked internal links — not just random links.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      7.0
 * Author:            Eugen Ullrich
 * Author URI:        https://eullrich.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       entity-link-engine
 * Domain Path:       /languages
 *
 * Entity Link Engine is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Entity Link Engine is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

define( 'ELE_VERSION', '1.0.0' );
define( 'ELE_FILE', __FILE__ );
define( 'ELE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELE_URL', plugin_dir_url( __FILE__ ) );
define( 'ELE_BASENAME', plugin_basename( __FILE__ ) );

require_once ELE_DIR . 'includes/class-install.php';
require_once ELE_DIR . 'includes/class-entity-map.php';
require_once ELE_DIR . 'includes/class-fanout-query.php';
require_once ELE_DIR . 'includes/class-retriever.php';
require_once ELE_DIR . 'includes/class-link-insert.php';
require_once ELE_DIR . 'includes/class-embedder.php';
require_once ELE_DIR . 'includes/class-engine.php';
require_once ELE_DIR . 'includes/class-report.php';
require_once ELE_DIR . 'includes/class-rest.php';
require_once ELE_DIR . 'includes/class-admin.php';

/**
 * Hook the engine into post lifecycle.
 *
 * @param int    $post_id Post ID.
 * @param WP_Post $post   Post object.
 * @param bool   $update  Whether this is an update.
 */
function ele_on_save_post( $post_id, $post, $update ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	// Re-entrancy guard: the engine itself calls wp_update_post(), which fires
	// save_post again. The transient set right before that update suppresses
	// the follow-up run (which would be a no-op anyway).
	if ( get_transient( 'ele_just_ran_' . $post_id ) ) {
		delete_transient( 'ele_just_ran_' . $post_id );
		return;
	}
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return;
	}
	// Skip bulk edit / quick edit saves that do not carry real content edits.
	if ( isset( $_POST['bulk_edit'] ) || isset( $_POST['action'] ) && 'inline-save' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$settings = ELE_Install::get_settings();
	if ( empty( $settings['auto_on_publish'] ) ) {
		return;
	}
	$engine = new ELE_Engine();
	$engine->run( $post_id );
}
add_action( 'save_post', 'ele_on_save_post', 20, 3 );

// Register activation / deactivation.
register_activation_hook( ELE_FILE, array( 'ELE_Install', 'activate' ) );
register_deactivation_hook( ELE_FILE, array( 'ELE_Install', 'deactivate' ) );

// Admin hooks (menus, meta box, REST, cron tick).
if ( is_admin() ) {
	new ELE_Admin();
	new ELE_REST();
}

add_action( 'ele_bulk_tick', 'ele_bulk_tick_callback' );
/**
 * Cron callback for the bulk run.
 *
 * @param array $args Tick arguments.
 */
function ele_bulk_tick_callback( $args ) {
	$engine = new ELE_Engine();
	$engine->bulk_tick( $args );
}

/**
 * Optional: allow other plugins to extend the entity index with
 * their own entities. Filter returns array of
 * array( 'entity_label' => string, 'target_post_id' => int, 'priority' => int ).
 *
 * @return array
 */
function ele_manual_entities() {
	$stored = get_option( 'ele_entities_manual', array() );
	$stored = is_array( $stored ) ? $stored : array();
	$filtered = apply_filters( 'ele_manual_entities', $stored );
	return is_array( $filtered ) ? $filtered : $stored;
}
