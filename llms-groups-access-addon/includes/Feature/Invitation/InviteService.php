<?php
namespace LLMSGAA\Feature\Invitation;

use LLMS_Group_Invitation;
use LLMS_Groups_Invitation_Email;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class InviteService {

    /**
     * Send an invite email using LifterLMS's group invitation system.
     *
     * @param int $group_id
     * @param string $email
     * @param string $role 'admin' or 'member'
     * @return bool|\WP_Error
     */
    public static function send_invite( $group_id, $email, $role = 'member' ) {
        global $wpdb;

        if ( ! is_email( $email ) ) {
            error_log("❌ Invalid email: {$email}");
            return new \WP_Error( 'invalid_email', 'Invalid email address provided.' );
        }

        if ( ! in_array( $role, [ 'admin', 'member' ], true ) ) {
            error_log("❌ Invalid role: {$role}");
            return new \WP_Error( 'invalid_role', 'Invalid group role. Use admin or member.' );
        }

        if ( get_post_status( $group_id ) !== 'publish' ) {
            error_log("❌ Group ID {$group_id} is not a valid published group");
            return new \WP_Error( 'invalid_group', 'Group does not exist or is not published.' );
        }

        $table      = $wpdb->prefix . 'lifterlms_group_invitations';
        $invite_key = wp_generate_password( 32, false );

        $inserted = $wpdb->insert(
            $table,
            [
                'group_id'   => $group_id,
                'invite_key' => $invite_key,
                'email'      => $email,
                'role'       => $role,
            ],
            [ '%d', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            return new \WP_Error( 'db_insert_error', $wpdb->last_error );
        }

        $invite_id = $wpdb->insert_id;

        $inv = new LLMS_Group_Invitation( [ 'id' => $invite_id ] );
        $emailer = new LLMS_Groups_Invitation_Email();

        $mail_result = $emailer->send( $invite_id, $inv );


        return true;
    }
}
