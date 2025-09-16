<?php
namespace LLMSGAA\Feature\AccessLogic;

// Exit if accessed directly to prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AccessLogic\Override
 *
 * Overrides LifterLMS default group access logic.
 * - Ensures primary group admins always have access.
 * - Ensures users listed in custom _llms_members meta always have access.
 * - Shows restricted message with admin list for non-authorized users.
 * - Falls back to LifterLMS default behavior otherwise.
 */
class Override {

    /**
     * Register the access filter.
     */
    public static function init() {
        add_filter( 'llms_group_user_can_access', [ __CLASS__, 'check' ], 9999, 3 );
        
        // Add content filter to show restricted message
        add_filter( 'the_content', [ __CLASS__, 'filter_restricted_content' ], 1 );
        
        // Add template redirect for non-admins - runs early
        add_action( 'template_redirect', [ __CLASS__, 'redirect_non_admins' ], 1 );
        
        // Add debug shortcode
        add_shortcode( 'llmsgaa_group_debug', [ __CLASS__, 'debug_shortcode' ] );
        
        // Handle the access denied page
        add_action( 'init', [ __CLASS__, 'register_access_denied_endpoint' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
        add_action( 'template_include', [ __CLASS__, 'access_denied_template' ] );
    }

    /**
     * Custom access check logic.
     *
     * @param bool $can      Original access result.
     * @param int  $group_id Group post ID.
     * @param int  $user_id  User ID.
     * @return bool
     */
    public static function check( $can, $group_id, $user_id ) {
        // Store debug info for console
        $debug = [
            'original_can' => $can,
            'group_id' => $group_id,
            'user_id' => $user_id,
            'checks' => []
        ];

        // RESTRICT TO ADMINS ONLY - Regular members cannot view
        
        // WordPress admins always have access
        $is_wp_admin = user_can( $user_id, 'manage_options' );
        $debug['checks']['is_wp_admin'] = $is_wp_admin;
        if ( $is_wp_admin ) {
            $debug['result'] = 'true (WP Admin)';
            self::$debug_info = $debug;
            return true;
        }

        // Check if user is a group admin in lifterlms_user_postmeta
        $is_group_admin = self::is_group_admin( $user_id, $group_id );
        $debug['checks']['is_group_admin'] = $is_group_admin;
        if ( $is_group_admin ) {
            $debug['result'] = 'true (Group Admin)';
            self::$debug_info = $debug;
            return true;
        }

        // Give access to the primary admin
        $primary_admin = (int) get_post_meta( $group_id, 'primary_admin', true );
        $debug['checks']['primary_admin_id'] = $primary_admin;
        $debug['checks']['is_primary_admin'] = ($primary_admin && $primary_admin === $user_id);
        if ( $primary_admin && $primary_admin === $user_id ) {
            $debug['result'] = 'true (Primary Admin)';
            self::$debug_info = $debug;
            return true;
        }

        // REMOVED: Regular members do NOT get access
        // Only admins can view the group page
        
        $members = get_post_meta( $group_id, '_llms_members', true );
        $debug['checks']['members_array'] = is_array($members) ? count($members) . ' members' : 'not an array';
        $debug['checks']['is_member'] = is_array( $members ) && in_array( $user_id, array_map( 'intval', $members ), true );
        
        // Return false - no access for non-admins
        $debug['result'] = 'false (not an admin)';
        self::$debug_info = $debug;
        return false;
    }
    
    /**
     * Store debug info.
     */
    private static $debug_info = null;

    /**
     * Check if user is a group admin.
     */
    public static function is_group_admin( $user_id, $group_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lifterlms_user_postmeta';
        
        $role = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value 
             FROM {$table_name} 
             WHERE user_id = %d 
             AND post_id = %d 
             AND meta_key = %s 
             AND meta_value = %s",
            $user_id,
            $group_id,
            '_group_role',
            'admin'
        ) );

        return $role === 'admin';
    }

    /**
     * Filter content to show restricted message.
     */
    public static function filter_restricted_content( $content ) {
        // Only on singular group pages
        if ( ! is_singular( 'llms_group' ) ) {
            return $content;
        }

        $group_id = get_the_ID();
        $user_id = get_current_user_id();

        // Check if user has access using our check method
        $has_access = self::check( false, $group_id, $user_id );

        if ( ! $has_access ) {
            return self::get_restricted_message( $group_id );
        }

        // If user has access but we want to show who admins are at the top
        if ( self::should_show_admin_info() ) {
            return self::get_admin_info_box( $group_id ) . $content;
        }

        return $content;
    }
    
    /**
     * Redirect non-admins away from group pages.
     */
    public static function redirect_non_admins() {
        if ( ! is_singular( 'llms_group' ) ) {
            return;
        }
        
        $group_id = get_the_ID();
        $user_id = get_current_user_id();
        
        // Check if user is admin
        if ( ! self::is_user_admin( $user_id, $group_id ) ) {
            // Redirect to access denied page with group info
            $redirect_url = add_query_arg( [
                'llmsgaa_access_denied' => 1,
                'group_id' => $group_id,
                'return_url' => urlencode( get_permalink( $group_id ) )
            ], home_url( '/group-access-denied/' ) );
            
            wp_redirect( $redirect_url );
            exit;
        }
    }
    
    /**
     * Register access denied endpoint.
     */
    public static function register_access_denied_endpoint() {
        add_rewrite_rule( '^group-access-denied/?', 'index.php?llmsgaa_access_denied=1', 'top' );
    }
    
    /**
     * Add query vars.
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'llmsgaa_access_denied';
        return $vars;
    }
    
    /**
     * Load custom template for access denied page.
     */
    public static function access_denied_template( $template ) {
        if ( get_query_var( 'llmsgaa_access_denied' ) ) {
            // Create a simple template
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo( 'charset' ); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php _e( 'Access Denied', 'llms-groups-access-addon' ); ?> - <?php bloginfo( 'name' ); ?></title>
                <?php wp_head(); ?>
                <style>
                    body {
                        background: #f5f5f5;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        margin: 0;
                        padding: 0;
                    }
                    .access-denied-container {
                        max-width: 600px;
                        margin: 50px auto;
                        padding: 20px;
                    }
                    .access-denied-box {
                        background: white;
                        border-radius: 10px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        padding: 40px;
                        text-align: center;
                    }
                    .lock-icon {
                        font-size: 60px;
                        margin-bottom: 20px;
                    }
                    .admins-list {
                        background: #f8f9fa;
                        border-radius: 8px;
                        padding: 20px;
                        margin: 30px 0;
                        text-align: left;
                    }
                    .admin-item {
                        padding: 10px 0;
                        border-bottom: 1px solid #dee2e6;
                    }
                    .admin-item:last-child {
                        border-bottom: none;
                    }
                    .button {
                        display: inline-block;
                        padding: 12px 30px;
                        background: #007bff;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        margin: 10px 5px;
                    }
                    .button:hover {
                        background: #0056b3;
                    }
                    .button-secondary {
                        background: #6c757d;
                    }
                    .button-secondary:hover {
                        background: #545b62;
                    }
                </style>
            </head>
            <body>
                <?php
                $group_id = isset( $_GET['group_id'] ) ? intval( $_GET['group_id'] ) : 0;
                $group_title = $group_id ? get_the_title( $group_id ) : __( 'Unknown Group', 'llms-groups-access-addon' );
                $admins = $group_id ? self::get_group_admins( $group_id ) : [];
                ?>
                
                <div class="access-denied-container">
                    <div class="access-denied-box">
                        <div class="lock-icon">🔒</div>
                        
                        <h1><?php _e( 'Access Restricted', 'llms-groups-access-addon' ); ?></h1>
                        
                        <p style="font-size: 18px; color: #666; margin: 20px 0;">
                            <?php _e( 'You do not have permission to access', 'llms-groups-access-addon' ); ?><br>
                            <strong style="color: #333; font-size: 22px;"><?php echo esc_html( $group_title ); ?></strong>
                        </p>
                        
                        <p style="color: #dc3545; font-weight: 500; font-size: 16px;">
                            <?php _e( 'Only group administrators can view this page.', 'llms-groups-access-addon' ); ?>
                        </p>
                        
                        <?php if ( ! empty( $admins ) ) : ?>
                            <div class="admins-list">
                                <h3 style="margin-top: 0;"><?php _e( 'Group Administrators', 'llms-groups-access-addon' ); ?></h3>
                                <p><?php _e( 'Please contact one of these administrators for access:', 'llms-groups-access-addon' ); ?></p>
                                
                                <?php foreach ( $admins as $admin ) : ?>
                                    <div class="admin-item">
                                        <strong><?php echo esc_html( $admin->display_name ); ?></strong><br>
                                        <a href="mailto:<?php echo esc_attr( $admin->user_email ); ?>" style="color: #007bff;">
                                            <?php echo esc_html( $admin->user_email ); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div style="background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px; padding: 20px; margin: 30px 0;">
                                <p style="color: #856404; margin: 0;">
                                    <?php _e( 'No administrators found for this group.', 'llms-groups-access-addon' ); ?><br>
                                    <?php _e( 'Please contact your site administrator.', 'llms-groups-access-addon' ); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 30px;">
                            <?php if ( ! is_user_logged_in() ) : ?>
                                <a href="<?php echo esc_url( wp_login_url( $_GET['return_url'] ?? home_url() ) ); ?>" class="button">
                                    <?php _e( 'Log In', 'llms-groups-access-addon' ); ?>
                                </a>
                            <?php endif; ?>
                            
                            <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="button button-secondary">
                                <?php _e( 'Return to Dashboard', 'llms-groups-access-addon' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php wp_footer(); ?>
            </body>
            </html>
            <?php
            exit;
        }
        
        return $template;
    }
    
    /**
     * Check if user is an admin (WordPress admin or group admin).
     */
    public static function is_user_admin( $user_id, $group_id ) {
        // WordPress admins
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        
        // Group admins
        if ( self::is_group_admin( $user_id, $group_id ) ) {
            return true;
        }
        
        // Primary admin
        $primary_admin = (int) get_post_meta( $group_id, 'primary_admin', true );
        if ( $primary_admin && $primary_admin === $user_id ) {
            return true;
        }
        
        return false;
    }

    /**
     * Get all group admins.
     */
    public static function get_group_admins( $group_id ) {
        global $wpdb;

        $admins = [];

        // Get primary admin
        $primary_admin_id = (int) get_post_meta( $group_id, 'primary_admin', true );
        if ( $primary_admin_id ) {
            $user = get_userdata( $primary_admin_id );
            if ( $user ) {
                $admins[$primary_admin_id] = $user;
            }
        }

        // Get admins from lifterlms_user_postmeta
        $table_name = $wpdb->prefix . 'lifterlms_user_postmeta';
        
        $admin_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id 
             FROM {$table_name} 
             WHERE post_id = %d 
             AND meta_key = %s 
             AND meta_value = %s",
            $group_id,
            '_group_role',
            'admin'
        ) );

        foreach ( $admin_ids as $admin_id ) {
            if ( ! isset( $admins[$admin_id] ) ) {
                $user = get_userdata( $admin_id );
                if ( $user ) {
                    $admins[$admin_id] = $user;
                }
            }
        }

        return array_values( $admins );
    }

    /**
     * Get restricted access message.
     */
    public static function get_restricted_message( $group_id ) {
        $group_title = get_the_title( $group_id );
        $admins = self::get_group_admins( $group_id );

        ob_start();
        ?>
        <div class="llmsgaa-access-restricted" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 40px 20px; margin: 20px 0; text-align: center; max-width: 600px; margin-left: auto; margin-right: auto;">
            <div style="font-size: 48px; margin-bottom: 20px;">🔒</div>
            
            <h2 style="color: #dc3545; margin-bottom: 20px; font-size: 28px;">Access Restricted</h2>
            
            <p style="font-size: 18px; color: #495057; margin-bottom: 30px; line-height: 1.6;">
                You do not have permission to access the group<br>
                <strong style="color: #212529;"><?php echo esc_html( $group_title ); ?></strong><br>
                <span style="color: #dc3545; font-weight: 500;">Only group administrators can view this page.</span>
            </p>

            <?php if ( ! empty( $admins ) ) : ?>
                <div style="background: white; border-radius: 8px; padding: 30px; margin: 30px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="color: #343a40; margin-bottom: 20px; font-size: 20px;">Contact Group Administrators</h3>
                    <p style="color: #6c757d; margin-bottom: 25px;">
                        To request access, please contact one of the administrators below:
                    </p>
                    
                    <div style="text-align: left; max-width: 400px; margin: 0 auto;">
                        <?php foreach ( $admins as $index => $admin ) : ?>
                            <div style="<?php echo $index > 0 ? 'border-top: 1px solid #dee2e6; ' : ''; ?>padding: 15px 0;">
                                <div style="font-weight: 600; color: #212529; margin-bottom: 5px;">
                                    <?php echo esc_html( $admin->display_name ); ?>
                                </div>
                                <div>
                                    <a href="mailto:<?php echo esc_attr( $admin->user_email ); ?>" style="color: #007bff; text-decoration: none;">
                                        <?php echo esc_html( $admin->user_email ); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else : ?>
                <div style="background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px; padding: 20px; margin: 30px 0;">
                    <p style="color: #856404; margin: 0; font-size: 16px;">
                        <strong>No administrators found.</strong><br>
                        Please contact your site administrator for assistance.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( ! is_user_logged_in() ) : ?>
                <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #dee2e6;">
                    <p style="color: #6c757d; margin-bottom: 15px;">Already have access?</p>
                    <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" style="background: #007bff; color: white; padding: 12px 40px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 500;">
                        Log In
                    </a>
                </div>
            <?php else : ?>
                <div style="margin-top: 30px;">
                    <a href="<?php echo esc_url( home_url() ); ?>" style="color: #6c757d; text-decoration: none;">
                        ← Return to Home
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Add some custom CSS for better mobile responsiveness
        ?>
        <style>
        @media (max-width: 600px) {
            .llmsgaa-access-restricted {
                padding: 30px 15px !important;
            }
        }
        </style>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Debug shortcode to show access info.
     */
    public static function debug_shortcode( $atts ) {
        if ( ! is_singular( 'llms_group' ) ) {
            return '<p>This shortcode only works on group pages.</p>';
        }

        $group_id = get_the_ID();
        $user_id = get_current_user_id();

        ob_start();
        ?>
        <div style="background: #f0f0f0; padding: 20px; margin: 20px 0; border: 2px solid #333; font-family: monospace;">
            <h3 style="margin-top: 0;">🔍 Group Access Debug</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><strong>Group ID:</strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><?php echo $group_id; ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><strong>Group Title:</strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><?php echo get_the_title( $group_id ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><strong>User ID:</strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><?php echo $user_id ?: 'Not logged in'; ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><strong>Is WP Admin:</strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><?php echo current_user_can( 'manage_options' ) ? '✅ YES' : '❌ NO'; ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><strong>Is Group Admin (DB):</strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><?php echo self::is_group_admin( $user_id, $group_id ) ? '✅ YES' : '❌ NO'; ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><strong>Is Primary Admin:</strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;">
                        <?php 
                        $primary = get_post_meta( $group_id, 'primary_admin', true );
                        echo ( $primary == $user_id ) ? '✅ YES' : '❌ NO';
                        echo ' (Primary Admin ID: ' . ( $primary ?: 'none' ) . ')';
                        ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><strong>In _llms_members:</strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;">
                        <?php 
                        $members = get_post_meta( $group_id, '_llms_members', true );
                        $is_member = is_array( $members ) && in_array( $user_id, array_map( 'intval', $members ) );
                        echo $is_member ? '✅ YES' : '❌ NO';
                        echo ' (' . ( is_array( $members ) ? count( $members ) : 0 ) . ' total members)';
                        ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><strong>Is Admin (Combined):</strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ccc;"><?php echo self::is_user_admin( $user_id, $group_id ) ? '✅ YES' : '❌ NO'; ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Has Access:</strong></td>
                    <td style="padding: 5px;"><?php echo self::check( false, $group_id, $user_id ) ? '✅ YES' : '❌ NO'; ?></td>
                </tr>
            </table>
            
            <h4 style="margin-top: 20px;">Group Administrators:</h4>
            <?php
            $admins = self::get_group_admins( $group_id );
            if ( $admins ) {
                echo '<ul style="margin: 0;">';
                foreach ( $admins as $admin ) {
                    echo '<li>' . esc_html( $admin->display_name ) . ' (' . esc_html( $admin->user_email ) . ')</li>';
                }
                echo '</ul>';
            } else {
                echo '<p style="color: #999;">No administrators found.</p>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * Output debug info to console.
     */
    public static function output_debug_console() {
        if ( ! is_singular( 'llms_group' ) ) {
            return;
        }

        $group_id = get_the_ID();
        $user_id = get_current_user_id();
        
        // Get all the debug data
        $is_admin = self::is_user_admin( $user_id, $group_id );
        $has_access = self::check( false, $group_id, $user_id );
        $is_group_admin = self::is_group_admin( $user_id, $group_id );
        $primary_admin = get_post_meta( $group_id, 'primary_admin', true );
        $members = get_post_meta( $group_id, '_llms_members', true );
        
        // Check database directly
        global $wpdb;
        $table_name = $wpdb->prefix . 'lifterlms_user_postmeta';
        $group_role_raw = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$table_name} WHERE user_id = %d AND post_id = %d AND meta_key = '_group_role'",
            $user_id,
            $group_id
        ) );
        
        ?>
        <script type="text/javascript">
        console.log('=== LLMSGAA Override Debug ===');
        console.log('Page Info:', {
            group_id: <?php echo json_encode($group_id); ?>,
            group_title: <?php echo json_encode(get_the_title($group_id)); ?>,
            user_id: <?php echo json_encode($user_id); ?>,
            user_email: <?php echo json_encode($user_id ? wp_get_current_user()->user_email : 'not logged in'); ?>
        });
        
        console.log('Access Checks:', {
            has_access: <?php echo json_encode($has_access); ?>,
            is_admin: <?php echo json_encode($is_admin); ?>,
            is_wp_admin: <?php echo json_encode(current_user_can('manage_options')); ?>,
            is_group_admin: <?php echo json_encode($is_group_admin); ?>,
            is_primary_admin: <?php echo json_encode($primary_admin == $user_id); ?>
        });
        
        console.log('Database Values:', {
            primary_admin_id: <?php echo json_encode($primary_admin); ?>,
            group_role_in_db: <?php echo json_encode($group_role_raw); ?>,
            members_array: <?php echo json_encode(is_array($members) ? 'array with ' . count($members) . ' members' : 'not an array'); ?>,
            is_in_members: <?php echo json_encode(is_array($members) && in_array($user_id, array_map('intval', $members))); ?>
        });
        
        <?php if (is_array($members) && count($members) < 20): ?>
        console.log('Members List:', <?php echo json_encode($members); ?>);
        <?php endif; ?>
        
        <?php if (self::$debug_info): ?>
        console.log('Last Check Debug:', <?php echo json_encode(self::$debug_info); ?>);
        <?php endif; ?>
        
        console.log('=== End Debug ===');
        
        // Visual indicator
        console.log('%c' + (<?php echo json_encode($is_admin); ?> ? '👑 You ARE an admin' : '👤 You are NOT an admin'), 
            'background: ' + (<?php echo json_encode($is_admin); ?> ? '#4CAF50' : '#FF9800') + '; color: white; padding: 5px 10px; font-size: 14px; font-weight: bold;');
        </script>
        <?php
    }
}

// Initialize
Override::init();