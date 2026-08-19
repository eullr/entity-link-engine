<?php
/**
 * Fan-out query generation.
 *
 * One post becomes many retrieval queries: the full title, every heading,
 * every mapped entity mention, taxonomy terms and title n-grams. Each query
 * is a weighted "fan-out" of the source document into the entity index.
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fan-out queries.
 */
class ELINK_Fanout_Query {

	/**
	 * Generate fan-out queries for a post.
	 *
	 * @param WP_Post $post      Post.
	 * @param array   $mentions  Entity mentions (from ELINK_Entity_Map::find_mentions).
	 * @return array List of array( 'type', 'text', 'weight', 'terms' ).
	 */
	public function generate( $post, $mentions = array() ) {
		$map = new ELINK_Entity_Map();
		$queries = array();

		$title = trim( $post->post_title );
		if ( '' !== $title ) {
			$queries[] = array(
				'type'   => 'title',
				'text'   => $title,
				'weight' => 1.0,
				'terms'  => $map->tokens( $title ),
			);
		}

		foreach ( $map->extract_headings( $post->post_content ) as $heading ) {
			$queries[] = array(
				'type'   => 'heading',
				'text'   => $heading,
				'weight' => 0.8,
				'terms'  => $map->tokens( $heading ),
			);
		}

		// Entity mentions become queries with the weight of their index row.
		$seen_entity = array();
		foreach ( (array) $mentions as $mention ) {
			if ( isset( $seen_entity[ $mention['key'] ] ) ) {
				continue;
			}
			$seen_entity[ $mention['key'] ] = 1;
			$queries[] = array(
				'type'   => 'entity',
				'text'   => $mention['label'],
				'weight' => 0.7,
				'terms'  => $map->tokens( $mention['label'] ),
			);
		}

		foreach ( $this->tax_terms( $post->ID, 'post_tag' ) as $term ) {
			$queries[] = array(
				'type'   => 'tag',
				'text'   => $term,
				'weight' => 0.6,
				'terms'  => $map->tokens( $term ),
			);
		}
		foreach ( $this->tax_terms( $post->ID, 'category' ) as $term ) {
			$queries[] = array(
				'type'   => 'category',
				'text'   => $term,
				'weight' => 0.5,
				'terms'  => $map->tokens( $term ),
			);
		}

		// Title n-grams (bigrams) as wide queries.
		foreach ( $this->title_ngrams( $title ) as $ngram ) {
			$queries[] = array(
				'type'   => 'ngram',
				'text'   => $ngram,
				'weight' => 0.4,
				'terms'  => $map->tokens( $ngram ),
			);
		}

		// Keep only queries that carry at least one usable term.
		$queries = array_values(
			array_filter(
				$queries,
				function ( $q ) {
					return ! empty( $q['terms'] );
				}
			)
		);

		return $queries;
	}

	/**
	 * Term names for a post and taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	private function tax_terms( $post_id, $taxonomy ) {
		$out   = array();
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( isset( $term->name ) ) {
					$out[] = $term->name;
				}
			}
		}
		return $out;
	}

	/**
	 * Bigrams of the normalized title tokens.
	 *
	 * @param string $title Title.
	 * @return array
	 */
	private function title_ngrams( $title ) {
		$map   = new ELINK_Entity_Map();
		$terms = $map->tokens( $title );
		$out   = array();
		for ( $i = 0; $i < count( $terms ) - 1; $i++ ) {
			$out[] = $terms[ $i ] . ' ' . $terms[ $i + 1 ];
		}
		return $out;
	}
}
