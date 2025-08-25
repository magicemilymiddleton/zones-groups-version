<?php

namespace LLMSGAA;

// Exit if accessed directly to protect from direct URL access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core class of the plugin.
 *
 * It is responsible for:
 *  - Registering custom post types (Licenses & Group Orders)
 *  - Adding URL rewrite rules for consent handling
 *  - Hooking into plugin activation & deactivation lifecycle
 */
class Core {

    /**
     * Called once when the plugin is activated.
     *
     * - Adds rewrite rules (so /group-consent/123/ works)
     * - Flushes WordPress rewrites so the rules take effect immediately
     */
    public static function activate() {
        self::register_rewrites();
        flush_rewrite_rules();
    }

    /**
     * Called once when the plugin is deactivated.
     *
     * - Just flushes rewrite rules to clean up routes
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Called on every page load on `init` action.
     *
     * - Registers CPTs (custom post types)
     * - Adds URL rewrite rules
     */
    public static function init() {
        self::register_rewrites();
        self::register_post_types();
    }

    /**
     * Registers custom post types for the plugin:
     *
     * - `llms_access_pass`: for temporary access permissions
     * - `llms_group_order`: for group order tracking
     */
    private static function register_post_types() {
        // License CPT
        register_post_type( 'llms_access_pass', [
            'labels' => [
                'name'          => __( 'Licenses', 'llms-groups-access-addon' ),
                'singular_name' => __( 'License', 'llms-groups-access-addon' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type=llms_group',
            'menu_icon'    => 'dashicons-tickets-alt',
            'supports'     => [ 'title' ],
        ]);

        // Group Order CPT
        register_post_type( 'llms_group_order', [
            'labels' => [
                'name'          => __( 'Group Orders', 'llms-groups-access-addon' ),
                'singular_name' => __( 'Group Order', 'llms-groups-access-addon' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type=llms_group',
            'menu_icon'    => 'dashicons-list-view',
            'supports'     => [ 'title' ],
        ]);
    }

    /**
     * Adds custom rewrite rules and tags to handle URLs like /group-consent/123/
     *
     * - Adds a rewrite tag `%llmsgaa_consent%`
     * - Adds a rewrite rule mapping it to `index.php?llmsgaa_consent=123`
     */
    private static function register_rewrites() {
        add_rewrite_tag( '%llmsgaa_consent%', '([0-9]+)' );
        add_rewrite_rule(
            '^group-consent/([0-9]+)/?$',
            'index.php?llmsgaa_consent=$matches[1]',
            'top'
        );
    }

    /**
     * Called from PluginRegistrar to register WordPress hooks.
     */
    public static function init_hooks() {
        add_action( 'init', [ __CLASS__, 'init' ] );
    }
}
