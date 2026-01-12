<?php
/**
 * Plugin Name: Anybody Editing
 * Description: Allow public visitors to edit WordPress posts
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: anybody-editing
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'ANYBODY_EDITING_VERSION', '1.0.0' );
define( 'ANYBODY_EDITING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ANYBODY_EDITING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes
require_once ANYBODY_EDITING_PLUGIN_DIR . 'includes/class-admin.php';
require_once ANYBODY_EDITING_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once ANYBODY_EDITING_PLUGIN_DIR . 'includes/class-frontend.php';

/**
 * Initialize the plugin.
 */
function anybody_editing_init() {
	new Anybody_Editing_Admin();
	new Anybody_Editing_REST_API();
	new Anybody_Editing_Frontend();
}
add_action( 'plugins_loaded', 'anybody_editing_init' );
