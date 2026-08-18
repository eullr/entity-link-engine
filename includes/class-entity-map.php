<?php
/**
 * Entity extraction and mapping.
 *
 * Builds and queries the entity index: every indexed phrase (post title,
 * heading, tag, category or manually defined entity) maps to target posts.
 * Mentions of those phrases inside content are found with word boundaries,
 * longest-first, exactly like the controlled vocabulary of the reference
 * implementation on eullrich.com.
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Entity map.
 */
class ELE_Entity_Map {

	/**
	 * Stopwords shared with the reference implementation (DE + EN).
	 *
	 * @var array
	 */
	const STOPWORDS = array(
		// DE.
		'aber', 'alle', 'auch', 'beim', 'eine', 'einem', 'einen', 'einer', 'eines', 'fur', 'fuer', 'ihre', 'ihren',
		'ihrem', 'ihrer', 'meine', 'meinem', 'meinen', 'meiner', 'nicht', 'oder', 'sich', 'sind', 'uber', 'ueber',
		'unter', 'wenn', 'werden', 'wird', 'warum', 'welche', 'welcher', 'welches', 'mehr', 'statt', 'durch',
		'gegen', 'ohne', 'dass', 'dieser', 'diese', 'nach', 'ganze',
		// EN.
		'about', 'after', 'again', 'against', 'article', 'because', 'before', 'between', 'from', 'into', 'more',
		'most', 'other', 'over', 'than', 'that', 'their', 'these', 'they', 'this', 'through', 'under', 'what',
		'when', 'where', 'which', 'while', 'with', 'without', 'your', 'first',
		// Additional common terms that never make good anchors.
		'also', 'oder', 'und', 'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'ist', 'sind', 'war', 'wie', 'was',
		'mit', 'von', 'zum', 'zur', 'fuer', 'auf', 'aus', 'bei', 'nur', 'sehr', 'wird', 'kann', 'muss', 'soll',
		'and', 'the', 'for', 'with', 'this', 'are', 'was', 'were', 'been', 'have', 'has', 'had', 'will', 'would',
		'could', 'should', 'your', 'their', 'there', 'here', 'some', 'such', 'than', 'then', 'them', 'also',
		'just', 'very', 'much', 'many', 'each', 'every',
	);

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'ele_entity_index';
	}

	/**
	 * Normalize a text for matching: lowercase, strip diacritics, ß -> ss,
	 * collapse whitespace.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function normalize( $text ) {
		$text = (string) $text;
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = mb_strtolower( $text, 'UTF-8' );
		// NFKD + strip combining marks.
		if ( class_exists( 'Normalizer' ) ) {
			$text = Normalizer::normalize( $text, Normalizer::FORM_KD );
			$text = preg_replace( '/[\x{0300}-\x{036f}]/u', '', $text );
		}
		$text = str_replace( array( 'ß', 'æ', 'œ' ), array( 'ss', 'ae', 'oe' ), $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * Tokenize into normalized terms of length >= 4 without stopwords.
	 *
	 * @param string $text Text.
	 * @return array
	 */
	public function tokens( $text ) {
		$normalized = $this->normalize( $text );
		$words      = preg_split( '/[^a-z0-9]+/u', $normalized );
		$out        = array();
		$stop       = array_flip( self::STOPWORDS );
		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( strlen( $word ) >= 4 && ! isset( $stop[ $word ] ) ) {
				$out[] = $word;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether a phrase is eligible as an entity key.
	 *
	 * @param string $phrase Phrase.
	 * @return bool
	 */
	private function is_good_phrase( $phrase ) {
		$tokens = $this->tokens( $phrase );
		return count( $tokens ) >= 1 && strlen( $phrase ) >= 3;
	}

	/**
	 * Extract entity rows for one post and store them in the index.
	 *
	 * @param int $post_id Post ID.
	 */
	public function index_post( $post_id ) {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );

		$rows     = array();
		$now      = current_time( 'mysql' );
		$lang     = $this->post_lang( $post );

		// Title: full title (if reasonable length) + long title tokens.
		$title = trim( $post->post_title );
		if ( '' !== $title ) {
			$title_key = $this->normalize( $title );
			$tokens    = $this->tokens( $title );
			$word_count = count( $tokens );
			if ( $word_count >= 2 && $word_count <= 8 && strlen( $title_key ) >= 3 ) {
				$rows[] = array(
					'entity_key'   => $title_key,
					'entity_label' => $title,
					'post_id'      => $post_id,
					'source'       => 'title',
					'weight'       => 100,
					'lang'         => $lang,
					'created_at'   => $now,
				);
			}
			foreach ( $tokens as $token ) {
				if ( strlen( $token ) >= 5 ) {
					$rows[] = array(
						'entity_key'   => $token,
						'entity_label' => $token,
						'post_id'      => $post_id,
						'source'       => 'title_word',
						'weight'       => 25,
						'lang'         => $lang,
						'created_at'   => $now,
					);
				}
			}
		}

		// Headings: H2/H3 text, only compact noun-phrase-like headings.
		$headings = $this->extract_headings( $post->post_content );
		foreach ( $headings as $heading ) {
			$key     = $this->normalize( $heading );
			$tokens  = $this->tokens( $heading );
			if ( count( $tokens ) >= 2 && count( $tokens ) <= 6 && strlen( $key ) >= 5 && ! $this->looks_like_instruction( $heading ) ) {
				$rows[] = array(
					'entity_key'   => $key,
					'entity_label' => $heading,
					'post_id'      => $post_id,
					'source'       => 'heading',
					'weight'       => 60,
					'lang'         => $lang,
					'created_at'   => $now,
				);
			}
		}

		// Tags and categories.
		foreach ( $this->tax_terms( $post_id, 'post_tag' ) as $term_name ) {
			$rows[] = array(
				'entity_key'   => $this->normalize( $term_name ),
				'entity_label' => $term_name,
				'post_id'      => $post_id,
				'source'       => 'tag',
				'weight'       => 50,
				'lang'         => $lang,
				'created_at'   => $now,
			);
		}
		foreach ( $this->tax_terms( $post_id, 'category' ) as $term_name ) {
			$rows[] = array(
				'entity_key'   => $this->normalize( $term_name ),
				'entity_label' => $term_name,
				'post_id'      => $post_id,
				'source'       => 'category',
				'weight'       => 40,
				'lang'         => $lang,
				'created_at'   => $now,
			);
		}

		foreach ( $rows as $row ) {
			// Guard against duplicate (key, post, source) rows from repeated tokens.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ele_entity_index WHERE entity_key = %s AND post_id = %d AND source = %s LIMIT 1",
					$row['entity_key'],
					$row['post_id'],
					$row['source']
				)
			);
			if ( $exists ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert( $table, $row, array( '%s', '%s', '%d', '%s', '%d', '%s', '%s' ) );
		}
	}

	/**
	 * Extract H2/H3 heading texts from HTML content.
	 *
	 * @param string $content HTML.
	 * @return array
	 */
	public function extract_headings( $content ) {
		$out = array();
		if ( preg_match_all( '/<h[23][^>]*>(.*?)<\/h[23]>/iu', $content, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$text = trim( wp_strip_all_tags( $match ) );
				if ( '' !== $text ) {
					$out[] = $text;
				}
			}
		}
		return $out;
	}

	/**
	 * Heuristic: headings that read like instructions/actions are weak entities.
	 *
	 * @param string $heading Heading text.
	 * @return bool
	 */
	private function looks_like_instruction( $heading ) {
		$lower = mb_strtolower( $heading, 'UTF-8' );
		foreach ( array( 'was ist', 'warum', 'wie funktioniert', 'wie du', 'wie sie', 'fazit', 'einleitung', 'what is', 'why ', 'how to', 'conclusion', 'introduction', 'schritt ', 'step ' ) as $needle ) {
			if ( 0 === strpos( $lower, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Term names for a post and taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	private function tax_terms( $post_id, $taxonomy ) {
		$out = array();
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
	 * Language of a post: from post meta '_ele_lang' or ''.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function post_lang( $post ) {
		$lang = get_post_meta( $post->ID, '_ele_lang', true );
		return is_string( $lang ) ? $lang : '';
	}

	/**
	 * Rebuild the entity index for all eligible posts.
	 *
	 * @param int[]|null $post_ids Optional explicit list.
	 * @return int Number of indexed posts.
	 */
	public function rebuild( $post_ids = null ) {
		$settings = ELE_Install::get_settings();
		if ( null === $post_ids ) {
			$post_ids = get_posts(
				array(
					'post_type'   => $settings['post_types'],
					'post_status' => array( 'publish' ),
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			);
		}
		$count = 0;
		foreach ( (array) $post_ids as $post_id ) {
			$this->index_post( (int) $post_id );
			$count++;
		}
		update_option( 'ele_index_built', current_time( 'mysql' ) );
		return $count;
	}

	/**
	 * Manual entities from settings + filter, normalized into the same row shape.
	 * Merged into the index at query time so manually defined entities always
	 * participate in mention matching and fan-out retrieval.
	 *
	 * @return array
	 */
	public function manual_rows() {
		$rows = array();
		$now  = current_time( 'mysql' );
		foreach ( ele_manual_entities() as $entity ) {
			if ( ! isset( $entity['entity_label'], $entity['target_post_id'] ) ) {
				continue;
			}
			$label  = (string) $entity['entity_label'];
			$target = (int) $entity['target_post_id'];
			$prio   = isset( $entity['priority'] ) ? (int) $entity['priority'] : 100;
			$keys   = array( $this->normalize( $label ) );
			if ( ! empty( $entity['aliases'] ) ) {
				foreach ( (array) $entity['aliases'] as $alias ) {
					$key = $this->normalize( $alias );
					if ( '' !== $key ) {
						$keys[] = $key;
					}
				}
			}
			foreach ( array_unique( $keys ) as $key ) {
				$rows[] = array(
					'entity_key'   => $key,
					'entity_label' => $label,
					'post_id'      => $target,
					'source'       => 'manual',
					'weight'       => 1000 + $prio,
					'lang'         => isset( $entity['lang'] ) ? (string) $entity['lang'] : '',
					'created_at'   => $now,
				);
			}
		}
		return $rows;
	}

	/**
	 * Find entity mentions in a text. Returns an ordered list of
	 * array( 'key', 'label', 'post_id', 'weight', 'source', 'position' ).
	 * Longest keys first; one mention per (key, post) pair.
	 *
	 * @param string   $text           Content HTML.
	 * @param int      $exclude_post   Post to exclude (the post being processed).
	 * @param int      $max_keys       Cap on keys scanned (perf guard).
	 * @return array
	 */
	public function find_mentions( $text, $exclude_post = 0, $max_keys = 3000 ) {
		global $wpdb;

		$exclude_post = (int) $exclude_post;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT entity_key, entity_label, post_id, weight, source
				FROM {$wpdb->prefix}ele_entity_index
				WHERE post_id <> %d
				ORDER BY LENGTH(entity_key) DESC, weight DESC
				LIMIT %d",
				$exclude_post,
				$max_keys
			)
		);

		// Manual entities join the match set (they always win by weight).
		foreach ( $this->manual_rows() as $manual ) {
			$manual['post_id'] = (int) $manual['post_id'];
			$rows[]            = (object) $manual;
		}

		$keys = array();
		$map  = array();
		foreach ( $rows as $row ) {
			$key = (string) $row->entity_key;
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = array();
				$keys[]      = $key;
			}
			$map[ $key ][] = array(
				'post_id' => (int) $row->post_id,
				'weight'  => (int) $row->weight,
				'source'  => $row->source,
				'label'   => $row->entity_label,
			);
		}
		if ( empty( $keys ) ) {
			return array();
		}

		// Longest keys first (manual rows may be shorter than DB rows).
		usort( $keys, function ( $a, $b ) {
			return strlen( $b ) <=> strlen( $a );
		} );

		// Build one alternation regex, longest keys first.
		$escaped = array();
		foreach ( $keys as $key ) {
			if ( strlen( $key ) < 3 ) {
				continue;
			}
			$escaped[] = preg_quote( $key, '/' );
		}
		if ( empty( $escaped ) ) {
			return array();
		}
		$regex = '/(?<![\p{L}\p{N}_-])(?:' . implode( '|', $escaped ) . ')(?![\p{L}\p{N}_-])/iu';

		// Strip markup for matching but keep a map of text segments.
		$clean = $this->strip_for_matching( $text );

		if ( ! preg_match_all( $regex, $clean, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$found = array();
		foreach ( $matches[0] as $index => $match ) {
			$key  = mb_strtolower( $match[0], 'UTF-8' );
			$pos  = $match[1];
			if ( ! isset( $map[ $key ] ) ) {
				// Fallback: normalize the matched slice (case/umlaut differences).
				$norm = $this->normalize( $match[0] );
				if ( isset( $map[ $norm ] ) ) {
					$key = $norm;
				} else {
					continue;
				}
			}
			foreach ( $map[ $key ] as $target ) {
				$found[] = array(
					'key'      => $key,
					'label'    => $target['label'],
					'text'     => $match[0],
					'post_id'  => $target['post_id'],
					'weight'   => $target['weight'],
					'source'   => $target['source'],
					'position' => $pos,
				);
			}
		}

		// Sort: highest weight first, then earliest position.
		usort(
			$found,
			function ( $a, $b ) {
				if ( $b['weight'] !== $a['weight'] ) {
					return $b['weight'] - $a['weight'];
				}
				return $a['position'] - $b['position'];
			}
		);

		// One mention per (key, post).
		$seen = array();
		$out  = array();
		foreach ( $found as $mention ) {
			$fingerprint = $mention['key'] . '|' . $mention['post_id'];
			if ( isset( $seen[ $fingerprint ] ) ) {
				continue;
			}
			$seen[ $fingerprint ] = 1;
			$out[] = $mention;
		}

		return $out;
	}

	/**
	 * Strip HTML for mention matching while keeping readable text segments.
	 * Scripts/styles and block-level markup are removed; inline text remains.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public function strip_for_matching( $html ) {
		$html = preg_replace( '/<(script|style|noscript)[^>]*>.*?<\/\1>/isu', ' ', $html );
		$html = preg_replace( '/<[^>]+>/u', ' ', $html );
		$html = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );
		$html = preg_replace( '/\s+/u', ' ', $html );
		return trim( $html );
	}
}
