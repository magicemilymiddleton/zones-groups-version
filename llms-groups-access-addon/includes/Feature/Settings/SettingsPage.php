<?php
namespace LLMSGAA\Feature\Settings;

use LLMSGAA\Common\Utils;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

    /**
     * Attach hooks for settings page initialization.
     */
    public static function init_hooks() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_item' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    /**
     * Register plugin settings with WordPress.
     */
    public static function register_settings() {
        register_setting(
            'llmsgaa-settings',
            'llmsgaa_sku_map',
            [
                'type'              => 'array',
                'sanitize_callback' => [ Utils::class, 'sanitize_sku_map' ],
                'default'           => []
            ]
        );

        register_setting(
            'llmsgaa-settings',
            'llmsgaa_sku_list',
            [
                'type'              => 'string',
                'sanitize_callback' => [ Utils::class, 'sanitize_comma_list' ],
                'default'           => ''
            ]
        );
    }

    /**
     * Adds the settings page to the WordPress admin menu.
     */
    public static function add_menu_item() {
        add_submenu_page(
            'edit.php?post_type=llms_group',
            __( 'SKU ↔ Course Map', 'llms-groups-access-addon' ),
            __( 'SKU Map', 'llms-groups-access-addon' ),
            'manage_options',
            'llmsgaa-sku-map',
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Render the settings page.
     * Uses an external PHP file as a view template.
     */
    public static function render_page() {
        // Grab stored options
        $sku_list = get_option( 'llmsgaa_sku_list', '' );
        $sku_map  = get_option( 'llmsgaa_sku_map', [] );

        // Get post list to map to SKUs
        $posts = get_posts([
            'post_type'      => [ 'course', 'llms_membership' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        // Load view template
        include LLMSGAA_DIR . 'views/settings/sku-map.php';
    }
}
