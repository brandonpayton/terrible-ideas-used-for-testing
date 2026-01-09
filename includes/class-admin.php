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
}
