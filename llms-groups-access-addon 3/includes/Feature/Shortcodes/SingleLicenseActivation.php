<?php
/**
 * Single License Self-Activation Shortcode
 * 
 * Provides a streamlined activation process for users who purchased a single license.
 * This shortcode displays a button that triggers a popup wizard for self-activation
 * or gifting to someone else.
 * 
 * Usage: [llmsgaa_single_license_activation]
 * 
 * @package LLMSGAA\Feature\Shortcodes
 */

namespace LLMSGAA\Feature\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SingleLicenseActivation {

    /**
     * Initialize the shortcode and hooks
     */
    public static function init() {
        add_shortcode( 'llmsgaa_single_license_activation', [ __CLASS__, 'render_shortcode' ] );
        
        // AJAX handlers
        add_action( 'wp_ajax_llmsgaa_activate_single_license', [ __CLASS__, 'ajax_activate_license' ] );
        add_action( 'wp_ajax_nopriv_llmsgaa_activate_single_license', [ __CLASS__, 'ajax_activate_license' ] );
        
        add_action( 'wp_ajax_llmsgaa_gift_single_license', [ __CLASS__, 'ajax_gift_license' ] );
        add_action( 'wp_ajax_nopriv_llmsgaa_gift_single_license', [ __CLASS__, 'ajax_gift_license' ] );
        
        // Enqueue scripts on shortcode render instead of wp_enqueue_scripts
        // This ensures scripts are loaded when shortcode is displayed
    }

    /**
     * Check if user has a single unredeemed license
     * 
     * @param int $user_id User ID to check
     * @return array|false Returns pass data if eligible, false otherwise
     */
    private static function get_eligible_pass( $user_id ) {
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return false;
        }

        // Get all access passes for this user's email
        $passes = get_posts([
            'post_type'      => 'llms_access_pass',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'buyer_id',
                    'value'   => $user->user_email,
                    'compare' => '='
                ]
            ]
        ]);

        foreach ( $passes as $pass ) {
            // Check if redeemed - treat empty/null as not redeemed
            $is_redeemed = get_post_meta( $pass->ID, 'llmsgaa_redeemed', true );
            
            // Skip if already redeemed (explicitly set to '1')
            if ( $is_redeemed === '1' ) {
                continue;
            }

            // Get pass items
            $items = get_post_meta( $pass->ID, 'llmsgaa_pass_items', true );
            if ( is_string( $items ) ) {
                $items = json_decode( $items, true );
            }

            // Check if this is a single-item purchase
            if ( is_array( $items ) && count( $items ) === 1 ) {
                $item = reset( $items );
                
                // Check if quantity is 1
                if ( isset( $item['quantity'] ) && $item['quantity'] == 1 ) {
                    // Get the group ID for this pass
                    $group_id = get_post_meta( $pass->ID, 'group_id', true );
                    
                    // Get product information
                    $product_id = null;
                    // Try to get product ID from SKU
                    if ( function_exists( '\LLMSGAA\Common\Utils::sku_to_product_id' ) ) {
                        $product_id = \LLMSGAA\Common\Utils::sku_to_product_id( $item['sku'] );
                    }
                    $product_title = $product_id ? get_the_title( $product_id ) : $item['sku'];
                    
                    return [
                        'pass_id'       => $pass->ID,
                        'pass_title'    => $pass->post_title,
                        'group_id'      => $group_id,
                        'group_title'   => $group_id ? get_the_title( $group_id ) : '',
                        'sku'           => $item['sku'],
                        'product_id'    => $product_id,
                        'product_title' => $product_title,
                        'buyer_email'   => $user->user_email
                    ];
                }
            }
        }

        return false;
    }

    /**
     * Check if user has active access to a group
     * 
     * @param int $user_id User ID
     * @param int $group_id Group ID
     * @return bool
     */
    private static function user_has_active_access( $user_id, $group_id ) {
        if ( ! $group_id ) {
            return false;
        }
        
        $orders = get_posts([
            'post_type'      => 'llms_group_order',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'student_id',
                    'value'   => $user_id,
                    'compare' => '='
                ],
                [
                    'key'     => 'group_id',
                    'value'   => $group_id,
                    'compare' => '='
                ],
                [
                    'key'     => 'status',
                    'value'   => 'active',
                    'compare' => '='
                ]
            ]
        ]);

        return ! empty( $orders );
    }

    /**
     * Render the shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_shortcode( $atts ) {
        // Parse attributes with better defaults
        $atts = shortcode_atts([
            'button_text'    => 'Activate Your License',
            'button_class'   => 'llmsgaa-activate-button',
            'show_always'    => 'false',
            'message'        => 'You have an unused license ready to activate!',
            'hide_if_active' => 'true',
            'debug'          => 'false'  // Add debug mode
        ], $atts );

        // Debug mode to help troubleshoot
        if ( $atts['debug'] === 'true' ) {
            $debug_output = '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
            $debug_output .= '<strong>Debug Info:</strong><br>';
            $debug_output .= 'User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No') . '<br>';
            
            if ( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                $pass_data = self::get_eligible_pass( $user_id );
                $debug_output .= 'User ID: ' . $user_id . '<br>';
                $debug_output .= 'Eligible pass found: ' . ($pass_data ? 'Yes' : 'No') . '<br>';
                
                if ( $pass_data ) {
                    $debug_output .= 'Pass ID: ' . $pass_data['pass_id'] . '<br>';
                    $debug_output .= 'Group ID: ' . $pass_data['group_id'] . '<br>';
                    $has_active = self::user_has_active_access( $user_id, $pass_data['group_id'] );
                    $debug_output .= 'Has active access: ' . ($has_active ? 'Yes' : 'No') . '<br>';
                }
            }
            
            $debug_output .= '</div>';
            return $debug_output;
        }

        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            // Option to show a login prompt instead of nothing
            return '<div class="llmsgaa-login-prompt">Please log in to activate your license.</div>';
        }

        $user_id = get_current_user_id();
        $pass_data = self::get_eligible_pass( $user_id );

        // If no eligible pass and not showing always, return empty
        if ( ! $pass_data && $atts['show_always'] !== 'true' ) {
            return '';
        }

        // If hide_if_active is true, check if user already has active access
        if ( $atts['hide_if_active'] === 'true' && $pass_data && $pass_data['group_id'] ) {
            $has_active = self::user_has_active_access( $user_id, $pass_data['group_id'] );
            if ( $has_active ) {
                return '';
            }
        }

        // Only render if we have eligible pass data OR show_always is true
        if ( ! $pass_data && $atts['show_always'] === 'true' ) {
            // Create mock data for testing
            $pass_data = [
                'pass_id'       => 0,
                'pass_title'    => 'Test License',
                'group_id'      => 0,
                'group_title'   => 'Test Group',
                'product_title' => 'Test Product',
                'buyer_email'   => wp_get_current_user()->user_email
            ];
        }
        
        if ( ! $pass_data ) {
            return '';
        }

        // Enqueue scripts and styles when shortcode is rendered
        self::enqueue_activation_scripts();

        // Build the output
        ob_start();
        ?>
        <div class="llmsgaa-single-license-activation" data-pass-data='<?php echo esc_attr( json_encode( $pass_data ) ); ?>'>
            <div class="llmsgaa-activation-notice">
                <p><?php echo esc_html( $atts['message'] ); ?></p>
                <button type="button" 
                        class="<?php echo esc_attr( $atts['button_class'] ); ?>" 
                        id="llmsgaa-activate-license-btn"
                        data-pass-id="<?php echo esc_attr( $pass_data['pass_id'] ); ?>">
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
            </div>
        </div>

        <!-- Activation Wizard Modal -->
        <div id="llmsgaa-activation-modal" class="llmsgaa-modal" style="display: none;">
            <div class="llmsgaa-modal-overlay"></div>
            <div class="llmsgaa-modal-content">
                <button class="llmsgaa-modal-close">&times;</button>
                
                <div id="llmsgaa-wizard-step-1" class="llmsgaa-wizard-step">
                    <h2>Activate Your License</h2>
                    <p>You purchased: <strong><span class="product-title"><?php echo esc_html( $pass_data['product_title'] ); ?></span></strong></p>
                    <p>Is this license for you or would you like to gift it to someone else?</p>
                    
                    <div class="llmsgaa-wizard-options">
                        <button type="button" class="llmsgaa-wizard-option" data-choice="self">
                            <span class="option-icon">👤</span>
                            <span class="option-text">
                                <strong>For Me</strong>
                                <small>I'll use this license myself</small>
                            </span>
                        </button>
                        
                        <button type="button" class="llmsgaa-wizard-option" data-choice="gift">
                            <span class="option-icon">🎁</span>
                            <span class="option-text">
                                <strong>Gift to Someone</strong>
                                <small>Send this license to another person</small>
                            </span>
                        </button>
                    </div>
                </div>

                <div id="llmsgaa-wizard-step-2-self" class="llmsgaa-wizard-step" style="display: none;">
                    <h2>Choose Your Start Date</h2>
                    <p>When would you like your access to begin?</p>
                    
                    <form id="llmsgaa-self-activation-form">
                        <div class="llmsgaa-form-group">
                            <label for="llmsgaa-start-date">Start Date:</label>
                            <input type="date" 
                                   id="llmsgaa-start-date" 
                                   name="start_date" 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo date('Y-m-d'); ?>"
                                   required>
                        </div>
                        
                        <div class="llmsgaa-wizard-actions">
                            <button type="button" class="llmsgaa-btn-secondary llmsgaa-wizard-back">Back</button>
                            <button type="submit" class="llmsgaa-btn-primary">Activate License</button>
                        </div>
                    </form>
                </div>

                <div id="llmsgaa-wizard-step-2-gift" class="llmsgaa-wizard-step" style="display: none;">
                    <h2>Gift Your License</h2>
                    <p>Enter the recipient's information:</p>
                    
                    <form id="llmsgaa-gift-form">
                        <div class="llmsgaa-form-group">
                            <label for="llmsgaa-recipient-email">Recipient's Email:</label>
                            <input type="email" 
                                   id="llmsgaa-recipient-email" 
                                   name="recipient_email" 
                                   placeholder="recipient@example.com"
                                   required>
                        </div>
                        
                        <div class="llmsgaa-form-group">
                            <label for="llmsgaa-recipient-message">Personal Message (optional):</label>
                            <textarea id="llmsgaa-recipient-message" 
                                      name="personal_message" 
                                      rows="3" 
                                      placeholder="Add a personal message to include with the gift..."></textarea>
                        </div>
                        
                        <div class="llmsgaa-wizard-actions">
                            <button type="button" class="llmsgaa-btn-secondary llmsgaa-wizard-back">Back</button>
                            <button type="submit" class="llmsgaa-btn-primary">Send Gift</button>
                        </div>
                    </form>
                </div>

                <div id="llmsgaa-wizard-success" class="llmsgaa-wizard-step" style="display: none;">
                    <div class="llmsgaa-success-icon">✓</div>
                    <h2>Success!</h2>
                    <p class="llmsgaa-success-message"></p>
                    <button type="button" class="llmsgaa-btn-primary llmsgaa-modal-close-success">Done</button>
                </div>

                <div id="llmsgaa-wizard-loading" class="llmsgaa-wizard-step" style="display: none;">
                    <div class="llmsgaa-spinner"></div>
                    <p>Processing your request...</p>
                </div>
            </div>
        </div>

        <style>
        .llmsgaa-single-license-activation {
            margin: 20px 0;
        }

        .llmsgaa-activation-notice {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .llmsgaa-activation-notice p {
            margin: 0 0 15px 0;
            font-size: 16px;
        }

        .llmsgaa-activate-button {
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .llmsgaa-activate-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Modal Styles */
        .llmsgaa-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 999999;
            display: none;
        }

        .llmsgaa-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
        }

        .llmsgaa-modal-content {
            position: relative;
            background: white;
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .llmsgaa-modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .llmsgaa-modal-close:hover {
            color: #333;
        }

        .llmsgaa-wizard-step h2 {
            margin: 0 0 20px 0;
            color: #333;
        }

        .llmsgaa-wizard-options {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .llmsgaa-wizard-option {
            flex: 1;
            padding: 20px;
            border: 2px solid #e5e7eb;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .llmsgaa-wizard-option:hover {
            border-color: #667eea;
            background: #f9fafb;
        }

        .option-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .option-text strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }

        .option-text small {
            color: #666;
            font-size: 12px;
        }

        .llmsgaa-form-group {
            margin-bottom: 20px;
        }

        .llmsgaa-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .llmsgaa-form-group input,
        .llmsgaa-form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
        }

        .llmsgaa-wizard-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 30px;
        }

        .llmsgaa-btn-primary,
        .llmsgaa-btn-secondary {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .llmsgaa-btn-primary {
            background: #667eea;
            color: white;
        }

        .llmsgaa-btn-primary:hover {
            background: #5a67d8;
        }

        .llmsgaa-btn-secondary {
            background: #e5e7eb;
            color: #333;
        }

        .llmsgaa-btn-secondary:hover {
            background: #d1d5db;
        }

        .llmsgaa-success-icon {
            width: 60px;
            height: 60px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px;
        }

        .llmsgaa-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: llmsgaa-spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes llmsgaa-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #llmsgaa-wizard-loading {
            text-align: center;
        }

        .llmsgaa-modal-close-success {
            margin: 0 auto;
            display: block;
        }

        .llmsgaa-login-prompt {
            background: #f9fafb;
            padding: 15px;
            text-align: center;
            border-radius: 6px;
            color: #666;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('Single License Activation Script Loaded');
            
            const passData = <?php echo json_encode( $pass_data ); ?>;
            const ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
            const nonce = '<?php echo wp_create_nonce( 'llmsgaa_single_activation' ); ?>';
            
            // Open modal handler
            $('#llmsgaa-activate-license-btn').on('click', function(e) {
                e.preventDefault();
                console.log('Activate button clicked');
                $('#llmsgaa-activation-modal').fadeIn(300);
                $('body').css('overflow', 'hidden');
                $('.product-title').text(passData.product_title || 'Your License');
                $('#llmsgaa-wizard-step-1').show();
                $('#llmsgaa-wizard-step-2-self, #llmsgaa-wizard-step-2-gift, #llmsgaa-wizard-success, #llmsgaa-wizard-loading').hide();
            });
            
            // Close modal handlers
            $('.llmsgaa-modal-close, .llmsgaa-modal-close-success').on('click', function(e) {
                e.preventDefault();
                $('#llmsgaa-activation-modal').fadeOut(300);
                $('body').css('overflow', '');
            });
            
            // Click outside to close
            $('#llmsgaa-activation-modal').on('click', function(e) {
                if ($(e.target).hasClass('llmsgaa-modal-overlay')) {
                    $(this).fadeOut(300);
                    $('body').css('overflow', '');
                }
            });
            
            // Choice buttons
            $('.llmsgaa-wizard-option').on('click', function() {
                const choice = $(this).data('choice');
                $('#llmsgaa-wizard-step-1').hide();
                
                if (choice === 'self') {
                    $('#llmsgaa-wizard-step-2-self').fadeIn(300);
                } else if (choice === 'gift') {
                    $('#llmsgaa-wizard-step-2-gift').fadeIn(300);
                }
            });
            
            // Back buttons
            $('.llmsgaa-wizard-back').on('click', function(e) {
                e.preventDefault();
                $('.llmsgaa-wizard-step').hide();
                $('#llmsgaa-wizard-step-1').fadeIn(300);
            });
            
            // Self activation form
            $('#llmsgaa-self-activation-form').on('submit', function(e) {
                e.preventDefault();
                
                const startDate = $('#llmsgaa-start-date').val();
                if (!startDate) {
                    alert('Please select a start date');
                    return;
                }
                
                $('.llmsgaa-wizard-step').hide();
                $('#llmsgaa-wizard-loading').show();
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'llmsgaa_activate_single_license',
                        nonce: nonce,
                        pass_id: passData.pass_id,
                        start_date: startDate
                    },
                    success: function(response) {
                        $('#llmsgaa-wizard-loading').hide();
                        if (response.success) {
                            $('.llmsgaa-success-message').text(response.data.message || 'Your license has been activated successfully!');
                            $('#llmsgaa-wizard-success').show();
                            $('.llmsgaa-single-license-activation').fadeOut();
                            
                            if (response.data.redirect) {
                                setTimeout(function() {
                                    window.location.href = response.data.redirect;
                                }, 3000);
                            }
                        } else {
                            alert('Error: ' + (response.data || 'Failed to activate license'));
                            $('#llmsgaa-wizard-step-2-self').show();
                        }
                    },
                    error: function() {
                        $('#llmsgaa-wizard-loading').hide();
                        alert('Network error. Please try again.');
                        $('#llmsgaa-wizard-step-2-self').show();
                    }
                });
            });
            
            // Gift form
            $('#llmsgaa-gift-form').on('submit', function(e) {
                e.preventDefault();
                
                const recipientEmail = $('#llmsgaa-recipient-email').val();
                const personalMessage = $('#llmsgaa-recipient-message').val();
                
                if (!recipientEmail) {
                    alert('Please enter recipient email');
                    return;
                }
                
                $('.llmsgaa-wizard-step').hide();
                $('#llmsgaa-wizard-loading').show();
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'llmsgaa_gift_single_license',
                        nonce: nonce,
                        pass_id: passData.pass_id,
                        recipient_email: recipientEmail,
                        personal_message: personalMessage
                    },
                    success: function(response) {
                        $('#llmsgaa-wizard-loading').hide();
                        if (response.success) {
                            $('.llmsgaa-success-message').text(response.data.message || 'Gift sent successfully!');
                            $('#llmsgaa-wizard-success').show();
                            $('.llmsgaa-single-license-activation').fadeOut();
                        } else {
                            alert('Error: ' + (response.data || 'Failed to send gift'));
                            $('#llmsgaa-wizard-step-2-gift').show();
                        }
                    },
                    error: function() {
                        $('#llmsgaa-wizard-loading').hide();
                        alert('Network error. Please try again.');
                        $('#llmsgaa-wizard-step-2-gift').show();
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue scripts directly when shortcode is rendered
     */
    private static function enqueue_activation_scripts() {
        // Only enqueue jQuery if not already enqueued
        if ( ! wp_script_is( 'jquery', 'enqueued' ) ) {
            wp_enqueue_script( 'jquery' );
        }
    }

    /**
     * AJAX handler for self-activation
     */
    public static function ajax_activate_license() {
        // Verify nonce
        if ( ! check_ajax_referer( 'llmsgaa_single_activation', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid security token' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to activate a license' );
        }

        $user_id = get_current_user_id();
        $pass_id = intval( $_POST['pass_id'] ?? 0 );
        $start_date = sanitize_text_field( $_POST['start_date'] ?? date('Y-m-d') );

        if ( ! $pass_id ) {
            wp_send_json_error( 'Invalid license ID' );
        }

        // Verify this pass belongs to the user
        $pass_data = self::get_eligible_pass( $user_id );
        if ( ! $pass_data || $pass_data['pass_id'] != $pass_id ) {
            wp_send_json_error( 'This license is not available for activation' );
        }

        // Create the group order for the user
        $user = get_user_by( 'ID', $user_id );
        
        // Get product ID if possible
        $product_id = $pass_data['product_id'] ?? null;
        
        $order_id = wp_insert_post([
            'post_type'   => 'llms_group_order',
            'post_status' => 'publish',
            'post_title'  => sprintf( 'Self-Activated Order - %s', $user->user_email ),
            'meta_input'  => [
                'group_id'      => $pass_data['group_id'],
                'product_id'    => $product_id,
                'student_id'    => $user_id,
                'student_email' => $user->user_email,
                'start_date'    => $start_date,
                'status'        => 'active',
                'seat_id'       => $pass_id,
            ],
        ]);

        if ( ! $order_id ) {
            wp_send_json_error( 'Failed to create order' );
        }

        // Mark the pass as redeemed
        update_post_meta( $pass_id, 'llmsgaa_redeemed', '1' );
        update_post_meta( $pass_id, 'llmsgaa_redeemed_by', $user_id );
        update_post_meta( $pass_id, 'llmsgaa_redeemed_date', current_time('mysql') );

        // Success response
        wp_send_json_success([
            'message'  => 'Your license has been successfully activated! Your access begins on ' . $start_date . '.',
            'redirect' => $pass_data['group_id'] ? get_permalink( $pass_data['group_id'] ) : home_url()
        ]);
    }

    /**
     * AJAX handler for gifting a license
     */
    public static function ajax_gift_license() {
        // Verify nonce
        if ( ! check_ajax_referer( 'llmsgaa_single_activation', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid security token' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to gift a license' );
        }

        $user_id = get_current_user_id();
        $pass_id = intval( $_POST['pass_id'] ?? 0 );
        $recipient_email = sanitize_email( $_POST['recipient_email'] ?? '' );
        $personal_message = sanitize_textarea_field( $_POST['personal_message'] ?? '' );

        if ( ! $pass_id || ! is_email( $recipient_email ) ) {
            wp_send_json_error( 'Invalid data provided' );
        }

        // Verify this pass belongs to the user
        $pass_data = self::get_eligible_pass( $user_id );
        if ( ! $pass_data || $pass_data['pass_id'] != $pass_id ) {
            wp_send_json_error( 'This license is not available for gifting' );
        }

        // Check if recipient already exists as a user
        $recipient_user = get_user_by( 'email', $recipient_email );
        
        // Get product ID if possible
        $product_id = $pass_data['product_id'] ?? null;

        // Create a pending group order
        $order_id = wp_insert_post([
            'post_type'   => 'llms_group_order',
            'post_status' => 'publish',
            'post_title'  => sprintf( 'Gifted Order - %s', $recipient_email ),
            'meta_input'  => [
                'group_id'      => $pass_data['group_id'],
                'product_id'    => $product_id,
                'student_id'    => $recipient_user ? $recipient_user->ID : null,
                'student_email' => $recipient_email,
                'start_date'    => null, // Will be set when recipient activates
                'status'        => 'pending',
                'has_accepted_invite' => '0',
                'seat_id'       => $pass_id,
                'gifted_by'     => $user_id,
                'gift_message'  => $personal_message,
            ],
        ]);

        if ( ! $order_id ) {
            wp_send_json_error( 'Failed to create gift order' );
        }

        // Mark the pass as gifted (but not fully redeemed yet)
        update_post_meta( $pass_id, 'llmsgaa_gifted', '1' );
        update_post_meta( $pass_id, 'llmsgaa_gifted_to', $recipient_email );
        update_post_meta( $pass_id, 'llmsgaa_gifted_by', $user_id );
        update_post_meta( $pass_id, 'llmsgaa_gifted_date', current_time('mysql') );

        // Send email notification to recipient
        $sender = get_user_by( 'ID', $user_id );
        $subject = sprintf( 
            '%s has gifted you access to %s', 
            $sender->display_name ?: $sender->user_email,
            $pass_data['product_title']
        );
        
        $message = sprintf(
            "Hello,\n\n%s has gifted you access to %s.\n\n",
            $sender->display_name ?: $sender->user_email,
            $pass_data['product_title']
        );
        
        if ( $personal_message ) {
            $message .= "Personal message:\n" . $personal_message . "\n\n";
        }
        
        $activation_url = home_url( '/activate-gift/?order=' . $order_id . '&email=' . urlencode($recipient_email) );
        $message .= "Click here to activate your gift: " . $activation_url . "\n\n";
        $message .= "Best regards,\n" . get_bloginfo('name');
        
        wp_mail( $recipient_email, $subject, $message );

        // Success response
        wp_send_json_success([
            'message' => sprintf(
                'Gift successfully sent to %s! They will receive an email with instructions to activate their access.',
                $recipient_email
            )
        ]);
    }
}

// Initialize the class
SingleLicenseActivation::init();