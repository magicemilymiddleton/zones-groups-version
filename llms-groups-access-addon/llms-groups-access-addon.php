<?php
/**
 * Plugin Name: LifterLMS Groups Advanced Access Addon
 * Description: Licenses & Group Orders with consent flow.
 * Version: Version 2.7
 * Author: Misadventures LLC - Emily Middleton
 * Text Domain: llms-groups-access-addon
 */

// Stop execution if accessed directly outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'LLMSGAA_PLUGIN_FILE' ) ) {
    define( 'LLMSGAA_PLUGIN_FILE', __FILE__ );
}

// Define plugin directory path for use in includes and asset loading.
define( 'LLMSGAA_DIR', plugin_dir_path( __FILE__ ) );

// Define plugin URL path for loading assets like CSS/JS.
define( 'LLMSGAA_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader if available (useful for PSR-4 classes).
if ( file_exists( LLMSGAA_DIR . 'vendor/autoload.php' ) ) {
    require_once LLMSGAA_DIR . 'vendor/autoload.php';
}

// ===== CORE INCLUDES =====
require_once LLMSGAA_DIR . 'includes/PluginRegistrar.php';
require_once LLMSGAA_DIR . 'includes/Feature/UnifiedMemberManager.php';
require_once LLMSGAA_DIR . 'includes/Feature/Shortcodes/StudentDashboard.php';
require_once LLMSGAA_DIR . 'includes/Feature/Shortcodes/SingleLicenseActivation.php';
\LLMSGAA\Feature\Shortcodes\SingleLicenseActivation::init();

add_action( 'plugins_loaded', function() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/email-customization.php';
}, 5 );



// ===== INTEGRATIONS =====
// Load FluentCRM Integration
require_once LLMSGAA_DIR . 'includes/Feature/integrations/fluentcrm/fluentcrm-integration.php';

// ===== ASSETS =====
add_action( 'wp_enqueue_scripts', 'llmsgaa_enqueue_student_dashboard_styles' );
function llmsgaa_enqueue_student_dashboard_styles() {
    if ( is_user_logged_in() && ( is_page() || is_single() || is_home() ) ) {
        wp_enqueue_style( 
            'llmsgaa-student-dashboard', 
            LLMSGAA_URL . 'public/css/student-dashboard.css', 
            [], 
            '1.0.2' 
        );
    }
}



// ===== AJAX HANDLERS =====
// Load all AJAX handlers
require_once LLMSGAA_DIR . 'includes/ajax-handlers.php';

// ===== ACTION SCHEDULER HANDLERS =====
require_once LLMSGAA_DIR . 'includes/Feature/Scheduler/ScheduleHandler.php';

// ===== INITIALIZE PLUGIN =====
LLMSGAA\PluginRegistrar::init();

// ===== DEVELOPMENT/DEBUG =====
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    // Flush rewrite rules in development
    add_action( 'init', function() {
        flush_rewrite_rules();
    });
    
    // Step completion logging
    add_action( 'updated_user_meta', function( $meta_id, $user_id, $meta_key, $meta_value ) {
        if ( 0 === strpos( $meta_key, '_step_' ) && strpos( $meta_key, '_completed' ) ) {
            error_log( sprintf(
                '[StepComplete] user_id=%d, meta_key=%s, meta_value=%s',
                $user_id,
                $meta_key,
                $meta_value
            ) );
        }
    }, 10, 4 );
}


// Monitor ALL database changes to lifterlms_user_postmeta
add_action( 'init', function() {
    // Log file path
    $log_file = WP_CONTENT_DIR . '/fluentcrm-debug.log';
    
    // Create custom logging function
    function fluentcrm_debug_log( $message ) {
        $log_file = WP_CONTENT_DIR . '/fluentcrm-debug.log';
        $timestamp = date( 'Y-m-d H:i:s' );
        file_put_contents( $log_file, "[{$timestamp}] {$message}\n", FILE_APPEND );
    }
    
    // Monitor database queries
    add_filter( 'query', function( $query ) {
        if ( strpos( $query, 'lifterlms_user_postmeta' ) !== false ) {
            fluentcrm_debug_log( "DB Query: " . $query );
            
            // If it's an INSERT or UPDATE for _group_role
            if ( strpos( $query, '_group_role' ) !== false ) {
                fluentcrm_debug_log( "🎯 GROUP ROLE QUERY DETECTED!" );
                
                // Extract user_id from the query
                if ( preg_match( '/user_id[\'"\s]*[=,][\'"\s]*(\d+)/', $query, $matches ) ) {
                    $user_id = $matches[1];
                    fluentcrm_debug_log( "Found user_id: {$user_id}" );
                    
                    // Force immediate webhook
                    add_action( 'shutdown', function() use ( $user_id ) {
                        fluentcrm_debug_log( "Shutdown hook - processing user {$user_id}" );
                        llmsgaa_fluentcrm_process_user( $user_id );
                    } );
                }
            }
        }
        return $query;
    } );
    
    // Monitor ALL user creation methods
    $user_creation_hooks = [
        'user_register',
        'wpmu_new_user',
        'wp_insert_user',
        'woocommerce_created_customer',
        'woocommerce_new_customer',
        'shopify_user_created',
        'llms_user_registered',
        'llms_user_enrolled_in_course',
        'groups_created_user_group',
    ];
    
    foreach ( $user_creation_hooks as $hook ) {
        add_action( $hook, function( $user_id ) use ( $hook ) {
            fluentcrm_debug_log( "Hook fired: {$hook} for user {$user_id}" );
            
            // Check for groups after 10 seconds
            wp_schedule_single_event( time() + 10, 'llmsgaa_debug_check_user_groups', [ $user_id ] );
        }, 1 );
    }
    
    // Create debug check action
    add_action( 'llmsgaa_debug_check_user_groups', function( $user_id ) {
        global $wpdb;
        
        fluentcrm_debug_log( "=== Debug check for user {$user_id} ===" );
        
        // Check if user exists
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            fluentcrm_debug_log( "User {$user_id} not found!" );
            return;
        }
        
        fluentcrm_debug_log( "User found: {$user->user_email}" );
        
        // Check for group roles
        $roles = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lifterlms_user_postmeta 
             WHERE user_id = %d AND meta_key = '_group_role'",
            $user_id
        ) );
        
        if ( $roles ) {
            fluentcrm_debug_log( "Found " . count( $roles ) . " group roles:" );
            foreach ( $roles as $role ) {
                fluentcrm_debug_log( "- Group {$role->post_id}: {$role->meta_value}" );
            }
            
            // Force webhook send
            fluentcrm_debug_log( "Forcing webhook send..." );
            llmsgaa_fluentcrm_process_user( $user_id );
        } else {
            fluentcrm_debug_log( "No group roles found for user {$user_id}" );
        }
        
        fluentcrm_debug_log( "=== End debug check ===" );
    } );
} );

// Add admin bar button to view debug log
add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $wp_admin_bar->add_node( [
        'id'    => 'fluentcrm-debug',
        'title' => '📋 FluentCRM Debug Log',
        'href'  => admin_url( 'admin.php?page=fluentcrm-view-log' ),
    ] );
}, 100 );

// Add page to view debug log
add_action( 'admin_menu', function() {
    add_submenu_page(
        null, // Hidden page
        'FluentCRM Debug Log',
        'FluentCRM Debug Log',
        'manage_options',
        'fluentcrm-view-log',
        function() {
            $log_file = WP_CONTENT_DIR . '/fluentcrm-debug.log';
            
            echo '<div class="wrap">';
            echo '<h1>FluentCRM Debug Log</h1>';
            
            if ( isset( $_GET['clear'] ) ) {
                file_put_contents( $log_file, '' );
                echo '<div class="notice notice-success"><p>Log cleared!</p></div>';
            }
            
            echo '<p><a href="' . admin_url( 'admin.php?page=fluentcrm-view-log&clear=1' ) . '" class="button">Clear Log</a></p>';
            
            if ( file_exists( $log_file ) ) {
                $log_content = file_get_contents( $log_file );
                echo '<pre style="background: #f0f0f0; padding: 20px; overflow: auto; max-height: 600px;">';
                echo esc_html( $log_content );
                echo '</pre>';
            } else {
                echo '<p>No log file found.</p>';
            }
            
            echo '</div>';
        }
    );
} );



// Test function to manually process a user
add_action( 'init', function() {
    if ( isset( $_GET['test_fluentcrm_user'] ) && current_user_can( 'manage_options' ) ) {
        $user_id = intval( $_GET['test_fluentcrm_user'] );
        
        echo '<h1>Testing FluentCRM for User ' . $user_id . '</h1>';
        
        // Get user
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            die( 'User not found' );
        }
        
        echo '<p>User: ' . $user->user_email . '</p>';
        
        // Check groups
        global $wpdb;
        $roles = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lifterlms_user_postmeta 
             WHERE user_id = %d AND meta_key = '_group_role'",
            $user_id
        ) );
        
        echo '<h2>Group Roles:</h2>';
        echo '<pre>' . print_r( $roles, true ) . '</pre>';
        
        // Force process
        echo '<h2>Processing user...</h2>';
        llmsgaa_fluentcrm_process_user( $user_id );
        
        echo '<p>Check error log for results.</p>';
        die();
    }
} );


