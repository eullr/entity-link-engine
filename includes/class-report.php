<?php
/**
 * Link report: outgoing/incoming auto links and orphans.
 *
 * Mirrors the build-time report of the reference implementation
 * (internal-link-report.mjs) inside the WordPress admin.
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Report.
 */
class ELINK_Report {

	/**
	 * Summary counts.
	 *
	 * @return array
	 */
	public function summary() {
		global $wpdb;
		$settings = ELINK_Install::get_settings();

		$posts = get_posts(
			array(
				'post_type'   => $settings['post_types'],
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$total_posts = count( $posts );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_links = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}elink_links WHERE status = 'active'" );

		// Posts with zero incoming internal links (from any source post).
		$incoming = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows     = $wpdb->get_results( "SELECT target_id, COUNT(*) AS c FROM {$wpdb->prefix}elink_links WHERE status = 'active' GROUP BY target_id" );
		foreach ( $rows as $row ) {
			$incoming[ (int) $row->target_id ] = (int) $row->c;
		}
		$orphans = array();
		foreach ( $posts as $pid ) {
			if ( empty( $incoming[ (int) $pid ] ) ) {
				$orphans[] = (int) $pid;
			}
		}

		// Total editorial+auto incoming edges on indexed posts (incl. manual links
		// that exist in content but are not tracked): approximate via log table
		// plus posts whose content already contains links to them.
		$auto_edges = $active_links;

		return array(
			'posts'      => $total_posts,
			'auto_edges' => $auto_edges,
			'orphans'    => $orphans,
			'incoming'   => $incoming,
		);
	}

	/**
	 * Per-post detail rows for the report table.
	 *
	 * @return array
	 */
	public function detail() {
		global $wpdb;
		$settings = ELINK_Install::get_settings();

		$posts = get_posts(
			array(
				'post_type'   => $settings['post_types'],
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
				'fields'      => 'ids',
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);

		$outgoing = array();
		$incoming = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows     = $wpdb->get_results( "SELECT source_id, target_id, COUNT(*) AS c FROM {$wpdb->prefix}elink_links WHERE status = 'active' GROUP BY source_id, target_id" );
		foreach ( $rows as $row ) {
			$outgoing[ (int) $row->source_id ][] = (int) $row->target_id;
			$incoming[ (int) $row->target_id ][] = (int) $row->source_id;
		}

		$detail = array();
		foreach ( $posts as $pid ) {
			$post = get_post( $pid );
			$detail[] = array(
				'id'         => (int) $pid,
				'title'      => $post ? $post->post_title : '',
				'url'        => get_permalink( $pid ),
				'outgoing'   => isset( $outgoing[ (int) $pid ] ) ? count( $outgoing[ (int) $pid ] ) : 0,
				'incoming'   => isset( $incoming[ (int) $pid ] ) ? count( $incoming[ (int) $pid ] ) : 0,
				'orphan'     => empty( $incoming[ (int) $pid ] ),
			);
		}
		return $detail;
	}
}
