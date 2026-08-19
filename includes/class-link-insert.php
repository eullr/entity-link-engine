<?php
/**
 * Link insertion.
 *
 * Wraps the first eligible mention of each target anchor inside the post
 * content. Rules mirror the reference implementation:
 *
 * - word boundaries only (never inside a longer word)
 * - never inside headings, code, pre, tables, blockquotes, figures, images
 * - never inside an existing link, never duplicate a URL already present
 * - at most one inserted link per paragraph/block
 * - at most max_links links per run
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Link inserter.
 */
class ELINK_Link_Insert {

	/**
	 * Insert links into content.
	 *
	 * @param string $content  Post content HTML.
	 * @param array  $candidates Candidate links:
	 *                           array( 'post_id', 'url', 'title', 'score', ... ).
	 * @param array  $settings Plugin settings.
	 * @return array array( 'content' => string, 'inserted' => array, 'skipped' => array )
	 */
	public function insert( $content, $candidates, $settings ) {
		$max_links  = isset( $settings['max_links'] ) ? (int) $settings['max_links'] : 3;
		$skip       = isset( $settings['skip_blocks'] ) && is_array( $settings['skip_blocks'] ) ? $settings['skip_blocks'] : array();
		$add_class  = ! empty( $settings['add_link_class'] );
		$link_class = isset( $settings['link_class'] ) ? sanitize_html_class( $settings['link_class'] ) : 'elink-auto-link';

		$existing = $this->existing_hrefs( $content );
		$inserted = array();
		$skipped  = array();
		$used_blocks = array();

		// Split into blocks on blank lines or before a block-level tag.
		// Handles Gutenberg (comment-delimited blocks, blank-line separated)
		// and classic content (single newlines between <p> tags).
		$blocks = preg_split(
			'/(\r?\n){2,}|(\r?\n)(?=<(?:p|h[1-6]|pre|code|table|blockquote|figure|ul|ol|li|div|section|!--)[\s>])/iu',
			$content
		);

		foreach ( $candidates as $candidate ) {
			if ( count( $inserted ) >= $max_links ) {
				break;
			}
			$url = isset( $candidate['url'] ) ? $candidate['url'] : '';
			if ( '' === $url ) {
				continue;
			}
			if ( isset( $existing[ $url ] ) ) {
				$skipped[] = $this->skip_entry( $candidate, 'already-linked' );
				continue;
			}
			$anchors = array();
			if ( ! empty( $candidate['anchors'] ) && is_array( $candidate['anchors'] ) ) {
				$anchors = array_values( array_unique( $candidate['anchors'] ) );
			}
			if ( empty( $anchors ) && isset( $candidate['anchor'] ) && '' !== $candidate['anchor'] ) {
				$anchors = array( $candidate['anchor'] );
			}
			if ( empty( $anchors ) && isset( $candidate['title'] ) && '' !== $candidate['title'] ) {
				$anchors = array( $candidate['title'] );
			}
			if ( empty( $anchors ) ) {
				$skipped[] = $this->skip_entry( $candidate, 'no-anchor' );
				continue;
			}

			$done = false;
			foreach ( $anchors as $anchor ) {
				$result = $this->insert_one( $blocks, $anchor, $url, $candidate, $skip, $add_class, $link_class, $used_blocks );
				if ( $result['done'] ) {
					$blocks    = $result['blocks'];
					$used_blocks[ $result['index'] ] = 1;
					$existing[ $url ] = 1;
					$inserted[] = array(
						'target_id' => isset( $candidate['post_id'] ) ? (int) $candidate['post_id'] : 0,
						'url'       => $url,
						'anchor'    => $result['anchor'],
						'score'     => isset( $candidate['score'] ) ? (float) $candidate['score'] : 0.0,
					);
					$done = true;
					break;
				}
			}
			if ( ! $done ) {
				$skipped[] = $this->skip_entry( $candidate, 'no-mention' );
			}
		}

		return array(
			'content'  => implode( "\n\n", $blocks ),
			'inserted' => $inserted,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Insert one anchor into the first eligible block.
	 *
	 * @param array  $blocks     Block array (passed by reference via return).
	 * @param string $anchor     Anchor phrase to wrap.
	 * @param string $url        Target URL.
	 * @param array  $candidate  Candidate data.
	 * @param array  $skip       Block tags to skip.
	 * @param bool   $add_class  Add css class.
	 * @param string $link_class Css class.
	 * @param array  $used_blocks Block indices that already received a link.
	 * @return array array( 'done' => bool, 'blocks' => array, 'index' => int, 'anchor' => string, 'reason' => string )
	 */
	private function insert_one( $blocks, $anchor, $url, $candidate, $skip, $add_class, $link_class, $used_blocks = array() ) {
		$anchor_norm = $this->normalize_anchor( $anchor );
		if ( '' === $anchor_norm || strlen( $anchor_norm ) < 3 ) {
			return array( 'done' => false, 'blocks' => $blocks, 'index' => -1, 'anchor' => $anchor, 'reason' => 'anchor-too-short' );
		}

		$regex = '/(?<![\p{L}\p{N}_-])(' . preg_quote( $anchor_norm, '/' ) . ')(?![\p{L}\p{N}_-])/iu';

		foreach ( $blocks as $i => $block ) {
			if ( isset( $used_blocks[ $i ] ) ) {
				continue;
			}
			// Blocks that already received an auto link in an earlier run are
			// treated as used (one auto link per paragraph, across runs).
			if ( false !== strpos( $block, 'data-elink=' ) ) {
				continue;
			}
			if ( $this->block_skipped( $block, $skip ) ) {
				continue;
			}

			$new_block = $this->wrap_in_block( $block, $regex, $url, $candidate, $add_class, $link_class );
			if ( null !== $new_block ) {
				$blocks[ $i ] = $new_block['block'];
				return array(
					'done'   => true,
					'blocks' => $blocks,
					'index'  => $i,
					'anchor' => $new_block['anchor'],
					'reason' => 'ok',
				);
			}
		}

		return array( 'done' => false, 'blocks' => $blocks, 'index' => -1, 'anchor' => $anchor, 'reason' => 'no-mention' );
	}

	/**
	 * Wrap the first anchor occurrence in an open (non-protected) segment of
	 * the block.
	 *
	 * @param string $block      Block HTML.
	 * @param string $regex      Anchor regex.
	 * @param string $url        Target URL.
	 * @param array  $candidate  Candidate data.
	 * @param bool   $add_class  Add css class.
	 * @param string $link_class Css class.
	 * @return array|null
	 */
	private function wrap_in_block( $block, $regex, $url, $candidate, $add_class, $link_class ) {
		// Split into open and protected segments. Protected: existing links,
		// inline code, and heading/table inner text we already skip at block level.
		$parts = preg_split(
			'/(<a\b[^>]*>.*?<\/a>|<code[^>]*>.*?<\/code>)/isu',
			$block,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		foreach ( $parts as $pi => $part ) {
			$trimmed = ltrim( $part );
			if ( 0 === strpos( $trimmed, '<a ' ) || 0 === strpos( $trimmed, '<code' ) ) {
				continue; // Protected segment.
			}
			if ( ! preg_match( $regex, $part, $match, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$label = $match[1][0];

			$class_attr = $add_class && '' !== $link_class ? ' class="' . esc_attr( $link_class ) . '"' : '';
			$data_attr  = ' data-elink="' . esc_attr( isset( $candidate['post_id'] ) ? (int) $candidate['post_id'] : '' ) . '"';
			$link       = '<a href="' . esc_url( $url ) . '"' . $class_attr . $data_attr . '>' . $label . '</a>';

			$parts[ $pi ] = substr_replace( $part, $link, $match[1][1], strlen( $label ) );
			return array(
				'block'  => implode( '', $parts ),
				'anchor' => $label,
			);
		}
		return null;
	}

	/**
	 * Whether a block should be skipped entirely.
	 *
	 * @param string $block Block HTML.
	 * @param array  $skip  Skip tags.
	 * @return bool
	 */
	private function block_skipped( $block, $skip ) {
		// Strip leading Gutenberg comment markers before classifying.
		$trimmed = trim( $block );
		$trimmed = preg_replace( '/^((\s*<!--.*?-->\s*)+)/s', '', $trimmed );
		$lower   = mb_strtolower( $trimmed );
		foreach ( (array) $skip as $tag ) {
			$tag = strtolower( $tag );
			if ( 0 === strpos( $lower, '<' . $tag . '>' ) || 0 === strpos( $lower, '<' . $tag . ' ' ) || 0 === strpos( $lower, '<' . $tag . "\n" ) ) {
				return true;
			}
		}
		// Comment-only blocks (no real content after markers).
		if ( '' === $trimmed || 0 === strpos( $trimmed, '<!--' ) ) {
			return true;
		}
		// A block that is entirely inside an anchor (rare edge) — skip if the
		// whole block is one link.
		if ( 0 === strpos( $trimmed, '<a ' ) && false === stripos( $trimmed, '</a>' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Normalize anchor for matching (same rules as entity keys).
	 *
	 * @param string $anchor Anchor.
	 * @return string
	 */
	private function normalize_anchor( $anchor ) {
		$map = new ELINK_Entity_Map();
		return $map->normalize( $anchor );
	}

	/**
	 * Existing hrefs keyed by URL.
	 *
	 * @param string $content HTML.
	 * @return array
	 */
	private function existing_hrefs( $content ) {
		$out = array();
		if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\']/iu', $content, $matches ) ) {
			foreach ( $matches[1] as $href ) {
				$out[ trim( $href ) ] = 1;
			}
		}
		return $out;
	}

	/**
	 * Skip entry for reporting.
	 *
	 * @param array  $candidate Candidate.
	 * @param string $reason    Reason.
	 * @return array
	 */
	private function skip_entry( $candidate, $reason ) {
		return array(
			'target_id' => isset( $candidate['post_id'] ) ? (int) $candidate['post_id'] : 0,
			'url'       => isset( $candidate['url'] ) ? $candidate['url'] : '',
			'title'     => isset( $candidate['title'] ) ? $candidate['title'] : '',
			'score'     => isset( $candidate['score'] ) ? (float) $candidate['score'] : 0.0,
			'reason'    => $reason,
		);
	}
}
