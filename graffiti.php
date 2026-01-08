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

function graffiti_init() {
	new Graffiti_Post_Type();
}
add_action( 'plugins_loaded', 'graffiti_init' );
