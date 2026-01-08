<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Graffiti_Post_Type {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type() {
		$labels = array(
			'name'               => 'Graffiti',
			'singular_name'      => 'Graffiti',
			'menu_name'          => 'Graffiti',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Graffiti',
			'edit_item'          => 'View Graffiti',
			'view_item'          => 'View Graffiti',
			'all_items'          => 'All Graffiti',
			'search_items'       => 'Search Graffiti',
			'not_found'          => 'No graffiti found.',
			'not_found_in_trash' => 'No graffiti found in Trash.',
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-art',
			'capability_type' => 'post',
			'supports'        => array( 'thumbnail' ),
			'has_archive'     => false,
			'rewrite'         => false,
			'show_in_rest'    => false,
		);

		register_post_type( 'graffiti', $args );
	}
}
