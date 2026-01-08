<?php
/**
 * Plugin Name: Graffiti
 * Description: Let visitors leave drawings between paragraphs of your content.
 * Version: 1.0.0
 * Author: Brandon
 * License: GPL v2 or later
 * Text Domain: graffiti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GRAFFITI_VERSION', '1.0.0' );
define( 'GRAFFITI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRAFFITI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-post-type.php';
require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-rest-api.php';
require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-renderer.php';
require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-admin.php';

function graffiti_init() {
	new Graffiti_Post_Type();
	new Graffiti_REST_API();
	new Graffiti_Renderer();

	if ( is_admin() ) {
		new Graffiti_Admin();
	}
}
add_action( 'plugins_loaded', 'graffiti_init' );

function graffiti_enqueue_assets() {
	if ( ! is_singular( array( 'post', 'page' ) ) ) {
		return;
	}

	wp_enqueue_style(
		'graffiti-style',
		GRAFFITI_PLUGIN_URL . 'assets/css/graffiti.css',
		array(),
		GRAFFITI_VERSION
	);

	wp_enqueue_script(
		'graffiti-script',
		GRAFFITI_PLUGIN_URL . 'assets/js/graffiti.js',
		array(),
		GRAFFITI_VERSION,
		true
	);

	wp_localize_script( 'graffiti-script', 'graffitiData', array(
		'restUrl' => esc_url_raw( rest_url() ),
		'postId'  => get_the_ID(),
	) );
}
add_action( 'wp_enqueue_scripts', 'graffiti_enqueue_assets' );
