<?php

namespace LLMSGAA\Feature\PassAssignment;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Controller for rendering user's Licenses on the frontend.
 */
class Controller {

    /**
     * Hook to display Licenses on group profile page.
     */
    public static function init_hooks() {
        add_action( 'llms_group_profile_main_passes', [ __CLASS__, 'render_pass_table' ] );
    }

    /**
     * Prepares data and renders the user's Licenses.
     */
    public static function render_pass_table() {
        if ( ! is_singular( 'llms_group' ) || ! is_user_logged_in() ) return;

        $user = wp_get_current_user();
        $passes = get_posts([
            'post_type'      => 'llms_access_pass',
            'posts_per_page' => -1,
            'meta_query'     => [
                [ 'key' => 'buyer_id', 'value' => $user->user_email ],
            ],
        ]);

        $sku_map = [];
        $raw_map = get_option( 'llmsgaa_sku_map', [] );
        if ( is_array( $raw_map ) ) {
            foreach ( $raw_map as $sku => $post_id ) {
                $sku_map[ (string) $sku ] = get_the_title( absint( $post_id ) );
            }
        }

        include LLMSGAA_DIR . 'views/feature/pass-assignment/passes.php';
    }
}

Controller::init_hooks();
