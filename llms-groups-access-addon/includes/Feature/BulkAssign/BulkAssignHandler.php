<?php
/**
 * File: /includes/Feature/BulkAssign/BulkAssignHandler.php
 * 
 * Enhanced bulk assignment handler with multi-pass support
 */

namespace LLMSGAA\Feature\BulkAssign;

if (!defined('ABSPATH')) {
    exit;
}

class BulkAssignHandler {
    
    /**
     * Initialize the bulk assignment feature
     */
    public static function init() {
        // AJAX handlers for bulk assignment
        add_action('wp_ajax_llmsgaa_get_available_licenses', [__CLASS__, 'get_available_licenses']);
        add_action('wp_ajax_llmsgaa_bulk_assign_licenses', [__CLASS__, 'bulk_assign_licenses']);
        add_action('wp_ajax_llmsgaa_get_available_licenses_detailed', [__CLASS__, 'get_available_licenses_detailed']);
        add_action('wp_ajax_llmsgaa_bulk_assign_multi_pass', [__CLASS__, 'bulk_assign_multi_pass']);

            // DEBUG CODE TEMPORARILY
    add_action('wp_ajax_llmsgaa_test_action', function() {
        wp_send_json_success('Test action works!');
    });
        
        // Add our JavaScript to BOTH admin and frontend
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_bulk_assign_scripts']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_bulk_assign_scripts']);
    }
    
    /**
     * Enqueue JavaScript for bulk assignment
     */
    public static function enqueue_bulk_assign_scripts($hook = '') {
        // Check if we're on a group page (admin or frontend)
        $is_group_page = false;
        
        // Admin check
        if (is_admin()) {
            $is_group_page = (strpos($hook, 'llms_group') !== false || get_post_type() === 'llms_group');
        }
        
        // Frontend check - this is where your passes.php view is displayed
        if (!is_admin()) {
            $is_group_page = (is_singular('llms_group') || isset($_GET['llmsgaa_group_id']) || isset($_POST['group_id']));
            
            // Also check if we're on a page that contains group content
            global $post;
            if ($post && (strpos($post->post_content, 'llmsgaa') !== false || strpos($_SERVER['REQUEST_URI'], 'group') !== false)) {
                $is_group_page = true;
            }
        }
        
        if (!$is_group_page) {
            return;
        }
        
        // Debug log
        error_log('BulkAssignHandler: Loading scripts for group page');
        
        wp_enqueue_script(
            'llmsgaa-bulk-assign',
            LLMSGAA_URL . 'public/js/llmsgaa-bulk-assign.js',
            ['jquery'],
            '2.0.0',
            true
        );
        
        // Pass AJAX URL and nonce to JavaScript
        wp_localize_script('llmsgaa-bulk-assign', 'llmsgaa_bulk', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('llmsgaa_unified_actions'), // Use the same nonce as your passes.php
            'group_id' => get_the_ID() ?: (isset($_GET['llmsgaa_group_id']) ? intval($_GET['llmsgaa_group_id']) : 0)
        ]);
    }
    
    /**
     * Get available licenses for a group with detailed information
     */
    public static function get_available_licenses() {
        // Verify nonce - accept both nonces for compatibility
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'llmsgaa_unified_actions') || 
                       wp_verify_nonce($_POST['nonce'] ?? '', 'llmsgaa_bulk_assign');
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $group_id = intval($_POST['group_id']);
        
        if (!$group_id) {
            wp_send_json_error('Invalid group ID');
            return;
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
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $licenses = [];
        
        foreach ($access_passes as $pass) {
            $total = intval(get_post_meta($pass->ID, 'quantity_total', true));
            $redeemed = intval(get_post_meta($pass->ID, 'quantity_redeemed', true));
            $available = max(0, $total - $redeemed);
            
            // Get product/course information
            $product_id = get_post_meta($pass->ID, 'product_id', true);
            $course_title = $pass->post_title;
            
            if ($product_id) {
                $product = get_post($product_id);
                if ($product) {
                    $course_title = $product->post_title;
                }
            }
            
            // Get dates
            $start_date = get_post_meta($pass->ID, 'start_date', true);
            $end_date = get_post_meta($pass->ID, 'end_date', true);
            
            // Format dates for display
            $start_date_formatted = $start_date ? date('M j, Y', strtotime($start_date)) : null;
            $end_date_formatted = $end_date ? date('M j, Y', strtotime($end_date)) : null;
            
            // Only include licenses with available seats
            if ($available > 0) {
                $licenses[] = [
                    'ID' => $pass->ID,
                    'id' => $pass->ID, // for backwards compatibility
                    'title' => $pass->post_title,
                    'course_title' => $course_title,
                    'product_id' => $product_id,
                    'total_seats' => $total,
                    'redeemed_seats' => $redeemed,
                    'available_seats' => $available,
                    'buyer_email' => get_post_meta($pass->ID, 'buyer_email', true),
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'start_date_formatted' => $start_date_formatted,
                    'end_date_formatted' => $end_date_formatted,
                    'status' => $available > 0 ? 'active' : 'full'
                ];
            }
        }
        
        wp_send_json_success($licenses);
    }
    
    /**
     * Get detailed license information for enhanced modal
     */
    public static function get_available_licenses_detailed() {
        // This is called by the enhanced modal
        return self::get_available_licenses();
    }
    
    /**
     * Handle multi-pass bulk assignment
     */
public static function bulk_assign_multi_pass() {
    ob_start(); // Capture any error output
    
    // Verify nonce
    $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'llmsgaa_unified_actions') || 
                   wp_verify_nonce($_POST['nonce'] ?? '', 'llmsgaa_bulk_assign');
    
    if (!$nonce_valid) {
        ob_clean();
        wp_send_json_error('Security check failed');
        return;
    }
    
    $assignments = json_decode(stripslashes($_POST['assignments'] ?? '[]'), true);
    $group_id = intval($_POST['group_id']);
    
    if (empty($assignments) || !$group_id) {
        ob_clean();
        wp_send_json_error('Invalid assignment data');
        return;
    }
    
    $results = [
        'success' => [],
        'errors' => [],
        'assigned_count' => 0
    ];
    
    global $wpdb;
    
    // Process each assignment
    foreach ($assignments as $assignment) {
        $email = sanitize_email($assignment['email']);
        $pass_id = intval($assignment['pass_id']);
        
        if (!$email || !$pass_id) {
            $results['errors'][] = "Invalid data for assignment";
            continue;
        }
        
        // Get or create user
        $user = get_user_by('email', $email);
        if (!$user) {
            // Create user if needed
            $username = strstr($email, '@', true);
            $username = sanitize_user($username);
            
            // Ensure unique username
            $base_username = $username;
            $counter = 1;
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }
            
            $user_id = wp_create_user($username, wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                $results['errors'][] = "Failed to create user for $email";
                continue;
            }
            
            $user = get_user_by('id', $user_id);
            
            // Send welcome email
            wp_new_user_notification($user_id, null, 'user');
        }
        
        // Create group order for this assignment
        $order_id = wp_insert_post([
            'post_type' => 'llms_group_order',
            'post_status' => 'publish',
            'post_title' => sprintf('Bulk Assigned - %s - Pass %d', $email, $pass_id)
        ]);
        
        if ($order_id && !is_wp_error($order_id)) {
            // Add meta data
            update_post_meta($order_id, 'group_id', $group_id);
            update_post_meta($order_id, 'student_id', $user->ID);
            update_post_meta($order_id, 'student_email', $email);
            update_post_meta($order_id, 'seat_id', $pass_id);
            update_post_meta($order_id, 'status', 'active');
            update_post_meta($order_id, 'assigned_date', current_time('mysql'));
            update_post_meta($order_id, 'assigned_by', get_current_user_id());
            
            // Update redeemed count for the pass
            $redeemed = intval(get_post_meta($pass_id, 'quantity_redeemed', true));
            update_post_meta($pass_id, 'quantity_redeemed', $redeemed + 1);
            
            // Add user to group using LifterLMS native method
            // This uses the proper LifterLMS tables instead of custom ones
            $table = $wpdb->prefix . 'lifterlms_user_postmeta';
            
            // Check if user is already in group
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_id FROM {$table} 
                WHERE user_id = %d AND post_id = %d AND meta_key = '_status'",
                $user->ID, $group_id
            ));
            
            if (!$existing) {
                // Add user to group
                $wpdb->insert($table, [
                    'user_id' => $user->ID,
                    'post_id' => $group_id,
                    'meta_key' => '_status',
                    'meta_value' => 'enrolled',
                    'updated_date' => current_time('mysql')
                ]);
                
                // Add role
                $wpdb->insert($table, [
                    'user_id' => $user->ID,
                    'post_id' => $group_id,
                    'meta_key' => '_group_role',
                    'meta_value' => 'member',
                    'updated_date' => current_time('mysql')
                ]);
            }
            
            $results['success'][] = $email;
            $results['assigned_count']++;
        } else {
            $results['errors'][] = "Failed to create order for $email";
        }
    }
    
    ob_clean(); // Clear any error output
    
    if ($results['assigned_count'] === 0 && empty($results['errors'])) {
        wp_send_json_error('No assignments were processed');
    } else {
        wp_send_json_success($results);
    }
}
    
    /**
     * Legacy bulk assign method (kept for backwards compatibility)
     */
    public static function bulk_assign_licenses() {
        // Verify nonce
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'llmsgaa_unified_actions') || 
                       wp_verify_nonce($_POST['nonce'] ?? '', 'llmsgaa_bulk_assign');
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Get input data
        $member_emails = isset($_POST['member_emails']) ? array_map('sanitize_email', $_POST['member_emails']) : [];
        $license_ids = isset($_POST['license_ids']) ? array_map('intval', $_POST['license_ids']) : [];
        $group_id = intval($_POST['group_id']);
        
        if (empty($member_emails) || empty($license_ids)) {
            wp_send_json_error('No members or licenses selected');
            return;
        }
        
        $results = [
            'success' => [],
            'errors' => []
        ];
        
        // Process assignments
        foreach ($license_ids as $index => $license_id) {
            if (!isset($member_emails[$index])) {
                break;
            }
            
            $email = $member_emails[$index];
            
            // Get or create user
            $user = get_user_by('email', $email);
            if (!$user) {
                $results['errors'][] = "User $email not found";
                continue;
            }
            
            // Add to group
            if (class_exists('\LLMSGAA\Feature\UnifiedMemberManager')) {
                $manager = new \LLMSGAA\Feature\UnifiedMemberManager();
                $added = $manager->add_member_to_group($user->ID, $group_id, 'member');
                
                if ($added) {
                    $results['success'][] = $email;
                    
                    // Update redeemed count
                    $redeemed = intval(get_post_meta($license_id, 'quantity_redeemed', true));
                    update_post_meta($license_id, 'quantity_redeemed', $redeemed + 1);
                } else {
                    $results['errors'][] = "Failed to add $email";
                }
            }
        }
        
        wp_send_json_success([
            'message' => sprintf('Assigned %d members successfully', count($results['success'])),
            'results' => $results
        ]);
    }
}