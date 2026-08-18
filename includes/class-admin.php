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
class ELE_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ele_add_entity', array( $this, 'handle_add_entity' ) );
		add_action( 'admin_post_ele_delete_entity', array( $this, 'handle_delete_entity' ) );
		add_action( 'admin_post_ele_rebuild', array( $this, 'handle_rebuild' ) );
		add_action( 'admin_post_ele_bulk_start', array( $this, 'handle_bulk_start' ) );
	}

	/**
	 * Menu pages.
	 */
	public function menu() {
		add_menu_page(
			__( 'Entity Link Engine', 'entity-link-engine' ),
			__( 'Entity Links', 'entity-link-engine' ),
			'manage_options',
			'ele-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-networking',
			81
		);
		add_submenu_page(
			'ele-dashboard',
			__( 'Dashboard', 'entity-link-engine' ),
			__( 'Dashboard', 'entity-link-engine' ),
			'manage_options',
			'ele-dashboard',
			array( $this, 'render_dashboard' )
		);
		add_submenu_page(
			'ele-dashboard',
			__( 'Settings', 'entity-link-engine' ),
			__( 'Settings', 'entity-link-engine' ),
			'manage_options',
			'ele-settings',
			array( $this, 'render_settings' )
		);
		add_submenu_page(
			'ele-dashboard',
			__( 'Entity vocabulary', 'entity-link-engine' ),
			__( 'Entity vocabulary', 'entity-link-engine' ),
			'manage_options',
			'ele-vocabulary',
			array( $this, 'render_vocabulary' )
		);
		add_submenu_page(
			'ele-dashboard',
			__( 'Bulk run', 'entity-link-engine' ),
			__( 'Bulk run', 'entity-link-engine' ),
			'manage_options',
			'ele-bulk',
			array( $this, 'render_bulk' )
		);
	}

	/**
	 * Register settings (whitelist option).
	 */
	public function register_settings() {
		register_setting(
			'ele_settings_group',
			'ele_settings',
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
		$defaults = ELE_Install::defaults();
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
		$is_ele = $screen && ( false !== strpos( $hook, 'ele-' ) || false !== strpos( $hook, 'page_ele-' ) );

		if ( $is_ele ) {
			wp_enqueue_style( 'ele-admin', ELE_URL . 'assets/admin.css', array(), ELE_VERSION );
		}

		// Meta box script only on post edit screens of enabled post types.
		if ( $screen && 'post' === $screen->base && ELE_Install::is_post_type_enabled( $screen->post_type ) ) {
			wp_enqueue_style( 'ele-admin', ELE_URL . 'assets/admin.css', array(), ELE_VERSION );
			wp_enqueue_script( 'ele-admin', ELE_URL . 'assets/admin.js', array(), ELE_VERSION, true );
			wp_localize_script(
				'ele-admin',
				'eleData',
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
		$settings = ELE_Install::get_settings();
		foreach ( (array) $settings['post_types'] as $post_type ) {
			add_meta_box(
				'ele_meta_box',
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
		$last = get_post_meta( $post->ID, '_ele_last_run', true );
		$count = is_array( $last ) && isset( $last['inserted'] ) ? count( $last['inserted'] ) : 0;
		$disabled = get_post_meta( $post->ID, '_ele_auto_links', true );
		?>
		<div id="ele-meta">
			<p class="ele-status">
				<?php if ( $disabled ) : ?>
					<em><?php esc_html_e( 'Auto-linking is disabled for this post (post meta _ele_auto_links).', 'entity-link-engine' ); ?></em>
				<?php else : ?>
					<?php
					/* translators: %d: number of inserted links. */
					printf( esc_html__( 'Last run: %d link(s) inserted.', 'entity-link-engine' ), (int) $count );
					?>
				<?php endif; ?>
			</p>
			<div class="ele-results"></div>
			<p>
				<button type="button" class="button ele-suggest"><?php esc_html_e( 'Suggest links', 'entity-link-engine' ); ?></button>
				<button type="button" class="button ele-apply" style="display:none;"><?php esc_html_e( 'Insert links', 'entity-link-engine' ); ?></button>
				<button type="button" class="button ele-undo" style="display:none;"><?php esc_html_e( 'Undo last run', 'entity-link-engine' ); ?></button>
			</p>
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
		$report = new ELE_Report();
		$summary = $report->summary();
		$detail  = $report->detail();
		$index_built = get_option( 'ele_index_built' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Entity Link Engine — Dashboard', 'entity-link-engine' ); ?></h1>

			<div class="ele-cards">
				<div class="ele-card">
					<span class="ele-card-num"><?php echo esc_html( $summary['posts'] ); ?></span>
					<span class="ele-card-label"><?php esc_html_e( 'Posts', 'entity-link-engine' ); ?></span>
				</div>
				<div class="ele-card">
					<span class="ele-card-num"><?php echo esc_html( $summary['auto_edges'] ); ?></span>
					<span class="ele-card-label"><?php esc_html_e( 'Auto links', 'entity-link-engine' ); ?></span>
				</div>
				<div class="ele-card">
					<span class="ele-card-num"><?php echo esc_html( count( $summary['orphans'] ) ); ?></span>
					<span class="ele-card-label"><?php esc_html_e( 'Orphans (no incoming links)', 'entity-link-engine' ); ?></span>
				</div>
			</div>

			<?php if ( $index_built ) : ?>
				<p><em>
				<?php
				/* translators: %s: date/time the index was built. */
				printf( esc_html__( 'Entity index last rebuilt: %s.', 'entity-link-engine' ), esc_html( $index_built ) );
				?>
				</em></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Posts', 'entity-link-engine' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', 'entity-link-engine' ); ?></th>
						<th><?php esc_html_e( 'Outgoing auto links', 'entity-link-engine' ); ?></th>
						<th><?php esc_html_e( 'Incoming links', 'entity-link-engine' ); ?></th>
						<th><?php esc_html_e( 'Orphan', 'entity-link-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $detail as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
						<td><?php echo esc_html( $row['outgoing'] ); ?></td>
						<td><?php echo esc_html( $row['incoming'] ); ?></td>
						<td><?php echo $row['orphan'] ? '⚠️' : ''; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
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
		$settings = ELE_Install::get_settings();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Entity Link Engine — Settings', 'entity-link-engine' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'ele_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'entity-link-engine' ); ?></th>
						<td>
							<?php foreach ( $post_types as $type ) : ?>
								<label style="margin-right:12px;">
									<input type="checkbox" name="ele_settings[post_types][]" value="<?php echo esc_attr( $type->name ); ?>"
									<?php checked( in_array( $type->name, (array) $settings['post_types'], true ) ); ?> />
									<?php echo esc_html( $type->labels->name ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Content types the engine scans and links.', 'entity-link-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ele_max_links"><?php esc_html_e( 'Max links per post', 'entity-link-engine' ); ?></label></th>
						<td>
							<input type="number" id="ele_max_links" name="ele_settings[max_links]" value="<?php echo esc_attr( $settings['max_links'] ); ?>" min="1" max="20" />
							<p class="description"><?php esc_html_e( 'Default 3, same as the reference implementation. Per-post override via post meta _ele_max_links.', 'entity-link-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ele_min_score"><?php esc_html_e( 'Min score', 'entity-link-engine' ); ?></label></th>
						<td>
							<input type="number" step="0.1" id="ele_min_score" name="ele_settings[min_score]" value="<?php echo esc_attr( $settings['min_score'] ); ?>" min="0" max="20" />
							<p class="description"><?php esc_html_e( 'Candidates below this score are not linked. Default 2.5.', 'entity-link-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatic run on publish', 'entity-link-engine' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="ele_settings[auto_on_publish]" value="1" <?php checked( $settings['auto_on_publish'] ); ?> />
								<?php esc_html_e( 'Run the engine automatically when a post is published.', 'entity-link-engine' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Link markup', 'entity-link-engine' ); ?></th>
						<td>
							<label style="display:block;">
								<input type="checkbox" name="ele_settings[add_link_class]" value="1" <?php checked( $settings['add_link_class'] ); ?> />
								<?php esc_html_e( 'Add CSS class to inserted links', 'entity-link-engine' ); ?>
							</label>
							<label style="display:block;margin-top:6px;">
								<?php esc_html_e( 'Class:', 'entity-link-engine' ); ?>
								<input type="text" name="ele_settings[link_class]" value="<?php echo esc_attr( $settings['link_class'] ); ?>" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Skip blocks', 'entity-link-engine' ); ?></th>
						<td>
							<?php
							$skip_defaults = ELE_Install::defaults()['skip_blocks'];
							foreach ( $skip_defaults as $tag ) :
								?>
								<label style="margin-right:12px;">
									<input type="checkbox" name="ele_settings[skip_blocks][]" value="<?php echo esc_attr( $tag ); ?>"
									<?php checked( in_array( $tag, (array) $settings['skip_blocks'], true ) ); ?> />
									<code>&lt;<?php echo esc_html( $tag ); ?>&gt;</code>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Block types that never receive auto links (headings, code, tables, quotes…).', 'entity-link-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Semantic layer (optional)', 'entity-link-engine' ); ?></th>
						<td>
							<label style="display:block;">
								<input type="checkbox" name="ele_settings[embed][enabled]" value="1" <?php checked( $settings['embed']['enabled'] ); ?> />
								<?php esc_html_e( 'Enable embeddings API (OpenAI-compatible)', 'entity-link-engine' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When enabled, this plugin sends text excerpts to the configured embeddings endpoint. This is an external service call; see readme for details.', 'entity-link-engine' ); ?></p>
							<label style="display:block;margin-top:6px;">
								<?php esc_html_e( 'API URL:', 'entity-link-engine' ); ?>
								<input type="url" class="regular-text" name="ele_settings[embed][api_url]" value="<?php echo esc_attr( $settings['embed']['api_url'] ); ?>" placeholder="https://api.openai.com/v1" />
							</label>
							<label style="display:block;margin-top:6px;">
								<?php esc_html_e( 'API key:', 'entity-link-engine' ); ?>
								<input type="password" class="regular-text" name="ele_settings[embed][api_key]" value="<?php echo esc_attr( $settings['embed']['api_key'] ); ?>" autocomplete="off" />
							</label>
							<label style="display:block;margin-top:6px;">
								<?php esc_html_e( 'Model:', 'entity-link-engine' ); ?>
								<input type="text" name="ele_settings[embed][model]" value="<?php echo esc_attr( $settings['embed']['model'] ); ?>" />
							</label>
							<label style="display:block;margin-top:6px;">
								<?php esc_html_e( 'Blend weight (0–1):', 'entity-link-engine' ); ?>
								<input type="number" step="0.05" min="0" max="1" name="ele_settings[embed][blend]" value="<?php echo esc_attr( $settings['embed']['blend'] ); ?>" />
							</label>
							<p class="description"><?php esc_html_e( 'Final score = lexical score + blend × 5 × mean cosine similarity.', 'entity-link-engine' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
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
		$entities = ele_manual_entities();
		$posts    = get_posts(
			array(
				'post_type'   => ELE_Install::get_settings()['post_types'],
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Entity Link Engine — Entity vocabulary', 'entity-link-engine' ); ?></h1>
			<p><?php esc_html_e( 'Manual entities always win over auto-extracted ones. Each entity maps a phrase (plus aliases) to one target post. Aliases are matched with word boundaries, longest first.', 'entity-link-engine' ); ?></p>

			<h2><?php esc_html_e( 'Add entity', 'entity-link-engine' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ele_add_entity" />
				<?php wp_nonce_field( 'ele_add_entity' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ele_entity_label"><?php esc_html_e( 'Entity name', 'entity-link-engine' ); ?></label></th>
						<td><input type="text" class="regular-text" id="ele_entity_label" name="entity_label" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ele_entity_aliases"><?php esc_html_e( 'Aliases (comma separated)', 'entity-link-engine' ); ?></label></th>
						<td><input type="text" class="regular-text" id="ele_entity_aliases" name="aliases" placeholder="SEO-Audit, SEO Audit" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ele_entity_target"><?php esc_html_e( 'Target post', 'entity-link-engine' ); ?></label></th>
						<td>
							<select id="ele_entity_target" name="target_post_id" required>
								<?php foreach ( $posts as $post ) : ?>
									<option value="<?php echo esc_attr( $post->ID ); ?>"><?php echo esc_html( $post->post_title ); ?> (<?php echo esc_html( $post->ID ); ?>)</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ele_entity_priority"><?php esc_html_e( 'Priority', 'entity-link-engine' ); ?></label></th>
						<td><input type="number" id="ele_entity_priority" name="priority" value="100" min="1" max="1000" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Add entity', 'entity-link-engine' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Existing entities', 'entity-link-engine' ); ?></h2>
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
					<tr><td colspan="5"><?php esc_html_e( 'No manual entities yet.', 'entity-link-engine' ); ?></td></tr>
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
								<input type="hidden" name="action" value="ele_delete_entity" />
								<input type="hidden" name="entity_id" value="<?php echo esc_attr( $entity['id'] ); ?>" />
								<?php wp_nonce_field( 'ele_delete_entity' ); ?>
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'entity-link-engine' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
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
		$active  = get_option( 'ele_bulk_active' );
		$total   = get_option( 'ele_bulk_total', 0 );
		$last    = get_option( 'ele_bulk_last' );
		$built   = get_option( 'ele_index_built' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Entity Link Engine — Bulk run', 'entity-link-engine' ); ?></h1>

			<h2><?php esc_html_e( 'Entity index', 'entity-link-engine' ); ?></h2>
			<p>
				<?php
				if ( $built ) {
					/* translators: %s: date/time. */
					printf( esc_html__( 'Index built: %s.', 'entity-link-engine' ), esc_html( $built ) );
				} else {
					esc_html_e( 'Index not built yet.', 'entity-link-engine' );
				}
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ele_rebuild" />
				<?php wp_nonce_field( 'ele_rebuild' ); ?>
				<?php submit_button( __( 'Rebuild entity index', 'entity-link-engine' ), 'secondary', 'submit', false ); ?>
			</form>
			<p class="description"><?php esc_html_e( 'Rebuild scans all posts and re-extracts entities (titles, headings, tags, categories). Run after adding or editing posts if auto-run is off.', 'entity-link-engine' ); ?></p>

			<h2><?php esc_html_e( 'Run engine on all posts', 'entity-link-engine' ); ?></h2>
			<?php if ( false !== $active ) : ?>
				<p><?php esc_html_e( 'A bulk run is in progress.', 'entity-link-engine' ); ?></p>
				<?php if ( $total ) : ?>
					<p><em><?php echo esc_html( $active ); ?> / <?php echo esc_html( $total ); ?> <?php esc_html_e( 'posts processed.', 'entity-link-engine' ); ?></em></p>
				<?php endif; ?>
				<?php if ( is_array( $last ) ) : ?>
					<p><em>
					<?php
					/* translators: 1: post id, 2: number of links. */
					printf( esc_html__( 'Last tick: post %1$d, %2$d link(s) inserted.', 'entity-link-engine' ), (int) $last['post_id'], (int) $last['inserted'] );
					?>
					</em></p>
				<?php endif; ?>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ele_bulk_start" />
					<?php wp_nonce_field( 'ele_bulk_start' ); ?>
					<?php submit_button( __( 'Start bulk run', 'entity-link-engine' ), 'primary', 'submit', false ); ?>
				</form>
				<p class="description"><?php esc_html_e( 'Processes posts in batches via WP-Cron (5 per tick). Snapshots are kept per post, so each run can be undone in the post editor.', 'entity-link-engine' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle add entity.
	 */
	public function handle_add_entity() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'ele_add_entity' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'entity-link-engine' ) );
		}
		$label   = isset( $_POST['entity_label'] ) ? sanitize_text_field( wp_unslash( $_POST['entity_label'] ) ) : '';
		$aliases = isset( $_POST['aliases'] ) ? sanitize_text_field( wp_unslash( $_POST['aliases'] ) ) : '';
		$target  = isset( $_POST['target_post_id'] ) ? (int) $_POST['target_post_id'] : 0;
		$prio    = isset( $_POST['priority'] ) ? max( 1, min( 1000, (int) $_POST['priority'] ) ) : 100;

		if ( '' === $label || ! $target ) {
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=ele-vocabulary' ) );
			exit;
		}

		$entities = get_option( 'ele_entities_manual', array() );
		if ( ! is_array( $entities ) ) {
			$entities = array();
		}
		$alias_list = array_values( array_filter( array_map( 'trim', explode( ',', $aliases ) ) ) );
		$entities[] = array(
			'id'             => uniqid( 'ele_' ),
			'entity_label'   => $label,
			'aliases'        => $alias_list,
			'target_post_id' => $target,
			'priority'       => $prio,
		);
		update_option( 'ele_entities_manual', $entities );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=ele-vocabulary' ) );
		exit;
	}

	/**
	 * Handle delete entity.
	 */
	public function handle_delete_entity() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'ele_delete_entity' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'entity-link-engine' ) );
		}
		$id       = isset( $_POST['entity_id'] ) ? sanitize_text_field( wp_unslash( $_POST['entity_id'] ) ) : '';
		$entities = get_option( 'ele_entities_manual', array() );
		if ( is_array( $entities ) ) {
			$entities = array_values(
				array_filter(
					$entities,
					function ( $e ) use ( $id ) {
						return ( $e['id'] ?? '' ) !== $id;
					}
				)
			);
			update_option( 'ele_entities_manual', $entities );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=ele-vocabulary' ) );
		exit;
	}

	/**
	 * Handle rebuild.
	 */
	public function handle_rebuild() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'ele_rebuild' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'entity-link-engine' ) );
		}
		$map = new ELE_Entity_Map();
		$map->rebuild();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=ele-bulk' ) );
		exit;
	}

	/**
	 * Handle bulk start.
	 */
	public function handle_bulk_start() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'ele_bulk_start' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'entity-link-engine' ) );
		}
		$engine = new ELE_Engine();
		$engine->start_bulk();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=ele-bulk' ) );
		exit;
	}
}
