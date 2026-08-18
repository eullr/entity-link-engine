<?php
/**
 * Entity Link Engine — functional test.
 * Run: wp eval-file tests/test-engine.php --allow-root
 */

defined( 'ABSPATH' ) || die( 'WP context required.' );

// WP-CLI eval-file executes in a closure scope; make counters global.
global $passes, $errors;

$errors = array();
$passes = array();

function t_check( $cond, $label ) {
	global $passes, $errors;
	if ( $cond ) {
		$passes[] = $label;
	} else {
		$errors[] = 'FAIL: ' . $label;
	}
}

// Isolate from the save_post auto-run hook while fixtures are created.
$original_settings = ELE_Install::get_settings();
$iso_settings = $original_settings;
$iso_settings['auto_on_publish'] = 0;
update_option( 'ele_settings', $iso_settings );

// --- Cleanup previous fixtures -------------------------------------------
$old = get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'meta_key' => '_ele_test_fixture', 'fields' => 'ids' ) );
foreach ( $old as $pid ) {
	wp_delete_post( $pid, true );
}

// --- Fixture posts --------------------------------------------------------
$fixtures = array(
	array(
		'slug'   => 'test-seo-audit-technik',
		'title'  => 'SEO-Audit: Technische Grundlagen fuer hohe Rankings',
		'cat'    => 'SEO',
		'tags'   => array( 'seo', 'technik' ),
		'body'   => "<h2>Was ein SEO-Audit prueft</h2>\n<p>Ein technisches SEO-Audit untersucht Crawlbarkeit, Indexierung und Rendering. Ohne saubere Technik hilft der beste Content nicht.</p>",
	),
	array(
		'slug'   => 'test-geo-audit-ki-antworten',
		'title'  => 'GEO-Audit: Sichtbarkeit in KI-Antworten messen',
		'cat'    => 'GEO',
		'tags'   => array( 'geo', 'llm-sichtbarkeit' ),
		'body'   => "<h2>Mention Rate und Citation Rate</h2>\n<p>Ein GEO-Audit misst, wie oft eine Marke in KI-Antworten genannt und verlinkt wird. Das ist die neue Waehrung fuer Sichtbarkeit.</p>",
	),
	array(
		'slug'   => 'test-ki-agenten-optimieren',
		'title'  => 'Website fuer KI-Agenten optimieren',
		'cat'    => 'GEO',
		'tags'   => array( 'geo', 'ki-techniken' ),
		'body'   => "<h2>Wie Agenten deine Seite lesen</h2>\n<p>KI-Agenten brauchen klare Entitaeten und verifizierbare Aussagen. RAG-Pipelines ziehen deine Seite nur dann in den Kontext, wenn sie die Antwort traegt.</p>",
	),
	array(
		'slug'   => 'test-llms-txt-leitfaden',
		'title'  => 'llms.txt Leitfaden: Der Index fuer Sprachmodelle',
		'cat'    => 'GEO',
		'tags'   => array( 'geo', 'llm-sichtbarkeit' ),
		'body'   => "<h2>Aufbau einer llms.txt</h2>\n<p>llms.txt gibt Modellen einen stabilen Einstiegspunkt in deine Seite. Eine klare Datei verbessert die Retrieval-Chance deutlich.</p>",
	),
	array(
		'slug'   => 'test-retrieval-zitat-funnel',
		'title'  => 'Retrieval-Zitat-Funnel: Abgerufen heisst nicht zitiert',
		'cat'    => 'GEO',
		'tags'   => array( 'geo', 'llm-sichtbarkeit' ),
		'body'   => "<h2>Drei Stufen: Retrieval, Zitat, Erwaehnung</h2>\n<p>Der Retrieval-Zitat-Funnel zeigt: Eine Seite kann abgerufen werden, ohne zitiert zu werden. Nur verlinkte Zitate zaehlen als Sichtbarkeit.</p>",
	),
	array(
		'slug'   => 'test-keywordrecherche-verzerrt',
		'title'  => 'Warum eine Keywordrecherche deine SEO-Strategie verzerren kann',
		'cat'    => 'SEO',
		'tags'   => array( 'seo', 'strategie' ),
		'body'   => "<h2>Keywords vs. Fragen</h2>\n<p>Klassische Keywordrecherche bildet nur ab, was gesucht wird, nicht was gefragt wird. Die Verzerrung kostet Sichtbarkeit in neuen Engines.</p>",
	),
);

$created = array();
foreach ( $fixtures as $f ) {
	$cat = get_term_by( 'name', $f['cat'], 'category' );
	if ( ! $cat ) {
		$cat = wp_insert_term( $f['cat'], 'category' );
		$cat = get_term_by( 'id', $cat['term_id'], 'category' );
	}
	$tag_ids = array();
	foreach ( $f['tags'] as $tag_name ) {
		$tag = get_term_by( 'name', $tag_name, 'post_tag' );
		if ( ! $tag ) {
			$tag = wp_insert_term( $tag_name, 'post_tag' );
			$tag = get_term_by( 'id', $tag['term_id'], 'post_tag' );
		}
		$tag_ids[] = (int) $tag->term_id;
	}
	$pid = wp_insert_post(
		array(
			'post_title'   => $f['title'],
			'post_name'    => $f['slug'],
			'post_content' => $f['body'],
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_category' => array( (int) $cat->term_id ),
			'tags_input'   => $tag_ids,
		)
	);
	update_post_meta( $pid, '_ele_test_fixture', 1 );
	$created[ $f['slug'] ] = (int) $pid;
}
t_check( count( $created ) === 6, 'fixtures created (' . count( $created ) . ')' );

// --- Rebuild index ---------------------------------------------------------
$map = new ELE_Entity_Map();
$count = $map->rebuild();
t_check( $count >= 6, 'index rebuilt (' . $count . ' posts)' );

// --- Manual vocabulary (mirrors the reference ENTITY_LINKS) -----------------
$manual_entities = array(
	array(
		'id'             => 'm1',
		'entity_label'   => 'GEO-Audit',
		'aliases'        => array( 'GEO Audit' ),
		'target_post_id' => $created['test-geo-audit-ki-antworten'],
		'priority'       => 100,
	),
	array(
		'id'             => 'm2',
		'entity_label'   => 'SEO-Audit',
		'aliases'        => array( 'technisches SEO-Audit' ),
		'target_post_id' => $created['test-seo-audit-technik'],
		'priority'       => 100,
	),
	array(
		'id'             => 'm3',
		'entity_label'   => 'llms.txt',
		'aliases'        => array( 'llms.txt Leitfaden' ),
		'target_post_id' => $created['test-llms-txt-leitfaden'],
		'priority'       => 90,
	),
	array(
		'id'             => 'm4',
		'entity_label'   => 'KI-Agenten',
		'aliases'        => array( 'KI Agenten' ),
		'target_post_id' => $created['test-ki-agenten-optimieren'],
		'priority'       => 90,
	),
	array(
		'id'             => 'm5',
		'entity_label'   => 'Retrieval-Zitat-Funnel',
		'aliases'        => array( 'Retrieval Zitat Funnel' ),
		'target_post_id' => $created['test-retrieval-zitat-funnel'],
		'priority'       => 90,
	),
);
update_option( 'ele_entities_manual', $manual_entities );

// --- Source post with entity mentions --------------------------------------
$source_body = "<h2>Warum Sichtbarkeit heute anders gemessen wird</h2>\n\n"
	. "<p>Ein technisches SEO-Audit ist die Basis. Wer in KI-Antworten erscheinen will, braucht zusaetzlich ein GEO-Audit, das Nennung und Zitat getrennt misst.</p>\n\n"
	. "<p>Dazu gehoert eine saubere llms.txt, damit Modelle die Seite ueberhaupt finden. Und der Retrieval-Zitat-Funnel zeigt, warum Abruf allein nicht reicht.</p>\n\n"
	. "<pre>code block mit SEO-Audit darf nicht verlinkt werden</pre>\n\n"
	. "<h2>Fazit</h2>\n\n"
	. "<p>KI-Agenten lesen anders als Menschen. Fuer die Zukunft zaehlt, welche Entitaeten deine Seite traegt.</p>";

$source_id = wp_insert_post(
	array(
		'post_title'   => 'Test: Sichtbarkeit in KI-Antworten messen und verbessern',
		'post_name'   => 'test-sichtbarkeit-ki-antworten',
		'post_content' => $source_body,
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'tags_input'   => array( (int) get_term_by( 'name', 'geo', 'post_tag' )->term_id ),
	)
);
update_post_meta( $source_id, '_ele_test_fixture', 1 );

// --- Dry run ----------------------------------------------------------------
$engine = new ELE_Engine();
$dry = $engine->run( $source_id, true );

echo "--- dry run ---\n";
echo 'candidates: ' . count( $dry['candidates'] ) . "\n";
foreach ( $dry['candidates'] as $c ) {
	echo '  ' . number_format( $c['score'], 2 ) . '  ' . $c['title'] . '  [' . implode( ',', $c['reasons'] ) . "]\n";
}
echo 'inserted (dry): ' . count( $dry['inserted'] ) . "\n";

t_check( ! empty( $dry['candidates'] ), 'dry run found candidates' );
t_check( count( $dry['inserted'] ) === 3, 'dry run proposes 3 links (max_links default)' );
foreach ( $dry['inserted'] as $l ) {
	t_check( '' !== $l['anchor'], 'dry-run anchor not empty for ' . $l['url'] );
}
$anchor_texts = array_column( $dry['inserted'], 'anchor' );
t_check( in_array( 'GEO-Audit', $anchor_texts, true ), 'GEO-Audit anchored exactly (manual entity wins)' );

$urls = array_column( $dry['inserted'], 'url' );
$unique = array_unique( $urls );
t_check( count( $unique ) === count( $urls ), 'no duplicate target URLs in dry run' );

// --- Real run ---------------------------------------------------------------
$res = $engine->run( $source_id, false );
echo "--- real run ---\n";
echo 'content_changed: ' . var_export( $res['content_changed'], true ) . "\n";
echo 'inserted: ' . count( $res['inserted'] ) . "\n";
foreach ( $res['inserted'] as $l ) {
	echo '  -> ' . $l['url'] . ' anchor="' . $l['anchor'] . '" score=' . $l['score'] . "\n";
}

$post = get_post( $source_id );
$content = $post->post_content;

t_check( $res['content_changed'], 'real run changed content' );
t_check( count( $res['inserted'] ) === 3, 'real run inserted 3 links' );

foreach ( $res['inserted'] as $l ) {
	t_check( false !== strpos( $content, $l['url'] ), 'content contains url ' . $l['url'] );
}

// Headings/code untouched: the H2 and <pre> must not contain <a>.
preg_match_all( '/<h[12][^>]*>.*?<\/h[12]>/iu', $content, $heads );
foreach ( $heads[0] as $h ) {
	t_check( false === strpos( $h, '<a ' ), 'no link inside heading: ' . wp_strip_all_tags( $h ) );
}
preg_match_all( '/<pre>.*?<\/pre>/isu', $content, $pres );
foreach ( $pres[0] as $p ) {
	t_check( false === strpos( $p, '<a ' ), 'no link inside pre block' );
}

// One link per paragraph.
preg_match_all( '/<p>(.*?)<\/p>/isu', $content, $paras );
foreach ( $paras[1] as $pbody ) {
	$link_count = substr_count( $pbody, '<a ' );
	t_check( $link_count <= 1, 'max one link per paragraph (got ' . $link_count . ')' );
}

// No duplicate URL.
$hrefs = array();
preg_match_all( '/<a\b[^>]*href="([^"]+)"/iu', $content, $am );
foreach ( $am[1] as $href ) {
	$hrefs[] = $href;
}
t_check( count( array_unique( $hrefs ) ) === count( $hrefs ), 'no duplicate hrefs in final content' );

// No partial match inside a hyphenated compound: "audit" must not be linked
// inside "GEO-Audit".
t_check( false === strpos( $content, 'GEO-<a ' ), 'no partial link inside hyphenated compound GEO-Audit' );
t_check( false !== strpos( $content, '>GEO-Audit</a>' ), 'GEO-Audit linked as a whole' );

// --- Undo -------------------------------------------------------------------
$engine->undo( $source_id );
$after_undo = get_post( $source_id )->post_content;
t_check( $after_undo === $source_body, 'undo restores exact original content' );
t_check( false === strpos( $after_undo, 'ele-auto-link' ), 'undo removes inserted classes' );
// Restore the linked state for the following tests (per-post override etc.).
$engine->run( $source_id, false );

// Existing links are preserved: pre-insert a manual link, rerun, expect 2 new + manual kept.
$manual_url = get_permalink( $created['test-llms-txt-leitfaden'] );
$with_manual = str_replace(
	'<p>Ein technisches SEO-Audit ist die Basis.',
	'<p>Ein technisches SEO-Audit ist die Basis. Siehe auch <a href="' . $manual_url . '">llms.txt</a>.',
	$source_body
);
$pid2 = wp_insert_post(
	array(
		'post_title'   => 'Test 2: bestehender Link wird respektiert',
		'post_name'   => 'test-existing-link',
		'post_content' => $with_manual,
		'post_status'  => 'publish',
		'post_type'    => 'post',
	)
);
update_post_meta( $pid2, '_ele_test_fixture', 1 );
$res2 = $engine->run( $pid2, false );
$manual_kept = false !== strpos( get_post( $pid2 )->post_content, '>llms.txt</a>' );
t_check( $manual_kept, 'existing manual link preserved after run' );
$inserted2 = $res2['inserted'];
$no_dup = true;
foreach ( $inserted2 as $l ) {
	if ( untrailingslashit( $l['url'] ) === untrailingslashit( $manual_url ) ) {
		$no_dup = false;
	}
}
t_check( $no_dup, 'run does not re-link an already linked URL' );

// Per-post override: _ele_max_links=1.
update_post_meta( $source_id, '_ele_max_links', 1 );
$res3 = $engine->run( $source_id, false );
t_check( count( $res3['inserted'] ) <= 1, 'per-post max_links=1 honored (' . count( $res3['inserted'] ) . ')' );
delete_post_meta( $source_id, '_ele_max_links' );

// --- Report ------------------------------------------------------------------
$report = new ELE_Report();
$summary = $report->summary();
echo '--- report ---' . "\n";
echo 'posts: ' . $summary['posts'] . ', auto_edges: ' . $summary['auto_edges'] . ', orphans: ' . count( $summary['orphans'] ) . "\n";
t_check( $summary['auto_edges'] >= 3, 'report counts auto links' );

// --- Auto-run on publish ------------------------------------------------------
$auto_settings = ELE_Install::get_settings();
$auto_settings['auto_on_publish'] = 1;
update_option( 'ele_settings', $auto_settings );

$auto_body = "<p>Wer Sichtbarkeit aufbauen will, beginnt mit einem GEO-Audit und prueft die llms.txt. Der Retrieval-Zitat-Funnel zeigt dann die Luecke.</p>";
$auto_id = wp_insert_post(
	array(
		'post_title'   => 'Test Auto: Sichtbarkeit systematisch aufbauen',
		'post_name'   => 'test-auto-on-publish',
		'post_content' => $auto_body,
		'post_status'  => 'publish',
		'post_type'    => 'post',
	)
);
update_post_meta( $auto_id, '_ele_test_fixture', 1 );
$auto_post = get_post( $auto_id );
$auto_has_links = false !== strpos( $auto_post->post_content, 'ele-auto-link' );
t_check( $auto_has_links, 'auto-run on publish inserted links without manual trigger' );
echo '--- auto-run ---' . "\n";
echo 'auto links in fresh post: ' . substr_count( $auto_post->post_content, 'ele-auto-link' ) . "\n";

// Restore original settings (auto_on_publish as configured by user).
update_option( 'ele_settings', $original_settings );

// --- Summary -----------------------------------------------------------------
echo "\n" . '=== PASS: ' . count( $passes ) . ' / ' . ( count( $passes ) + count( $errors ) ) . " ===\n";
if ( $errors ) {
	echo implode( "\n", $errors ) . "\n";
	exit( 1 );
}
echo "ALL TESTS PASSED\n";
