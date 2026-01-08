<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Graffiti_REST_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'graffiti/v1', '/drawings', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_drawing' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'paragraph_index' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'image_data' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
	}

	public function create_drawing( $request ) {
		$post_id         = $request->get_param( 'post_id' );
		$paragraph_index = $request->get_param( 'paragraph_index' );
		$image_data      = $request->get_param( 'image_data' );

		// Verify parent post exists
		$parent_post = get_post( $post_id );
		if ( ! $parent_post || ! in_array( $parent_post->post_type, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'invalid_post', 'Invalid parent post.', array( 'status' => 400 ) );
		}

		// Decode base64 image
		if ( strpos( $image_data, 'data:image/png;base64,' ) !== 0 ) {
			return new WP_Error( 'invalid_image', 'Invalid image data.', array( 'status' => 400 ) );
		}

		$image_data = str_replace( 'data:image/png;base64,', '', $image_data );
		$image_data = base64_decode( $image_data );

		if ( false === $image_data ) {
			return new WP_Error( 'decode_failed', 'Failed to decode image.', array( 'status' => 400 ) );
		}

		// Create graffiti post
		$graffiti_id = wp_insert_post( array(
			'post_type'   => 'graffiti',
			'post_status' => 'publish',
			'post_title'  => 'Graffiti on ' . $parent_post->post_title,
		) );

		if ( is_wp_error( $graffiti_id ) ) {
			return new WP_Error( 'create_failed', 'Failed to create graffiti.', array( 'status' => 500 ) );
		}

		// Save meta
		update_post_meta( $graffiti_id, '_graffiti_post_id', $post_id );
		update_post_meta( $graffiti_id, '_graffiti_paragraph_index', $paragraph_index );
		update_post_meta( $graffiti_id, '_graffiti_ip_address', $this->get_client_ip() );

		// Upload image to media library
		$upload = wp_upload_bits( 'graffiti-' . $graffiti_id . '.png', null, $image_data );

		if ( $upload['error'] ) {
			wp_delete_post( $graffiti_id, true );
			return new WP_Error( 'upload_failed', $upload['error'], array( 'status' => 500 ) );
		}

		// Create attachment
		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Graffiti ' . $graffiti_id,
			'post_status'    => 'inherit',
		), $upload['file'], $graffiti_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_post( $graffiti_id, true );
			return new WP_Error( 'attachment_failed', 'Failed to create attachment.', array( 'status' => 500 ) );
		}

		// Generate attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		// Set as featured image
		set_post_thumbnail( $graffiti_id, $attachment_id );

		return array(
			'success'     => true,
			'graffiti_id' => $graffiti_id,
			'image_url'   => wp_get_attachment_url( $attachment_id ),
		);
	}

	private function get_client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}
}
