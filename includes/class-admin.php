<?php
/**
 * Admin: menu, settings, vocabulary, bulk tool, report, meta box.
 *
 * @package EntityLinkEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
class ELINK_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_elink_add_entity', array( $this, 'handle_add_entity' ) );
		add_action( 'admin_post_elink_delete_entity', array( $this, 'handle_delete_entity' ) );
		add_action( 'admin_post_elink_rebuild', array( $this, 'handle_rebuild' ) );
		add_action( 'admin_post_elink_bulk_start', array( $this, 'handle_bulk_start' ) );
	}

	/**
	 * Menu pages.
	 */
	public function menu() {
		add_menu_page(
			__( 'Entity Link Engine', 'entity-link-engine' ),
			__( 'Entity Links', 'entity-link-engine' ),
			'manage_options',
			'elink-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-networking',
			81
		);
		add_submenu_page(
			'elink-dashboard',
			__( 'Dashboard', 'entity-link-engine' ),
			__( 'Dashboard', 'entity-link-engine' ),
			'manage_options',
			'elink-dashboard',
			array( $this, 'render_dashboard' )
		);
		add_submenu_page(
			'elink-dashboard',
			__( 'Settings', 'entity-link-engine' ),
			__( 'Settings', 'entity-link-engine' ),
			'manage_options',
			'elink-settings',
			array( $this, 'render_settings' )
		);
		add_submenu_page(
			'elink-dashboard',
			__( 'Entity vocabulary', 'entity-link-engine' ),
			__( 'Entity vocabulary', 'entity-link-engine' ),
			'manage_options',
			'elink-vocabulary',
			array( $this, 'render_vocabulary' )
		);
		add_submenu_page(
			'elink-dashboard',
			__( 'Bulk run', 'entity-link-engine' ),
			__( 'Bulk run', 'entity-link-engine' ),
			'manage_options',
			'elink-bulk',
			array( $this, 'render_bulk' )
		);
		add_submenu_page(
			'elink-dashboard',
			__( 'Help & documentation', 'entity-link-engine' ),
			__( 'Help', 'entity-link-engine' ),
			'manage_options',
			'elink-help',
			array( $this, 'render_help' )
		);
	}

	/**
	 * Render the shared page introduction.
	 *
	 * @param string $title       Page title.
	 * @param string $description Short page description.
	 */
	private function page_header( $title, $description ) {
		?>
		<div class="elink-page-header">
			<div>
				<p class="elink-eyebrow"><?php esc_html_e( 'Entity Link Engine', 'entity-link-engine' ); ?></p>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p class="elink-lead"><?php echo esc_html( $description ); ?></p>
			</div>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=elink-help' ) ); ?>"><?php esc_html_e( 'View help', 'entity-link-engine' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Render transparent author attribution.
	 */
	private function page_footer() {
		?>
		<footer class="elink-footer">
			<a href="https://eullrich.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Built by Eugen Ullrich', 'entity-link-engine' ); ?></a>
		</footer>
		<?php
	}

	/**
	 * Register settings (whitelist option).
	 */
	public function register_settings() {
		register_setting(
			'elink_settings_group',
			'elink_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = ELINK_Install::defaults();
		$out      = $defaults;

		$out['post_types'] = array();
		if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$available = get_post_types( array( 'public' => true ) );
			foreach ( $input['post_types'] as $type ) {
				$type = sanitize_key( $type );
				if ( isset( $available[ $type ] ) ) {
					$out['post_types'][] = $type;
				}
			}
		}
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = array( 'post' );
		}

		$out['max_links']       = isset( $input['max_links'] ) ? max( 1, min( 20, (int) $input['max_links'] ) ) : $defaults['max_links'];
		$out['min_score']       = isset( $input['min_score'] ) ? (float) max( 0.0, min( 20.0, (float) $input['min_score'] ) ) : $defaults['min_score'];
		$out['auto_on_publish'] = empty( $input['auto_on_publish'] ) ? 0 : 1;
		$out['add_link_class']  = empty( $input['add_link_class'] ) ? 0 : 1;
		$out['link_class']      = isset( $input['link_class'] ) ? sanitize_html_class( $input['link_class'] ) : $defaults['link_class'];

		$out['skip_blocks'] = array();
		if ( ! empty( $input['skip_blocks'] ) && is_array( $input['skip_blocks'] ) ) {
			foreach ( $input['skip_blocks'] as $tag ) {
				$tag = strtolower( sanitize_key( $tag ) );
				if ( '' !== $tag ) {
					$out['skip_blocks'][] = $tag;
				}
			}
		}

		$out['embed'] = array(
			'enabled' => empty( $input['embed']['enabled'] ) ? 0 : 1,
			'api_url' => isset( $input['embed']['api_url'] ) ? esc_url_raw( $input['embed']['api_url'] ) : $defaults['embed']['api_url'],
			'api_key' => isset( $input['embed']['api_key'] ) ? sanitize_text_field( $input['embed']['api_key'] ) : '',
			'model'   => isset( $input['embed']['model'] ) ? sanitize_text_field( $input['embed']['model'] ) : $defaults['embed']['model'],
			'blend'   => isset( $input['embed']['blend'] ) ? (float) max( 0.0, min( 1.0, (float) $input['embed']['blend'] ) ) : $defaults['embed']['blend'],
		);

		return $out;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( $hook ) {
		$screen = get_current_screen();
		$is_ele = $screen && ( false !== strpos( $hook, 'elink-' ) || false !== strpos( $hook, 'page_elink-' ) );

		if ( $is_ele ) {
			wp_enqueue_style( 'elink-admin', ELINK_URL . 'assets/admin.css', array(), ELINK_VERSION );
		}

		// Meta box script only on post edit screens of enabled post types.
		if ( $screen && 'post' === $screen->base && ELINK_Install::is_post_type_enabled( $screen->post_type ) ) {
			wp_enqueue_style( 'elink-admin', ELINK_URL . 'assets/admin.css', array(), ELINK_VERSION );
			wp_enqueue_script( 'elink-admin', ELINK_URL . 'assets/admin.js', array(), ELINK_VERSION, true );
			wp_localize_script(
				'elink-admin',
				'elinkData',
				array(
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'restUrl' => esc_url_raw( rest_url( 'entity-link-engine/v1' ) ),
					'postId'  => (int) get_the_ID(),
					'i18n'    => array(
						'running'    => __( 'Running…', 'entity-link-engine' ),
						'suggest'    => __( 'Suggest links', 'entity-link-engine' ),
						'apply'      => __( 'Insert links', 'entity-link-engine' ),
						'undo'       => __( 'Undo last run', 'entity-link-engine' ),
						'noResults'  => __( 'No candidates above the score threshold.', 'entity-link-engine' ),
						'already'    => __( 'already linked', 'entity-link-engine' ),
						'error'      => __( 'Request failed.', 'entity-link-engine' ),
						'applied'    => __( 'Links inserted.', 'entity-link-engine' ),
						'undone'     => __( 'Undo complete.', 'entity-link-engine' ),
					),
				)
			);
		}
	}

	/**
	 * Meta box registration.
	 */
	public function meta_box() {
		$settings = ELINK_Install::get_settings();
		foreach ( (array) $settings['post_types'] as $post_type ) {
			add_meta_box(
				'elink_meta_box',
				__( 'Entity Link Engine', 'entity-link-engine' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Meta box render.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		$last = get_post_meta( $post->ID, '_elink_last_run', true );
		$count = is_array( $last ) && isset( $last['inserted'] ) ? count( $last['inserted'] ) : 0;
		$disabled = get_post_meta( $post->ID, '_elink_auto_links', true );
		?>
		<div id="elink-meta">
			<div class="elink-meta-intro">
				<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
				<p><?php esc_html_e( 'Review suggestions before inserting contextual internal links.', 'entity-link-engine' ); ?></p>
			</div>
			<p class="elink-status" aria-live="polite">
				<?php if ( $disabled ) : ?>
					<em><?php esc_html_e( 'Auto-linking is disabled for this post (post meta _elink_auto_links).', 'entity-link-engine' ); ?></em>
				<?php else : ?>
					<?php
					/* translators: %d: number of inserted links. */
					printf( esc_html__( 'Last run: %d link(s) inserted.', 'entity-link-engine' ), (int) $count );
					?>
				<?php endif; ?>
			</p>
			<div class="elink-results" aria-live="polite"></div>
			<div class="elink-meta-actions">
				<button type="button" class="button button-primary elink-suggest"><?php esc_html_e( 'Suggest links', 'entity-link-engine' ); ?></button>
				<button type="button" class="button elink-apply" style="display:none;"><?php esc_html_e( 'Insert links', 'entity-link-engine' ); ?></button>
				<button type="button" class="button elink-undo" style="display:none;"><?php esc_html_e( 'Undo last run', 'entity-link-engine' ); ?></button>
			</div>
			<p class="elink-meta-help"><a href="<?php echo esc_url( admin_url( 'admin.php?page=elink-help#editor-workflow' ) ); ?>"><?php esc_html_e( 'How suggestions and safeguards work', 'entity-link-engine' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Dashboard (report) render.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report       = new ELINK_Report();
		$summary      = $report->summary();
		$detail       = $report->detail();
		$index_built  = get_option( 'elink_index_built' );
		$manual_count = count( (array) elink_manual_entities() );
		$has_settings = false !== get_option( 'elink_settings', false );
		?>
		<div class="wrap elink-app">
			<?php $this->page_header( __( 'Link intelligence dashboard', 'entity-link-engine' ), __( 'Configure the engine, build your entity map, and monitor internal-link coverage.', 'entity-link-engine' ) ); ?>
			<section class="elink-panel elink-onboarding" data-elink-onboarding="true" aria-labelledby="elink-onboarding-title">
				<div class="elink-section-heading">
					<div><p class="elink-kicker"><?php esc_html_e( 'Start here', 'entity-link-engine' ); ?></p><h2 id="elink-onboarding-title"><?php esc_html_e( 'Four-step workflow', 'entity-link-engine' ); ?></h2></div>
					<span class="elink-status-pill <?php echo $index_built ? 'is-ready' : 'is-pending'; ?>"><?php echo $index_built ? esc_html__( 'Ready to suggest', 'entity-link-engine' ) : esc_html__( 'Setup in progress', 'entity-link-engine' ); ?></span>
				</div>
				<ol class="elink-steps">
					<li class="<?php echo $has_settings ? 'is-complete' : 'is-current'; ?>" data-elink-step="configure"><span class="elink-step-number">1</span><div><h3><?php esc_html_e( 'Configure', 'entity-link-engine' ); ?></h3><p><?php esc_html_e( 'Choose content types, link limits, score threshold, and safeguards.', 'entity-link-engine' ); ?></p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elink-settings' ) ); ?>"><?php esc_html_e( 'Open settings', 'entity-link-engine' ); ?></a></div></li>
					<li class="<?php echo $index_built ? 'is-complete' : 'is-current'; ?>" data-elink-step="build-index"><span class="elink-step-number">2</span><div><h3><?php esc_html_e( 'Build the index', 'entity-link-engine' ); ?></h3><p><?php echo $index_built ? esc_html( sprintf( /* translators: %s: date/time. */ __( 'Last built %s.', 'entity-link-engine' ), $index_built ) ) : esc_html__( 'Scan published content to create the entity index.', 'entity-link-engine' ); ?></p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elink-bulk' ) ); ?>"><?php esc_html_e( 'Manage index', 'entity-link-engine' ); ?></a></div></li>
					<li class="<?php echo $manual_count > 0 ? 'is-complete' : ''; ?>" data-elink-step="review-entities"><span class="elink-step-number">3</span><div><h3><?php esc_html_e( 'Review entities', 'entity-link-engine' ); ?></h3><p><?php printf( esc_html( _n( '%d manual mapping. Add mappings only where you need precise control.', '%d manual mappings. Add mappings only where you need precise control.', $manual_count, 'entity-link-engine' ) ), (int) $manual_count ); ?></p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elink-vocabulary' ) ); ?>"><?php esc_html_e( 'Review vocabulary', 'entity-link-engine' ); ?></a></div></li>
					<li class="<?php echo $index_built ? 'is-current' : ''; ?>" data-elink-step="editor"><span class="elink-step-number">4</span><div><h3><?php esc_html_e( 'Suggest and insert', 'entity-link-engine' ); ?></h3><p><?php esc_html_e( 'Open a post, review scored suggestions, then insert or undo links.', 'entity-link-engine' ); ?></p><a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'Open posts', 'entity-link-engine' ); ?></a></div></li>
				</ol>
			</section>
			<div class="elink-cards" aria-label="<?php esc_attr_e( 'Link report summary', 'entity-link-engine' ); ?>">
				<div class="elink-card elink-stat"><span class="elink-card-num"><?php echo esc_html( $summary['posts'] ); ?></span><span class="elink-card-label"><?php esc_html_e( 'Posts', 'entity-link-engine' ); ?></span></div>
				<div class="elink-card elink-stat"><span class="elink-card-num"><?php echo esc_html( $summary['auto_edges'] ); ?></span><span class="elink-card-label"><?php esc_html_e( 'Auto links', 'entity-link-engine' ); ?></span></div>
				<div class="elink-card elink-stat"><span class="elink-card-num"><?php echo esc_html( count( $summary['orphans'] ) ); ?></span><span class="elink-card-label"><?php esc_html_e( 'Orphans (no incoming links)', 'entity-link-engine' ); ?></span></div>
			</div>
			<section class="elink-panel" aria-labelledby="elink-report-title">
				<div class="elink-section-heading"><div><p class="elink-kicker"><?php esc_html_e( 'Coverage', 'entity-link-engine' ); ?></p><h2 id="elink-report-title"><?php esc_html_e( 'Post link report', 'entity-link-engine' ); ?></h2></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=elink-bulk' ) ); ?>"><?php esc_html_e( 'Run bulk workflow', 'entity-link-engine' ); ?></a></div>
				<table class="widefat striped elink-report-table">
					<thead><tr><th><?php esc_html_e( 'Post', 'entity-link-engine' ); ?></th><th><?php esc_html_e( 'Outgoing auto links', 'entity-link-engine' ); ?></th><th><?php esc_html_e( 'Incoming links', 'entity-link-engine' ); ?></th><th><?php esc_html_e( 'Status', 'entity-link-engine' ); ?></th></tr></thead>
					<tbody>
					<?php if ( empty( $detail ) ) : ?><tr><td colspan="4" class="elink-empty"><?php esc_html_e( 'No published content is available for this report yet.', 'entity-link-engine' ); ?></td></tr><?php endif; ?>
					<?php foreach ( $detail as $row ) : ?><tr><td><a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td><td><?php echo esc_html( $row['outgoing'] ); ?></td><td><?php echo esc_html( $row['incoming'] ); ?></td><td><span class="elink-status-pill <?php echo $row['orphan'] ? 'is-pending' : 'is-ready'; ?>"><?php echo $row['orphan'] ? esc_html__( 'Needs an incoming link', 'entity-link-engine' ) : esc_html__( 'Covered', 'entity-link-engine' ); ?></span></td></tr><?php endforeach; ?>
					</tbody>
				</table>
			</section>
			<?php $this->page_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Settings render.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings      = ELINK_Install::get_settings();
		$post_types    = get_post_types( array( 'public' => true ), 'objects' );
		$skip_defaults = ELINK_Install::defaults()['skip_blocks'];
		?>
		<div class="wrap elink-app">
			<?php $this->page_header( __( 'Settings', 'entity-link-engine' ), __( 'Tune retrieval and insertion while keeping editorial control.', 'entity-link-engine' ) ); ?>
			<form method="post" action="options.php" class="elink-settings-form">
				<?php settings_fields( 'elink_settings_group' ); ?>
				<div class="elink-settings-grid">
					<section class="elink-panel" aria-labelledby="elink-content-settings"><p class="elink-kicker"><?php esc_html_e( 'Scope', 'entity-link-engine' ); ?></p><h2 id="elink-content-settings"><?php esc_html_e( 'Content and automation', 'entity-link-engine' ); ?></h2>
						<div class="elink-field"><span class="elink-field-label"><?php esc_html_e( 'Post types', 'entity-link-engine' ); ?></span><div class="elink-check-grid"><?php foreach ( $post_types as $type ) : ?><label><input type="checkbox" name="elink_settings[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, (array) $settings['post_types'], true ) ); ?> /> <?php echo esc_html( $type->labels->name ); ?></label><?php endforeach; ?></div><p class="description"><?php esc_html_e( 'Content types the engine scans and links.', 'entity-link-engine' ); ?></p></div>
						<div class="elink-field"><label class="elink-toggle"><input type="checkbox" name="elink_settings[auto_on_publish]" value="1" <?php checked( $settings['auto_on_publish'] ); ?> /><span><strong><?php esc_html_e( 'Run automatically on publish', 'entity-link-engine' ); ?></strong><small><?php esc_html_e( 'Run the engine whenever an enabled post is published.', 'entity-link-engine' ); ?></small></span></label></div>
					</section>
					<section class="elink-panel" aria-labelledby="elink-quality-settings"><p class="elink-kicker"><?php esc_html_e( 'Quality', 'entity-link-engine' ); ?></p><h2 id="elink-quality-settings"><?php esc_html_e( 'Scoring and limits', 'entity-link-engine' ); ?></h2>
						<div class="elink-field-row"><div class="elink-field"><label class="elink-field-label" for="elink_max_links"><?php esc_html_e( 'Max links per post', 'entity-link-engine' ); ?></label><input type="number" id="elink_max_links" name="elink_settings[max_links]" value="<?php echo esc_attr( $settings['max_links'] ); ?>" min="1" max="20" /><p class="description"><?php esc_html_e( 'Limits inserted links in one run.', 'entity-link-engine' ); ?></p></div><div class="elink-field"><label class="elink-field-label" for="elink_min_score"><?php esc_html_e( 'Minimum score', 'entity-link-engine' ); ?></label><input type="number" step="0.1" id="elink_min_score" name="elink_settings[min_score]" value="<?php echo esc_attr( $settings['min_score'] ); ?>" min="0" max="20" /><p class="description"><?php esc_html_e( 'Candidates below this threshold are excluded.', 'entity-link-engine' ); ?></p></div></div>
						<div class="elink-field"><span class="elink-field-label"><?php esc_html_e( 'Protected blocks', 'entity-link-engine' ); ?></span><div class="elink-check-grid"><?php foreach ( $skip_defaults as $tag ) : ?><label><input type="checkbox" name="elink_settings[skip_blocks][]" value="<?php echo esc_attr( $tag ); ?>" <?php checked( in_array( $tag, (array) $settings['skip_blocks'], true ) ); ?> /> <code>&lt;<?php echo esc_html( $tag ); ?>&gt;</code></label><?php endforeach; ?></div><p class="description"><?php esc_html_e( 'Selected block types never receive inserted links.', 'entity-link-engine' ); ?></p></div>
					</section>
					<section class="elink-panel" aria-labelledby="elink-markup-settings"><p class="elink-kicker"><?php esc_html_e( 'Output', 'entity-link-engine' ); ?></p><h2 id="elink-markup-settings"><?php esc_html_e( 'Link markup', 'entity-link-engine' ); ?></h2>
						<div class="elink-field"><label class="elink-toggle"><input type="checkbox" name="elink_settings[add_link_class]" value="1" <?php checked( $settings['add_link_class'] ); ?> /><span><strong><?php esc_html_e( 'Add a CSS class', 'entity-link-engine' ); ?></strong><small><?php esc_html_e( 'Apply a consistent class to links inserted by the engine.', 'entity-link-engine' ); ?></small></span></label></div>
						<div class="elink-field"><label class="elink-field-label" for="elink_link_class"><?php esc_html_e( 'CSS class', 'entity-link-engine' ); ?></label><input type="text" id="elink_link_class" name="elink_settings[link_class]" value="<?php echo esc_attr( $settings['link_class'] ); ?>" /></div>
					</section>
					<section class="elink-panel" aria-labelledby="elink-semantic-settings"><p class="elink-kicker"><?php esc_html_e( 'Optional', 'entity-link-engine' ); ?></p><h2 id="elink-semantic-settings"><?php esc_html_e( 'Semantic layer', 'entity-link-engine' ); ?></h2>
						<div class="elink-privacy-note"><span class="dashicons dashicons-shield" aria-hidden="true"></span><p><?php esc_html_e( 'Disabled by default. When enabled, text excerpts are sent to your configured OpenAI-compatible endpoint. The plugin itself adds no tracking.', 'entity-link-engine' ); ?></p></div>
						<div class="elink-field"><label class="elink-toggle"><input type="checkbox" name="elink_settings[embed][enabled]" value="1" <?php checked( $settings['embed']['enabled'] ); ?> /><span><strong><?php esc_html_e( 'Enable embeddings API', 'entity-link-engine' ); ?></strong><small><?php esc_html_e( 'Blend semantic similarity into the lexical score.', 'entity-link-engine' ); ?></small></span></label></div>
						<div class="elink-field"><label class="elink-field-label" for="elink_api_url"><?php esc_html_e( 'API URL', 'entity-link-engine' ); ?></label><input type="url" class="regular-text" id="elink_api_url" name="elink_settings[embed][api_url]" value="<?php echo esc_attr( $settings['embed']['api_url'] ); ?>" placeholder="https://api.openai.com/v1" /></div>
						<div class="elink-field"><label class="elink-field-label" for="elink_api_key"><?php esc_html_e( 'API key', 'entity-link-engine' ); ?></label><input type="password" class="regular-text" id="elink_api_key" name="elink_settings[embed][api_key]" value="<?php echo esc_attr( $settings['embed']['api_key'] ); ?>" autocomplete="off" /></div>
						<div class="elink-field-row"><div class="elink-field"><label class="elink-field-label" for="elink_model"><?php esc_html_e( 'Model', 'entity-link-engine' ); ?></label><input type="text" id="elink_model" name="elink_settings[embed][model]" value="<?php echo esc_attr( $settings['embed']['model'] ); ?>" /></div><div class="elink-field"><label class="elink-field-label" for="elink_blend"><?php esc_html_e( 'Blend weight (0–1)', 'entity-link-engine' ); ?></label><input type="number" id="elink_blend" step="0.05" min="0" max="1" name="elink_settings[embed][blend]" value="<?php echo esc_attr( $settings['embed']['blend'] ); ?>" /></div></div>
					</section>
				</div>
				<div class="elink-save-bar"><?php submit_button( __( 'Save settings', 'entity-link-engine' ), 'primary', 'submit', false ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=elink-help#scoring' ) ); ?>"><?php esc_html_e( 'Understand scoring and safeguards', 'entity-link-engine' ); ?></a></div>
			</form>
			<?php $this->page_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Vocabulary render.
	 */
	public function render_vocabulary() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$entities = elink_manual_entities();
		$posts    = get_posts(
			array(
				'post_type'   => ELINK_Install::get_settings()['post_types'],
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		?>
		<div class="wrap elink-app">
			<?php $this->page_header( __( 'Entity vocabulary', 'entity-link-engine' ), __( 'Add precise phrase-to-post mappings that take precedence over extracted entities.', 'entity-link-engine' ) ); ?>
			<div class="elink-two-column">
			<section class="elink-panel" aria-labelledby="elink-add-entity"><p class="elink-kicker"><?php esc_html_e( 'Manual mapping', 'entity-link-engine' ); ?></p><h2 id="elink-add-entity"><?php esc_html_e( 'Add entity', 'entity-link-engine' ); ?></h2>
			<p><?php esc_html_e( 'Map a preferred phrase and optional aliases to one published target.', 'entity-link-engine' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="elink-entity-form">
				<input type="hidden" name="action" value="elink_add_entity" />
				<?php wp_nonce_field( 'elink_add_entity' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="elink_entity_label"><?php esc_html_e( 'Entity name', 'entity-link-engine' ); ?></label></th>
						<td><input type="text" class="regular-text" id="elink_entity_label" name="entity_label" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="elink_entity_aliases"><?php esc_html_e( 'Aliases (comma separated)', 'entity-link-engine' ); ?></label></th>
						<td><input type="text" class="regular-text" id="elink_entity_aliases" name="aliases" placeholder="SEO-Audit, SEO Audit" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="elink_entity_target"><?php esc_html_e( 'Target post', 'entity-link-engine' ); ?></label></th>
						<td>
							<select id="elink_entity_target" name="target_post_id" required>
								<?php foreach ( $posts as $post ) : ?>
									<option value="<?php echo esc_attr( $post->ID ); ?>"><?php echo esc_html( $post->post_title ); ?> (<?php echo esc_html( $post->ID ); ?>)</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="elink_entity_priority"><?php esc_html_e( 'Priority', 'entity-link-engine' ); ?></label></th>
						<td><input type="number" id="elink_entity_priority" name="priority" value="100" min="1" max="1000" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Add entity', 'entity-link-engine' ) ); ?>
			</form>
			</section>

			<section class="elink-panel elink-vocabulary-list" aria-labelledby="elink-existing-entities"><div class="elink-section-heading"><div><p class="elink-kicker"><?php esc_html_e( 'Vocabulary', 'entity-link-engine' ); ?></p><h2 id="elink-existing-entities"><?php esc_html_e( 'Existing entities', 'entity-link-engine' ); ?></h2></div><span class="elink-status-pill is-ready"><?php echo esc_html( count( (array) $entities ) ); ?></span></div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Entity', 'entity-link-engine' ); ?></th>
						<th><?php esc_html_e( 'Aliases', 'entity-link-engine' ); ?></th>
						<th><?php esc_html_e( 'Target', 'entity-link-engine' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'entity-link-engine' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $entities ) ) : ?>
					<tr><td colspan="5" class="elink-empty"><strong><?php esc_html_e( 'No manual entities yet.', 'entity-link-engine' ); ?></strong><br /><?php esc_html_e( 'The extracted index still works. Add a mapping when a phrase needs an exact target.', 'entity-link-engine' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( (array) $entities as $entity ) : ?>
					<tr>
						<td><?php echo esc_html( $entity['entity_label'] ); ?></td>
						<td><?php echo esc_html( is_array( $entity['aliases'] ) ? implode( ', ', $entity['aliases'] ) : '' ); ?></td>
						<td>
							<?php
							$target = get_post( (int) $entity['target_post_id'] );
							echo $target ? esc_html( $target->post_title ) : esc_html__( '(missing)', 'entity-link-engine' );
							?>
						</td>
						<td><?php echo esc_html( $entity['priority'] ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this entity?', 'entity-link-engine' ) ); ?>');">
								<input type="hidden" name="action" value="elink_delete_entity" />
								<input type="hidden" name="entity_id" value="<?php echo esc_attr( $entity['id'] ); ?>" />
								<?php wp_nonce_field( 'elink_delete_entity' ); ?>
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'entity-link-engine' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</section>
			</div>
			<?php $this->page_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Bulk render.
	 */
	public function render_bulk() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$active = get_option( 'elink_bulk_active' );
		$total  = (int) get_option( 'elink_bulk_total', 0 );
		$last   = get_option( 'elink_bulk_last' );
		$built  = get_option( 'elink_index_built' );
		$done   = false !== $active ? (int) $active : 0;
		$percent = $total > 0 ? min( 100, round( ( $done / $total ) * 100 ) ) : 0;
		?>
		<div class="wrap elink-app">
			<?php $this->page_header( __( 'Bulk workflow', 'entity-link-engine' ), __( 'Refresh the entity index, then process published content in safe background batches.', 'entity-link-engine' ) ); ?>
			<div class="elink-two-column">
				<section class="elink-panel" aria-labelledby="elink-index-title"><div class="elink-section-heading"><div><p class="elink-kicker"><?php esc_html_e( 'Step one', 'entity-link-engine' ); ?></p><h2 id="elink-index-title"><?php esc_html_e( 'Entity index', 'entity-link-engine' ); ?></h2></div><span class="elink-status-pill <?php echo $built ? 'is-ready' : 'is-pending'; ?>"><?php echo $built ? esc_html__( 'Built', 'entity-link-engine' ) : esc_html__( 'Not built', 'entity-link-engine' ); ?></span></div>
					<p><?php echo $built ? esc_html( sprintf( /* translators: %s: date/time. */ __( 'The index was last rebuilt %s.', 'entity-link-engine' ), $built ) ) : esc_html__( 'Build the index before requesting suggestions or starting a bulk run.', 'entity-link-engine' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elink_rebuild" /><?php wp_nonce_field( 'elink_rebuild' ); ?><?php submit_button( __( 'Rebuild entity index', 'entity-link-engine' ), 'secondary', 'submit', false ); ?></form>
					<p class="description"><?php esc_html_e( 'Scans enabled published content and re-extracts titles, headings, tags, and categories.', 'entity-link-engine' ); ?></p>
				</section>
				<section class="elink-panel" aria-labelledby="elink-bulk-title"><div class="elink-section-heading"><div><p class="elink-kicker"><?php esc_html_e( 'Step two', 'entity-link-engine' ); ?></p><h2 id="elink-bulk-title"><?php esc_html_e( 'Process all posts', 'entity-link-engine' ); ?></h2></div><span class="elink-status-pill <?php echo false !== $active ? 'is-working' : 'is-ready'; ?>"><?php echo false !== $active ? esc_html__( 'In progress', 'entity-link-engine' ) : esc_html__( 'Available', 'entity-link-engine' ); ?></span></div>
				<?php if ( false !== $active ) : ?>
					<p><?php esc_html_e( 'The engine is processing five posts per WP-Cron tick.', 'entity-link-engine' ); ?></p>
					<div class="elink-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $percent ); ?>"><span style="width:<?php echo esc_attr( $percent ); ?>%"></span></div>
					<p class="elink-progress-label">
					<?php
					/* translators: 1: processed post count, 2: total post count. */
					printf( esc_html__( '%1$d of %2$d posts processed', 'entity-link-engine' ), esc_html( $done ), esc_html( $total ) );
					?>
					</p>
					<?php if ( is_array( $last ) ) : ?>
						<p class="description">
						<?php
						/* translators: 1: latest post ID, 2: inserted link count. */
						printf( esc_html__( 'Latest: post %1$d, %2$d link(s) inserted.', 'entity-link-engine' ), esc_html( (int) $last['post_id'] ), esc_html( (int) $last['inserted'] ) );
						?>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Insert eligible links across enabled published content. A snapshot is retained per post for undo.', 'entity-link-engine' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elink_bulk_start" /><?php wp_nonce_field( 'elink_bulk_start' ); ?><?php submit_button( __( 'Start bulk run', 'entity-link-engine' ), 'primary', 'submit', false ); ?></form>
				<?php endif; ?>
				</section>
			</div>
			<div class="elink-privacy-note"><span class="dashicons dashicons-backup" aria-hidden="true"></span><p><strong><?php esc_html_e( 'Reversible by design.', 'entity-link-engine' ); ?></strong> <?php esc_html_e( 'Each processed post keeps a content snapshot. Use Undo last run in the editor meta box to restore it.', 'entity-link-engine' ); ?></p></div>
			<?php $this->page_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Render integrated documentation.
	 */
	public function render_help() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap elink-app">
			<?php $this->page_header( __( 'Help & documentation', 'entity-link-engine' ), __( 'A practical guide to the entity map, retrieval, scoring, and safe insertion workflow.', 'entity-link-engine' ) ); ?>
			<div class="elink-docs-layout">
				<nav class="elink-docs-nav" aria-label="<?php esc_attr_e( 'Documentation sections', 'entity-link-engine' ); ?>"><strong><?php esc_html_e( 'On this page', 'entity-link-engine' ); ?></strong><a href="#entity-mapping"><?php esc_html_e( 'Entity mapping', 'entity-link-engine' ); ?></a><a href="#retrieval"><?php esc_html_e( 'Fan-out retrieval', 'entity-link-engine' ); ?></a><a href="#scoring"><?php esc_html_e( 'Scoring', 'entity-link-engine' ); ?></a><a href="#editor-workflow"><?php esc_html_e( 'Insertion and undo', 'entity-link-engine' ); ?></a><a href="#bulk-workflow"><?php esc_html_e( 'Bulk workflow', 'entity-link-engine' ); ?></a><a href="#semantic-privacy"><?php esc_html_e( 'Semantic layer and privacy', 'entity-link-engine' ); ?></a></nav>
				<main class="elink-docs-content">
					<section id="entity-mapping" class="elink-panel"><p class="elink-kicker"><?php esc_html_e( 'Foundation', 'entity-link-engine' ); ?></p><h2><?php esc_html_e( 'Entity mapping', 'entity-link-engine' ); ?></h2><p><?php esc_html_e( 'The index extracts entities from enabled published content. Manual vocabulary entries map a preferred phrase and aliases to one target post and take precedence when phrases overlap.', 'entity-link-engine' ); ?></p><p><?php esc_html_e( 'Use manual mappings for ambiguous names, abbreviations, or cornerstone pages—not as a requirement for every post.', 'entity-link-engine' ); ?></p></section>
					<section id="retrieval" class="elink-panel"><p class="elink-kicker"><?php esc_html_e( 'Discovery', 'entity-link-engine' ); ?></p><h2><?php esc_html_e( 'Fan-out retrieval', 'entity-link-engine' ); ?></h2><p><?php esc_html_e( 'The engine expands a source post into several query signals and retrieves possible target posts from the local entity index. Results are merged and deduplicated before scoring.', 'entity-link-engine' ); ?></p></section>
					<section id="scoring" class="elink-panel"><p class="elink-kicker"><?php esc_html_e( 'Ranking', 'entity-link-engine' ); ?></p><h2><?php esc_html_e( 'Scoring', 'entity-link-engine' ); ?></h2><p><?php esc_html_e( 'Candidates receive a lexical score from matching evidence. The minimum-score setting filters weak candidates, and the per-post maximum limits insertions. If enabled, semantic similarity is blended into—not substituted for—the lexical score.', 'entity-link-engine' ); ?></p></section>
					<section id="editor-workflow" class="elink-panel"><p class="elink-kicker"><?php esc_html_e( 'Editorial control', 'entity-link-engine' ); ?></p><h2><?php esc_html_e( 'Insertion safeguards and undo', 'entity-link-engine' ); ?></h2><p><?php esc_html_e( 'Suggest links only previews scored candidates. Insert links runs the guarded inserter: it avoids existing links, the current post, protected blocks, repeated targets, and more than one automatic link per eligible text block.', 'entity-link-engine' ); ?></p><p><?php esc_html_e( 'Before insertion, the current content is saved as a snapshot. Undo last run restores that snapshot from the editor meta box.', 'entity-link-engine' ); ?></p></section>
					<section id="bulk-workflow" class="elink-panel"><p class="elink-kicker"><?php esc_html_e( 'At scale', 'entity-link-engine' ); ?></p><h2><?php esc_html_e( 'Bulk workflow', 'entity-link-engine' ); ?></h2><p><?php esc_html_e( 'Rebuild the entity index after meaningful content changes, then start the bulk run. WP-Cron processes five posts per tick so the work is spread across small batches. Individual post snapshots remain available for undo.', 'entity-link-engine' ); ?></p></section>
					<section id="semantic-privacy" class="elink-panel"><p class="elink-kicker"><?php esc_html_e( 'Optional external service', 'entity-link-engine' ); ?></p><h2><?php esc_html_e( 'Semantic layer and privacy', 'entity-link-engine' ); ?></h2><p><?php esc_html_e( 'The semantic layer is off by default. When enabled, text excerpts are sent to the OpenAI-compatible API URL you configure. Review that provider’s terms and privacy practices before enabling it. Without this option, link analysis stays within WordPress and the plugin makes no external calls.', 'entity-link-engine' ); ?></p></section>
				</main>
			</div>
			<?php $this->page_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Handle add entity.
	 */
	public function handle_add_entity() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'elink_add_entity' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'entity-link-engine' ) );
		}
		$label   = isset( $_POST['entity_label'] ) ? sanitize_text_field( wp_unslash( $_POST['entity_label'] ) ) : '';
		$aliases = isset( $_POST['aliases'] ) ? sanitize_text_field( wp_unslash( $_POST['aliases'] ) ) : '';
		$target  = isset( $_POST['target_post_id'] ) ? (int) $_POST['target_post_id'] : 0;
		$prio    = isset( $_POST['priority'] ) ? max( 1, min( 1000, (int) $_POST['priority'] ) ) : 100;

		if ( '' === $label || ! $target ) {
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=elink-vocabulary' ) );
			exit;
		}

		$entities = get_option( 'elink_entities_manual', array() );
		if ( ! is_array( $entities ) ) {
			$entities = array();
		}
		$alias_list = array_values( array_filter( array_map( 'trim', explode( ',', $aliases ) ) ) );
		$entities[] = array(
			'id'             => uniqid( 'elink_' ),
			'entity_label'   => $label,
			'aliases'        => $alias_list,
			'target_post_id' => $target,
			'priority'       => $prio,
		);
		update_option( 'elink_entities_manual', $entities );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=elink-vocabulary' ) );
		exit;
	}

	/**
	 * Handle delete entity.
	 */
	public function handle_delete_entity() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'elink_delete_entity' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'entity-link-engine' ) );
		}
		$id       = isset( $_POST['entity_id'] ) ? sanitize_text_field( wp_unslash( $_POST['entity_id'] ) ) : '';
		$entities = get_option( 'elink_entities_manual', array() );
		if ( is_array( $entities ) ) {
			$entities = array_values(
				array_filter(
					$entities,
					function ( $e ) use ( $id ) {
						return ( $e['id'] ?? '' ) !== $id;
					}
				)
			);
			update_option( 'elink_entities_manual', $entities );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=elink-vocabulary' ) );
		exit;
	}

	/**
	 * Handle rebuild.
	 */
	public function handle_rebuild() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'elink_rebuild' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'entity-link-engine' ) );
		}
		$map = new ELINK_Entity_Map();
		$map->rebuild();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=elink-bulk' ) );
		exit;
	}

	/**
	 * Handle bulk start.
	 */
	public function handle_bulk_start() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'elink_bulk_start' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'entity-link-engine' ) );
		}
		$engine = new ELINK_Engine();
		$engine->start_bulk();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=elink-bulk' ) );
		exit;
	}
}
