<?php
/**
 * Engine: orchestrates entity mapping, fan-out retrieval, scoring and
 * insertion for one post, plus undo and bulk processing.
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Engine.
 */
class ELE_Engine {

	/**
	 * Run the engine on one post.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $dry_run Do not change content.
	 * @return array|WP_Error Result array or error.
	 */
	public function run( $post_id, $dry_run = false ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'ele_no_post', __( 'Post not found.', 'entity-link-engine' ) );
		}

		$per_post_disabled = get_post_meta( $post->ID, '_ele_auto_links', true );
		if ( $per_post_disabled && ! $dry_run && 'publish' === $post->post_status ) {
			return array(
				'post_id'    => (int) $post->ID,
				'disabled'   => true,
				'inserted'   => array(),
				'skipped'    => array(),
				'candidates' => array(),
				'content_changed' => false,
			);
		}

		$settings  = ELE_Install::get_settings();
		$max_links = (int) get_post_meta( $post->ID, '_ele_max_links', true );
		if ( ! $max_links ) {
			$max_links = (int) $settings['max_links'];
		}
		$settings['max_links'] = $max_links;

		$content  = $post->post_content;
		$map      = new ELE_Entity_Map();

		// 1. Entity mapping: find entity mentions in the content.
		$mentions = $map->find_mentions( $content, $post->ID );

		// 2. Fan-out queries: one post -> many retrieval queries.
		$fanout   = new ELE_Fanout_Query();
		$queries  = $fanout->generate( $post, $mentions );

		// 3. Retrieve + score candidates.
		$retriever = new ELE_Retriever();
		$candidates = $retriever->retrieve( $post, $queries, $mentions );

		// Attach mention texts of each candidate as anchor candidates.
		// find_mentions returns mentions weight-sorted (manual first), so the
		// anchor list per post is ordered strongest first. The inserter tries
		// each anchor until one lands in an insertable block.
		$anchors_by_post = array();
		foreach ( $mentions as $m ) {
			if ( empty( $m['text'] ) ) {
				continue;
			}
			if ( ! isset( $anchors_by_post[ $m['post_id'] ] ) ) {
				$anchors_by_post[ $m['post_id'] ] = array();
			}
			if ( ! in_array( $m['text'], $anchors_by_post[ $m['post_id'] ], true ) ) {
				$anchors_by_post[ $m['post_id'] ][] = $m['text'];
			}
		}
		foreach ( $candidates as $i => $c ) {
			if ( ! empty( $anchors_by_post[ $c['post_id'] ] ) ) {
				$candidates[ $i ]['anchors'] = $anchors_by_post[ $c['post_id'] ];
				$candidates[ $i ]['anchor']  = $anchors_by_post[ $c['post_id'] ][0];
			}
		}

		if ( empty( $candidates ) ) {
			$result = array(
				'post_id'    => (int) $post->ID,
				'disabled'   => false,
				'inserted'   => array(),
				'skipped'    => array(),
				'candidates' => array(),
				'content_changed' => false,
			);
			if ( ! $dry_run ) {
				update_post_meta( $post->ID, '_ele_last_run', $result );
			}
			return $result;
		}

		// 4. Insert links.
		$inserter = new ELE_Link_Insert();
		$insertion = $inserter->insert( $content, $candidates, $settings );

		$result = array(
			'post_id'    => (int) $post->ID,
			'disabled'   => false,
			'inserted'   => $insertion['inserted'],
			'skipped'    => $insertion['skipped'],
			'candidates' => $candidates,
			'content_changed' => $insertion['content'] !== $content,
		);

		if ( $dry_run ) {
			return $result;
		}

		// Persist.
		if ( $result['content_changed'] ) {
			// Snapshot for undo (only keep the latest snapshot per run).
			update_post_meta( $post->ID, '_ele_snapshot', $content );
			// Suppress the follow-up save_post run caused by our own update.
			set_transient( 'ele_just_ran_' . $post->ID, 1, 30 );
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => $insertion['content'],
				)
			);
		}

		// Rebuild the active log from the final content so the report always
		// reflects what is really in the post (incl. links from earlier runs).
		$this->rebuild_log( $post->ID, $insertion['content'] );

		update_post_meta( $post->ID, '_ele_inserted_links', $insertion['inserted'] );
		update_post_meta( $post->ID, '_ele_last_run', $result );

		return $result;
	}

	/**
	 * Undo the last run on a post: restore snapshot, clear log and metas.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function undo( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		$snapshot = get_post_meta( $post->ID, '_ele_snapshot', true );
		if ( is_string( $snapshot ) && '' !== $snapshot ) {
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => $snapshot,
				)
			);
		}
		$this->clear_log( $post->ID );
		delete_post_meta( $post->ID, '_ele_snapshot' );
		delete_post_meta( $post->ID, '_ele_inserted_links' );
		delete_post_meta( $post->ID, '_ele_last_run' );
		return true;
	}

	/**
	 * Bulk tick: process a batch of posts from a cron queue.
	 *
	 * @param array $args Tick args (offset, batch, total).
	 */
	public function bulk_tick( $args ) {
		$args     = wp_parse_args(
			(array) $args,
			array(
				'offset' => 0,
				'batch'  => 5,
				'total'  => 0,
			)
		);
		$settings = ELE_Install::get_settings();

		$posts = get_posts(
			array(
				'post_type'   => $settings['post_types'],
				'post_status' => array( 'publish' ),
				'numberposts' => (int) $args['batch'],
				'offset'      => (int) $args['offset'],
				'fields'      => 'ids',
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);

		foreach ( $posts as $pid ) {
			$result = $this->run( $pid );
			update_option( 'ele_bulk_last', array(
				'post_id' => (int) $pid,
				'inserted' => is_array( $result ) && isset( $result['inserted'] ) ? count( $result['inserted'] ) : 0,
				'at'      => current_time( 'mysql' ),
			) );
		}

		$next_offset = (int) $args['offset'] + count( $posts );
		if ( ! empty( $posts ) && ( 0 === (int) $args['total'] || $next_offset < (int) $args['total'] ) ) {
			wp_schedule_single_event(
				time() + 5,
				'ele_bulk_tick',
				array(
					'offset' => $next_offset,
					'batch'  => (int) $args['batch'],
					'total'  => (int) $args['total'],
				)
			);
			update_option( 'ele_bulk_active', $next_offset );
		} else {
			delete_option( 'ele_bulk_active' );
			delete_option( 'ele_bulk_last' );
		}
	}

	/**
	 * Start a bulk run over all posts.
	 */
	public function start_bulk() {
		$settings = ELE_Install::get_settings();
		$total    = count(
			get_posts(
				array(
					'post_type'   => $settings['post_types'],
					'post_status' => array( 'publish' ),
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
		update_option( 'ele_bulk_total', $total );
		update_option( 'ele_bulk_active', 0 );
		wp_schedule_single_event(
			time() + 2,
			'ele_bulk_tick',
			array(
				'offset' => 0,
				'batch'  => 5,
				'total'  => $total,
			)
		);
		return $total;
	}

	/**
	 * Log one link row.
	 *
	 * @param int   $source_id Source post.
	 * @param array $link      Link data.
	 * @param string $mode     'auto' or 'manual'.
	 */
	private function log_link( $source_id, $link, $mode ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$wpdb->prefix . 'ele_links',
			array(
				'source_id' => (int) $source_id,
				'target_id' => isset( $link['target_id'] ) ? (int) $link['target_id'] : 0,
				'anchor'    => isset( $link['anchor'] ) ? mb_substr( (string) $link['anchor'], 0, 255 ) : '',
				'score'     => isset( $link['score'] ) ? (float) $link['score'] : 0.0,
				'mode'      => $mode,
				'status'    => 'active',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%f', '%s', '%s', '%s' )
		);
	}

	/**
	 * Clear link log rows for a source post.
	 *
	 * @param int $source_id Source post.
	 */
	private function clear_log( $source_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'ele_links', array( 'source_id' => $source_id ), array( '%d' ) );
	}

	/**
	 * Rebuild the active log from the final content: every auto-inserted link
	 * (marked with data-ele) is recorded, so the report matches reality even
	 * after re-runs that add no new links.
	 *
	 * @param int    $source_id Source post.
	 * @param string $content   Final content.
	 */
	private function rebuild_log( $source_id, $content ) {
		$this->clear_log( $source_id );
		$links = array();

		// Match both attribute orders: <a href data-ele> and <a data-ele href>.
		if ( preg_match_all( '/<a\b[^>]*\bhref="([^"]+)"[^>]*\bdata-ele="(\d+)"[^>]*>(.*?)<\/a>/isu', $content, $m1 ) ) {
			foreach ( $m1[0] as $i => $unused ) {
				$links[] = array( 'url' => $m1[1][ $i ], 'target_id' => (int) $m1[2][ $i ], 'anchor' => trim( wp_strip_all_tags( $m1[3][ $i ] ) ) );
			}
		}
		if ( preg_match_all( '/<a\b[^>]*\bdata-ele="(\d+)"[^>]*\bhref="([^"]+)"[^>]*>(.*?)<\/a>/isu', $content, $m2 ) ) {
			foreach ( $m2[0] as $i => $unused ) {
				$links[] = array( 'target_id' => (int) $m2[1][ $i ], 'url' => $m2[2][ $i ], 'anchor' => trim( wp_strip_all_tags( $m2[3][ $i ] ) ) );
			}
		}

		$seen = array();
		foreach ( $links as $link ) {
			$fingerprint = $link['url'] . '|' . $link['target_id'];
			if ( isset( $seen[ $fingerprint ] ) ) {
				continue;
			}
			$seen[ $fingerprint ] = 1;
			$this->log_link( $source_id, $link, 'auto' );
		}
	}
}
