<?php
/**
 * REST API: suggest, run, undo, rebuild.
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints.
 */
class ELE_REST {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	const NS = 'entity-link-engine/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/suggest',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'can_edit_post' ),
				'callback'            => array( $this, 'suggest' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/run',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'can_edit_post' ),
				'callback'            => array( $this, 'run' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/undo',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'can_edit_post' ),
				'callback'            => array( $this, 'undo' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/rebuild',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( $this, 'rebuild' ),
			)
		);
	}

	/**
	 * Permission: current user may edit this post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_post( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Suggest candidates (dry run).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function suggest( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$engine  = new ELE_Engine();
		$result  = $engine->run( $post_id, true );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Run and apply.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function run( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$engine  = new ELE_Engine();
		$result  = $engine->run( $post_id, false );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Undo last run.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function undo( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$engine  = new ELE_Engine();
		$ok      = $engine->undo( $post_id );
		return new WP_REST_Response( array( 'ok' => $ok ), 200 );
	}

	/**
	 * Rebuild entity index.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rebuild( $request ) {
		$map   = new ELE_Entity_Map();
		$count = $map->rebuild();
		return new WP_REST_Response( array( 'indexed' => $count ), 200 );
	}
}
