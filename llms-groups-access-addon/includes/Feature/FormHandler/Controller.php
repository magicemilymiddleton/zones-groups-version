<?php
namespace LLMSGAA\Feature\FormHandler;

use LLMSGAA\Common\Utils;
use LLMSGAA\Feature\Invitation\InviteService; // import for central InviteService
use LLMS_Groups_Enrollment;


// Exit early if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Controller {

    public static function init_hooks() {
        add_action( 'admin_post_llmsgaa_assign_multiple', [ __CLASS__, 'assign_multiple' ] );
        add_action( 'template_redirect',                 [ __CLASS__, 'handle_consent'    ] );
        add_action( 'admin_post_llmsgaa_add_admin',      [ __CLASS__, 'add_admin'         ] );
        add_action( 'admin_post_llmsgaa_remove_admin',   [ __CLASS__, 'remove_admin'      ] );
        add_action( 'wp_ajax_llmsgaa_remove_admin',      [ __CLASS__, 'remove_admin'      ] );
        add_action( 'admin_post_llmsgaa_cancel_invite',  [ __CLASS__, 'cancel_invite'     ] );
        add_action( 'save_post_llms_group_order',        [ __CLASS__, 'auto_set_group_order_end_date' ], 10, 3 );
        add_action( 'admin_post_llmsgaa_cancel_invite', [ __CLASS__, 'cancel_invite' ] );
        add_action( 'admin_post_llmsgaa_save_group_seats',      [ __CLASS__, 'save_group_seats' ] );

    }

    public static function assign_multiple() {
        if (
            ! is_user_logged_in() ||
            empty( $_POST['action'] ) ||
            'llmsgaa_assign_multiple' !== $_POST['action'] ||
            empty( $_POST['llmsgaa_assign_multiple_nonce'] ) ||
            ! wp_verify_nonce( wp_unslash( $_POST['llmsgaa_assign_multiple_nonce'] ), 'llmsgaa_assign_multiple' )
        ) {
            wp_die( __( 'Unauthorized', 'llms-groups-access-addon' ) );
        }

        $group_id = intval( $_POST['group_id'] );
        $pass_id  = intval( $_POST['pass_id'] );

        foreach ( $_POST['assign']['email'] as $i => $email ) {
            $email = sanitize_email( $email );
            $start = sanitize_text_field( $_POST['assign']['start_date'][ $i ] );
            if ( ! is_email( $email ) || empty( $start ) ) {
                continue;
            }
            $order_id = wp_insert_post([
                'post_type'   => 'llms_group_order',
                'post_title'  => sprintf( __( 'Order for %s', 'llms-groups-access-addon' ), $email ),
                'post_status' => 'publish',
            ]);

            update_post_meta( $order_id, 'student_id', null );
            update_post_meta( $order_id, 'group_id',   $group_id );
            update_post_meta( $order_id, 'seat_id',    $pass_id );
            update_post_meta( $order_id, 'start_date', $start );

            $end_timestamp = strtotime( '+1 year', strtotime( $start ) );
            if ( $end_timestamp ) {
                update_post_meta( $order_id, 'end_date', date( 'Y-m-d', $end_timestamp ) );
            }

            update_post_meta( $order_id, 'status', 'pending' );
            update_post_meta( $order_id, 'has_accepted_invite', '0' );

            Utils::update_redeem( $pass_id, 1 );

            $nonce   = wp_create_nonce( "llmsgaa_consent_{$order_id}" );
            $link    = home_url( "/group-consent/{$order_id}/?nonce={$nonce}" );
            $subject = sprintf( __( 'Confirm enrollment in %s', 'llms-groups-access-addon' ), get_the_title( $group_id ) );
            $body    = sprintf( __( 'Please confirm by clicking: %s', 'llms-groups-access-addon' ), esc_url( $link ) );
            wp_mail( $email, $subject, $body );
        }

        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    public static function handle_consent() {
        $order_id = absint( get_query_var( 'llmsgaa_consent' ) );
        $nonce    = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! $order_id || ! wp_verify_nonce( $nonce, "llmsgaa_consent_{$order_id}" ) ) {
            return;
        }

        update_post_meta( $order_id, 'has_accepted_invite', '1' );
        update_post_meta( $order_id, 'status',              'active' );

        get_header();
        echo '<div style="max-width:600px;margin:2em auto;">';
        echo '<h1>' . esc_html__( 'Enrollment Confirmed', 'llms-groups-access-addon' ) . '</h1>';
        echo '<p>' . esc_html__( 'Your access is now active.', 'llms-groups-access-addon' ) . '</p>';
        echo '</div>';
        get_footer();
        exit;
    }


public static function update_order_email() {
    if (
        ! is_user_logged_in() ||
        empty( $_POST['order_id'] ) ||
        empty( $_POST['email'] ) ||
        ! is_email( $_POST['email'] ) ||
        ! wp_verify_nonce( $_POST['nonce'], 'llmsgaa_update_email_nonce' )
    ) {
        wp_send_json_error( __( 'Invalid data or unauthorized.', 'llms-groups-access-addon' ) );
    }

    $order_id = absint( $_POST['order_id'] );
    $email    = sanitize_email( $_POST['email'] );

    // Save assigned email (standardized key)
    update_post_meta( $order_id, 'student_email', $email );

    $user = get_user_by( 'email', $email );

    if ( $user ) {
        // Assign student + activate immediately
        update_post_meta( $order_id, 'student_id', $user->ID );
        update_post_meta( $order_id, 'status', 'active' );
        update_post_meta( $order_id, '_invite_accepted', current_time( 'mysql' ) );
        wp_send_json_success( [
            'message' => __( 'User linked and activated.', 'llms-groups-access-addon' ),
            'email'   => $email,
            'user_id' => $user->ID,
        ] );

        
    }

    // Create invite link
    $nonce   = wp_create_nonce( "llmsgaa_consent_{$order_id}" );
    $link    = home_url( "/group-consent/{$order_id}/?nonce={$nonce}" );

    // Send email invite
    $subject = __( 'You’ve been invited to join a course', 'llms-groups-access-addon' );
    $body    = sprintf( __( 'Click to join: %s', 'llms-groups-access-addon' ), esc_url( $link ) );
    $sent    = wp_mail( $email, $subject, $body );

    // Store meta
    update_post_meta( $order_id, 'student_id', null );
    update_post_meta( $order_id, 'status', 'pending' );
    update_post_meta( $order_id, '_invite_sent', $sent ? '1' : '0' );
    update_post_meta( $order_id, '_invite_link', esc_url_raw( $link ) );

    wp_send_json_success( [
        'message' => __( 'Invite sent to email address.', 'llms-groups-access-addon' ),
        'email'   => $email,
        'invite'  => $sent,
    ] );
}

public static function save_group_seats() {

    if (
        ! is_user_logged_in() ||
        empty( $_POST['llmsgaa_group_seats_nonce'] ) ||
        ! wp_verify_nonce( $_POST['llmsgaa_group_seats_nonce'], 'llmsgaa_save_group_seats' )
    ) {
        wp_die( __( 'Unauthorized', 'llms-groups-access-addon' ) );
    }

    $group_id = absint( $_POST['group_id'] ?? 0 );

    foreach ( $_POST['order_email'] as $order_id => $email ) {
        $order_id = absint( $order_id );
        $email    = sanitize_email( $email );

        if ( ! $order_id || ! is_email( $email ) ) {
            continue;
        }

        // 1) Update the email meta for every order
        update_post_meta( $order_id, 'student_email', $email );

        // 3) Link existing user, or queue an invite
        $user = get_user_by( 'email', $email );
        if ( $user ) {
            update_post_meta( $order_id, 'student_id',          $user->ID );
            update_post_meta( $order_id, 'status',              'active' );
            update_post_meta( $order_id, 'has_accepted_invite', '1' );
        } else {
            // Mark as pending and fire central InviteService
            update_post_meta( $order_id, 'student_id',          null );
            update_post_meta( $order_id, 'status',              'pending' );
            update_post_meta( $order_id, 'has_accepted_invite', '0' );

            error_log( "✉️ Pending invite for new email {$email} on order {$order_id}" );

            $result = InviteService::send_invite( $group_id, $email, 'member' );
            if ( is_wp_error( $result ) ) {
                error_log( "❌ InviteService error for {$email}: " . $result->get_error_message() );
            } else {
                error_log( "📬 InviteService sent invite to {$email}" );
            }
        }
    } // end foreach

    // NOW—and only now—redirect back
    wp_safe_redirect( wp_get_referer() ?: home_url() );
    exit;
}




public static function auto_set_group_order_end_date( $post_id, $post, $update ) {
    if ( $update ) {
        // This is an edit/update - don't override manually adjusted end_date
        return;
    }

    $start = get_post_meta( $post_id, 'start_date', true );

    if ( $start && strtotime( $start ) ) {
        $end = date( 'Y-m-d', strtotime( '+1 year', strtotime( $start ) ) );
        update_post_meta( $post_id, 'end_date', $end );
    }
}


public static function cancel_invite() {
    if (
        ! is_user_logged_in() ||
        ! isset( $_REQUEST['llmsgaa_cancel_invite_nonce'] ) ||
        ! wp_verify_nonce( $_REQUEST['llmsgaa_cancel_invite_nonce'], 'llmsgaa_cancel_invite_action' )
    ) {
        wp_die( __( 'Unauthorized', 'llms-groups-access-addon' ) );
    }

    $group_id = absint( $_REQUEST['group_id'] ?? 0 );
    $email    = sanitize_email( $_REQUEST['email'] ?? '' );

    if ( ! $group_id || ! is_email( $email ) ) {
        wp_die( __( 'Invalid request', 'llms-groups-access-addon' ) );
    }

    global $wpdb;

    // Delete invitation row
    $table = $wpdb->prefix . 'lifterlms_group_invitations';
    $wpdb->delete( $table, [
        'group_id' => $group_id,
        'email'    => $email,
    ] );

    // Clear `_invite_sent` meta from any orders for this group+email
    $orders = get_posts([
        'post_type'   => 'llms_group_order',
        'post_status' => 'any',
        'meta_query'  => [
            [ 'key' => 'group_id', 'value' => $group_id ],
            [
                'relation' => 'OR',
                [ 'key' => 'student_email', 'value' => $email ],
                [ 'key' => 'student_email', 'value' => $email ],
            ],
        ],
    ]);

    foreach ( $orders as $order ) {
        delete_post_meta( $order->ID, '_invite_sent' );
    }

    wp_safe_redirect( wp_get_referer() ?: get_permalink( $group_id ) );
    exit;
}


public static function add_admin() {
    // --- 1) Security checks ---
    if (
        empty( $_POST['llmsgaa_add_admin_nonce'] ) ||
        ! wp_verify_nonce( wp_unslash( $_POST['llmsgaa_add_admin_nonce'] ), 'llmsgaa_add_admin' )
    ) {
        wp_die( __( 'Unauthorized', 'llms-groups-access-addon' ) );
    }

    $group_id = absint( $_POST['group_id'] ?? 0 );
    $email    = sanitize_email( wp_unslash( $_POST['admin_email'] ?? '' ) );

    if ( ! $group_id || ! is_email( $email ) ) {
        wp_safe_redirect( add_query_arg( 'admin_added', '0', wp_get_referer() ?: admin_url() ) );
        exit;
    }

    // --- 2) Look up a WP user by that email ---
    $user = get_user_by( 'email', $email );

    if ( $user ) {
        // --- 3a) If they’re already enrolled in the group, grant admin immediately ---
        if ( LLMS_Groups_Enrollment::user_has_access( $user->ID, $group_id ) ) {
            // Use LifterLMS Groups API to add an admin
            $group = new \LLMS_Group( $group_id );
            $group->add_admin( $user->ID );

            // Redirect back with success flag
            wp_safe_redirect( add_query_arg( 'admin_added', '1', wp_get_referer() ?: admin_url() ) );
            exit;
        }
        // --- 3b) If user exists but isn’t yet in the group, link them as member + admin ---
        else {
            // Enroll them as a member
            LLMS_Groups_Enrollment::instance()->enroll_user( $group_id, $user->ID );
            // Then immediately grant admin
            $group = new \LLMS_Group( $group_id );
            $group->add_admin( $user->ID );

            wp_safe_redirect( add_query_arg( 'admin_added', '1', wp_get_referer() ?: admin_url() ) );
            exit;
        }
    }

    // --- 4) Fallback: no WP user yet, so fall back to invite flow ---
    global $wpdb;
    $table      = $wpdb->prefix . 'lifterlms_group_invitations';
    $invite_key = wp_generate_password( 32, false );

    $inserted = $wpdb->insert(
        $table,
        [
            'group_id'  => $group_id,
            'invite_key'=> $invite_key,
            'email'     => $email,
            'role'      => 'admin',
        ],
        [ '%d', '%s', '%s', '%s' ]
    );

    if ( false === $inserted ) {
        wp_safe_redirect( add_query_arg( 'admin_added', 'dbfail', wp_get_referer() ?: admin_url() ) );
        exit;
    }

    // send the invite email
    $invite_id = $wpdb->insert_id;
    $inv       = new \LLMS_Group_Invitation( [ 'id' => $invite_id ] );
    $emailer   = new \LLMS_Groups_Invitation_Email();
    $emailer->send( $invite_id, $inv );

    wp_safe_redirect( add_query_arg( 'admin_added', '1', wp_get_referer() ?: admin_url() ) );
    exit;
}





}