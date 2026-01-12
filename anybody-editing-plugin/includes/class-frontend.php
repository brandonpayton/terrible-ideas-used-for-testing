<?php
/**
 * Frontend functionality for Anybody Editing.
 */

defined( 'ABSPATH' ) || exit;

class Anybody_Editing_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_scripts' ) );
		add_filter( 'the_content', array( $this, 'wrap_blocks' ), 20 );
	}

	/**
	 * Check if we should load editor scripts on current page.
	 *
	 * @return bool
	 */
	private function should_load_editor() {
		if ( ! is_singular( 'post' ) ) {
			return false;
		}

		return Anybody_Editing_Admin::is_editing_enabled( get_the_ID() );
	}

	/**
	 * Enqueue frontend scripts and styles if editing is enabled.
	 */
	public function maybe_enqueue_scripts() {
		if ( ! $this->should_load_editor() ) {
			return;
		}

		$asset_file = ANYBODY_EDITING_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'anybody-editing-frontend',
			ANYBODY_EDITING_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'anybody-editing-frontend',
			ANYBODY_EDITING_PLUGIN_URL . 'build/index.css',
			array( 'wp-components', 'wp-block-editor' ),
			$asset['version']
		);

		// Pass data to JavaScript
		wp_localize_script(
			'anybody-editing-frontend',
			'anybodyEditingData',
			array(
				'postId'    => get_the_ID(),
				'restUrl'   => rest_url( 'anybody-editing/v1' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'post'      => array(
					'title'          => get_the_title(),
					'content'        => get_post_field( 'post_content', get_the_ID() ),
					'excerpt'        => get_the_excerpt(),
					'featured_media' => get_post_thumbnail_id(),
				),
			)
		);
	}

	/**
	 * Wrap each block with a container for edit buttons.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function wrap_blocks( $content ) {
		if ( ! $this->should_load_editor() ) {
			return $content;
		}

		// Parse blocks from content
		$blocks = parse_blocks( get_post_field( 'post_content', get_the_ID() ) );

		if ( empty( $blocks ) ) {
			return $content;
		}

		$output     = '';
		$block_index = 0;

		foreach ( $blocks as $block ) {
			// Skip empty blocks (usually spacing between blocks)
			if ( empty( $block['blockName'] ) ) {
				$output .= render_block( $block );
				continue;
			}

			$rendered = render_block( $block );

			// Wrap block with editing container
			$output .= sprintf(
				'<div class="anybody-editing-block" data-block-index="%d" data-block-name="%s">%s</div>',
				intval( $block_index ),
				esc_attr( $block['blockName'] ),
				$rendered
			);

			$block_index++;
		}

		return $output;
	}
}
