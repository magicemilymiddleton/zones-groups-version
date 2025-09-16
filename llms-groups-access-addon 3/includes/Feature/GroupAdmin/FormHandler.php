<?php
namespace LLMSGAA\Feature\GroupAdmin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles admin form submissions related to group editing and bulk assignment actions.
 */
class FormHandler {

    public static function init() {
        add_action( 'admin_post_llmsgroups_update',     [ __CLASS__, 'handle_update' ] );
        add_action( 'admin_post_llmsgaa_assign_multiple', [ __CLASS__, 'handle_assign_multiple' ] );
        add_action( 'admin_post_llmsgroups_update', [ __CLASS__, 'handle_update_group' ] );
    }

    /**
     * Process the group update form.
     */
    public static function handle_update() {
        if ( empty( $_POST['llmsgroups_update_nonce'] ) ||
             ! wp_verify_nonce( $_POST['llmsgroups_update_nonce'], 'llmsgroups_update_group' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'llms-groups-access-addon' ) );
        }

        $group_id = isset( $_POST['group_id'] )
  ? absint( wp_unslash( $_POST['group_id'] ) )
  : 0;


        $new_title = isset( $_POST['group_title'] )
          ? sanitize_text_field( wp_unslash( $_POST['group_title'] ) )
          : '';

        $new_slug  = isset( $_POST['group_slug'] )
          ? sanitize_title( wp_unslash( $_POST['group_slug'] ) )
          : '';

        $update = [ 'ID' => $group_id ];
        if ( $new_title !== '' ) {
            $update['post_title'] = $new_title;
        }
        if ( $new_slug !== '' ) {
            $update['post_name'] = $new_slug;
        }

        wp_update_post( $update );

        $redirect = add_query_arg( 'updated', '1', wp_get_referer() ?: get_permalink( $group_id ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle the bulk assignment form submission.
     */
    public static function handle_assign_multiple() {
        if ( empty( $_POST['llmsgaa_assign_multiple_nonce'] ) ||
             ! wp_verify_nonce( $_POST['llmsgaa_assign_multiple_nonce'], 'llmsgaa_assign_multiple' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'llms-groups-access-addon' ) );
        }

        $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
        if ( ! $group_id || ! current_user_can( 'edit_post', $group_id ) ) {
            wp_die( esc_html__( 'Invalid group ID.', 'llms-groups-access-addon' ) );
        }

        $emails = isset( $_POST['order_email'] ) && is_array( $_POST['order_email'] )
          ? array_map( 'sanitize_email', wp_unslash( $_POST['order_email'] ) )
          : [];

        foreach ( $emails as $order_id => $email ) {
            if ( is_email( $email ) ) {
                update_post_meta( absint( $order_id ), 'student_email', $email );
            }
        }

        $redirect = add_query_arg( 'assigned', '1', wp_get_referer() ?: get_permalink( $group_id ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    public static function handle_update_group() {
    if (
        empty( $_POST['llmsgroups_update_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( $_POST['llmsgroups_update_nonce'] ), 'llmsgroups_update_group' )
    ) {
        wp_die( __( 'Security check failed', 'llms-groups-access-addon' ) );
    }

        $group_id = absint( $_POST['group_id'] ?? 0 );
        $title    = sanitize_text_field( $_POST['group_title'] ?? '' );
        $slug     = sanitize_title( $_POST['group_slug'] ?? '' );   

        if ( $group_id && $title && $slug ) {
           wp_update_post([
              'ID'         => $group_id,
              'post_title' => $title,
               'post_name'  => $slug,
           ]);
     }

       wp_safe_redirect( add_query_arg( 'updated', '1', get_permalink( $group_id ) ) );
       exit;
    }

}
