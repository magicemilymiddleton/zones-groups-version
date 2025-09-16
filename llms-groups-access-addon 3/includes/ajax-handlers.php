<?php
/**
 * AJAX Handlers for LifterLMS Groups Advanced Access Addon
 * 
 * This file contains all AJAX action handlers for the plugin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get Available Licenses
add_action( 'wp_ajax_llmsgaa_get_available_licenses', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $group_id = absint( $_POST['group_id'] ?? 0 );
    if ( ! $group_id ) {
        wp_send_json_error( 'Missing group ID' );
    }
    
    $licenses = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::get_available_licenses( $group_id );
    wp_send_json_success( $licenses );
});

// Add Member Handler
add_action( 'wp_ajax_llmsgaa_add_member', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $group_id = absint( $_POST['group_id'] ?? 0 );
    $email = sanitize_email( $_POST['email'] ?? '' );
    $role = sanitize_text_field( $_POST['role'] ?? 'member' );

    if ( ! $group_id || ! $email ) {
        wp_send_json_error( 'Missing required fields' );
    }

    $result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::add_member( $group_id, $email, $role );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( 'Member added successfully' );
});

// Assign Licenses Handler
add_action( 'wp_ajax_llmsgaa_assign_licenses', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $email = sanitize_email( $_POST['email'] ?? '' );
    $license_ids = array_map( 'absint', $_POST['license_ids'] ?? [] );

    if ( ! $email || empty( $license_ids ) ) {
        wp_send_json_error( 'Missing required fields' );
    }

    $success_count = 0;
    $errors = [];

    foreach ( $license_ids as $license_id ) {
        $result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::assign_license( $license_id, $email );
        if ( is_wp_error( $result ) ) {
            $errors[] = $result->get_error_message();
        } else {
            $success_count++;
        }
    }

    if ( $success_count > 0 ) {
        wp_send_json_success( "Successfully assigned {$success_count} license(s)" );
    } else {
        wp_send_json_error( 'Failed to assign licenses: ' . implode( ', ', $errors ) );
    }
});

// Get Member Licenses Handler  
add_action( 'wp_ajax_llmsgaa_get_member_licenses', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $group_id = absint( $_POST['group_id'] ?? 0 );
    $email = sanitize_email( $_POST['email'] ?? '' );

    if ( ! $group_id || ! $email ) {
        wp_send_json_error( 'Missing required fields' );
    }

    $licenses = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::get_member_licenses( $group_id, $email );
    wp_send_json_success( $licenses );
});

// Remove License Handler
add_action( 'wp_ajax_llmsgaa_unassign_license', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $order_id = absint( $_POST['order_id'] ?? 0 );

    if ( ! $order_id ) {
        wp_send_json_error( 'Missing order ID' );
    }

    $result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::unassign_license( $order_id );
    
    if ( $result ) {
        wp_send_json_success( 'License removed successfully' );
    } else {
        wp_send_json_error( 'Failed to remove license' );
    }
});

// Update Member Role Handler
add_action( 'wp_ajax_llmsgaa_update_member_role', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $user_id = intval( $_POST['user_id'] ?? 0 );
    $group_id = intval( $_POST['group_id'] ?? 0 );
    $new_role = sanitize_text_field( $_POST['role'] ?? '' );
    $email = sanitize_email( $_POST['email'] ?? '' );
    
    if ( ! $group_id || ( ! $user_id && ! $email ) || ! in_array( $new_role, ['member', 'admin'] ) ) {
        wp_send_json_error( 'Missing or invalid data' );
    }
    
    if ( ! $user_id && $email ) {
        $user = get_user_by( 'email', $email );
        if ( $user ) {
            $user_id = $user->ID;
        }
    }
    
    if ( ! $user_id ) {
        wp_send_json_error( 'Cannot change role for pending invitations' );
    }
    
    $result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::change_member_role( $group_id, $user_id, $new_role );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    } else {
        wp_send_json_success( 'Role updated successfully to ' . $new_role );
    }
});

// Remove Member Handler
add_action( 'wp_ajax_llmsgaa_remove_member', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $user_id = intval( $_POST['user_id'] ?? 0 );
    $group_id = intval( $_POST['group_id'] ?? 0 );
    $email = sanitize_email( $_POST['email'] ?? '' );
    
    if ( ! $group_id || ( ! $user_id && ! $email ) ) {
        wp_send_json_error( 'Missing required data' );
    }
    
    if ( ! $user_id && $email ) {
        $user = get_user_by( 'email', $email );
        if ( $user ) {
            $user_id = $user->ID;
        }
    }
    
    $result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::remove_member( $group_id, $user_id, $email );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    } else {
        wp_send_json_success( 'Member removed successfully' );
    }
});

// Cancel Invite Handler
add_action( 'wp_ajax_llmsgaa_cancel_invite', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $invite_id = intval( $_POST['invite_id'] ?? 0 );
    $group_id = intval( $_POST['group_id'] ?? 0 );
    $email = sanitize_email( $_POST['email'] ?? '' );
    
    if ( ! $group_id || ( ! $invite_id && ! $email ) ) {
        wp_send_json_error( 'Missing required data' );
    }
    
    global $wpdb;
    
    $where = [ 'group_id' => $group_id ];
    $where_format = [ '%d' ];
    
    if ( $invite_id ) {
        $where['id'] = $invite_id;
        $where_format[] = '%d';
    } else {
        $where['email'] = $email;
        $where_format[] = '%s';
    }
    
    $result = $wpdb->delete(
        $wpdb->prefix . 'lifterlms_group_invitations',
        $where,
        $where_format
    );
    
    if ( $result === false ) {
        wp_send_json_error( 'Failed to cancel invitation' );
    }
    
    if ( $result === 0 ) {
        wp_send_json_error( 'Invitation not found' );
    }
    
    // CRITICAL FIX: Find and unassign any licenses associated with this cancelled invitation
    if ( $email ) {
        // Find all license orders for this email in this group
        $license_orders = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id 
                AND pm_group.meta_key = 'group_id' 
                AND pm_group.meta_value = %d
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = 'student_email' 
                AND pm_email.meta_value = %s
             WHERE p.post_type = 'llms_group_order'",
            $group_id,
            $email
        ) );
        
        // Unassign each license found
        $unassigned_count = 0;
        foreach ( $license_orders as $order ) {
            // Clear all the assignment metadata
            delete_post_meta( $order->ID, 'student_email' );
            delete_post_meta( $order->ID, 'student_id' );
            delete_post_meta( $order->ID, 'has_accepted_invite' );
            delete_post_meta( $order->ID, '_invite_sent' );
            
            // Set status back to available
            update_post_meta( $order->ID, 'status', 'available' );
            
            $unassigned_count++;
            error_log( "🔓 [AJAX] Unassigned license (Order ID: {$order->ID}) from cancelled invitation for {$email}" );
        }
        
        if ( $unassigned_count > 0 ) {
            error_log( "✅ [AJAX] Successfully unassigned {$unassigned_count} license(s) from cancelled invitation" );
        }
    }
    
    wp_send_json_success( 'Invitation cancelled successfully' );
});

// Bulk Assign Licenses Handler
add_action( 'wp_ajax_llmsgaa_bulk_assign_licenses', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $emails = isset( $_POST['emails'] ) ? array_map( 'sanitize_email', $_POST['emails'] ) : [];
    $license_id = intval( $_POST['license_id'] ?? 0 );
    $group_id = intval( $_POST['group_id'] ?? 0 );
    
    if ( empty( $emails ) || ! $license_id || ! $group_id ) {
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
        
        $result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::assign_license( $license_id, $email );
        
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
});

// Sequential Bulk Assign Licenses Handler
add_action( 'wp_ajax_llmsgaa_bulk_assign_sequential', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $emails = isset( $_POST['emails'] ) ? array_map( 'sanitize_email', $_POST['emails'] ) : [];
    $group_id = intval( $_POST['group_id'] ?? 0 );
    
    if ( empty( $emails ) || ! $group_id ) {
        wp_send_json_error( 'Invalid request data' );
    }
    
    $available_licenses = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::get_available_licenses( $group_id );
    
    if ( empty( $available_licenses ) ) {
        wp_send_json_error( 'No available licenses to assign' );
    }
    
    $success_count = 0;
    $error_count = 0;
    $messages = [];
    $license_index = 0;
    $assignments_made = [];
    
    foreach ( $emails as $email ) {
        if ( $license_index >= count( $available_licenses ) ) {
            $messages[] = "No more licenses available for {$email}";
            break;
        }
        
        if ( ! is_email( $email ) ) {
            $error_count++;
            $messages[] = "Invalid email: {$email}";
            continue;
        }
        
        $license = $available_licenses[ $license_index ];
        $result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::assign_license( $license->ID, $email );
        
        if ( is_wp_error( $result ) ) {
            $error_count++;
            $messages[] = "Error assigning license to {$email}: " . $result->get_error_message();
        } else {
            $success_count++;
            $assignments_made[] = [
                'email' => $email,
                'license' => $license->course_title
            ];
            $messages[] = "Assigned '{$license->course_title}' to {$email}";
            $license_index++;
        }
    }
    
    $summary = '';
    if ( $success_count > 0 ) {
        $summary = "Successfully assigned {$success_count} license" . ( $success_count > 1 ? 's' : '' );
        
        $unassigned_count = count( $emails ) - $success_count - $error_count;
        if ( $unassigned_count > 0 ) {
            $summary .= ". {$unassigned_count} member" . ( $unassigned_count > 1 ? 's' : '' ) . " not assigned (no more licenses)";
        }
        
        if ( $error_count > 0 ) {
            $summary .= ". {$error_count} error" . ( $error_count > 1 ? 's' : '' ) . " occurred";
        }
        
        wp_send_json_success( [
            'message' => $summary,
            'assignments' => $assignments_made,
            'details' => $messages
        ] );
    } else {
        wp_send_json_error( "Failed to assign any licenses. {$error_count} errors occurred." );
    }
});

// CSV Import Handler
add_action( 'wp_ajax_llmsgaa_import_csv', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmsgaa_unified_actions' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    if ( ! isset( $_FILES['csv_file'] ) ) {
        wp_send_json_error( 'No file uploaded' );
    }
    
    $group_id = intval( $_POST['group_id'] ?? 0 );
    $default_role = sanitize_text_field( $_POST['default_role'] ?? 'member' );
    $assign_licenses = ( $_POST['assign_licenses'] ?? '0' ) === '1';
    
    if ( ! in_array( $default_role, [ 'member', 'admin' ] ) ) {
        $default_role = 'member';
    }
    
    $file = $_FILES['csv_file'];
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( 'File upload error' );
    }
    
    $file_type = wp_check_filetype( $file['name'] );
    if ( ! in_array( $file_type['ext'], ['csv'] ) ) {
        wp_send_json_error( 'Please upload a CSV file' );
    }
    
    $handle = fopen( $file['tmp_name'], 'r' );
    if ( ! $handle ) {
        wp_send_json_error( 'Could not read CSV file' );
    }
    
    $emails = [];
    $header = null;
    $row_count = 0;
    $email_column = -1;
    
    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $row_count++;
        
        if ( $row_count === 1 ) {
            $header = array_map( 'trim', array_map( 'strtolower', $row ) );
            $email_column = array_search( 'email', $header );
            if ( $email_column === false ) {
                fclose( $handle );
                wp_send_json_error( 'CSV must have an "email" column header' );
            }
            continue;
        }
        
        if ( isset( $row[$email_column] ) ) {
            $email = trim( $row[$email_column] );
            if ( is_email( $email ) ) {
                $emails[] = $email;
            }
        }
    }
    
    fclose( $handle );
    
    if ( empty( $emails ) ) {
        wp_send_json_error( 'No valid emails found in CSV' );
    }
    
    // Process the import
    $added_count = 0;
    $existing_count = 0;
    $error_count = 0;
    $licenses_assigned = 0;
    $messages = [];
    
    $available_licenses = [];
    if ( $assign_licenses ) {
        $available_licenses = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::get_available_licenses( $group_id );
    }
    
    $license_index = 0;
    
    foreach ( $emails as $email ) {
        try {
            $user = get_user_by( 'email', $email );
            $is_member = false;
            
            if ( $user ) {
                global $wpdb;
                $role = $wpdb->get_var( $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->prefix}lifterlms_user_postmeta 
                     WHERE user_id = %d AND post_id = %d AND meta_key = '_group_role'",
                    $user->ID,
                    $group_id
                ) );
                
                if ( $role ) {
                    $is_member = true;
                }
            }
            
            if ( ! $is_member ) {
                global $wpdb;
                $invite = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}lifterlms_group_invitations 
                     WHERE group_id = %d AND email = %s",
                    $group_id,
                    $email
                ) );
                
                if ( $invite ) {
                    $is_member = true;
                }
            }
            
            if ( $is_member ) {
                $existing_count++;
                $messages[] = "{$email} already exists in group";
                
                if ( $assign_licenses && $license_index < count( $available_licenses ) ) {
                    $license = $available_licenses[ $license_index ];
                    $license_result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::assign_license( $license->ID, $email );
                    
                    if ( ! is_wp_error( $license_result ) ) {
                        $licenses_assigned++;
                        $messages[] = "License assigned to existing member {$email}";
                        $license_index++;
                    }
                }
                
                continue;
            }
            
            $add_result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::add_member( $group_id, $email, $default_role );
            
            if ( is_wp_error( $add_result ) ) {
                $error_count++;
                $messages[] = "Error adding {$email}: " . $add_result->get_error_message();
                continue;
            }
            
            $added_count++;
            $messages[] = "Added {$email} as {$default_role}";
            
            if ( $assign_licenses && $license_index < count( $available_licenses ) ) {
                $license = $available_licenses[ $license_index ];
                $license_result = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::assign_license( $license->ID, $email );
                
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
    
    wp_send_json_success( [
        'added' => $added_count,
        'existing' => $existing_count,
        'errors' => $error_count,
        'licenses_assigned' => $licenses_assigned,
        'total_processed' => count( $emails ),
        'messages' => $messages
    ] );
});





// Enhanced Get Available Licenses with Detailed Info
add_action('wp_ajax_llmsgaa_get_available_licenses', function() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'llmsgaa_unified_actions')) {
        wp_send_json_error('Invalid nonce');
    }
    
    $group_id = intval($_POST['group_id'] ?? 0);
    
    if (!$group_id) {
        wp_send_json_error('Missing group ID');
    }
    
    // Get all access passes for this group
    $access_passes = get_posts([
        'post_type' => 'llms_access_pass',
        'meta_query' => [
            [
                'key' => 'group_id',
                'value' => $group_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);
    
    $detailed_licenses = [];
    
    foreach ($access_passes as $pass) {
        $pass_id = $pass->ID;
        
        // Get pass metadata
        $items = get_post_meta($pass_id, 'llmsgaa_pass_items', true);
        if (is_string($items)) {
            $items = json_decode($items, true);
        }
        
        // Calculate available seats
        $total_seats = is_array($items) ? array_sum(wp_list_pluck($items, 'quantity')) : 0;
        $assigned_licenses = get_post_meta($pass_id, 'llmsgaa_assigned_licenses', true);
        $used_seats = is_array($assigned_licenses) ? count($assigned_licenses) : 0;
        $available_seats = max(0, $total_seats - $used_seats);
        
        // Skip if no available seats
        if ($available_seats <= 0) {
            continue;
        }
        
        // Get course information from items
        $sku_map = get_option('llmsgaa_sku_map', []);
        
        foreach ($items as $item) {
            $course_id = null;
            $course_title = 'Unknown Course';
            
            // Try to get course from SKU mapping
            if (isset($item['sku']) && isset($sku_map[$item['sku']])) {
                $course_id = $sku_map[$item['sku']];
                $course = get_post($course_id);
                if ($course) {
                    $course_title = $course->post_title;
                }
            } else if (isset($item['label'])) {
                $course_title = $item['label'];
            }
            
            // Get start date if set
            $start_date = get_post_meta($pass_id, 'start_date', true);
            
            // Create license entries for each available seat
            for ($i = 0; $i < min($available_seats, $item['quantity']); $i++) {
                $detailed_licenses[] = [
                    'id' => $pass_id . '_' . $course_id . '_' . $i,
                    'pass_id' => $pass_id,
                    'pass_title' => $pass->post_title,
                    'course_id' => $course_id,
                    'course_title' => $course_title,
                    'start_date' => $start_date,
                    'start_date_formatted' => $start_date ? date('M j, Y', strtotime($start_date)) : 'Immediate',
                    'buyer_email' => get_post_meta($pass_id, 'buyer_id', true),
                    'order_number' => get_post_meta($pass_id, 'order_number', true),
                    'sku' => $item['sku'] ?? '',
                    'quantity' => $item['quantity'] ?? 1
                ];
            }
        }
    }
    
    wp_send_json_success($detailed_licenses);
});
