<?php
/**
 * Entity Link Engine — admin & REST smoke test.
 * Run: wp eval-file tests/test-admin.php --allow-root
 */

defined( 'ABSPATH' ) || die( 'WP context required.' );

global $passes, $errors;
$passes = array();
$errors = array();

function a_check( $cond, $label ) {
	global $passes, $errors;
	if ( $cond ) {
		$passes[] = $label;
	} else {
		$errors[] = 'FAIL: ' . $label;
	}
}

wp_set_current_user( 1 );

// --- Render each admin page (catch notices/warnings) ------------------------
$admin = new ELE_Admin();
$pages = array(
	'render_dashboard'  => array(),
	'render_settings'   => array(),
	'render_vocabulary' => array(),
	'render_bulk'       => array(),
	'render_help'       => array(),
);

// Need a WP_Screen-ish context for enqueue checks; render methods only need caps.
foreach ( $pages as $method => $args ) {
	ob_start();
	$previous = error_reporting( E_ALL & ~E_DEPRECATED );
	try {
		call_user_func_array( array( $admin, $method ), $args );
		$html = ob_get_clean();
		$has_html = strlen( $html ) > 100;
		a_check( $has_html, "page $method renders" );
	} catch ( Throwable $e ) {
		ob_end_clean();
		a_check( false, "page $method throws: " . $e->getMessage() );
	}
	error_reporting( $previous );
}

// --- UX, onboarding, documentation and attribution ----------------------------
ob_start();
$admin->render_dashboard();
$dashboard_html = ob_get_clean();
a_check( false !== strpos( $dashboard_html, 'data-ele-onboarding="true"' ), 'dashboard has first-run onboarding' );
a_check( false !== strpos( $dashboard_html, 'data-ele-step="build-index"' ), 'onboarding contains index step' );
a_check( false !== strpos( $dashboard_html, 'https://eullrich.com/' ), 'dashboard has author link' );
a_check( false !== strpos( $dashboard_html, 'noopener noreferrer' ), 'author link uses safe rel attributes' );

ob_start();
$admin->render_help();
$help_html = ob_get_clean();
a_check( false !== strpos( $help_html, 'id="entity-mapping"' ), 'help documents entity mapping' );
a_check( false !== strpos( $help_html, 'id="retrieval"' ), 'help documents fan-out retrieval' );
a_check( false !== strpos( $help_html, 'id="editor-workflow"' ), 'help documents insertion and undo' );
a_check( false !== strpos( $help_html, 'id="semantic-privacy"' ), 'help documents semantic privacy' );

// --- Meta box render ----------------------------------------------------------
$posts = get_posts( array( 'post_type' => 'post', 'numberposts' => 1, 'post_status' => 'publish' ) );
if ( $posts ) {
	ob_start();
	$admin->render_meta_box( $posts[0] );
	$meta_html = ob_get_clean();
	a_check( false !== strpos( $meta_html, 'ele-meta' ), 'meta box renders with container' );
	a_check( false !== strpos( $meta_html, 'ele-suggest' ), 'meta box has suggest button' );
} else {
	a_check( false, 'no post available for meta box test' );
}

// --- REST endpoints -------------------------------------------------------------
$rest = new ELE_REST();
$post_id = $posts[0]->ID;

$suggest_req = new WP_REST_Request( 'POST', '/' . ELE_REST::NS . '/suggest' );
$suggest_req->set_param( 'post_id', $post_id );
$resp = rest_do_request( $suggest_req );
a_check( 200 === $resp->get_status(), 'REST suggest returns 200' );
$suggest_body = $resp->get_data();
a_check( isset( $suggest_body['candidates'] ), 'REST suggest returns candidates key' );
echo 'suggest: ' . count( $suggest_body['candidates'] ) . " candidates, status " . $resp->get_status() . "\n";

$run_req = new WP_REST_Request( 'POST', '/' . ELE_REST::NS . '/run' );
$run_req->set_param( 'post_id', $post_id );
$resp = rest_do_request( $run_req );
a_check( 200 === $resp->get_status(), 'REST run returns 200' );
echo 'run inserted: ' . count( $resp->get_data()['inserted'] ) . "\n";

$undo_req = new WP_REST_Request( 'POST', '/' . ELE_REST::NS . '/undo' );
$undo_req->set_param( 'post_id', $post_id );
$resp = rest_do_request( $undo_req );
a_check( 200 === $resp->get_status() && ! empty( $resp->get_data()['ok'] ), 'REST undo returns ok' );

$rebuild_req = new WP_REST_Request( 'POST', '/' . ELE_REST::NS . '/rebuild' );
$resp = rest_do_request( $rebuild_req );
a_check( 200 === $resp->get_status(), 'REST rebuild returns 200' );
echo 'rebuild indexed: ' . $resp->get_data()['indexed'] . "\n";

// --- Settings sanitization ------------------------------------------------------
$admin = new ELE_Admin();
$dirty = array(
	'post_types' => array( 'post', 'page', '../../evil' ),
	'max_links' => 99,
	'min_score' => -5,
	'auto_on_publish' => 0,
	'add_link_class' => 1,
	'link_class' => 'bad class" onmouseover="x',
	'skip_blocks' => array( 'h1', 'SCRIPT' ),
	'embed' => array( 'enabled' => 1, 'api_url' => 'javascript:alert(1)', 'api_key' => 'k', 'model' => 'm', 'blend' => 2 ),
);
$clean = $admin->sanitize_settings( $dirty );
a_check( in_array( 'post', $clean['post_types'], true ) && ! in_array( '../../evil', $clean['post_types'], true ), 'sanitize: post types whitelisted' );
a_check( 20 === $clean['max_links'], 'sanitize: max_links capped at 20' );
a_check( 0.0 === $clean['min_score'], 'sanitize: min_score floored at 0' );
a_check( 'bad-class-onmouseover-x' === $clean['link_class'] || 'bad-class' === $clean['link_class'] || false === strpos( $clean['link_class'], '"' ), 'sanitize: link class has no quotes' );
a_check( in_array( 'script', $clean['skip_blocks'], true ), 'sanitize: skip tags lowercased (SCRIPT -> script)' );
a_check( 1.0 === $clean['embed']['blend'], 'sanitize: blend capped at 1' );

// --- Report detail ---------------------------------------------------------------
$report = new ELE_Report();
$detail = $report->detail();
a_check( is_array( $detail ) && count( $detail ) > 0, 'report detail has rows' );

echo "\n=== PASS: " . count( $passes ) . ' / ' . ( count( $passes ) + count( $errors ) ) . " ===\n";
if ( $errors ) {
	echo implode( "\n", $errors ) . "\n";
	exit( 1 );
}
echo "ALL ADMIN TESTS PASSED\n";
