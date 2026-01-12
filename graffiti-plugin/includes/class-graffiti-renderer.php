<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Graffiti_Renderer {

    public function __construct() {
        add_filter( 'the_content', array( $this, 'inject_graffiti' ), 20 );
    }

    public function inject_graffiti( $content ) {
        if ( ! is_singular( array( 'post', 'page' ) ) ) {
            return $content;
        }

        if ( ! get_option( 'graffiti_enabled', true ) ) {
            return $content;
        }

        $post_id = get_the_ID();

        // Query graffiti for this post
        $graffiti_posts = get_posts( array(
            'post_type'      => 'graffiti',
            'posts_per_page' => -1,
            'meta_key'       => '_graffiti_post_id',
            'meta_value'     => $post_id,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ) );

        if ( empty( $graffiti_posts ) ) {
            return $content;
        }

        // Group graffiti by paragraph index
        $graffiti_by_paragraph = array();
        foreach ( $graffiti_posts as $graffiti ) {
            $paragraph_index = get_post_meta( $graffiti->ID, '_graffiti_paragraph_index', true );
            if ( ! isset( $graffiti_by_paragraph[ $paragraph_index ] ) ) {
                $graffiti_by_paragraph[ $paragraph_index ] = array();
            }
            $graffiti_by_paragraph[ $paragraph_index ][] = $graffiti;
        }

        // Split content by block elements
        $pattern = '/(<(?:p|h[1-6]|ul|ol|blockquote|figure)[^>]*>.*?<\/(?:p|h[1-6]|ul|ol|blockquote|figure)>)/is';
        $blocks = preg_split( $pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

        $output = '';
        $block_index = 0;

        foreach ( $blocks as $block ) {
            // Check if this is a block element
            if ( preg_match( '/^<(?:p|h[1-6]|ul|ol|blockquote|figure)/i', trim( $block ) ) ) {
                $block_index++;

                // Insert graffiti before this block if any exist
                if ( isset( $graffiti_by_paragraph[ $block_index ] ) ) {
                    $output .= $this->render_graffiti_cluster( $graffiti_by_paragraph[ $block_index ], $block_index );
                }
            }

            $output .= $block;
        }

        // Insert graffiti after last block
        $last_index = $block_index + 1;
        if ( isset( $graffiti_by_paragraph[ $last_index ] ) ) {
            $output .= $this->render_graffiti_cluster( $graffiti_by_paragraph[ $last_index ], $last_index );
        }

        return $output;
    }

    private function render_graffiti_cluster( $graffiti_posts, $paragraph_index ) {
        $html = '<div class="graffiti-cluster" data-paragraph="' . esc_attr( $paragraph_index ) . '">';

        foreach ( $graffiti_posts as $graffiti ) {
            $image_url = get_the_post_thumbnail_url( $graffiti->ID, 'full' );
            if ( $image_url ) {
                $html .= '<div class="graffiti-item">';
                $html .= '<img src="' . esc_url( $image_url ) . '" alt="Visitor graffiti" loading="lazy" />';
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        return $html;
    }
}
