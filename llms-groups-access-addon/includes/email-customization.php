<?php
/**
 * Force Email Interception - Combined working version
 * 
 * File: llms-groups-access-addon/includes/email-customization.php
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Log that this file loaded
error_log( 'LLMSGAA: email-customization.php loaded at ' . current_time( 'mysql' ) );

/**
 * Main Email Customization Class
 */
class LLMSGAA_Email_Customization {
    
    private static $processing_invitation = false;
    private static $current_invitation = null;
    
    /**
     * Initialize the customization system
     */
    public static function init() {
        // Log that we're initializing
        error_log( 'LLMSGAA: Email customization init() called' );
        
        // Subject line customization (this works)
        add_filter( 'llms_group_invitation_email_subject', [ __CLASS__, 'custom_subject_line' ], 999 );
        
        // Try to intercept at EVERY possible point
        add_filter( 'wp_mail', [ __CLASS__, 'debug_all_emails' ], 1 );
        add_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ], 999 );
        
        // Try LifterLMS specific hooks
        add_filter( 'llms_email_body', [ __CLASS__, 'filter_llms_email_body' ], 999, 2 );
        add_filter( 'llms_email_message', [ __CLASS__, 'filter_llms_email_message' ], 999, 2 );
        add_filter( 'llms_email_content', [ __CLASS__, 'filter_llms_email_content' ], 999, 2 );
        
        // Try template override
        add_filter( 'llms_locate_template', [ __CLASS__, 'override_template' ], 999, 2 );
        add_filter( 'lifterlms_locate_template', [ __CLASS__, 'override_template' ], 999, 2 );
        
        // Track when invitation is being created
        add_action( 'llms_group_invitation_created', [ __CLASS__, 'track_invitation' ], 1, 2 );
        
        // Admin settings
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }
    
    /**
     * Track when an invitation is being processed
     */
    public static function track_invitation( $invitation_id, $invitation ) {
        self::$processing_invitation = true;
        self::$current_invitation = $invitation;
        
        error_log( 'LLMSGAA: Tracking invitation ID: ' . $invitation_id );
        
        // Set a flag that expires after this request
        add_action( 'shutdown', function() {
            self::$processing_invitation = false;
            self::$current_invitation = null;
        });
    }
    
    /**
     * Debug ALL emails going through wp_mail
     */
    public static function debug_all_emails( $args ) {
        // Log EVERY email
        error_log( 'LLMSGAA: wp_mail called' );
        error_log( 'LLMSGAA: To: ' . print_r( $args['to'], true ) );
        error_log( 'LLMSGAA: Subject: ' . $args['subject'] );
        error_log( 'LLMSGAA: Message length: ' . strlen( $args['message'] ) );
        error_log( 'LLMSGAA: Headers: ' . print_r( $args['headers'], true ) );
        
        // Check if this looks like an invitation
        $is_invitation = false;
        
        // Method 1: Check if we're tracking an invitation
        if ( self::$processing_invitation ) {
            $is_invitation = true;
            error_log( 'LLMSGAA: Detected invitation via tracking' );
        }
        
        // Method 2: Check subject and content
        if ( ! $is_invitation ) {
            if ( strpos( $args['subject'], '🎉' ) !== false ||
                 stripos( $args['subject'], 'invitation' ) !== false ||
                 stripos( $args['subject'], 'invite' ) !== false ||
                 stripos( $args['message'], 'invitation' ) !== false ||
                 stripos( $args['message'], 'invite' ) !== false ) {
                
                $is_invitation = true;
                error_log( 'LLMSGAA: Detected invitation via content' );
            }
        }
        
        if ( $is_invitation ) {
            error_log( 'LLMSGAA: This appears to be an invitation email!' );
            
            // Log first 1000 chars of original message
            error_log( 'LLMSGAA: Original message start: ' . substr( $args['message'], 0, 1000 ) );
            
            // EXTRACT THE ACTUAL INVITE URL from the original message
            $invite_url = '';
            $group_name = '';
            $site_title = get_bloginfo( 'name' );
            
            // Try to find ANY href with invite parameter
            if ( preg_match_all( '/href\s*=\s*["\']([^"\']+)["\']/', $args['message'], $matches ) ) {
                error_log( 'LLMSGAA: Found ' . count( $matches[1] ) . ' links in email' );
                foreach ( $matches[1] as $link ) {
                    error_log( 'LLMSGAA: Link found: ' . $link );
                    if ( strpos( $link, 'invite=' ) !== false ) {
                        $invite_url = html_entity_decode( $link );
                        error_log( 'LLMSGAA: >>> This is the invite link: ' . $invite_url );
                        break;
                    }
                }
            }
            
            // If no URL found, build it from the invitation object if available
            if ( ! $invite_url && self::$current_invitation ) {
                $invite_key = self::$current_invitation->get( 'invite_key' );
                if ( $invite_key ) {
                    $invite_url = home_url( '/dashboard/?invite=' . $invite_key );
                    error_log( 'LLMSGAA: Built URL from invitation object: ' . $invite_url );
                }
            }
            
            // If still no URL, try to extract just the code
            if ( ! $invite_url && preg_match( '/invite=([A-Za-z0-9]+)/', $args['message'], $matches ) ) {
                $invite_code = $matches[1];
                $invite_url = home_url( '/dashboard/?invite=' . $invite_code );
                error_log( 'LLMSGAA: Built URL from invite code: ' . $invite_url );
            }
            
            // Try to extract group name
            if ( preg_match( '/<strong>([^<]+)<\/strong>/', $args['message'], $matches ) ) {
                $group_name = $matches[1];
                error_log( 'LLMSGAA: Found group name: ' . $group_name );
            } elseif ( preg_match( '/"([^"]+)"/', $args['subject'], $matches ) ) {
                $group_name = $matches[1];
                error_log( 'LLMSGAA: Found group name from subject: ' . $group_name );
            }
            
            // REPLACE THE MESSAGE
            $args['message'] = self::get_custom_html_email( $invite_url, $group_name, $site_title );
            
            // Ensure HTML headers
            if ( ! is_array( $args['headers'] ) ) {
                $args['headers'] = array();
            }
            
            // Remove any existing content-type headers
            $args['headers'] = array_filter( $args['headers'], function( $header ) {
                return stripos( $header, 'content-type' ) === false;
            });
            
            // Add HTML content type
            $args['headers'][] = 'Content-Type: text/html; charset=UTF-8';
            
            error_log( 'LLMSGAA: Message replaced with custom HTML' );
            error_log( 'LLMSGAA: Final URL in email: ' . $invite_url );
        }
        
        return $args;
    }
    
    /**
     * Filter LLMS email body
     */
    public static function filter_llms_email_body( $body, $email ) {
        error_log( 'LLMSGAA: llms_email_body filter called' );
        if ( is_object( $email ) && method_exists( $email, 'get_id' ) ) {
            error_log( 'LLMSGAA: Email ID: ' . $email->get_id() );
            if ( $email->get_id() === 'group_invitation' ) {
                error_log( 'LLMSGAA: This is a group invitation in llms_email_body!' );
                return self::get_custom_html_email();
            }
        }
        return $body;
    }
    
    /**
     * Filter LLMS email message
     */
    public static function filter_llms_email_message( $message, $email ) {
        error_log( 'LLMSGAA: llms_email_message filter called' );
        if ( is_object( $email ) && method_exists( $email, 'get_id' ) ) {
            error_log( 'LLMSGAA: Email ID: ' . $email->get_id() );
            if ( $email->get_id() === 'group_invitation' ) {
                error_log( 'LLMSGAA: This is a group invitation in llms_email_message!' );
                return self::get_custom_html_email();
            }
        }
        return $message;
    }
    
    /**
     * Filter LLMS email content
     */
    public static function filter_llms_email_content( $content, $email ) {
        error_log( 'LLMSGAA: llms_email_content filter called' );
        if ( is_object( $email ) && method_exists( $email, 'get_id' ) ) {
            error_log( 'LLMSGAA: Email ID: ' . $email->get_id() );
            if ( $email->get_id() === 'group_invitation' ) {
                error_log( 'LLMSGAA: This is a group invitation in llms_email_content!' );
                return self::get_custom_html_email();
            }
        }
        return $content;
    }
    
    /**
     * Override template
     */
    public static function override_template( $template, $template_name ) {
        error_log( 'LLMSGAA: Template override called for: ' . $template_name );
        
        if ( $template_name === 'emails/invitation.php' ) {
            error_log( 'LLMSGAA: Overriding invitation template!' );
            
            // Create a temp file with our content
            $upload_dir = wp_upload_dir();
            $temp_file = $upload_dir['basedir'] . '/llms-custom-invitation.php';
            
            $content = '<?php echo "' . addslashes( self::get_custom_html_email() ) . '"; ?>';
            file_put_contents( $temp_file, $content );
            
            return $temp_file;
        }
        
        return $template;
    }
    
    /**
     * Set HTML content type
     */
    public static function set_html_content_type( $content_type ) {
        return 'text/html';
    }
    
    /**
     * Get custom HTML email
     */
    private static function get_custom_html_email( $invite_url = '', $group_name = '', $site_title = '' ) {
        $primary_color = get_option( 'llmsgaa_primary_color', '#667eea' );
        $secondary_color = get_option( 'llmsgaa_secondary_color', '#764ba2' );
        $header_text = get_option( 'llmsgaa_header_text', 'You\'re Invited!' );
        $header_subtext = get_option( 'llmsgaa_header_subtext', 'Join our exclusive learning community' );
        $cta_text = get_option( 'llmsgaa_cta_text', 'Accept Invitation' );
        
        // Default values
        if ( ! $invite_url ) {
            $invite_url = '{invite_url}';
        }
        if ( ! $group_name ) {
            $group_name = '{group_name}';
        }
        if ( ! $site_title ) {
            $site_title = get_bloginfo( 'name' );
        }
        
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html( $header_text ) . '</title>
</head>
<body style="margin: 0; padding: 0; background: #f7f7f7; font-family: Arial, sans-serif;">
    <div style="width: 100%; background: #f7f7f7; padding: 40px 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            
            <!-- CUSTOM HEADER -->
            <div style="background: linear-gradient(135deg, ' . $primary_color . ' 0%, ' . $secondary_color . ' 100%); padding: 40px; text-align: center;">
                <h1 style="color: white; margin: 0; font-size: 32px;">
                    ' . esc_html( $header_text ) . '
                </h1>
                <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">
                    ' . esc_html( $header_subtext ) . '
                </p>
            </div>
            
            <!-- BODY -->
            <div style="padding: 40px;">
                <p style="font-size: 16px; line-height: 1.6; color: #333;">
                    You have been invited to join <strong style="color: ' . $primary_color . ';">' . esc_html( $group_name ) . '</strong> at <strong>' . esc_html( $site_title ) . '</strong>.
                </p>
                
                <p style="font-size: 16px; line-height: 1.6; color: #333;">
                    Click the button below to accept your invitation and get started:
                </p>
                
                <div style="text-align: center; margin: 40px 0;">
                    <a href="' . esc_attr( $invite_url ) . '" style="display: inline-block; padding: 18px 40px; background: linear-gradient(135deg, ' . $primary_color . ' 0%, ' . $secondary_color . ' 100%); color: white; text-decoration: none; font-size: 18px; font-weight: bold; border-radius: 50px;">
                        ' . esc_html( $cta_text ) . '
                    </a>
                </div>
                
                <p style="text-align: center; color: #666; font-size: 14px;">
                    <em>This invitation is unique to you. Please do not share it.</em>
                </p>
            </div>
            
            <!-- FOOTER -->
            <div style="background: #f9f9f9; padding: 30px; text-align: center; border-top: 1px solid #e5e5e5;">
                <p style="color: #999; margin: 0 0 10px 0; font-size: 13px;">
                    Can\'t click the button? Copy and paste this link:
                </p>
                <p style="word-break: break-all; margin: 0;">
                    <a href="' . esc_attr( $invite_url ) . '" style="color: ' . $primary_color . '; text-decoration: none; font-size: 12px;">
                        ' . esc_html( $invite_url ) . '
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Custom subject line
     */
    public static function custom_subject_line() {
        $args = func_get_args();
        $group_name = isset( $args[2] ) ? $args[2] : 'Your Group';
        
        $custom_subject = get_option( 'llmsgaa_email_subject', '🎉 {group_name} - Exclusive Invitation Inside!' );
        $custom_subject = str_replace( '{group_name}', $group_name, $custom_subject );
        
        error_log( 'LLMSGAA: Custom subject line called, returning: ' . $custom_subject );
        
        return $custom_subject;
    }
    
    /**
     * Add settings page
     */
    public static function add_settings_page() {
        add_submenu_page(
            'lifterlms',
            'Email Customization',
            'Email Templates',
            'manage_options',
            'llms-email-customization',
            [ __CLASS__, 'render_settings_page' ]
        );
    }
    
    /**
     * Register settings
     */
/**
 * Register settings
 */
public static function register_settings() {
    // Original settings
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_email_subject' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_header_text' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_header_subtext' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_cta_text' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_primary_color' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_secondary_color' );
    
    // Missing settings that need to be registered
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_logo_url' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_welcome_message' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_body_text' );  // This was missing!
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_body_subtext' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_cta_disclaimer' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_footer_help_text' );
    
    
    // Styling settings
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_text_color' );
    register_setting( 'llmsgaa_email_settings', 'llmsgaa_bg_color' );
}
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap llms-email-settings">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Settings saved successfully!</strong></p>
                </div>
            <?php endif; ?>
            
            <div class="settings-wrapper">
                <div class="settings-main">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'llmsgaa_email_settings' ); ?>
                        
                        <div class="nav-tab-wrapper">
                            <a href="#content" class="nav-tab nav-tab-active">Content</a>
                            <a href="#styling" class="nav-tab">Styling</a>
                            <a href="#preview" class="nav-tab">Preview</a>
                        </div>
                        
                        <!-- Content Tab -->
                        <div id="content" class="tab-content active">
                            <h2>Email Content Settings</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="llmsgaa_email_subject">Email Subject</label>
                                    </th>
                                    <td>
                                        <input type="text" id="llmsgaa_email_subject" name="llmsgaa_email_subject" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_email_subject', '🎉 {group_name} - Exclusive Invitation Inside!' ) ); ?>" 
                                               class="large-text" />
                                        <p class="description">Use {group_name} for the group name, {site_title} for site name</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Logo URL</th>
                                    <td>
                                        <input type="url" id="llmsgaa_logo_url" name="llmsgaa_logo_url" 
                                               value="<?php echo esc_url( get_option( 'llmsgaa_logo_url', '' ) ); ?>" 
                                               class="regular-text" />
                                        <button type="button" class="button upload-logo">Choose Image</button>
                                        <p class="description">Optional: Add your logo to the email header</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Header Title</th>
                                    <td>
                                        <input type="text" name="llmsgaa_header_text" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_header_text', 'You\'re Invited!' ) ); ?>" 
                                               class="regular-text" />
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Header Subtitle</th>
                                    <td>
                                        <input type="text" name="llmsgaa_header_subtext" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_header_subtext', 'Join our exclusive learning community' ) ); ?>" 
                                               class="large-text" />
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Welcome Message</th>
                                    <td>
                                        <textarea name="llmsgaa_welcome_message" rows="3" class="large-text"><?php 
                                            echo esc_textarea( get_option( 'llmsgaa_welcome_message', '' ) ); 
                                        ?></textarea>
                                        <p class="description">Optional welcome message (appears in highlighted box)</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Body Text</th>
                                    <td>
                                        <textarea name="llmsgaa_body_text" rows="3" class="large-text"><?php 
                                            echo esc_textarea( get_option( 'llmsgaa_body_text', 'You have been invited to join <strong style="color: {primary_color};">{group_name}</strong> at <strong>{site_title}</strong>.' ) ); 
                                        ?></textarea>
                                        <p class="description">Use {group_name}, {site_title}, {primary_color} as placeholders</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Pre-Button Text</th>
                                    <td>
                                        <input type="text" name="llmsgaa_body_subtext" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_body_subtext', 'Click the button below to accept your invitation and get started:' ) ); ?>" 
                                               class="large-text" />
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Button Text</th>
                                    <td>
                                        <input type="text" name="llmsgaa_cta_text" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_cta_text', 'Accept Invitation' ) ); ?>" 
                                               class="regular-text" />
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Button Disclaimer</th>
                                    <td>
                                        <input type="text" name="llmsgaa_cta_disclaimer" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_cta_disclaimer', 'This invitation is unique to you. Please do not share it.' ) ); ?>" 
                                               class="large-text" />
                                        <p class="description">Small text below the button</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Footer Help Text</th>
                                    <td>
                                        <input type="text" name="llmsgaa_footer_help_text" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_footer_help_text', 'Can\'t click the button? Copy and paste this link:' ) ); ?>" 
                                               class="large-text" />
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Benefits Tab -->
                        <div id="benefits" class="tab-content">
                            <h2>Benefits Section</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Show Benefits</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="llmsgaa_show_benefits" value="yes" 
                                                   <?php checked( get_option( 'llmsgaa_show_benefits', 'yes' ), 'yes' ); ?> />
                                            Display benefits section in the email
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Benefits Title</th>
                                    <td>
                                        <input type="text" name="llmsgaa_benefits_title" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_benefits_title', 'What\'s Included:' ) ); ?>" 
                                               class="regular-text" />
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Benefits List</th>
                                    <td>
                                        <textarea name="llmsgaa_benefits_list" rows="6" class="large-text"><?php 
                                            echo esc_textarea( get_option( 'llmsgaa_benefits_list', "Exclusive access to group content\nConnect with like-minded members\nParticipate in group discussions\nAccess special resources" ) ); 
                                        ?></textarea>
                                        <p class="description">One benefit per line</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Styling Tab -->
                        <div id="styling" class="tab-content">
                            <h2>Email Styling</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Primary Color</th>
                                    <td>
                                        <input type="text" name="llmsgaa_primary_color" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_primary_color', '#667eea' ) ); ?>" 
                                               class="color-field" data-default-color="#667eea" />
                                        <p class="description">Main brand color for header gradient and buttons</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Secondary Color</th>
                                    <td>
                                        <input type="text" name="llmsgaa_secondary_color" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_secondary_color', '#764ba2' ) ); ?>" 
                                               class="color-field" data-default-color="#764ba2" />
                                        <p class="description">Secondary color for gradient effects</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Text Color</th>
                                    <td>
                                        <input type="text" name="llmsgaa_text_color" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_text_color', '#333333' ) ); ?>" 
                                               class="color-field" data-default-color="#333333" />
                                        <p class="description">Main body text color</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Background Color</th>
                                    <td>
                                        <input type="text" name="llmsgaa_bg_color" 
                                               value="<?php echo esc_attr( get_option( 'llmsgaa_bg_color', '#f7f7f7' ) ); ?>" 
                                               class="color-field" data-default-color="#f7f7f7" />
                                        <p class="description">Email wrapper background color</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Preview Tab -->
                        <div id="preview" class="tab-content">
                            <h2>Email Preview</h2>
                            <p>Save your settings to see the preview update.</p>
                            
                            <div style="background: #f0f0f0; padding: 20px; border-radius: 8px;">
                                <iframe id="email-preview" style="width: 100%; height: 700px; border: 1px solid #ddd; background: white; border-radius: 4px;"></iframe>
                            </div>
                            
                            <p style="margin-top: 20px;">
                                <strong>Test Email:</strong><br>
                                <input type="email" id="test-email" placeholder="test@example.com" class="regular-text" />
                                <button type="button" class="button" id="send-test">Send Test Email</button>
                                <span id="test-result"></span>
                            </p>
                        </div>
                        
                        <?php submit_button( 'Save All Settings' ); ?>
                    </form>
                </div>
                
                <div class="settings-sidebar">
                    <div class="sidebar-box">
                        <h3>📧 Quick Actions</h3>
                        <p>
                            <button type="button" class="button button-primary" id="refresh-preview">Refresh Preview</button>
                        </p>
                        <p>
                            <button type="button" class="button" id="reset-defaults">Reset to Defaults</button>
                        </p>
                    </div>
                    
                    <div class="sidebar-box">
                        <h3>💡 Tips</h3>
                        <ul>
                            <li>Use emojis in your subject line to increase open rates</li>
                            <li>Keep your header text short</li>
                            <li>Benefits expressed in first contact email show value immediately</li>
                            <li>Test your emails before sending to groups!>
                        </ul>
                    </div>
                    
                    <div class="sidebar-box">
                        <h3>🏷️ Available Tags</h3>
                        <ul>
                            <li><code>{group_name}</code> - Group name</li>
                            <li><code>{site_title}</code> - Site name</li>
                            <li><code>{primary_color}</code> - Your primary color</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .llms-email-settings .settings-wrapper {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        .llms-email-settings .settings-main {
            flex: 1;
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .llms-email-settings .settings-sidebar {
            width: 300px;
        }
        .llms-email-settings .sidebar-box {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .llms-email-settings .sidebar-box h3 {
            margin-top: 0;
        }
        .llms-email-settings .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        .llms-email-settings .tab-content {
            display: none;
        }
        .llms-email-settings .tab-content.active {
            display: block;
        }
        .llms-email-settings code {
            background: #f0f0f0;
            padding: 2px 5px;
            border-radius: 3px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize color pickers
            $('.color-field').wpColorPicker();
            
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
            
            // Logo upload
            $('.upload-logo').on('click', function(e) {
                e.preventDefault();
                var input = $('#llmsgaa_logo_url');
                
                var frame = wp.media({
                    title: 'Select Logo',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    input.val(attachment.url);
                });
                
                frame.open();
            });
            
            // Refresh preview
            $('#refresh-preview').on('click', function() {
                updatePreview();
            });
            
            // Update preview function
            function updatePreview() {
                var html = generatePreviewHTML();
                var iframe = $('#email-preview')[0];
                var doc = iframe.contentDocument || iframe.contentWindow.document;
                doc.open();
                doc.write(html);
                doc.close();
            }
            
            // Generate preview HTML
            function generatePreviewHTML() {
                var primary = $('[name="llmsgaa_primary_color"]').val() || '#667eea';
                var secondary = $('[name="llmsgaa_secondary_color"]').val() || '#764ba2';
                var headerText = $('[name="llmsgaa_header_text"]').val() || 'You\'re Invited!';
                var ctaText = $('[name="llmsgaa_cta_text"]').val() || 'Accept Invitation';
                
                return `
                    <html>
                    <body style="margin:0; padding:20px; background:#f7f7f7;">
                        <div style="max-width:600px; margin:0 auto; background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                            <div style="background:linear-gradient(135deg, ${primary} 0%, ${secondary} 100%); padding:40px; text-align:center;">
                                <h1 style="color:white; margin:0; font-size:32px;">${headerText}</h1>
                            </div>
                            <div style="padding:40px;">
                                <p>This is a preview of your email template.</p>
                                <div style="text-align:center; margin:40px 0;">
                                    <a href="#" style="display:inline-block; padding:18px 40px; background:linear-gradient(135deg, ${primary} 0%, ${secondary} 100%); color:white; text-decoration:none; font-size:18px; font-weight:600; border-radius:50px;">
                                        ${ctaText}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>`;
            }
            
            // Initial preview load
            setTimeout(updatePreview, 500);
            
            // Send test email
            $('#send-test').on('click', function() {
                var email = $('#test-email').val();
                if (!email) {
                    alert('Please enter an email address');
                    return;
                }
                
                $('#test-result').html(' Sending...');
                
                $.post(ajaxurl, {
                    action: 'llmsgaa_send_test_email',
                    email: email,
                    _ajax_nonce: '<?php echo wp_create_nonce( 'llmsgaa_test_email' ); ?>'
                }, function(response) {
                    $('#test-result').html(response.success ? 
                        ' <span style="color:green;">✓ Test email sent!</span>' : 
                        ' <span style="color:red;">✗ ' + response.data + '</span>'
                    );
                });
            });
            
            // Reset to defaults
            $('#reset-defaults').on('click', function() {
                if (confirm('Reset all settings to defaults? This cannot be undone.')) {
                    // Reset form values to defaults
                    $('[name="llmsgaa_header_text"]').val('You\'re Invited!');
                    $('[name="llmsgaa_header_subtext"]').val('Join our exclusive learning community');
                    $('[name="llmsgaa_cta_text"]').val('Accept Invitation');
                    // ... add more as needed
                    alert('Default values restored. Click "Save All Settings" to apply.');
                }
            });
        });
        </script>
        <?php
        
        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }
}

// Initialize the customization system
add_action( 'plugins_loaded', [ 'LLMSGAA_Email_Customization', 'init' ], 5 );
add_action( 'init', [ 'LLMSGAA_Email_Customization', 'init' ], 1 );

// Also add a direct wp_mail intercept as early as possible
add_filter( 'wp_mail', function( $args ) {
    error_log( 'LLMSGAA: Direct wp_mail filter (outside class) - Subject: ' . $args['subject'] );
    return $args;
}, 0 );

// Debug that hooks are active - FIXED VERSION
add_action( 'init', function() {
    global $wp_filter;
    if ( isset( $wp_filter['wp_mail'] ) && ! empty( $wp_filter['wp_mail'] ) ) {
        $count = 0;
        if ( $wp_filter['wp_mail'] instanceof WP_Hook ) {
            $callbacks = $wp_filter['wp_mail']->callbacks;
            foreach ( $callbacks as $priority => $hooks ) {
                $count += count( $hooks );
            }
        }
        error_log( 'LLMSGAA: wp_mail hooks registered: ' . $count );
    } else {
        error_log( 'LLMSGAA: NO wp_mail hooks registered!' );
    }
}, 999 );

// Log that this file was loaded
error_log( 'LLMSGAA: email-customization.php file loaded at ' . current_time( 'mysql' ) );