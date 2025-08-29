<?php
/**
 * File: /includes/Feature/BulkAssign/BulkAssignHandler.php
 * 
 * Create this new file in your plugin directory
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
        
        // Add our JavaScript to the admin area
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_bulk_assign_scripts']);
    }
    
    /**
     * Enqueue JavaScript for bulk assignment
     */
    public static function enqueue_bulk_assign_scripts($hook) {
        // Only load on your group pages
        if (strpos($hook, 'llms_group') === false && get_post_type() !== 'llms_group') {
            return;
        }
        
        wp_enqueue_script(
            'llmsgaa-bulk-assign',
            LLMSGAA_URL . 'public/js/llmsgaa-bulk-assign.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        // Pass AJAX URL and nonce to JavaScript
        wp_localize_script('llmsgaa-bulk-assign', 'llmsgaa_bulk', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('llmsgaa_bulk_assign'),
            'group_id' => get_the_ID()
        ]);
    }
    
    /**
     * Get available licenses for a group
     */
    public static function get_available_licenses() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'llmsgaa_bulk_assign')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $group_id = intval($_POST['group_id']);
        
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
            'posts_per_page' => -1
        ]);
        
        $licenses = [];
        
        foreach ($access_passes as $pass) {
            $total = intval(get_post_meta($pass->ID, 'quantity_total', true));
            $redeemed = intval(get_post_meta($pass->ID, 'quantity_redeemed', true));
            $available = max(0, $total - $redeemed);
            
            // Only include licenses with available seats
            if ($available > 0) {
                $licenses[] = [
                    'id' => $pass->ID,
                    'title' => $pass->post_title,
                    'total_seats' => $total,
                    'redeemed_seats' => $redeemed,
                    'available_seats' => $available,
                    'buyer_email' => get_post_meta($pass->ID, 'buyer_id', true)
                ];
            }
        }
        
        wp_send_json_success($licenses);
    }
    
    /**
     * Bulk assign licenses to members
     */
    public static function bulk_assign_licenses() {
        global $wpdb;
        
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'llmsgaa_bulk_assign')) {
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
        
        // For now, let's do a simple assignment to the first license
        // We'll enhance this in the next step
        $license_id = $license_ids[0];
        
        // Check available seats
        $total = intval(get_post_meta($license_id, 'quantity_total', true));
        $redeemed = intval(get_post_meta($license_id, 'quantity_redeemed', true));
        $available = max(0, $total - $redeemed);
        
        if ($available < count($member_emails)) {
            wp_send_json_error('Not enough seats available');
            return;
        }
        
        // Process each member
        foreach ($member_emails as $email) {
            // Get or create user
            $user = get_user_by('email', $email);
            if (!$user) {
                // For now, skip users that don't exist
                $results['errors'][] = "User $email not found";
                continue;
            }
            
            // Add to group using UnifiedMemberManager if it exists
            if (class_exists('\LLMSGAA\Feature\UnifiedMemberManager')) {
                $manager = new \LLMSGAA\Feature\UnifiedMemberManager();
                $added = $manager->add_member_to_group($user->ID, $group_id, 'member');
                
                if ($added) {
                    $results['success'][] = $email;
                    
                    // Increment redeemed count
                    $redeemed++;
                    update_post_meta($license_id, 'quantity_redeemed', $redeemed);
                } else {
                    $results['errors'][] = "Failed to add $email";
                }
            }
        }
        
        // Return results
        wp_send_json_success([
            'message' => sprintf('Assigned %d members successfully', count($results['success'])),
            'results' => $results
        ]);
    }
}