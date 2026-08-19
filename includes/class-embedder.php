<?php
/**
 * Optional semantic layer.
 *
 * When enabled, the plugin calls an OpenAI-compatible embeddings endpoint to
 * score candidates semantically. This is strictly opt-in: without an API key
 * the plugin stays fully local and deterministic. The remote call is disclosed
 * in the readme and fails soft (degrades to lexical scoring).
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Embedder.
 */
class ELINK_Embedder {

	/**
	 * Whether the semantic layer is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$settings = ELINK_Install::get_settings();
		$embed    = isset( $settings['embed'] ) ? $settings['embed'] : array();
		return ! empty( $embed['enabled'] ) && ! empty( $embed['api_key'] ) && ! empty( $embed['api_url'] );
	}

	/**
	 * Embed a list of texts via the configured endpoint.
	 *
	 * @param array $texts Texts.
	 * @return array|WP_Error Vectors or error.
	 */
	public function embed_texts( $texts ) {
		$settings = ELINK_Install::get_settings();
		$embed    = isset( $settings['embed'] ) ? $settings['embed'] : array();
		$api_url  = untrailingslashit( (string) $embed['api_url'] );
		$model    = ! empty( $embed['model'] ) ? (string) $embed['model'] : 'text-embedding-3-small';

		$response = wp_remote_post(
			$api_url . '/embeddings',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . (string) $embed['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model' => $model,
						'input' => array_values( $texts ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'elink_embed_http', 'Embedding API returned HTTP ' . $code );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['data'] ) ) {
			return new WP_Error( 'elink_embed_empty', 'Embedding API returned no data.' );
		}

		$vectors = array();
		foreach ( $body['data'] as $item ) {
			if ( isset( $item['embedding'] ) && is_array( $item['embedding'] ) ) {
				$vectors[] = array_map( 'floatval', $item['embedding'] );
			}
		}
		return $vectors;
	}

	/**
	 * Cached embedding for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_post_embedding( $post_id ) {
		$settings = ELINK_Install::get_settings();
		$model    = isset( $settings['embed']['model'] ) ? (string) $settings['embed']['model'] : '';

		$cached = get_post_meta( $post_id, '_elink_embedding', true );
		if ( is_array( $cached ) && isset( $cached['model'], $cached['vec'] ) && $cached['model'] === $model ) {
			return $cached['vec'];
		}
		return null;
	}

	/**
	 * Embed a post (title + excerpt) and cache the vector.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function ensure_post_embedding( $post_id ) {
		$cached = $this->get_post_embedding( $post_id );
		if ( null !== $cached ) {
			return $cached;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$text  = $post->post_title . "\n" . wp_strip_all_tags( $post->post_excerpt );
		$texts = array( mb_substr( $text, 0, 8000 ) );
		$vecs  = $this->embed_texts( $texts );
		if ( is_wp_error( $vecs ) || empty( $vecs ) ) {
			return null;
		}
		$settings = ELINK_Install::get_settings();
		$model    = isset( $settings['embed']['model'] ) ? (string) $settings['embed']['model'] : '';
		update_post_meta( $post_id, '_elink_embedding', array( 'model' => $model, 'vec' => $vecs[0] ) );
		return $vecs[0];
	}

	/**
	 * Mean cosine similarity between a post embedding and query embeddings.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $queries Query texts.
	 * @return float 0..1
	 */
	public function similarity_to_queries( $post_id, $queries ) {
		$post_vec = $this->ensure_post_embedding( $post_id );
		if ( ! $post_vec ) {
			return 0.0;
		}
		// Batch query embedding, capped.
		$batch = array_slice( $queries, 0, 12 );
		$vecs  = $this->embed_texts( $batch );
		if ( is_wp_error( $vecs ) || empty( $vecs ) ) {
			return 0.0;
		}
		$sum = 0.0;
		$n   = 0;
		foreach ( $vecs as $vec ) {
			$sim = $this->cosine( $post_vec, $vec );
			if ( ! is_nan( $sim ) ) {
				$sum += $sim;
				$n++;
			}
		}
		return $n > 0 ? $sum / $n : 0.0;
	}

	/**
	 * Cosine similarity.
	 *
	 * @param array $a Vector.
	 * @param array $b Vector.
	 * @return float
	 */
	public function cosine( $a, $b ) {
		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;
		$count = min( count( $a ), count( $b ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$dot += (float) $a[ $i ] * (float) $b[ $i ];
			$na  += (float) $a[ $i ] * (float) $a[ $i ];
			$nb  += (float) $b[ $i ] * (float) $b[ $i ];
		}
		if ( $na <= 0.0 || $nb <= 0.0 ) {
			return 0.0;
		}
		return $dot / ( sqrt( $na ) * sqrt( $nb ) );
	}
}
