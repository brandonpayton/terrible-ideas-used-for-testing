<?php
/**
 * Admin functionality for Anybody Editing.
 */

defined( 'ABSPATH' ) || exit;

class Anybody_Editing_Admin {

	const META_KEY = '_anybody_editing_enabled';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_library' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_media_filter_dropdown' ) );
		add_filter( 'parse_query', array( $this, 'filter_media_query' ) );
	}

	/**
	 * Register the meta box.
	 */
	public function add_meta_box() {
		add_meta_box(
			'anybody-editing-meta-box',
			__( 'Public Editing', 'anybody-editing' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_meta_box( $post ) {
		$enabled = get_post_meta( $post->ID, self::META_KEY, true );
		wp_nonce_field( 'anybody_editing_meta_box', 'anybody_editing_nonce' );
		?>
		<label>
			<input type="checkbox" name="anybody_editing_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
			<?php esc_html_e( 'Allow anyone to edit this post', 'anybody-editing' ); ?>
		</label>
		<?php if ( $enabled ) : ?>
			<p class="description" style="margin-top: 8px;">
				<?php esc_html_e( 'Visitors can edit this post without logging in.', 'anybody-editing' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save the meta box value.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public function save_meta_box( $post_id, $post ) {
		// Verify nonce
		if ( ! isset( $_POST['anybody_editing_nonce'] ) ||
			 ! wp_verify_nonce( $_POST['anybody_editing_nonce'], 'anybody_editing_meta_box' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save or delete meta
		if ( isset( $_POST['anybody_editing_enabled'] ) && $_POST['anybody_editing_enabled'] === '1' ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Check if a post has public editing enabled.
	 *
	 * @param int $post_id The post ID.
	 * @return bool
	 */
	public static function is_editing_enabled( $post_id ) {
		return get_post_meta( $post_id, self::META_KEY, true ) === '1';
	}

	/**
	 * Add settings page to admin menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Anybody Editing', 'anybody-editing' ),
			__( 'Anybody Editing', 'anybody-editing' ),
			'manage_options',
			'anybody-editing',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'anybody_editing_settings', 'anybody_editing_max_upload_size' );
		register_setting( 'anybody_editing_settings', 'anybody_editing_allowed_types' );
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$max_size = get_option( 'anybody_editing_max_upload_size', 2 );
		$allowed_types = get_option( 'anybody_editing_allowed_types', array( 'jpg', 'png', 'gif', 'webp' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Anybody Editing Settings', 'anybody-editing' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'anybody_editing_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="max_upload_size"><?php esc_html_e( 'Max Upload Size (MB)', 'anybody-editing' ); ?></label>
						</th>
						<td>
							<input type="number" id="max_upload_size" name="anybody_editing_max_upload_size" value="<?php echo esc_attr( $max_size ); ?>" min="1" max="10">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allowed Image Types', 'anybody-editing' ); ?></th>
						<td>
							<?php foreach ( array( 'jpg', 'png', 'gif', 'webp' ) as $type ) : ?>
								<label>
									<input type="checkbox" name="anybody_editing_allowed_types[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, (array) $allowed_types, true ) ); ?>>
									<?php echo esc_html( strtoupper( $type ) ); ?>
								</label><br>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add filter dropdown to media library.
	 */
	public function add_media_filter_dropdown() {
		$screen = get_current_screen();
		if ( $screen->id !== 'upload' ) {
			return;
		}

		$selected = isset( $_GET['anybody_editing_filter'] ) ? $_GET['anybody_editing_filter'] : '';
		?>
		<select name="anybody_editing_filter">
			<option value=""><?php esc_html_e( 'All uploads', 'anybody-editing' ); ?></option>
			<option value="visitor" <?php selected( $selected, 'visitor' ); ?>><?php esc_html_e( 'Visitor uploads', 'anybody-editing' ); ?></option>
			<option value="admin" <?php selected( $selected, 'admin' ); ?>><?php esc_html_e( 'Admin uploads', 'anybody-editing' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Filter media query based on dropdown selection.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function filter_media_query( $query ) {
		global $pagenow;

		if ( $pagenow !== 'upload.php' || ! isset( $_GET['anybody_editing_filter'] ) ) {
			return;
		}

		$filter = $_GET['anybody_editing_filter'];

		if ( $filter === 'visitor' ) {
			$query->query_vars['meta_key']   = '_anybody_editing_upload';
			$query->query_vars['meta_value'] = '1';
		} elseif ( $filter === 'admin' ) {
			$query->query_vars['meta_query'] = array(
				array(
					'key'     => '_anybody_editing_upload',
					'compare' => 'NOT EXISTS',
				),
			);
		}
	}

	/**
	 * Filter media library AJAX query.
	 *
	 * @param array $query Query arguments.
	 * @return array
	 */
	public function filter_media_library( $query ) {
		if ( isset( $_REQUEST['query']['anybody_editing_filter'] ) ) {
			$filter = $_REQUEST['query']['anybody_editing_filter'];

			if ( $filter === 'visitor' ) {
				$query['meta_key']   = '_anybody_editing_upload';
				$query['meta_value'] = '1';
			} elseif ( $filter === 'admin' ) {
				$query['meta_query'] = array(
					array(
						'key'     => '_anybody_editing_upload',
						'compare' => 'NOT EXISTS',
					),
				);
			}
		}

		return $query;
	}
}
