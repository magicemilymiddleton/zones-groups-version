<?php
/**
 * Unified Group Member Management
 * Handles all group members (active + pending) and license assignments
 */
 // v2.3a modified to change 'Starts Soon' to 'Not Started'
 // v2.4 after InviteService::send_invite add Fire FluentCRM automation hook

namespace LLMSGAA\Feature\Shortcodes;
use LLMSGAA\Feature\GroupAdmin\Reporting;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UnifiedMemberManager {

    /**
     * Initialize the class
     */
    public static function init() {
        // open for any later adds
    }

/**
 * Get all group members (both active and pending) with their license counts
 * 
 * @param int $group_id
 * @return array Combined array of members with status, role, and license info
 */
public static function get_all_group_members( $group_id ) {
    global $wpdb;
    
    // Get active members from lifterlms_user_postmeta WITH last login
    $active_members = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT 
            lum.user_id, 
            lum.meta_value as role,
            COALESCE(ll.meta_value, '') as last_login_timestamp
         FROM {$wpdb->prefix}lifterlms_user_postmeta lum
         LEFT JOIN {$wpdb->usermeta} ll 
            ON lum.user_id = ll.user_id 
            AND ll.meta_key = 'llms_last_login'
         WHERE lum.post_id = %d 
         AND lum.meta_key = '_group_role'
         AND lum.meta_value IN ('admin', 'member')",
        $group_id
    ) );

    // Get pending invitations from lifterlms_group_invitations  
    $pending_invites = $wpdb->get_results( $wpdb->prepare(
        "SELECT email, role, id as invite_id
         FROM {$wpdb->prefix}lifterlms_group_invitations 
         WHERE group_id = %d",
        $group_id
    ) );

    $members = [];

    // Process active members
    foreach ( $active_members as $member ) {
        $user = get_user_by( 'ID', $member->user_id );
        if ( ! $user ) continue;

        $license_count = self::count_member_licenses( $group_id, $user->user_email );
        
        // Build display name: First Name Last Name, with fallbacks
        $display_name = self::build_display_name( $user );
        
        // Format last login for display
    $last_activity = Reporting::get_user_last_activity( $user->ID );
    $last_login_display = self::format_last_login( $last_activity );
		
        $members[] = [
            'user_id'       => $user->ID,
            'email'         => $user->user_email,
            'name'          => $display_name,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'role'          => $member->role,
            'status'        => 'active',
            'last_login'    => $last_login_display,
            'last_login_raw'=> $member->last_login_timestamp, // Keep raw timestamp for sorting
            'license_count' => $license_count,
            'invited_date'  => null,
            'invite_id'     => null,
        ];
    }

    // Process pending invitations
    foreach ( $pending_invites as $invite ) {
        $license_count = self::count_member_licenses( $group_id, $invite->email );
        
        $members[] = [
            'user_id'       => null,
            'email'         => $invite->email,
            'name'          => 'Pending Invitation',
            'first_name'    => '',
            'last_name'     => '',
            'role'          => $invite->role,
            'status'        => 'pending',
            'last_login'    => 'Invite pending',
            'last_login_raw'=> 0,
            'license_count' => $license_count,
            'invited_date'  => null, // No date column in this table
            'invite_id'     => $invite->invite_id,
        ];
    }

    // Sort by status (active first) then by name
    usort( $members, function( $a, $b ) {
        if ( $a['status'] !== $b['status'] ) {
            return $a['status'] === 'active' ? -1 : 1;
        }
        return strcasecmp( $a['name'], $b['name'] );
    });

    return $members;
}

/**
 * Format last login timestamp into human-readable format
 * Add this as a new method in the same class
 */
public static function format_last_login( $timestamp ) {
    if ( empty( $timestamp ) || $timestamp == '0' ) {
        return 'Never';
    }
    
    // LifterLMS stores as MySQL datetime string (Y-m-d H:i:s)
    // Convert to Unix timestamp
    $last_login_timestamp = strtotime( $timestamp );
    
    // If conversion failed or invalid date
    if ( $last_login_timestamp === false || $last_login_timestamp < 946684800 ) { // Before year 2000
        return 'Never';
    }
    
    $current_timestamp = current_time( 'timestamp' );
    $time_diff = $current_timestamp - $last_login_timestamp;
    
    // Format based on time difference
    if ( $time_diff < 60 ) {
        return 'Just now';
    } elseif ( $time_diff < 3600 ) {
        $minutes = floor( $time_diff / 60 );
        return $minutes . ' min ago';
    } elseif ( $time_diff < 86400 ) {
        $hours = floor( $time_diff / 3600 );
        return $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ago';
    } elseif ( $time_diff < 172800 ) {
        return 'Yesterday';
    } elseif ( $time_diff < 604800 ) {
        $days = floor( $time_diff / 86400 );
        return $days . ' days ago';
    } elseif ( $time_diff < 2592000 ) {
        $weeks = floor( $time_diff / 604800 );
        return $weeks . ' week' . ( $weeks > 1 ? 's' : '' ) . ' ago';
    } elseif ( $time_diff < 31536000 ) {
        $months = floor( $time_diff / 2592000 );
        return $months . ' month' . ( $months > 1 ? 's' : '' ) . ' ago';
    } else {
        // For dates older than a year, show the actual date
        return date( 'M j, Y', $last_login_timestamp );
    }
}





/**
 * Build display name from user data with proper fallbacks
 * 
 * @param WP_User $user
 * @return string Display name
 */
private static function build_display_name( $user ) {
    $first_name = trim( $user->first_name );
    $last_name = trim( $user->last_name );
    
    // Priority 1: First Name + Last Name
    if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
        return $first_name . ' ' . $last_name;
    }
    
    // Priority 2: First Name only
    if ( ! empty( $first_name ) ) {
        return $first_name;
    }
    
    // Priority 3: Last Name only
    if ( ! empty( $last_name ) ) {
        return $last_name;
    }
    
    // Priority 4: Display Name (if set)
    if ( ! empty( $user->display_name ) && $user->display_name !== $user->user_login ) {
        return $user->display_name;
    }
    
    // Priority 5: Fallback to username (user_login)
    return $user->user_login;
}


    /**
     * Count how many licenses (llms_group_orders) are assigned to a specific email
     * 
     * @param int $group_id
     * @param string $email
     * @return int Number of licenses assigned
     */
    public static function count_member_licenses( $group_id, $email ) {
        global $wpdb;
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} gm ON p.ID = gm.post_id AND gm.meta_key = 'group_id' AND gm.meta_value = %d
             INNER JOIN {$wpdb->postmeta} em ON p.ID = em.post_id AND em.meta_key = 'student_email' AND em.meta_value = %s
             WHERE p.post_type = 'llms_group_order'
             AND p.post_status = 'publish'",
            $group_id,
            $email
        ) );

        return absint( $count );
    }

    /**
     * Get available (unassigned) licenses for a group
     * 
     * @param int $group_id
     * @return array Array of unassigned llms_group_orders
     */
    public static function get_available_licenses( $group_id ) {
        global $wpdb;
        
        $licenses = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_title,
                    pm_product.meta_value as product_id,
                    pm_start.meta_value as start_date,
                    pm_end.meta_value as end_date,
                    pm_status.meta_value as status
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id AND pm_group.meta_key = 'group_id' AND pm_group.meta_value = %d
             LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'student_email'
             LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = 'product_id'
             LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'start_date'
             LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = 'end_date'
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status'
             WHERE p.post_type = 'llms_group_order'
             AND p.post_status = 'publish'
             AND (pm_email.meta_value IS NULL OR pm_email.meta_value = '')
             ORDER BY pm_start.meta_value ASC, pm_product.meta_value ASC",
            $group_id
        ) );

        // Add course/product titles
        foreach ( $licenses as &$license ) {
            if ( $license->product_id ) {
                $license->course_title = get_the_title( $license->product_id );
            } else {
                $license->course_title = 'Unknown Course';
            }
            
            // Format dates
            $license->start_date_formatted = $license->start_date ? date_i18n( 'F j, Y', strtotime( $license->start_date ) ) : '';
            $license->end_date_formatted = $license->end_date ? date_i18n( 'F j, Y', strtotime( $license->end_date ) ) : '';
        }

        return $licenses;
    }

    /**
     * Get licenses assigned to a specific member
     * 
     * @param int $group_id
     * @param string $email
     * @return array Array of assigned llms_group_orders
     */
    public static function get_member_licenses( $group_id, $email ) {
        global $wpdb;
        
        $licenses = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_title,
                    pm_product.meta_value as product_id,
                    pm_start.meta_value as start_date,
                    pm_end.meta_value as end_date,
                    pm_status.meta_value as status,
                    pm_student_id.meta_value as student_id
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id AND pm_group.meta_key = 'group_id' AND pm_group.meta_value = %d
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'student_email' AND pm_email.meta_value = %s
             LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = 'product_id'
             LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'start_date'
             LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = 'end_date'
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status'
             LEFT JOIN {$wpdb->postmeta} pm_student_id ON p.ID = pm_student_id.post_id AND pm_student_id.meta_key = 'student_id'
             WHERE p.post_type = 'llms_group_order'
             AND p.post_status = 'publish'
             ORDER BY pm_start.meta_value ASC",
            $group_id,
            $email
        ) );

        // Add course/product titles
        foreach ( $licenses as &$license ) {
            if ( $license->product_id ) {
                $license->course_title = get_the_title( $license->product_id );
            } else {
                $license->course_title = 'Unknown Course';
            }
            
            // Format dates
            $license->start_date_formatted = $license->start_date ? date_i18n( 'F j, Y', strtotime( $license->start_date ) ) : '';
            $license->end_date_formatted = $license->end_date ? date_i18n( 'F j, Y', strtotime( $license->end_date ) ) : '';
        }

        return $licenses;
    }

/**
 * Add a new member to the group
 * 
 * @param int $group_id
 * @param string $email
 * @param string $role (admin|member)
 * @return bool|WP_Error True on success, WP_Error on failure
 */
public static function add_member( $group_id, $email, $role = 'member' ) {
    if ( ! is_email( $email ) ) {
        return new \WP_Error( 'invalid_email', 'Invalid email address' );
    }

    if ( ! in_array( $role, [ 'admin', 'member' ] ) ) {
        return new \WP_Error( 'invalid_role', 'Role must be admin or member' );
    }

    // Check if user already exists
    $user = get_user_by( 'email', $email );
    
    if ( $user ) {
        // User exists - check if they're already in the group
        global $wpdb;
        
        $existing_role = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}lifterlms_user_postmeta 
             WHERE user_id = %d AND post_id = %d AND meta_key = '_group_role'",
            $user->ID,
            $group_id
        ) );

        if ( $existing_role ) {
            // Already a group member
            return new \WP_Error( 'already_member', 'User is already a member of this group' );
        }

        // User exists but not in group
        try {
            $result = $wpdb->insert(
                $wpdb->prefix . 'lifterlms_user_postmeta',
                [
                    'user_id'    => $user->ID,
                    'post_id'    => $group_id,
                    'meta_key'   => '_group_role',
                    'meta_value' => $role
                ],
                [ '%d', '%d', '%s', '%s' ]
            );
            
            if ( $result === false ) {
                return new \WP_Error( 'database_error', 'Failed to add user to group' );
            }
            
            return true;
            
        } catch ( Exception $e ) {
            return new \WP_Error( 'database_error', 'Database error: ' . $e->getMessage() );
        }
        
    } else {
        // User doesn't exist
        $result = \LLMSGAA\Feature\Invitation\InviteService::send_invite( $group_id, $email, $role );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		// Fire FluentCRM automation hook immediately after invite creation
		do_action( 'llmsgaa_invitation_created', [
			'group_id' => $group_id,
			'email'    => $email,
			'role'     => $role,
			'source'   => 'UnifiedMemberManager::add_member'
		]);

		
		return true;

        
    }
}


    /**
     * Change a member's role
     * 
     * @param int $group_id
     * @param int $user_id
     * @param string $new_role
     * @return bool|WP_Error
     */
    public static function change_member_role( $group_id, $user_id, $new_role ) {
        if ( ! in_array( $new_role, [ 'admin', 'member' ] ) ) {
            return new \WP_Error( 'invalid_role', 'Role must be admin or member' );
        }

        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lifterlms_user_postmeta',
            [ 'meta_value' => $new_role ],
            [
                'user_id'    => $user_id,
                'post_id'    => $group_id,
                'meta_key'   => '_group_role'
            ],
            [ '%s' ],
            [ '%d', '%d', '%s' ]
        );

        return $result !== false;
    }


/**
 * Assign a license to a member
 * 
 * @param int $order_id llms_group_order ID
 * @param string $email Member email
 * @return bool|WP_Error
 */
public static function assign_license( $order_id, $email ) {
    if ( ! is_email( $email ) ) {
        return new \WP_Error( 'invalid_email', 'Invalid email address' );
    }

    // Check if order exists and is available
    $order = get_post( $order_id );
    if ( ! $order || $order->post_type !== 'llms_group_order' ) {
        return new \WP_Error( 'invalid_order', 'Invalid group order' );
    }

    $current_email = get_post_meta( $order_id, 'student_email', true );
    if ( $current_email && $current_email !== $email ) {
        return new \WP_Error( 'order_assigned', 'This license is already assigned to another member' );
    }

    // Update the order with the email
    update_post_meta( $order_id, 'student_email', $email );

    // Check if user exists
    $user = get_user_by( 'email', $email );
    if ( $user ) {
        // User exists - activate the license
        update_post_meta( $order_id, 'student_id', $user->ID );
        update_post_meta( $order_id, 'status', 'active' );
        update_post_meta( $order_id, 'has_accepted_invite', '1' );
        
    } else {
        // User doesn't exist - mark as pending
        update_post_meta( $order_id, 'student_id', null );
        update_post_meta( $order_id, 'status', 'pending' );
        update_post_meta( $order_id, 'has_accepted_invite', '0' );
        
    }

    // Fire FluentCRM event
    if ( $user ) {
        do_action( 'llmsgaa_access_pass_assigned', $user->ID, $order_id, get_post_meta( $order_id, 'product_id', true ) );
    }

    return true;
}


/**
 * Unassign a license from a member
 * 
 * @param int $order_id
 * @return bool
 */
public static function unassign_license( $order_id ) {
    error_log( __METHOD__ . " 🔓 Starting license unassignment for order {$order_id}" );
    
    // Get the current values before deletion
    $student_id = get_post_meta( $order_id, 'student_id', true );
    $student_email = get_post_meta( $order_id, 'student_email', true );
    $product_id = get_post_meta( $order_id, 'product_id', true );
    
    
    error_log( __METHOD__ . " Found: student_id={$student_id}, email={$student_email}, product_id={$product_id}" );
    
    // Perform immediate unenrollment BEFORE clearing metadata
    if ( $student_id && $product_id ) {
        error_log( __METHOD__ . " 📤 Triggering unenrollment before clearing metadata" );
        
        // Check if user has other licenses for the same product
        global $wpdb;
        $other_licenses = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_student ON p.ID = pm_student.post_id 
                AND pm_student.meta_key = 'student_id' 
                AND pm_student.meta_value = %d
             INNER JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id 
                AND pm_product.meta_key = 'product_id' 
                AND pm_product.meta_value = %d
             WHERE p.post_type = 'llms_group_order'
             AND p.ID != %d
             AND p.post_status = 'publish'",
            $student_id,
            $product_id,
            $order_id
        ));
        
        if ( $other_licenses == 0 ) {
            // No other licenses, safe to unenroll
            error_log( __METHOD__ . " No other licenses found, proceeding with unenrollment" );
            
            if ( function_exists( 'llms_unenroll_student' ) ) {
                // CORRECTED: Use proper parameter order
                llms_unenroll_student( $student_id, $product_id, 'expired', 'any' );
                error_log( __METHOD__ . " Called llms_unenroll_student with correct parameters" );
            }
            
            // Verify and try alternative if needed
            if ( function_exists( 'llms_is_user_enrolled' ) && llms_is_user_enrolled( $student_id, $product_id ) ) {
                if ( class_exists( 'LLMS_Student' ) ) {
                    error_log( __METHOD__ . " Primary unenroll failed, trying LLMS_Student class" );
                    $student = new \LLMS_Student( $student_id );
                    $student->unenroll( $product_id, 'expired', 'group' );
                }
            }
        } else {
            error_log( __METHOD__ . " User has {$other_licenses} other license(s) for this product, skipping unenrollment" );
        }
    }
    
    // Now clear the metadata
    delete_post_meta( $order_id, 'student_email' );
    delete_post_meta( $order_id, 'student_id' );
    delete_post_meta( $order_id, 'has_accepted_invite' );
    delete_post_meta( $order_id, '_invite_sent' );
    delete_post_meta( $order_id, '_invite_accepted' );
    
    // Update status to available
    update_post_meta( $order_id, 'status', 'available' );
    // Fire FluentCRM event
    if ( $student_id ) {
        do_action( 'llmsgaa_access_pass_removed', $student_id, $order_id, $product_id );
    }
    
    // Clear any scheduled actions
    if ( function_exists( 'as_unschedule_all_actions' ) ) {
        $args = [ 'order_id' => $order_id ];
        as_unschedule_all_actions( 'llmsggaa_enroll_user', $args );
        as_unschedule_all_actions( 'llmsggaa_expire_user', $args );
    }
    
    // Fire custom action
    do_action( 'llmsgaa_license_unassigned', $order_id, $student_id, $student_email, $product_id );
    
    // Trigger a save_post action to ensure ScheduleHandler picks up the change
    wp_update_post( [ 'ID' => $order_id ] );
    
    error_log( __METHOD__ . " ✅ License unassignment complete for order {$order_id}" );
    
    return true;
}

/**
 * Update member role in group
 */
public function update_member_role( $user_id, $group_id, $new_role ) {
    global $wpdb;
    
    try {
        // Validate inputs
        if ( ! in_array( $new_role, ['member', 'admin'] ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid role specified'
            );
        }
        
        // Check if user exists in group
        $existing_membership = $wpdb->get_row( $wpdb->prepare( "
            SELECT id, role 
            FROM {$wpdb->prefix}lifterlms_group_invitations 
            WHERE user_id = %d AND group_id = %d
        ", $user_id, $group_id ) );
        
        if ( ! $existing_membership ) {
            return array(
                'success' => false,
                'message' => 'User is not a member of this group'
            );
        }
        
        // Check if role is already the same
        if ( $existing_membership->role === $new_role ) {
            return array(
                'success' => true,
                'message' => 'Role is already set to ' . $new_role
            );
        }
        
        // Update the role
        $update_result = $wpdb->update(
            $wpdb->prefix . 'lifterlms_group_invitations',
            array( 'role' => $new_role ),
            array( 
                'user_id' => $user_id,
                'group_id' => $group_id 
            ),
            array( '%s' ),
            array( '%d', '%d' )
        );
        
        if ( $update_result === false ) {
            return array(
                'success' => false,
                'message' => 'Failed to update role in database'
            );
        }
        
        // If changing to admin, we might want to give them additional permissions
        if ( $new_role === 'admin' ) {
            // Add any admin-specific setup here
            do_action( 'llmsgaa_member_promoted_to_admin', $user_id, $group_id );
        } elseif ( $new_role === 'member' ) {
            // Remove admin-specific permissions if needed
            do_action( 'llmsgaa_admin_demoted_to_member', $user_id, $group_id );
        }
        
        return array(
            'success' => true,
            'message' => 'Role updated successfully to ' . $new_role
        );
        
    } catch ( Exception $e ) {
        return array(
            'success' => false,
            'message' => 'Error updating role: ' . $e->getMessage()
        );
    }
}

/**
 * Remove a member from the group (existing system handles course unenrollment)
 * 
 * @param int $group_id
 * @param int $user_id (optional if email provided)
 * @param string $email (optional if user_id provided)
 * @return bool|WP_Error
 */
public static function remove_member( $group_id, $user_id = 0, $email = '' ) {
    global $wpdb;
    
    // Get email if not provided
    if ( ! $email && $user_id ) {
        $user = get_user_by( 'ID', $user_id );
        if ( $user ) {
            $email = $user->user_email;
        }
    }
    
    // Get user_id if not provided
    if ( ! $user_id && $email ) {
        $user = get_user_by( 'email', $email );
        if ( $user ) {
            $user_id = $user->ID;
        }
    }
    
    if ( ! $email ) {
        return new \WP_Error( 'missing_email', 'Email is required to remove member' );
    }
    
    try {
        // Start transaction for data consistency
        $wpdb->query( 'START TRANSACTION' );
        
        // 1. Remove from group role (if user exists)
        if ( $user_id ) {
            $role_delete = $wpdb->delete(
                $wpdb->prefix . 'lifterlms_user_postmeta',
                [
                    'user_id'  => $user_id,
                    'post_id'  => $group_id,
                    'meta_key' => '_group_role'
                ],
                [ '%d', '%d', '%s' ]
            );
            
        }
        
        // 2. Remove from group invitations (for pending members)
        $invite_delete = $wpdb->delete(
            $wpdb->prefix . 'lifterlms_group_invitations',
            [
                'group_id' => $group_id,
                'email'    => $email
            ],
            [ '%d', '%s' ]
        );
        
        
        // 3. Get all license orders for this member
        $license_orders = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id AND pm_group.meta_key = 'group_id' AND pm_group.meta_value = %d
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'student_email' AND pm_email.meta_value = %s
             WHERE p.post_type = 'llms_group_order'",
            $group_id,
            $email
        ) );
        
        // 4. Unassign each license
        $unassigned_count = 0;
        foreach ( $license_orders as $order ) {
            if ( self::unassign_license( $order->ID ) ) {
                $unassigned_count++;
            }
        }
        
        error_log( "🎫 Unassigned {$unassigned_count} licenses - existing system will handle unenrollment" );
        
        // 5. Remove from LifterLMS group membership (if function exists)
        if ( $user_id && function_exists( 'LLMS_Groups_Members' ) ) {
            try {
                $llms_removal = \LLMS_Groups_Members::remove_user_from_group( $user_id, $group_id );
            } catch ( Exception $e ) {
            }
        }
        
        // Commit transaction
        $wpdb->query( 'COMMIT' );
        
        // Log the complete action
        error_log( sprintf( 
            '✅ MEMBER REMOVAL COMPLETE - Group: %d, User: %d, Email: %s, Licenses Unassigned: %d (existing system handles unenrollment)', 
            $group_id, 
            $user_id, 
            $email,
            $unassigned_count
        ) );
        
        return true;
        
    } catch ( Exception $e ) {
        // Rollback on error
        $wpdb->query( 'ROLLBACK' );
        
        error_log( "❌ Member removal failed: " . $e->getMessage() );
        
        return new \WP_Error( 'removal_failed', 'Failed to remove member: ' . $e->getMessage() );
    }
}



/**
 * Get detailed course access information for a member
 * 
 * @param int $group_id
 * @param string $email
 * @return array Array of course access details with dates
 */
public static function get_member_course_access( $group_id, $email ) {
    global $wpdb;
    
    $courses = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.ID as order_id,
                pm_product.meta_value as product_id,
                pm_start.meta_value as start_date,
                pm_end.meta_value as end_date,
                pm_status.meta_value as status
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id AND pm_group.meta_key = 'group_id' AND pm_group.meta_value = %d
         INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'student_email' AND pm_email.meta_value = %s
         LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = 'product_id'
         LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'start_date'
         LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = 'end_date'
         LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status'
         WHERE p.post_type = 'llms_group_order'
         AND p.post_status = 'publish'
         ORDER BY pm_start.meta_value ASC",
        $group_id,
        $email
    ) );

    $course_access = [];
    
    foreach ( $courses as $course ) {
        // Get course/product title
        $course_title = 'Unknown Course';
        if ( $course->product_id ) {
            $title = get_the_title( $course->product_id );
            if ( $title ) {
                $course_title = $title;
            }
        }
        
        // Format dates
        $start_formatted = '';
        $end_formatted = '';
        $status_indicator = '';
        
        if ( $course->start_date ) {
            $start_formatted = date_i18n( 'M j, Y', strtotime( $course->start_date ) );
        }
        
        if ( $course->end_date ) {
            $end_formatted = date_i18n( 'M j, Y', strtotime( $course->end_date ) );
            
            // Check if expired
            if ( strtotime( $course->end_date ) < time() ) {
                $status_indicator = '🔴 Expired';
            } else {
                $status_indicator = '🟢 Active';
            }
        } else {
            $status_indicator = '🟢 Active';
        }
        
        // Check if not started yet
        if ( $course->start_date && strtotime( $course->start_date ) > time() ) {
            $status_indicator = '🟡 Not Started';
        }
        
        $course_access[] = [
            'course_title' => $course_title,
            'start_date' => $start_formatted,
            'end_date' => $end_formatted,
            'status' => $course->status ?: 'active',
            'status_indicator' => $status_indicator,
            'order_id' => $course->order_id
        ];
    }
    
    return $course_access;
}

/**
 * Initialize AJAX handlers for bulk features
 */
public static function init_bulk_handlers() {
    add_action( 'wp_ajax_llmsgaa_bulk_assign_licenses', [ __CLASS__, 'ajax_bulk_assign_licenses' ] );
    add_action( 'wp_ajax_llmsgaa_import_csv', [ __CLASS__, 'ajax_import_csv' ] );
}

/**
 * AJAX handler for bulk license assignment
 */
public static function ajax_bulk_assign_licenses() {
    check_ajax_referer( 'llmsgaa_unified_actions', 'nonce' );
    
    $emails = isset( $_POST['emails'] ) ? array_map( 'sanitize_email', $_POST['emails'] ) : [];
    $license_id = intval( $_POST['license_id'] );
    $group_id = intval( $_POST['group_id'] );
    
    if ( empty( $emails ) || ! $license_id ) {
        wp_send_json_error( 'Invalid request data' );
    }
    
    $success_count = 0;
    $error_count = 0;
    $messages = [];
    
    foreach ( $emails as $email ) {
        if ( ! is_email( $email ) ) {
            $error_count++;
            $messages[] = "Invalid email: {$email}";
            continue;
        }
        
        $result = self::assign_license( $license_id, $email );
        
        if ( is_wp_error( $result ) ) {
            $error_count++;
            $messages[] = "Error for {$email}: " . $result->get_error_message();
        } else {
            $success_count++;
            $messages[] = "License assigned to {$email}";
        }
    }
    
    if ( $success_count > 0 ) {
        $summary = "Successfully assigned licenses to {$success_count} members";
        if ( $error_count > 0 ) {
            $summary .= " ({$error_count} errors)";
        }
        wp_send_json_success( $summary );
    } else {
        wp_send_json_error( "Failed to assign licenses. {$error_count} errors occurred." );
    }
}

/**
 * AJAX handler for CSV import
 */
public static function ajax_import_csv() {
    check_ajax_referer( 'llmsgaa_unified_actions', 'nonce' );
    
    if ( ! isset( $_FILES['csv_file'] ) ) {
        wp_send_json_error( 'No file uploaded' );
    }
    
    $group_id = intval( $_POST['group_id'] );
    $default_role = sanitize_text_field( $_POST['default_role'] );
    $assign_licenses = $_POST['assign_licenses'] === '1';
    
    if ( ! in_array( $default_role, [ 'member', 'admin' ] ) ) {
        $default_role = 'member';
    }
    
    // Validate file
    $file = $_FILES['csv_file'];
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( 'File upload error' );
    }
    
    if ( $file['type'] !== 'text/csv' && ! str_ends_with( $file['name'], '.csv' ) ) {
        wp_send_json_error( 'Please upload a CSV file' );
    }
    
    // Parse CSV
    $csv_data = self::parse_csv_file( $file['tmp_name'] );
    
    if ( is_wp_error( $csv_data ) ) {
        wp_send_json_error( $csv_data->get_error_message() );
    }
    
    // Process each email
    $results = self::process_csv_import( $csv_data, $group_id, $default_role, $assign_licenses );
    
    wp_send_json_success( $results );
}

/**
 * Parse CSV file and extract emails
 */
private static function parse_csv_file( $file_path ) {
    if ( ! file_exists( $file_path ) ) {
        return new \WP_Error( 'file_not_found', 'CSV file not found' );
    }
    
    $handle = fopen( $file_path, 'r' );
    if ( ! $handle ) {
        return new \WP_Error( 'file_read_error', 'Could not read CSV file' );
    }
    
    $emails = [];
    $header = null;
    $row_count = 0;
    
    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $row_count++;
        
        if ( $row_count === 1 ) {
            // Process header row
            $header = array_map( 'trim', array_map( 'strtolower', $row ) );
            
            // Find email column
            $email_column = array_search( 'email', $header );
            if ( $email_column === false ) {
                fclose( $handle );
                return new \WP_Error( 'missing_email_column', 'CSV must have an "email" column header' );
            }
            
            continue;
        }
        
        // Process data rows
        if ( isset( $row[$email_column] ) ) {
            $email = trim( $row[$email_column] );
            if ( is_email( $email ) ) {
                $emails[] = $email;
            }
        }
    }
    
    fclose( $handle );
    
    if ( empty( $emails ) ) {
        return new \WP_Error( 'no_valid_emails', 'No valid emails found in CSV' );
    }
    
    return $emails;
}

/**
 * Process CSV import
 */
private static function process_csv_import( $emails, $group_id, $default_role, $assign_licenses ) {
    $added_count = 0;
    $existing_count = 0;
    $error_count = 0;
    $licenses_assigned = 0;
    $messages = [];
    
    // Get available licenses if needed
    $available_licenses = [];
    if ( $assign_licenses ) {
        $available_licenses = self::get_available_licenses( $group_id );
    }
    
    $license_index = 0;
    
    foreach ( $emails as $email ) {
        try {
            // Check if user is already in the group
            $existing_member = self::is_email_in_group( $email, $group_id );
            
            if ( $existing_member ) {
                $existing_count++;
                $messages[] = "{$email} already exists in group";
                
                // Still try to assign license if requested and available
                if ( $assign_licenses && $license_index < count( $available_licenses ) ) {
                    $license = $available_licenses[ $license_index ];
                    $license_result = self::assign_license( $license->ID, $email );
                    
                    if ( ! is_wp_error( $license_result ) ) {
                        $licenses_assigned++;
                        $messages[] = "License assigned to existing member {$email}";
                        $license_index++;
                    }
                }
                
                continue;
            }
            
            // Add new member
            $add_result = self::add_member( $group_id, $email, $default_role );
            
            if ( is_wp_error( $add_result ) ) {
                $error_count++;
                $messages[] = "Error adding {$email}: " . $add_result->get_error_message();
                continue;
            }
            
            $added_count++;
            $messages[] = "Added {$email} as {$default_role}";
            
            // Assign license if requested and available
            if ( $assign_licenses && $license_index < count( $available_licenses ) ) {
                $license = $available_licenses[ $license_index ];
                $license_result = self::assign_license( $license->ID, $email );
                
                if ( ! is_wp_error( $license_result ) ) {
                    $licenses_assigned++;
                    $messages[] = "License assigned to {$email}";
                    $license_index++;
                } else {
                    $messages[] = "License assignment failed for {$email}: " . $license_result->get_error_message();
                }
            }
            
        } catch ( Exception $e ) {
            $error_count++;
            $messages[] = "Error processing {$email}: " . $e->getMessage();
        }
    }
    
    return [
        'added' => $added_count,
        'existing' => $existing_count,
        'errors' => $error_count,
        'licenses_assigned' => $licenses_assigned,
        'total_processed' => count( $emails ),
        'messages' => $messages
    ];
}

/**
 * Check if email is already in group (member or pending invite)
 */
private static function is_email_in_group( $email, $group_id ) {
    global $wpdb;
    
    // Check active members
    $user = get_user_by( 'email', $email );
    if ( $user ) {
        $role = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}lifterlms_user_postmeta 
             WHERE user_id = %d AND post_id = %d AND meta_key = '_group_role'",
            $user->ID,
            $group_id
        ) );
        
        if ( $role ) {
            return 'member';
        }
    }
    
    // Check pending invites
    $invite = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}lifterlms_group_invitations 
         WHERE group_id = %d AND email = %s",
        $group_id,
        $email
    ) );
    
    return $invite ? 'invite' : false;
}




}

// Initialize the manager
UnifiedMemberManager::init();