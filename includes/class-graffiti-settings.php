<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Graffiti_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=graffiti',
            'Graffiti Settings',
            'Settings',
            'manage_options',
            'graffiti-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'graffiti_settings', 'graffiti_enabled', array(
            'type'              => 'boolean',
            'default'           => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ) );

        add_settings_section(
            'graffiti_general',
            'General Settings',
            null,
            'graffiti-settings'
        );

        add_settings_field(
            'graffiti_enabled',
            'Enable Graffiti',
            array( $this, 'render_enabled_field' ),
            'graffiti-settings',
            'graffiti_general'
        );
    }

    public function render_enabled_field() {
        $enabled = get_option( 'graffiti_enabled', true );
        ?>
        <label>
            <input type="checkbox" name="graffiti_enabled" value="1" <?php checked( $enabled ); ?> />
            Allow visitors to leave graffiti on posts and pages
        </label>
        <?php
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Graffiti Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'graffiti_settings' );
                do_settings_sections( 'graffiti-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
