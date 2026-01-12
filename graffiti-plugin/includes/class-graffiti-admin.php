<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Graffiti_Admin {

    public function __construct() {
        add_filter( 'manage_graffiti_posts_columns', array( $this, 'set_columns' ) );
        add_action( 'manage_graffiti_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-graffiti_sortable_columns', array( $this, 'sortable_columns' ) );
    }

    public function set_columns( $columns ) {
        $new_columns = array(
            'cb'              => $columns['cb'],
            'thumbnail'       => 'Preview',
            'parent_post'     => 'Parent Post',
            'paragraph'       => 'Paragraph',
            'ip_address'      => 'IP Address',
            'date'            => 'Date',
        );
        return $new_columns;
    }

    public function render_column( $column, $post_id ) {
        switch ( $column ) {
            case 'thumbnail':
                $image_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
                if ( $image_url ) {
                    echo '<img src="' . esc_url( $image_url ) . '" style="max-width: 100px; height: auto;" />';
                } else {
                    echo '—';
                }
                break;

            case 'parent_post':
                $parent_id = get_post_meta( $post_id, '_graffiti_post_id', true );
                if ( $parent_id ) {
                    $parent = get_post( $parent_id );
                    if ( $parent ) {
                        echo '<a href="' . esc_url( get_edit_post_link( $parent_id ) ) . '">' . esc_html( $parent->post_title ) . '</a>';
                    } else {
                        echo 'Deleted post';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'paragraph':
                $index = get_post_meta( $post_id, '_graffiti_paragraph_index', true );
                echo esc_html( $index !== '' ? $index : '—' );
                break;

            case 'ip_address':
                $ip = get_post_meta( $post_id, '_graffiti_ip_address', true );
                echo esc_html( $ip ? $ip : '—' );
                break;
        }
    }

    public function sortable_columns( $columns ) {
        $columns['date'] = 'date';
        return $columns;
    }
}
