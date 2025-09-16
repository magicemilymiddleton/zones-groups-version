<?php


namespace LLMSGAA\Feature\Shortcodes;

// Exit if accessed directly to protect from direct URL access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use LLMSGAA\Common\Utils;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🔗 Register POST handlers early
add_action( 'admin_post_llmsgaa_new_org', [ \LLMSGAA\Feature\Shortcodes\Controller::class, 'handle_new_org' ] );
add_action( 'admin_post_nopriv_llmsgaa_new_org', [ \LLMSGAA\Feature\Shortcodes\Controller::class, 'handle_new_org' ] );

class Controller {

    public static function init() {
        add_shortcode( 'llmsgaa_new_org_form', [ __CLASS__, 'render_new_org_form' ] );
        add_action( 'admin_post_llmsgaa_redeem_pass', [ __CLASS__, 'handle_redeem_pass' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function enqueue_scripts() {
        wp_enqueue_script(
            'llmsgaa-cart-repeater',
            plugins_url( 'public/js/cart-repeater.js', LLMSGAA_PLUGIN_FILE ),
            [ 'jquery' ],
            '1.0',
            true
        );
    }

    public static function render_new_org_form() {
        ob_start();
        include LLMSGAA_DIR . 'views/frontend/new-org-form.php';
        return ob_get_clean();
    }

    public static function handle_new_org() {
        if (
            ! isset( $_POST['llmsgaa_new_org_nonce'] ) ||
            ! wp_verify_nonce( $_POST['llmsgaa_new_org_nonce'], 'llmsgaa_new_org' )
        ) {
            wp_die( __( 'Invalid nonce.', 'llms-groups-access-addon' ) );
        }

        $data = $_POST['new_org'] ?? [];
        $email = sanitize_email( $data['email'] ?? null );
        $name  = sanitize_text_field( $data['name'] ?? null );
        $org   = sanitize_text_field( $data['org'] ?? null );
        $items = $data['items'] ?? [];

        if ( ! $email || ! $name || ! $org || empty( $items ) || ! is_array( $items ) ) {
            wp_die( __( 'Missing required fields.', 'llms-groups-access-addon' ) );
        }

        $org_slug = sanitize_title( $org );

        $group_id = wp_insert_post([
            'post_type'   => 'llms_group',
            'post_title'  => $org,
            'post_name'   => $org_slug,
            'post_status' => 'publish',
        ]);

        if ( is_wp_error( $group_id ) ) {
            wp_die( __( 'Failed to create group.', 'llms-groups-access-addon' ) );
        }

        update_post_meta( $group_id, 'primary_admin', get_current_user_id() );
        update_post_meta( $group_id, '_llms_visibility', 'private' );

        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            $user_id = wp_insert_user([
                'user_login'    => sanitize_user( current( explode( '@', $email ) ) ),
                'user_email'    => $email,
                'user_pass'     => wp_generate_password(),
                'display_name'  => $name,
            ]);

            if ( ! is_wp_error( $user_id ) ) {
                wp_new_user_notification( $user_id, null, 'user' );
            }

        } else {
            $user_id = $user->ID;
        }

        if ( is_wp_error( $user_id ) ) {
            wp_die( __( 'Failed to create or retrieve user.', 'llms-groups-access-addon' ) );
        }

        global $wpdb;
        $wpdb->replace(
            $wpdb->prefix . 'lifterlms_user_postmeta',
            [
                'user_id'    => $user_id,
                'post_id'    => $group_id,
                'meta_key'   => '_group_role',
                'meta_value' => 'admin',
            ],
            [ '%d', '%d', '%s', '%s' ]
        );

        $wpdb->replace(
            $wpdb->prefix . 'lifterlms_user_postmeta',
            [
               'user_id'    => $user_id,
               'post_id'    => $group_id,
               'meta_key'   => '_is_group_member',
               'meta_value' => 'yes',
            ],
            [ '%d', '%d', '%s', '%s' ]
        );

        $wpdb->replace(
            $wpdb->prefix . 'lifterlms_user_postmeta',
            [
               'user_id'    => $user_id,
               'post_id'    => $group_id,
               'meta_key'   => '_status',
               'meta_value' => 'enrolled',
            ],
            [ '%d', '%d', '%s', '%s' ]
        );

        $pass_items = [];

        foreach ( $items as $item ) {
            $sku = sanitize_text_field( $item['sku'] ?? '' );
            $qty = absint( $item['quantity'] ?? 1 );

            if ( ! $sku || $qty < 1 ) {
                continue;
            }

            $pass_items[] = [
                'sku'      => $sku,
                'quantity' => $qty,
            ];
        }

        if ( empty( $pass_items ) ) {
            wp_die( __( 'No valid pass items provided.', 'llms-groups-access-addon' ) );
        }

        wp_insert_post([
            'post_type'   => 'llms_access_pass',
            'post_title'  => "Your Order DCXXXX $org",
            'post_status' => 'publish',
            'meta_input'  => [
                'group_id'           => $group_id,
                'buyer_id'           => $email,
                'llmsgaa_pass_items' => wp_json_encode( $pass_items ),
            ],
        ]);

        wp_safe_redirect( add_query_arg( 'new_org_status', 'success', wp_get_referer() ) );
        exit;
    }

public static function handle_redeem_pass() {
    if (
        empty( $_POST['llmsgaa_redeem_pass_nonce'] ) ||
        ! wp_verify_nonce( $_POST['llmsgaa_redeem_pass_nonce'], 'llmsgaa_redeem_pass_action' )
    ) {
        wp_die( 'Invalid nonce', 403 );
    }

    $pass_id    = absint( $_POST['pass_id'] ?? 0 );
    $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );

    if ( ! $pass_id || ! $start_date ) {
        wp_die( 'Missing required data.', 400 );
    }

    $pass = get_post( $pass_id );
    if ( ! $pass || 'llms_access_pass' !== $pass->post_type ) {
        wp_die( 'Invalid License.', 404 );
    }

    $items = get_post_meta( $pass_id, 'llmsgaa_pass_items', true );
    if ( is_string( $items ) ) {
        $items = json_decode( $items, true );
    }

    if ( empty( $items ) || ! is_array( $items ) ) {
        wp_die( 'No pass items to redeem.', 400 );
    }

    $group_id = get_post_meta( $pass_id, 'group_id', true );
    
    // Extract pass identifier from title (e.g., DC1234)
    $pass_identifier = '';
    if ( preg_match( '/\b(DC\d+)\b/i', $pass->post_title, $matches ) ) {
        $pass_identifier = $matches[1];
    } else {
        // Fallback: use pass ID if no DC code found
        $pass_identifier = 'Pass-' . $pass_id;
    }

    foreach ( $items as $item ) {
        $sku = sanitize_text_field( $item['sku'] ?? '' );
        $qty = absint( $item['quantity'] ?? 0 );

        $product_id = \LLMSGAA\Common\Utils::sku_to_product_id( $sku );

        if ( ! $product_id ) {
            continue; // Skip if product ID couldn't be resolved
        }
        
        // Get the product title for better naming
        $product_title = get_the_title( $product_id );
        if ( empty( $product_title ) ) {
            $product_title = $sku; // Fallback to SKU if title not found
        }

        for ( $i = 0; $i < $qty; $i++ ) {
            // Create descriptive title with pass identifier and product name
            $order_title = sprintf( '%s for %s', $product_title, $pass_identifier );
            
            $order_id = wp_insert_post([
                'post_type'   => 'llms_group_order',
                'post_status' => 'publish',
                'post_title'  => $order_title,
                'meta_input'  => [
                    'group_id'   => $group_id,
                    'product_id' => $product_id,
                    'start_date' => $start_date,
                    'status'     => 'active',
                    'source_pass_id' => $pass_id, // Track which pass created this order
                    'source_pass_identifier' => $pass_identifier, // Store the identifier
                ],
            ]);
        }
    }

    update_post_meta( $pass_id, 'llmsgaa_redeemed', '1' );

    wp_safe_redirect( add_query_arg( 'redeemed', '1', get_permalink( $group_id ) ) );
    exit;
}
}


// Boot this controller
Controller::init();
