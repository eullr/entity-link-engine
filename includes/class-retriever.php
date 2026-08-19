<?php
/**
 * Retrieval and scoring.
 *
 * Runs every fan-out query against the entity index, aggregates candidates,
 * then scores them with the same signal mix as the reference implementation:
 * taxonomy overlap, title/content term overlap, entity overlap, editorial
 * link signals, fan-out coverage and an optional semantic (embedding) blend.
 *
 * Threshold and default link count mirror the Astro implementation
 * (min score 2.5, max 3 links per post).
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retriever.
 */
class ELINK_Retriever {

	/**
	 * Entity map instance.
	 *
	 * @var ELINK_Entity_Map
	 */
	private $map;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->map = new ELINK_Entity_Map();
	}

	/**
	 * Retrieve and score candidates for a source post.
	 *
	 * @param WP_Post $post     Source post.
	 * @param array   $queries  Fan-out queries.
	 * @param array   $mentions Entity mentions found in the source.
	 * @return array List of candidates:
	 *               array( 'post_id', 'title', 'url', 'score', 'lexical', 'semantic',
	 *                      'reasons', 'fanout_types' ).
	 */
	public function retrieve( $post, $queries, $mentions ) {
		$settings  = ELINK_Install::get_settings();
		$min_score = isset( $settings['min_score'] ) ? (float) $settings['min_score'] : 2.5;

		$raw = $this->query_index( $queries, $post->ID );
		if ( empty( $raw ) ) {
			return array();
		}

		// Cap the pool before full scoring.
		uasort( $raw, function ( $a, $b ) {
			return $b['raw'] <=> $a['raw'];
		} );
		$pool = array_slice( $raw, 0, 100, true );

		$existing_hrefs = $this->existing_hrefs( $post->post_content );
		$source_terms  = $this->map->tokens( $post->post_title . ' ' . $post->post_excerpt . ' ' . $this->map->strip_for_matching( $post->post_content ) );

		$embedder = new ELINK_Embedder();
		$embed_query = array();
		if ( $embedder->is_enabled() ) {
			foreach ( $queries as $query ) {
				$embed_query[] = $query['text'];
			}
		}

		$candidates = array();
		foreach ( array_keys( $pool ) as $candidate_id ) {
			$candidate = get_post( $candidate_id );
			if ( ! $candidate || $candidate->post_status !== 'publish' || (int) $candidate->ID === (int) $post->ID ) {
				continue;
			}
			if ( ! ELINK_Install::is_post_type_enabled( $candidate->post_type ) ) {
				continue;
			}

			$score_data = $this->score( $post, $candidate, $pool[ $candidate_id ], $source_terms, $existing_hrefs );
			$candidate  = array(
				'post_id'     => (int) $candidate->ID,
				'title'       => $candidate->post_title,
				'url'         => get_permalink( $candidate->ID ),
				'score'       => $score_data['score'],
				'lexical'     => $score_data['lexical'],
				'semantic'    => 0.0,
				'reasons'     => $score_data['reasons'],
				'fanout_types'=> $pool[ $candidate_id ]['types'],
				'pub_date'    => $candidate->post_date,
				'already_linked' => isset( $existing_hrefs[ $score_data['url'] ] ),
			);

			if ( $embedder->is_enabled() && ! empty( $embed_query ) ) {
				$candidate['semantic'] = $embedder->similarity_to_queries( $candidate_id, $embed_query );
			}
			$candidates[] = $candidate;
		}

		// Blend semantic contribution: blend * 5 * mean cosine.
		$blend = isset( $settings['embed']['blend'] ) ? (float) $settings['embed']['blend'] : 0.4;
		if ( $embedder->is_enabled() && $blend > 0 ) {
			foreach ( $candidates as &$candidate ) {
				$candidate['score'] = $candidate['lexical'] + $blend * 5 * max( 0.0, (float) $candidate['semantic'] );
				$candidate['reasons'][] = 'semantic:' . number_format( $candidate['semantic'], 3 );
			}
			unset( $candidate );
		}

		// Filter, sort (score desc, then recency), return.
		$candidates = array_values(
			array_filter(
				$candidates,
				function ( $c ) use ( $min_score ) {
					return $c['score'] >= $min_score;
				}
			)
		);
		usort( $candidates, function ( $a, $b ) {
			if ( abs( $b['score'] - $a['score'] ) > 0.0001 ) {
				return $b['score'] <=> $a['score'];
			}
			return strtotime( $b['pub_date'] ) <=> strtotime( $a['pub_date'] );
		} );

		return $candidates;
	}

	/**
	 * Run all fan-out queries against the entity index and aggregate raw
	 * candidate scores per post.
	 *
	 * @param array $queries     Fan-out queries.
	 * @param int   $exclude_id  Post to exclude.
	 * @return array post_id => array( 'raw' => float, 'types' => array )
	 */
	private function query_index( $queries, $exclude_id ) {
		global $wpdb;

		$terms = array();
		foreach ( $queries as $query ) {
			foreach ( $query['terms'] as $term ) {
				$terms[ $term ] = 1;
			}
		}
		if ( empty( $terms ) ) {
			return array();
		}

		// Single query with OR'd LIKE conditions per term. Values are escaped via
		// $wpdb->esc_like() and the whole statement is prepared — the dynamic
		// placeholder list is why the sniffs need to be suppressed here.
		$conditions = array();
		$params     = array( (int) $exclude_id );
		foreach ( array_keys( $terms ) as $term ) {
			$conditions[] = 'entity_key LIKE %s';
			$params[]     = '%' . $wpdb->esc_like( $term ) . '%';
		}
		$query = 'SELECT post_id, entity_key, weight FROM ' . $wpdb->prefix . 'elink_entity_index WHERE post_id <> %d AND (' . implode( ' OR ', $conditions ) . ')';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		// Map term -> matched rows.
		$term_rows = array();
		foreach ( $rows as $row ) {
			$row_key = (string) $row->entity_key;
			foreach ( array_keys( $terms ) as $term ) {
				if ( false !== mb_strpos( $row_key, $term ) ) {
					$term_rows[ $term ][] = $row;
					break;
				}
			}
		}

		// Manual entities participate in retrieval with their high weight.
		$map = new ELINK_Entity_Map();
		foreach ( $map->manual_rows() as $manual ) {
			$manual_key = (string) $manual['entity_key'];
			foreach ( array_keys( $terms ) as $term ) {
				if ( false !== mb_strpos( $manual_key, $term ) ) {
					$term_rows[ $term ][] = (object) $manual;
				}
			}
		}

		$acc = array();
		foreach ( $queries as $query ) {
			foreach ( $query['terms'] as $term ) {
				if ( empty( $term_rows[ $term ] ) ) {
					continue;
				}
				foreach ( $term_rows[ $term ] as $row ) {
					$pid = (int) $row->post_id;
					if ( ! isset( $acc[ $pid ] ) ) {
						$acc[ $pid ] = array( 'raw' => 0.0, 'types' => array() );
					}
					// Weighted contribution: query weight * normalized index weight.
					$acc[ $pid ]['raw'] += (float) $query['weight'] * ( (int) $row->weight / 100.0 );
					// Track which fan-out query types retrieved this post.
					$acc[ $pid ]['types'][ $query['type'] ] = 1;
				}
			}
		}

		return $acc;
	}

	/**
	 * Full score of a candidate against the source post.
	 *
	 * @param WP_Post $source         Source post.
	 * @param WP_Post $candidate      Candidate post.
	 * @param array   $raw            Raw retrieval data (raw, types).
	 * @param array   $source_terms   Source term list.
	 * @param array   $existing_hrefs Set of URLs already linked in source.
	 * @return array array( 'score', 'lexical', 'url', 'reasons' )
	 */
	public function score( $source, $candidate, $raw, $source_terms, $existing_hrefs ) {
		$reasons = array();
		$score   = 0.0;

		// Taxonomy overlap (tags + categories).
		$shared_tags = $this->shared_terms( $source->ID, $candidate->ID, 'post_tag' );
		$score      += min( count( $shared_tags ), 4 ) * 2.0;
		if ( $shared_tags ) {
			$reasons[] = 'tags:' . implode( ',', array_slice( $shared_tags, 0, 3 ) );
		}
		$shared_cats = $this->shared_terms( $source->ID, $candidate->ID, 'category' );
		$score      += min( count( $shared_cats ), 3 ) * 1.5;
		if ( $shared_cats ) {
			$reasons[] = 'cats:' . implode( ',', array_slice( $shared_cats, 0, 3 ) );
		}

		// Optional objectives + format metadata (mirrors Astro objectives/useTag).
		$shared_objectives = $this->shared_meta_list( $source->ID, $candidate->ID, '_elink_objectives' );
		$score            += min( count( $shared_objectives ), 3 ) * 3.0;
		if ( $shared_objectives ) {
			$reasons[] = 'objectives:' . implode( ',', array_slice( $shared_objectives, 0, 3 ) );
		}
		$source_format = get_post_meta( $source->ID, '_elink_use_tag', true );
		$cand_format   = get_post_meta( $candidate->ID, '_elink_use_tag', true );
		if ( $source_format && $source_format === $cand_format ) {
			$score    += 0.5;
			$reasons[] = 'format:' . $source_format;
		}

		// Title term overlap (strong signal, capped).
		$source_title_terms = $this->map->tokens( $source->post_title );
		$cand_title_terms   = $this->map->tokens( $candidate->post_title );
		$title_shared       = array_values( array_intersect( $source_title_terms, $cand_title_terms ) );
		$score             += min( count( $title_shared ), 4 ) * 1.25;
		if ( $title_shared ) {
			$reasons[] = 'title:' . implode( ',', array_slice( $title_shared, 0, 3 ) );
		}

		// Supporting term overlap (candidate title + excerpt vs source terms).
		$cand_terms = $this->map->tokens( $candidate->post_title . ' ' . $candidate->post_excerpt );
		$support    = array_values( array_intersect( $cand_terms, $source_terms ) );
		$support    = array_values( array_diff( $support, $title_shared ) );
		$score     += min( count( $support ), 4 ) * 0.25;
		if ( $support ) {
			$reasons[] = 'terms:' . implode( ',', array_slice( $support, 0, 3 ) );
		}

		// Fan-out coverage: how many distinct query types retrieved this post.
		$types = isset( $raw['types'] ) ? array_keys( $raw['types'] ) : array();
		$score += min( count( $types ), 4 ) * 0.75;
		if ( $types ) {
			$reasons[] = 'fanout:' . implode( ',', $types );
		}

		// Editorial link signals.
		$candidate_url = get_permalink( $candidate->ID );
		if ( isset( $existing_hrefs[ $candidate_url ] ) ) {
			$score    += 2.5;
			$reasons[] = 'source-links-candidate';
		}
		if ( $this->post_links_to( $candidate->ID, $source->ID ) ) {
			$score    += 1.5;
			$reasons[] = 'candidate-links-source';
		}

		return array(
			'score'   => round( $score, 3 ),
			'lexical' => round( $score, 3 ),
			'url'     => $candidate_url,
			'reasons' => $reasons,
		);
	}

	/**
	 * Shared term slugs between two posts for a taxonomy.
	 *
	 * @param int    $a        Post A.
	 * @param int    $b        Post B.
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	private function shared_terms( $a, $b, $taxonomy ) {
		$ta = get_the_terms( $a, $taxonomy );
		$tb = get_the_terms( $b, $taxonomy );
		if ( ! is_array( $ta ) || ! is_array( $tb ) ) {
			return array();
		}
		$slugs_a = wp_list_pluck( $ta, 'slug' );
		$slugs_b = wp_list_pluck( $tb, 'slug' );
		return array_values( array_intersect( $slugs_a, $slugs_b ) );
	}

	/**
	 * Shared comma-separated meta values between two posts.
	 *
	 * @param int    $a       Post A.
	 * @param int    $b       Post B.
	 * @param string $key     Meta key.
	 * @return array
	 */
	private function shared_meta_list( $a, $b, $key ) {
		$va = get_post_meta( $a, $key, true );
		$vb = get_post_meta( $b, $key, true );
		if ( ! is_string( $va ) || ! is_string( $vb ) || '' === trim( $va ) || '' === trim( $vb ) ) {
			return array();
		}
		$norm = function ( $v ) {
			$parts = array_map( 'trim', explode( ',', $v ) );
			$parts = array_filter( $parts );
			return array_map( 'mb_strtolower', $parts );
		};
		return array_values( array_intersect( $norm( $va ), $norm( $vb ) ) );
	}

	/**
	 * Whether a post's content contains a link to another post.
	 *
	 * @param int $from Post ID.
	 * @param int $to   Post ID.
	 * @return bool
	 */
	private function post_links_to( $from, $to ) {
		$post = get_post( $from );
		if ( ! $post ) {
			return false;
		}
		$target_url = get_permalink( $to );
		$target_url = untrailingslashit( $target_url );
		return false !== strpos( $post->post_content, $target_url )
			|| false !== strpos( $post->post_content, untrailingslashit( wp_make_link_relative( $target_url ) ) );
	}

	/**
	 * Existing hrefs in content, keyed by URL.
	 *
	 * @param string $content HTML.
	 * @return array
	 */
	public function existing_hrefs( $content ) {
		$out = array();
		if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\']/iu', $content, $matches ) ) {
			foreach ( $matches[1] as $href ) {
				$out[ trim( $href ) ] = 1;
			}
		}
		return $out;
	}
}
