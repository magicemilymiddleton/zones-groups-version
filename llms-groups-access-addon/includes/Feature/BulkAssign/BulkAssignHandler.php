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
        add_action('wp_ajax_llmsgaa_get_available_licenses', [__CLASS__, 'get_available_licenses_detailed']);
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
/**
 * Handle multi-pass bulk assignment - UPDATES existing llms_group_order posts
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
        $course_id = isset($assignment['course_id']) ? intval($assignment['course_id']) : 0;
        
        if (!$email || !$pass_id) {
            $results['errors'][] = "Invalid data for assignment";
            continue;
        }
        
        // Find an available llms_group_order for this pass/course combination
        $available_order = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id 
                AND pm_group.meta_key = 'group_id' 
                AND pm_group.meta_value = %d
             LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = 'student_email'
             LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id 
                AND pm_product.meta_key = 'product_id'
             WHERE p.post_type = 'llms_group_order'
                AND p.post_status = 'publish'
                AND (pm_email.meta_value IS NULL OR pm_email.meta_value = '')
                AND (pm_product.meta_value = %d OR pm_product.meta_value IS NULL)
             LIMIT 1",
            $group_id,
            $course_id
        ));
        
        if (!$available_order) {
            $results['errors'][] = "No available license found for $email";
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
        
        // UPDATE the existing llms_group_order with assignment info
        update_post_meta($available_order, 'student_id', $user->ID);
        update_post_meta($available_order, 'student_email', $email);
        update_post_meta($available_order, 'assigned_date', current_time('mysql'));
        update_post_meta($available_order, 'assigned_by', get_current_user_id());
        
        // Make sure product_id is set if we have course_id
        if ($course_id) {
            update_post_meta($available_order, 'product_id', $course_id);
        }
        
        // Check if dates are already set, if not set defaults
        $existing_start = get_post_meta($available_order, 'start_date', true);
        $existing_end = get_post_meta($available_order, 'end_date', true);
        
        if (empty($existing_start)) {
            update_post_meta($available_order, 'start_date', date('Y-m-d'));
        }
        if (empty($existing_end)) {
            update_post_meta($available_order, 'end_date', date('Y-m-d', strtotime('+1 year')));
        }
        
        // Set status to trigger ScheduleHandler
        update_post_meta($available_order, 'status', 'pending');
        
        error_log("Updated existing order {$available_order} for {$email}");
        
        // Add user to group if not already member
        $table = $wpdb->prefix . 'lifterlms_user_postmeta';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$table} 
            WHERE user_id = %d AND post_id = %d AND meta_key = '_group_role'",
            $user->ID, $group_id
        ));
        
        if (!$existing) {
            $wpdb->insert($table, [
                'user_id' => $user->ID,
                'post_id' => $group_id,
                'meta_key' => '_group_role',
                'meta_value' => 'member',
                'updated_date' => current_time('mysql')
            ]);
        }
        
        // Trigger ScheduleHandler to process this order
        if (class_exists('\LLMSGAA\Feature\Scheduler\ScheduleHandler')) {
            \LLMSGAA\Feature\Scheduler\ScheduleHandler::process_order_scheduling($available_order);
            error_log("Triggered ScheduleHandler for order {$available_order}");
        }
        
        $results['success'][] = $email;
        $results['assigned_count']++;
    }
    
    ob_clean(); // Clear any error output
    
    if ($results['assigned_count'] === 0 && empty($results['errors'])) {
        wp_send_json_error('No assignments were processed');
    } else {
        wp_send_json_success($results);
    }
}
}