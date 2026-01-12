<?php
/**
 * REST API functionality for Anybody Editing.
 */

defined( 'ABSPATH' ) || exit;

class Anybody_Editing_REST_API {

	const NAMESPACE = 'anybody-editing/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_post' ),
				'permission_callback' => array( $this, 'check_post_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upload_image' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
			)
		);
	}

	/**
	 * Check if the user has permission to edit the post.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_post_permission( $request ) {
		$post_id = $request->get_param( 'id' );
		return $this->check_post_permission_by_id( $post_id );
	}

	/**
	 * Check if the user has permission to upload images for a post.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_upload_permission( $request ) {
		$post_id = $request->get_param( 'post_id' );

		if ( empty( $post_id ) ) {
			return new WP_Error(
				'missing_post_id',
				__( 'The post_id parameter is required.', 'anybody-editing' ),
				array( 'status' => 400 )
			);
		}

		return $this->check_post_permission_by_id( $post_id );
	}

	/**
	 * Check if a post has public editing enabled.
	 *
	 * @param int $post_id The post ID.
	 * @return bool|WP_Error
	 */
	private function check_post_permission_by_id( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				__( 'Post not found.', 'anybody-editing' ),
				array( 'status' => 404 )
			);
		}

		if ( $post->post_status !== 'publish' ) {
			return new WP_Error(
				'post_not_published',
				__( 'Post is not published.', 'anybody-editing' ),
				array( 'status' => 403 )
			);
		}

		if ( ! Anybody_Editing_Admin::is_editing_enabled( $post_id ) ) {
			return new WP_Error(
				'editing_not_enabled',
				__( 'Public editing is not enabled for this post.', 'anybody-editing' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Update a post.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_post( $request ) {
		$post_id = $request->get_param( 'id' );
		$params  = $request->get_json_params();

		$post_data = array(
			'ID' => $post_id,
		);

		// Handle title
		if ( isset( $params['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $params['title'] );
		}

		// Handle content
		if ( isset( $params['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $params['content'] );
		}

		// Handle excerpt
		if ( isset( $params['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $params['excerpt'] );
		}

		// Update the post
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Handle featured media
		if ( isset( $params['featured_media'] ) ) {
			$featured_media = absint( $params['featured_media'] );
			if ( $featured_media > 0 ) {
				set_post_thumbnail( $post_id, $featured_media );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}

		// Handle categories
		if ( isset( $params['categories'] ) && is_array( $params['categories'] ) ) {
			$categories = array_map( 'absint', $params['categories'] );
			wp_set_post_categories( $post_id, $categories );
		}

		// Handle tags
		if ( isset( $params['tags'] ) && is_array( $params['tags'] ) ) {
			$tags = array_map( 'sanitize_text_field', $params['tags'] );
			wp_set_post_tags( $post_id, $tags );
		}

		$post = get_post( $post_id );

		return rest_ensure_response(
			array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'content'        => $post->post_content,
				'excerpt'        => $post->post_excerpt,
				'featured_media' => get_post_thumbnail_id( $post_id ),
				'categories'     => wp_get_post_categories( $post_id ),
				'tags'           => wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ),
			)
		);
	}

	/**
	 * Upload an image.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_image( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$files   = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error(
				'no_file',
				__( 'No file was uploaded.', 'anybody-editing' ),
				array( 'status' => 400 )
			);
		}

		$file = $files['file'];

		// Check for upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error(
				'upload_error',
				__( 'File upload failed.', 'anybody-editing' ),
				array( 'status' => 400 )
			);
		}

		// Validate file type
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$file_type     = wp_check_filetype( $file['name'] );
		$mime_type     = $file['type'];

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return new WP_Error(
				'invalid_file_type',
				__( 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.', 'anybody-editing' ),
				array( 'status' => 400 )
			);
		}

		// Validate file size (max 2MB)
		$max_size = 2 * 1024 * 1024; // 2MB
		if ( $file['size'] > $max_size ) {
			return new WP_Error(
				'file_too_large',
				__( 'File is too large. Maximum size is 2MB.', 'anybody-editing' ),
				array( 'status' => 400 )
			);
		}

		// Require WordPress media handling functions
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Handle the upload
		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'gif'          => 'image/gif',
					'webp'         => 'image/webp',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_Error(
				'upload_failed',
				$upload['error'],
				array( 'status' => 400 )
			);
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate attachment metadata
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		// Add tracking meta
		update_post_meta( $attachment_id, '_anybody_editing_upload', '1' );
		update_post_meta( $attachment_id, '_anybody_editing_source_post', $post_id );
		update_post_meta( $attachment_id, '_anybody_editing_upload_ip', $this->get_client_ip() );

		return rest_ensure_response(
			array(
				'id'  => $attachment_id,
				'url' => wp_get_attachment_url( $attachment_id ),
			)
		);
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Handle comma-separated list of IPs (first is the client)
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}
