<?php
/**
 * Demo-Entities fuer Entity Link Engine importieren.
 * Aufruf: wp eval-file demo-entities.php --allow-root
 * Setzt elink_entities_manual (Ziele per Slug aufgeloest) und baut den Index neu.
 */
defined( 'ABSPATH' ) || die( 'WP context required.' );

$entities = array();
$entities[] = array(
    'id' => 'demo_startseite',
    'entity_label' => 'Industrie-Klimageräte',
    'aliases' => array('Industrieklimageräte', 'Industrie Klimageräte', 'Industrieklimaanlagen'),
    'target_post_id' => 0,
    'priority' => 100,
    '_target_slug' => 'startseite',
);
$entities[] = array(
    'id' => 'demo_prozessklimatisierung_produktionshallen',
    'entity_label' => 'Prozessklimatisierung',
    'aliases' => array('Prozesskühlung'),
    'target_post_id' => 0,
    'priority' => 100,
    '_target_slug' => 'prozessklimatisierung-produktionshallen',
);
$entities[] = array(
    'id' => 'demo_produkte_kaltwassersaetze',
    'entity_label' => 'Kaltwassersatz',
    'aliases' => array('Kaltwassersätze', 'Kaltwassersaetze', 'Chiller'),
    'target_post_id' => 0,
    'priority' => 100,
    '_target_slug' => 'produkte-kaltwassersaetze',
);
$entities[] = array(
    'id' => 'demo_produkte_schaltschrankklimatisierung',
    'entity_label' => 'Schaltschrankklimatisierung',
    'aliases' => array('Schaltschrank-Kühlung'),
    'target_post_id' => 0,
    'priority' => 100,
    '_target_slug' => 'produkte-schaltschrankklimatisierung',
);
$entities[] = array(
    'id' => 'demo_produkte_serverraum_klimatisierung',
    'entity_label' => 'Serverraum-Klimatisierung',
    'aliases' => array('Serverraumklimatisierung', 'Rechenzentrum-Klimatisierung'),
    'target_post_id' => 0,
    'priority' => 100,
    '_target_slug' => 'produkte-serverraum-klimatisierung',
);
$entities[] = array(
    'id' => 'demo_free_cooling_aussenluft',
    'entity_label' => 'Free Cooling',
    'aliases' => array('Free-Cooling'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'free-cooling-aussenluft',
);
$entities[] = array(
    'id' => 'demo_f_gas_verordnung_2026',
    'entity_label' => 'F-Gas-Verordnung',
    'aliases' => array('F-Gas-VO', 'F-Gas'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'f-gas-verordnung-2026',
);
$entities[] = array(
    'id' => 'demo_kaeltemittel_r290',
    'entity_label' => 'Kältemittel R290',
    'aliases' => array('R290', 'Propan als Kältemittel'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'kaeltemittel-r290',
);
$entities[] = array(
    'id' => 'demo_adiabatische_kuehlung',
    'entity_label' => 'Adiabatische Kühlung',
    'aliases' => array('adiabatische Kuehlung', 'Adiabatik'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'adiabatische-kuehlung',
);
$entities[] = array(
    'id' => 'demo_kaeltelastberechnung',
    'entity_label' => 'Kältelastberechnung',
    'aliases' => array('Kältebedarf berechnen'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'kaeltelastberechnung',
);
$entities[] = array(
    'id' => 'demo_produkte_dachklimageraete',
    'entity_label' => 'Dachklimageräte',
    'aliases' => array('Dach-Klimageräte'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'produkte-dachklimageraete',
);
$entities[] = array(
    'id' => 'demo_produkte_mobile_klimageraete',
    'entity_label' => 'Mobile Klimageräte',
    'aliases' => array('mobile Industrieklimageräte', 'Miet-Klimageräte'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'produkte-mobile-klimageraete',
);
$entities[] = array(
    'id' => 'demo_wartungsvertrag_klimaanlagen',
    'entity_label' => 'Wartungsvertrag',
    'aliases' => array('Klima-Wartungsvertrag', 'Wartungsverträge'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'wartungsvertrag-klimaanlagen',
);
$entities[] = array(
    'id' => 'demo_bafa_foerderung_kaelteanlagen',
    'entity_label' => 'BAFA-Förderung',
    'aliases' => array('BAFA', 'Bafa-Förderung', 'BAFA-Zuschuss'),
    'target_post_id' => 0,
    'priority' => 95,
    '_target_slug' => 'bafa-foerderung-kaelteanlagen',
);
$entities[] = array(
    'id' => 'demo_en_378_sicherheit',
    'entity_label' => 'EN 378',
    'aliases' => array('EN378'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'en-378-sicherheit',
);
$entities[] = array(
    'id' => 'demo_atex_klimageraete',
    'entity_label' => 'ATEX-Klimageräte',
    'aliases' => array('ATEX', 'explosionsgeschützte Klimageräte'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'atex-klimageraete',
);
$entities[] = array(
    'id' => 'demo_waermerueckgewinnung_kaelte',
    'entity_label' => 'Wärmerückgewinnung',
    'aliases' => array('WRG', 'Wärmerückgewinnung aus Kälte'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'waermerueckgewinnung-kaelte',
);
$entities[] = array(
    'id' => 'demo_kaelteleistung_berechnen',
    'entity_label' => 'Kälteleistung',
    'aliases' => array('Kälteleistung berechnen'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'kaelteleistung-berechnen',
);
$entities[] = array(
    'id' => 'demo_notkuehlung_redundanz',
    'entity_label' => 'Notkühlung',
    'aliases' => array('Notkühlkonzept'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'notkuehlung-redundanz',
);
$entities[] = array(
    'id' => 'demo_container_klimatisierung_rechenzentren',
    'entity_label' => 'Container-Klimatisierung',
    'aliases' => array('Containerklimatisierung'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'container-klimatisierung-rechenzentren',
);
$entities[] = array(
    'id' => 'demo_erp_richtlinie_effizienzklassen',
    'entity_label' => 'ErP-Richtlinie',
    'aliases' => array('ErP'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'erp-richtlinie-effizienzklassen',
);
$entities[] = array(
    'id' => 'demo_co2_kaeltemittel_r744',
    'entity_label' => 'CO2-Kältemittel R744',
    'aliases' => array('R744', 'CO2-Kältemittel'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'co2-kaeltemittel-r744',
);
$entities[] = array(
    'id' => 'demo_hybridkuehler_trocken_verdunstung',
    'entity_label' => 'Hybridkühler',
    'aliases' => array('Hybridkühlung'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'hybridkuehler-trocken-verdunstung',
);
$entities[] = array(
    'id' => 'demo_abluft_waermepumpen_hallen',
    'entity_label' => 'Abluft-Wärmepumpe',
    'aliases' => array('Abluft-Wärmepumpen'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'abluft-waermepumpen-hallen',
);
$entities[] = array(
    'id' => 'demo_reinraumklimatisierung_iso_14644',
    'entity_label' => 'Reinraumklimatisierung',
    'aliases' => array('ISO 14644'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'reinraumklimatisierung-iso-14644',
);
$entities[] = array(
    'id' => 'demo_kaeltemittelleckagen_erkennen',
    'entity_label' => 'Kältemittelleckage',
    'aliases' => array('Kältemittelleckagen', 'Leckage'),
    'target_post_id' => 0,
    'priority' => 90,
    '_target_slug' => 'kaeltemittelleckagen-erkennen',
);
$entities[] = array(
    'id' => 'demo_glykol_kuehlkreislaeufe',
    'entity_label' => 'Glykol',
    'aliases' => array('Glykol-Kühlkreislauf'),
    'target_post_id' => 0,
    'priority' => 85,
    '_target_slug' => 'glykol-kuehlkreislaeufe',
);
$entities[] = array(
    'id' => 'demo_pufferspeicher_kaelte',
    'entity_label' => 'Pufferspeicher',
    'aliases' => array('Kältepufferspeicher'),
    'target_post_id' => 0,
    'priority' => 85,
    '_target_slug' => 'pufferspeicher-kaelte',
);

foreach ( $entities as $i => $entity ) {
    $post = get_page_by_path( $entity['_target_slug'], OBJECT, array( 'post', 'page' ) );
    if ( ! $post ) {
        $posts = get_posts( array( 'name' => $entity['_target_slug'], 'post_type' => array( 'post', 'page' ), 'post_status' => 'publish', 'numberposts' => 1 ) );
        $post = $posts ? $posts[0] : null;
    }
    if ( ! $post ) {
        echo 'WARN: Ziel nicht gefunden: ' . $entity['_target_slug'] . PHP_EOL;
        unset($entities[ $i ]);
        continue;
    }
    $entities[ $i ]['target_post_id'] = (int) $post->ID;
    unset($entities[ $i ]['_target_slug']);
}

update_option( 'elink_entities_manual', array_values( $entities ) );
echo 'Entities gesetzt: ' . count( $entities ) . PHP_EOL;

$map = new ELINK_Entity_Map();
$count = $map->rebuild();
echo 'Index neu aufgebaut: ' . $count . ' Posts' . PHP_EOL;

