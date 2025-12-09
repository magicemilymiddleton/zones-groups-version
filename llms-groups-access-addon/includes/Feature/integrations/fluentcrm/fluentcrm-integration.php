<?php
/**
 * FluentCRM Integration for LifterLMS Groups
 * 
 * Handles two distinct flows:
 * 1. Shopify purchases → Group Admins → Admin webhook
 * 2. Admin invites → Group Members → Member webhook
 * 
 * @package LLMSGAA
 * @subpackage Integrations
 * @version 3.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FluentCRM Integration Class
 */
class LLMSGAA_FluentCRM_Integration {
    
    /**
     * FluentCRM Webhook endpoints
     */
    const WEBHOOK_ADMIN = 'https://dc.zonesofregulation.com/?fluentcrm=1&route=contact&hash=6c8b371e-19d3-4063-9fc7-ab15ea58f512';
    const WEBHOOK_MEMBER = 'https://dc.zonesofregulation.com/?fluentcrm=1&route=contact&hash=2bfc64d9-3fec-40b0-bd8a-c3e932a215d3';
    
    /**
     * Initialize the integration
     */
    public static function init() {
        // Shopify webhook endpoint for Group Admin purchases
        add_action( 'rest_api_init', [ __CLASS__, 'register_shopify_webhook' ] );
        
        // Monitor when members are added to groups (invited by admins)
        add_action( 'added_user_meta', [ __CLASS__, 'handle_member_added' ], 999, 4 );
        add_action( 'updated_user_meta', [ __CLASS__, 'handle_member_updated' ], 999, 4 );
        
        // Scheduled actions
        add_action( 'llmsgaa_process_fluentcrm_webhook', [ __CLASS__, 'process_webhook' ], 10, 3 );
        add_action( 'llmsgaa_fluentcrm_daily_sync', [ __CLASS__, 'daily_sync' ] );
        
        // Admin interface
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_menu' ], 100 );
        
        // Schedule daily sync if not already scheduled
        if ( ! wp_next_scheduled( 'llmsgaa_fluentcrm_daily_sync' ) ) {
            wp_schedule_event( time(), 'daily', 'llmsgaa_fluentcrm_daily_sync' );
        }
        
        // AJAX handler for manual member invites
        add_action( 'wp_ajax_llmsgaa_invite_member', [ __CLASS__, 'handle_ajax_invite' ] );
    }
    
    /**
     * Register Shopify webhook endpoint
     */
    public static function register_shopify_webhook() {
        register_rest_route( 'llmsgaa/v1', '/shopify-order', [
            'methods' => 'POST',
            'callback' => [ __CLASS__, 'handle_shopify_order' ],
            'permission_callback' => [ __CLASS__, 'verify_shopify_webhook' ],
        ] );
    }
    
    /**
     * Verify Shopify webhook signature
     */
    public static function verify_shopify_webhook( $request ) {
        $hmac_header = $request->get_header( 'X-Shopify-Hmac-Sha256' );
        
        if ( empty( $hmac_header ) ) {
            self::log( 'Shopify webhook rejected - no HMAC header', 'warning' );
            return false;
        }
        
        $webhook_secret = get_option( 'llmsgaa_shopify_webhook_secret', '' );
        
        if ( empty( $webhook_secret ) ) {
            self::log( 'Shopify webhook secret not configured - allowing for testing', 'warning' );
            return true; // Allow for testing, change to false in production
        }
        
        $calculated_hmac = base64_encode( hash_hmac( 'sha256', $request->get_body(), $webhook_secret, true ) );
        
        return hash_equals( $calculated_hmac, $hmac_header );
    }
    
    /**
     * Handle Shopify order webhook - Creates GROUP ADMINS
     * 
     * When someone purchases on Shopify, they become a Group Admin
     */
    public static function handle_shopify_order( $request ) {
        $order_data = $request->get_json_params();
        
        self::log( '🛍️ Shopify order received: ' . $order_data['name'], 'info' );
        
        // Extract customer information
        $customer = $order_data['customer'] ?? [];
        $email = $customer['email'] ?? '';
        
        if ( empty( $email ) ) {
            self::log( 'No customer email in Shopify order', 'error' );
            return new WP_REST_Response( [ 'error' => 'No customer email' ], 400 );
        }
        
        // Check if user exists
        $user = get_user_by( 'email', $email );
        
        if ( ! $user ) {
            // Create user from Shopify data
            $user_id = wp_create_user(
                sanitize_user( $email ),
                wp_generate_password(),
                $email
            );
            
            if ( is_wp_error( $user_id ) ) {
                self::log( 'Failed to create user: ' . $user_id->get_error_message(), 'error' );
                return new WP_REST_Response( [ 'error' => 'Failed to create user' ], 500 );
            }
            
            // Update user details
            wp_update_user( [
                'ID' => $user_id,
                'first_name' => $customer['first_name'] ?? '',
                'last_name' => $customer['last_name'] ?? '',
                'display_name' => trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) ),
            ] );
            
            // Send new user notification
            wp_new_user_notification( $user_id, null, 'user' );
            
            $user = get_user_by( 'id', $user_id );
            self::log( '✅ Created new user from Shopify order: ' . $email, 'success' );
        } else {
            self::log( '👤 Existing user found: ' . $email, 'info' );
        }
        
        // Store Shopify metadata
        update_user_meta( $user->ID, '_shopify_customer_id', $customer['id'] ?? '' );
        update_user_meta( $user->ID, '_shopify_last_order', $order_data['name'] );
        update_user_meta( $user->ID, '_shopify_last_order_date', current_time( 'mysql' ) );
        update_user_meta( $user->ID, '_shopify_total_spent', $order_data['total_price'] ?? 0 );
        
        // Mark user as Group Admin source
        update_user_meta( $user->ID, '_user_source', 'shopify_purchase' );
        update_user_meta( $user->ID, '_is_group_admin', 'yes' );
        
        // Process line items to find group products
        $groups_created = [];
        foreach ( $order_data['line_items'] as $item ) {
            // Check if this product should create a group
            $group_config = self::get_group_config_by_product( $item['product_id'], $item['variant_id'] );
            
            if ( $group_config ) {
                // Create or assign group
                $group_id = self::create_or_assign_group( $user->ID, $group_config, $item );
                
                if ( $group_id ) {
                    $groups_created[] = $group_id;
                    
                    // Add user as GROUP ADMIN
                    self::add_user_to_group( $user->ID, $group_id, 'admin' );
                    
                    self::log( sprintf( 
                        '👑 User %s is now ADMIN of group %d (Product: %s)',
                        $email,
                        $group_id,
                        $item['title']
                    ), 'success' );
                }
            }
        }
        
        // Send GROUP ADMIN webhook to FluentCRM
        self::send_admin_webhook( $user, $order_data, $groups_created );
        
        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Order processed - Group Admin created',
            'user_id' => $user->ID,
            'groups_created' => $groups_created,
            'role' => 'admin'
        ], 200 );
    }
    
    /**
     * Handle when members are added to groups (invited by admins)
     */
    public static function handle_member_added( $meta_id, $user_id, $meta_key, $meta_value ) {
        if ( $meta_key !== '_group_role' ) {
            return;
        }
        
        // Check if this is a member (not admin)
        if ( $meta_value === 'member' ) {
            // Check if this user came from Shopify (they would be admin, not member)
            $user_source = get_user_meta( $user_id, '_user_source', true );
            
            if ( $user_source !== 'shopify_purchase' ) {
                // This is a member invited by an admin
                self::log( "👥 Member added to group: User {$user_id} as {$meta_value}", 'info' );
                
                // Schedule webhook send (delayed to gather all data)
                wp_schedule_single_event( 
                    time() + 2, 
                    'llmsgaa_process_fluentcrm_webhook', 
                    [ $user_id, 'member', 'group_invite' ]
                );
            }
        }
    }
    
    /**
     * Handle when member roles are updated
     */
    public static function handle_member_updated( $meta_id, $user_id, $meta_key, $meta_value ) {
        if ( $meta_key === '_group_role' && $meta_value === 'member' ) {
            self::handle_member_added( $meta_id, $user_id, $meta_key, $meta_value );
        }
    }
    
    /**
     * Get group configuration based on Shopify product
     */
    private static function get_group_config_by_product( $product_id, $variant_id = null ) {
        // This is where you map Shopify products to group configurations
        // You can store this in options or custom post meta
        
        $product_mappings = get_option( 'llmsgaa_shopify_product_mappings', [] );
        
        // Check variant first, then product
        if ( $variant_id && isset( $product_mappings['variants'][$variant_id] ) ) {
            return $product_mappings['variants'][$variant_id];
        }
        
        if ( isset( $product_mappings['products'][$product_id] ) ) {
            return $product_mappings['products'][$product_id];
        }
        
        // Default configuration
        return [
            'create_group' => true,
            'group_size' => 10,
            'course_ids' => [], // Courses to grant access to
            'membership_ids' => [], // Memberships to grant
        ];
    }
    
    /**
     * Create or assign a group for the admin
     */
    private static function create_or_assign_group( $user_id, $config, $order_item ) {
        // Check if user already has a group
        $existing_groups = get_posts( [
            'post_type' => 'llms_group',
            'author' => $user_id,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ] );
        
        if ( ! empty( $existing_groups ) ) {
            return $existing_groups[0]->ID;
        }
        
        // Create new group
        $group_id = wp_insert_post( [
            'post_title' => sprintf( 'Group - %s', $order_item['title'] ),
            'post_type' => 'llms_group',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ] );
        
        if ( ! is_wp_error( $group_id ) ) {
            // Store group metadata
            update_post_meta( $group_id, '_shopify_product_id', $order_item['product_id'] );
            update_post_meta( $group_id, '_shopify_variant_id', $order_item['variant_id'] ?? '' );
            update_post_meta( $group_id, '_group_size', $config['group_size'] ?? 10 );
            update_post_meta( $group_id, '_group_courses', $config['course_ids'] ?? [] );
            update_post_meta( $group_id, '_group_memberships', $config['membership_ids'] ?? [] );
            
            self::log( "✅ Created group {$group_id} for user {$user_id}", 'success' );
        }
        
        return $group_id;
    }
    
    /**
     * Add user to LifterLMS group with specific role
     */
    private static function add_user_to_group( $user_id, $group_id, $role = 'member' ) {
        global $wpdb;
        
        // Check if already in group
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->prefix}lifterlms_user_postmeta 
             WHERE user_id = %d AND post_id = %d AND meta_key = '_group_role'",
            $user_id,
            $group_id
        ) );
        
        if ( $existing ) {
            // Update role if different
            $wpdb->update(
                $wpdb->prefix . 'lifterlms_user_postmeta',
                [ 'meta_value' => $role ],
                [ 'meta_id' => $existing ]
            );
            self::log( "Updated user {$user_id} role to {$role} in group {$group_id}", 'info' );
        } else {
            // Add to group
            $wpdb->insert(
                $wpdb->prefix . 'lifterlms_user_postmeta',
                [
                    'user_id' => $user_id,
                    'post_id' => $group_id,
                    'meta_key' => '_group_role',
                    'meta_value' => $role,
                    'updated_date' => current_time( 'mysql' )
                ]
            );
            self::log( "Added user {$user_id} to group {$group_id} as {$role}", 'success' );
        }
        
        // Grant course/membership access based on group settings
        self::grant_group_access( $user_id, $group_id );
    }
    
    /**
     * Grant course and membership access based on group settings
     */
    private static function grant_group_access( $user_id, $group_id ) {
        // Get group courses and memberships
        $courses = get_post_meta( $group_id, '_group_courses', true ) ?: [];
        $memberships = get_post_meta( $group_id, '_group_memberships', true ) ?: [];
        
        // Enroll in courses
        foreach ( $courses as $course_id ) {
            llms_enroll_student( $user_id, $course_id, 'group_assignment' );
            self::log( "Enrolled user {$user_id} in course {$course_id}", 'info' );
        }
        
        // Enroll in memberships
        foreach ( $memberships as $membership_id ) {
            llms_enroll_student( $user_id, $membership_id, 'group_assignment' );
            self::log( "Enrolled user {$user_id} in membership {$membership_id}", 'info' );
        }
    }
    
    /**
     * Send GROUP ADMIN webhook to FluentCRM
     */
    private static function send_admin_webhook( $user, $order_data, $group_ids = [] ) {
        $data = [
            'email' => $user->user_email,
            'first_name' => $user->first_name ?: $user->display_name,
            'last_name' => $user->last_name ?: '',
            'tags' => [ 'group-admin', 'shopify-customer', 'group-purchaser' ],
            'lists' => [ 'group-admins' ],
            'status' => 'subscribed',
            'custom_fields' => [
                'user_role' => 'group_admin',
                'source' => 'shopify_purchase',
                'shopify_order_number' => $order_data['name'] ?? '',
                'shopify_order_total' => $order_data['total_price'] ?? '',
                'shopify_customer_id' => $order_data['customer']['id'] ?? '',
                'group_ids' => implode( ',', $group_ids ),
                'total_groups' => count( $group_ids ),
                'purchase_date' => current_time( 'mysql' )
            ]
        ];
        
        $sent = self::send_webhook( self::WEBHOOK_ADMIN, $data );
        
        if ( $sent ) {
            // Mark webhook as sent
            update_user_meta( $user->ID, '_fluentcrm_admin_webhook_sent', current_time( 'mysql' ) );
            self::log( "✅ GROUP ADMIN webhook sent for {$user->user_email}", 'success' );
        }
        
        return $sent;
    }
    
    /**
     * Send GROUP MEMBER webhook to FluentCRM
     */
    private static function send_member_webhook( $user, $group_id, $source = 'admin_invite' ) {
        // Get the admin who owns this group
        $group = get_post( $group_id );
        $admin_user = get_user_by( 'id', $group->post_author );
        
        $data = [
            'email' => $user->user_email,
            'first_name' => $user->first_name ?: $user->display_name,
            'last_name' => $user->last_name ?: '',
            'tags' => [ 'group-member', 'invited-user' ],
            'lists' => [ 'group-members' ],
            'status' => 'subscribed',
            'custom_fields' => [
                'user_role' => 'group_member',
                'source' => $source,
                'group_id' => $group_id,
                'group_name' => get_the_title( $group_id ),
                'invited_by' => $admin_user ? $admin_user->user_email : '',
                'invitation_date' => current_time( 'mysql' )
            ]
        ];
        
        $sent = self::send_webhook( self::WEBHOOK_MEMBER, $data );
        
        if ( $sent ) {
            // Mark webhook as sent
            update_user_meta( $user->ID, "_fluentcrm_member_webhook_sent_{$group_id}", current_time( 'mysql' ) );
            self::log( "✅ GROUP MEMBER webhook sent for {$user->user_email}", 'success' );
        }
        
        return $sent;
    }
    
    /**
     * Process webhook sending (called by scheduled action)
     */
    public static function process_webhook( $user_id, $role, $source ) {
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            self::log( "User {$user_id} not found for webhook processing", 'error' );
            return;
        }
        
        if ( $role === 'member' ) {
            // Get the group(s) this user is a member of
            global $wpdb;
            $groups = $wpdb->get_results( $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->prefix}lifterlms_user_postmeta 
                 WHERE user_id = %d AND meta_key = '_group_role' AND meta_value = 'member'",
                $user_id
            ) );
            
            foreach ( $groups as $group ) {
                // Check if webhook already sent for this group
                $already_sent = get_user_meta( $user_id, "_fluentcrm_member_webhook_sent_{$group->post_id}", true );
                
                if ( ! $already_sent ) {
                    self::send_member_webhook( $user, $group->post_id, $source );
                }
            }
        }
    }
    
    /**
     * Send webhook to FluentCRM
     */
    private static function send_webhook( $url, $data ) {
        $response = wp_remote_post( $url, [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode( $data ),
        ] );
        
        if ( is_wp_error( $response ) ) {
            self::log( "❌ Webhook failed: " . $response->get_error_message(), 'error' );
            return false;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        if ( $code >= 200 && $code < 300 ) {
            self::log( "✅ Webhook sent successfully (HTTP {$code})", 'success' );
            return true;
        } else {
            self::log( "⚠️ Webhook returned code {$code}: {$body}", 'warning' );
            return false;
        }
    }
    
    /**
     * Handle AJAX member invite
     */
    public static function handle_ajax_invite() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'llmsgaa_invite_member' ) ) {
            wp_die( 'Security check failed' );
        }
        
        $email = sanitize_email( $_POST['email'] );
        $group_id = intval( $_POST['group_id'] );
        
        // Check if current user owns this group
        $group = get_post( $group_id );
        if ( $group->post_author != get_current_user_id() ) {
            wp_send_json_error( 'You do not have permission to invite to this group' );
        }
        
        // Get or create user
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            // Create user
            $user_id = wp_create_user( 
                sanitize_user( $email ), 
                wp_generate_password(),
                $email
            );
            
            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error( 'Failed to create user' );
            }
            
            $user = get_user_by( 'id', $user_id );
            
            // Send new user notification
            wp_new_user_notification( $user_id, null, 'user' );
        }
        
        // Add as member
        self::add_user_to_group( $user->ID, $group_id, 'member' );
        
        // Send member webhook
        self::send_member_webhook( $user, $group_id, 'admin_invite' );
        
        wp_send_json_success( [
            'message' => 'Member invited successfully',
            'user_id' => $user->ID
        ] );
    }
    
    /**
     * Daily sync to catch any missed webhooks
     */
    public static function daily_sync() {
        global $wpdb;
        
        self::log( "🔄 Starting daily sync", 'info' );
        
        // Get all users with group roles
        $group_users = $wpdb->get_results( "
            SELECT DISTINCT u.user_id, u.post_id as group_id, u.meta_value as role
            FROM {$wpdb->prefix}lifterlms_user_postmeta u
            WHERE u.meta_key = '_group_role' 
            AND u.meta_value IN ('admin', 'member')
            LIMIT 100
        " );
        
        $admins_processed = 0;
        $members_processed = 0;
        
        foreach ( $group_users as $group_user ) {
            $user = get_user_by( 'id', $group_user->user_id );
            if ( ! $user ) continue;
            
            if ( $group_user->role === 'admin' ) {
                // Check if admin webhook was sent
                $webhook_sent = get_user_meta( $user->ID, '_fluentcrm_admin_webhook_sent', true );
                
                if ( ! $webhook_sent ) {
                    // Send admin webhook
                    $data = [
                        'email' => $user->user_email,
                        'first_name' => $user->first_name ?: $user->display_name,
                        'last_name' => $user->last_name ?: '',
                        'tags' => [ 'group-admin' ],
                        'lists' => [ 'group-admins' ],
                        'status' => 'subscribed'
                    ];
                    
                    if ( self::send_webhook( self::WEBHOOK_ADMIN, $data ) ) {
                        update_user_meta( $user->ID, '_fluentcrm_admin_webhook_sent', current_time( 'mysql' ) );
                        $admins_processed++;
                    }
                }
            } else if ( $group_user->role === 'member' ) {
                // Check if member webhook was sent for this group
                $webhook_sent = get_user_meta( $user->ID, "_fluentcrm_member_webhook_sent_{$group_user->group_id}", true );
                
                if ( ! $webhook_sent ) {
                    if ( self::send_member_webhook( $user, $group_user->group_id, 'sync' ) ) {
                        $members_processed++;
                    }
                }
            }
        }
        
        self::log( "✅ Daily sync complete: {$admins_processed} admins, {$members_processed} members processed", 'info' );
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=llms_group',
            'FluentCRM Integration',
            'FluentCRM Integration',
            'manage_options',
            'llmsgaa-fluentcrm',
            [ __CLASS__, 'render_admin_page' ]
        );
    }
    
    /**
     * Add admin bar menu
     */
    public static function add_admin_bar_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $wp_admin_bar->add_node( [
            'id'    => 'fluentcrm-integration',
            'title' => '🔄 FluentCRM',
            'href'  => admin_url( 'edit.php?post_type=llms_group&page=llmsgaa-fluentcrm' ),
        ] );
    }
    
    /**
     * Render admin page
     */
    public static function render_admin_page() {
        // Handle actions
        if ( isset( $_POST['action'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'fluentcrm_admin' ) ) {
            switch ( $_POST['action'] ) {
                case 'sync_all':
                    self::daily_sync();
                    echo '<div class="notice notice-success"><p>Sync completed!</p></div>';
                    break;
                    
                case 'test_admin_webhook':
                    $user = wp_get_current_user();
                    $sent = self::send_admin_webhook( $user, [ 'name' => 'TEST-001', 'total_price' => '0.00' ], [] );
                    echo $sent 
                        ? '<div class="notice notice-success"><p>Admin webhook test sent!</p></div>'
                        : '<div class="notice notice-error"><p>Admin webhook test failed!</p></div>';
                    break;
                    
                case 'test_member_webhook':
                    $user = wp_get_current_user();
                    $sent = self::send_member_webhook( $user, 0, 'test' );
                    echo $sent 
                        ? '<div class="notice notice-success"><p>Member webhook test sent!</p></div>'
                        : '<div class="notice notice-error"><p>Member webhook test failed!</p></div>';
                    break;
                    
                case 'save_settings':
                    update_option( 'llmsgaa_shopify_webhook_secret', sanitize_text_field( $_POST['webhook_secret'] ) );
                    update_option( 'llmsgaa_fluentcrm_debug', isset( $_POST['debug_mode'] ) ? 1 : 0 );
                    echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
                    break;
            }
        }
        
        $webhook_secret = get_option( 'llmsgaa_shopify_webhook_secret', '' );
        $debug_mode = get_option( 'llmsgaa_fluentcrm_debug', 0 );
        
        // Get stats
        global $wpdb;
        $admin_count = $wpdb->get_var( "
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->prefix}lifterlms_user_postmeta 
            WHERE meta_key = '_group_role' AND meta_value = 'admin'
        " );
        
        $member_count = $wpdb->get_var( "
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->prefix}lifterlms_user_postmeta 
            WHERE meta_key = '_group_role' AND meta_value = 'member'
        " );
        ?>
        <div class="wrap">
            <h1>FluentCRM Integration</h1>
            
            <div class="card">
                <h2>📊 Statistics</h2>
                <p>
                    <strong>Group Admins:</strong> <?php echo intval( $admin_count ); ?><br>
                    <strong>Group Members:</strong> <?php echo intval( $member_count ); ?>
                </p>
            </div>
            
            <div class="card">
                <h2>🔗 Integration Endpoints</h2>
                
                <h3>Shopify Webhook URL (for Group Admin purchases):</h3>
                <code style="display: block; padding: 10px; background: #f0f0f0; margin: 10px 0;">
                    <?php echo esc_url( get_rest_url( null, 'llmsgaa/v1/shopify-order' ) ); ?>
                </code>
                <p class="description">Add this URL to your Shopify webhook settings for "Order creation" events.</p>
                
                <h3>FluentCRM Webhook Endpoints:</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Trigger</th>
                            <th>Webhook URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Group Admin</strong></td>
                            <td>Shopify Purchase</td>
                            <td><code style="font-size: 11px;"><?php echo esc_html( self::WEBHOOK_ADMIN ); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Group Member</strong></td>
                            <td>Admin Invitation</td>
                            <td><code style="font-size: 11px;"><?php echo esc_html( self::WEBHOOK_MEMBER ); ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2>⚙️ Settings</h2>
                <form method="post">
                    <?php wp_nonce_field( 'fluentcrm_admin' ); ?>
                    <input type="hidden" name="action" value="save_settings">
                    
                    <table class="form-table">
                        <tr>
                            <th>Shopify Webhook Secret</th>
                            <td>
                                <input type="text" name="webhook_secret" value="<?php echo esc_attr( $webhook_secret ); ?>" class="regular-text" />
                                <p class="description">The secret key from your Shopify webhook configuration (for webhook verification)</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Debug Mode</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?> />
                                    Enable debug logging
                                </label>
                                <p class="description">Logs will be saved to: <code><?php echo WP_CONTENT_DIR; ?>/fluentcrm-integration.log</code></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Settings</button>
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2>🧪 Testing & Manual Actions</h2>
                
                <h3>Test Webhooks</h3>
                <p class="description">Send test webhooks using your current user account:</p>
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field( 'fluentcrm_admin' ); ?>
                    <input type="hidden" name="action" value="test_admin_webhook">
                    <button type="submit" class="button">Test Admin Webhook</button>
                </form>
                
                <form method="post" style="display: inline; margin-left: 10px;">
                    <?php wp_nonce_field( 'fluentcrm_admin' ); ?>
                    <input type="hidden" name="action" value="test_member_webhook">
                    <button type="submit" class="button">Test Member Webhook</button>
                </form>
                
                <h3 style="margin-top: 20px;">Manual Sync</h3>
                <p class="description">Process any users who haven't been sent to FluentCRM:</p>
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field( 'fluentcrm_admin' ); ?>
                    <input type="hidden" name="action" value="sync_all">
                    <button type="submit" class="button">Run Daily Sync Now</button>
                </form>
            </div>
            
            <?php if ( $debug_mode ): ?>
            <div class="card">
                <h2>📋 Recent Log Entries</h2>
                <pre style="background: #23282d; color: #0a0; padding: 15px; max-height: 400px; overflow-y: auto; font-family: 'Courier New', monospace;">
<?php
$log_file = WP_CONTENT_DIR . '/fluentcrm-integration.log';
if ( file_exists( $log_file ) ) {
    $lines = file( $log_file );
    $recent = array_slice( $lines, -100 ); // Last 100 lines
    foreach ( $recent as $line ) {
        // Color code by log level
        if ( strpos( $line, '[success]' ) !== false ) {
            echo '<span style="color: #0f0;">' . esc_html( $line ) . '</span>';
        } elseif ( strpos( $line, '[error]' ) !== false ) {
            echo '<span style="color: #f00;">' . esc_html( $line ) . '</span>';
        } elseif ( strpos( $line, '[warning]' ) !== false ) {
            echo '<span style="color: #ff0;">' . esc_html( $line ) . '</span>';
        } elseif ( strpos( $line, '[info]' ) !== false ) {
            echo '<span style="color: #0ff;">' . esc_html( $line ) . '</span>';
        } else {
            echo esc_html( $line );
        }
    }
} else {
    echo 'No log entries yet.';
}
?>
                </pre>
                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                    <input type="hidden" name="action" value="download_fluentcrm_log">
                    <button type="submit" class="button">Download Full Log</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Enhanced logging with levels
     */
    private static function log( $message, $level = 'info' ) {
        if ( ! get_option( 'llmsgaa_fluentcrm_debug', 0 ) ) {
            return;
        }
        
        $log_file = WP_CONTENT_DIR . '/fluentcrm-integration.log';
        $timestamp = date( 'Y-m-d H:i:s' );
        $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
        
        file_put_contents( $log_file, $log_entry, FILE_APPEND );
        
        // Also use WordPress error log if WP_DEBUG is on
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[FluentCRM] [{$level}] {$message}" );
        }
    }
}

// Initialize the integration
add_action( 'init', [ 'LLMSGAA_FluentCRM_Integration', 'init' ] );