<?php
/**
 * Single License Self-Activation Shortcode (updated)
 *
 * Implements:
 *  - Multi vs single unused-pass branching
 *  - Gift path mirrors self-activation (no invite), adds recipient as Group Admin
 *  - Start date required for both self and gift
 *  - FluentCRM tag "gifted_license" on gift
 *  - Modal portaled to <body> and stacking fixed
 *
 * Usage: [llmsgaa_single_license_activation]
 *
 * @package LLMSGAA\Feature\Shortcodes

v2.3b design updates
v2.3c add notification box for renewals
v2.3d bug fixes
v2.3e bug fix: set end date for auto-enroll

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

        add_action( 'wp_ajax_llmsgaa_gift_single_license', [ __CLASS__, 'ajax_gift_single_license' ] );
        add_action( 'wp_ajax_nopriv_llmsgaa_gift_single_license', [ __CLASS__, 'ajax_gift_single_license' ] );
    }

    /*───────────────────────────────────────────────────────────────────────────*
     |                    DATA QUERIES & HELPERS (NEW/CHANGED)                  |
     *───────────────────────────────────────────────────────────────────────────*/

    /**
     * [NEW] Return all UNUSED single-item passes for this buyer (normalized rows)
     * @param int $user_id
     * @return array
     */
    private static function get_unused_single_passes( $user_id ) {
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) { return []; }

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

        $out = [];
        

        foreach ( $passes as $pass ) {
            $is_redeemed = get_post_meta( $pass->ID, 'llmsgaa_redeemed', true );
            if ( $is_redeemed === '1' ) {
                continue;
            }
            $items = get_post_meta( $pass->ID, 'llmsgaa_pass_items', true );
            if ( is_string( $items ) ) {
                $items = json_decode( $items, true );
            }
            if ( ! is_array( $items ) || count( $items ) !== 1 ) {
                continue;
            }
            $item = reset( $items );
            if ( ! isset( $item['quantity'] ) || intval( $item['quantity'] ) !== 1 ) {
                continue;
            }

            $group_id   = get_post_meta( $pass->ID, 'group_id', true );
			$product_id = null;
			if ( is_callable( ['\\LLMSGAA\\Common\\Utils', 'sku_to_product_id'] ) ) {
				$product_id = \LLMSGAA\Common\Utils::sku_to_product_id( $item['sku'] );
			}
            $out[] = [
                'pass_id'       => $pass->ID,
                'pass_title'    => $pass->post_title,
                'group_id'      => $group_id,
                'group_title'   => $group_id ? get_the_title( $group_id ) : '',
                'sku'           => $item['sku'],
                'product_id'    => $product_id,
                'product_title' => $product_id ? get_the_title( $product_id ) : $item['sku'],
                'buyer_email'   => $user->user_email,
            ];
        }
        return $out;
    }

    /**
     * [NEW] Convenience: return the single row or false when not exactly one
     */
    private static function get_exactly_one_unused_pass( $user_id ) {
        $list = self::get_unused_single_passes( $user_id );
        return count( $list ) === 1 ? $list[0] : false;
    }

    /**
     * Check if user has active access to a group (unchanged)
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
     * [NEW] Ensure a WP user exists for the email WITHOUT sending default emails.
     * Returns WP_User or WP_Error.
     */
    private static function ensure_user_for_email( $email ) {
        $user = get_user_by( 'email', $email );
        if ( $user ) { return $user; }

        // Suppress default "new user" notifications in this flow
        remove_action( 'register_new_user', 'wp_send_new_user_notifications' );
        add_filter( 'wp_new_user_notification_email', '__return_empty_array' );
        add_filter( 'wp_new_user_notification_email_admin', '__return_empty_array' );

        $uid = wp_create_user( $email, wp_generate_password( 20, true ), $email );

        // Remove filters regardless of success/failure to avoid side effects
        remove_filter( 'wp_new_user_notification_email', '__return_empty_array' );
        remove_filter( 'wp_new_user_notification_email_admin', '__return_empty_array' );

        if ( is_wp_error( $uid ) ) {
            return $uid;
        }
        return get_user_by( 'ID', $uid );
    }

    /**
     * [NEW] Assign user to group as ADMIN via plugin's own routines if present.
     * Defensive wrapper tries common locations; no-ops gracefully if not found.
     * @return bool success
     */
    private static function assign_admin_via_plugin( $group_id, $user_id ) {
        $ok = false;

        // Try LLMSGAA-specific helpers (adjust if you know the exact class/function)
        if ( function_exists( '\LLMSGAA\Groups\add_user_to_group' ) ) {
            $ok = (bool) \LLMSGAA\Groups\add_user_to_group( $group_id, $user_id );
        } elseif ( class_exists( '\LLMSGAA\Feature\Groups\Membership' )
            && method_exists( '\LLMSGAA\Feature\Groups\Membership', 'add_user_to_group' ) ) {
            $ok = (bool) \LLMSGAA\Feature\Groups\Membership::add_user_to_group( $group_id, $user_id );
        } elseif ( function_exists( 'LLMS_Groups' ) && method_exists( LLMS_Groups(), 'add_user_to_group' ) ) {
            try { LLMS_Groups()->add_user_to_group( $group_id, $user_id ); $ok = true; } catch ( \Throwable $t ) { $ok = false; }
        }

        // Try a UnifiedMemberManager if present (namespace guess; update if known)
        if ( ! $ok && class_exists( '\LLMSGAA\Feature\Groups\UnifiedMemberManager' )
            && method_exists( '\LLMSGAA\Feature\Groups\UnifiedMemberManager', 'add_member' ) ) {
            try {
                $res = \LLMSGAA\Feature\Groups\UnifiedMemberManager::add_member( (int) $group_id, get_userdata($user_id)->user_email, 'admin' );
                $ok = ! is_wp_error( $res );
            } catch ( \Throwable $t ) {}
        }

        // Fallback: mark role in user meta if your plugin reads this (safe no-op if unused)
        if ( ! $ok ) {
            update_user_meta( $user_id, "llmsgaa_group_role_{$group_id}", 'admin' );
        }

        /**
         * Allow suppressing any emails that other hooks might send while adding a member.
         * If your plugin honors this, wrap internal email senders with:
         *   if ( apply_filters('llmsgaa_suppress_emails', false) ) return;
         */
        return true;
    }

    /**
     * [NEW] Apply a FluentCRM tag to a WP user if FluentCRM is active.
     */
    private static function apply_fluentcrm_tag( $user_id, $email, $tag_slug = 'gifted_license', $tag_title = 'Gifted License' ) {
        if ( ! function_exists( 'FluentCrmApi' ) ) return;

        try {
            $wp_user  = get_user_by( 'ID', $user_id );
            $contact  = FluentCrmApi('contacts')->createOrUpdate([
                'user_id'    => $user_id,
                'first_name' => $wp_user ? ( $wp_user->first_name ?? '' ) : '',
                'last_name'  => $wp_user ? ( $wp_user->last_name ?? '' ) : '',
                'email'      => $email,
                'status'     => 'subscribed',
            ]);

            if ( class_exists( '\FluentCrm\App\Models\Tag' ) ) {
                $tag = \FluentCrm\App\Models\Tag::firstOrCreate(
                    ['slug' => $tag_slug],
                    ['title' => $tag_title, 'slug' => $tag_slug]
                );
                if ( $tag && isset( $tag->id ) ) {
                    FluentCrmApi('contacts')->addTags( [ $tag->id ], $contact->id );
                }
            }
        } catch ( \Throwable $t ) {
            // Silent fail – do not break activation if CRM is misconfigured
        }
    }

    /*───────────────────────────────────────────────────────────────────────────*
     |                              RENDER SHORTCODE                             |
     *───────────────────────────────────────────────────────────────────────────*/

    /**
     * Render the shortcode
     */
    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts([
            'button_text'    => 'Activate Your Order',
            'button_class'   => 'llmsgaa-activate-button',
            'show_always'    => 'false',
            'message'        => 'You have a new Access Pass ready to activate!',
            'hide_if_active' => 'true',
            'debug'          => 'false'
        ], $atts );

        // Debug block (unchanged)
        if ( $atts['debug'] === 'true' ) {
            $debug_output = '<div style="background:#f0f0f0;padding:10px;margin:10px 0;border:1px solid #ccc;">';
            $debug_output .= '<strong>Debug Info:</strong><br>';
            $debug_output .= 'User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No') . '<br>';
            if ( is_user_logged_in() ) {
                $user_id  = get_current_user_id();
                $passes   = self::get_unused_single_passes( $user_id ); // [CHANGED]
                $debug_output .= 'User ID: ' . $user_id . '<br>';
                $debug_output .= 'Unused single-item passes: ' . count($passes) . '<br>';
                if ( count($passes) === 1 ) {
                    $p = $passes[0];
                    $debug_output .= 'Pass ID: ' . $p['pass_id'] . '<br>';
                    $debug_output .= 'Group ID: ' . $p['group_id'] . '<br>';
                    $has_active = self::user_has_active_access( $user_id, $p['group_id'] );
                    $debug_output .= 'Has active access: ' . ($has_active ? 'Yes' : 'No') . '<br>';
                }
            }
            $debug_output .= '</div>';
            return $debug_output;
        }

        if ( ! is_user_logged_in() ) {
            return '<div class="llmsgaa-login-prompt">Please log in to activate your order.</div>';
        }
        ?>

        
<style>
.llmsgaa-single-license-activation { margin: 20px 0; }
.llmsgaa-activation-notice {
	background-color: var(--global-palette2);
	color: #fff; padding: 20px; border-radius: 8px; text-align: center;
	box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.llmsgaa-activation-notice p { margin: 0 0 15px 0; font-size: 16px; }
.llmsgaa-activate-button {
	background: #fff; color: var(--global-palette4); border: 0; padding: 12px 30px; font-size: 16px;
	font-weight: 700; border-radius: 50px; cursor: pointer; transition: all .3s ease;
}
.llmsgaa-activate-button:hover { transform: translateY(-2px); background-color: var(--global-palette6); box-shadow: 0 6px 12px rgba(0,0,0,.15); }

/* Modal Styles */
.llmsgaa-modal {
	position: fixed; inset: 0; z-index: 1000000; display: none; /* [CHANGED] stronger layer */
}
.llmsgaa-modal-overlay {
	position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 1000000; /* [CHANGED] fixed */
}
.llmsgaa-modal-content {
	position: fixed; left: 50%; top: 60px; transform: translateX(-50%);
	max-width: 500px; width: calc(100% - 32px); background: #fff; padding: 30px;
	border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,.1); z-index: 1000001; /* [CHANGED] */
}
.llmsgaa-modal-close {
	position: absolute; top: 15px; right: 15px; background: none; border: none;
	font-size: 24px; cursor: pointer; color: #999; width: 30px; height: 30px;
	display: flex; align-items: center; justify-content: center;
}
.llmsgaa-modal-close:hover { color: #333; }

.llmsgaa-wizard-step h2 { margin: 0 0 20px 0; color: #333; }
.llmsgaa-wizard-options { display: flex; gap: 15px; margin-top: 30px; }
.llmsgaa-wizard-option {
	flex: 1; padding: 20px; border: 2px solid #e5e7eb; background: #fff; border-radius: 8px;
	cursor: pointer; transition: all .3s ease; display: flex; flex-direction: column; align-items: center; text-align: center;
}
.llmsgaa-wizard-option:hover { border-color: #667eea; background: #f9fafb; }
.option-icon { font-size: 32px; margin-bottom: 10px; }
.option-text strong { display: block; margin-bottom: 5px; color: #333; }
.option-text small { color: #666; font-size: 12px; }

.llmsgaa-form-group { margin-bottom: 20px; }
.llmsgaa-form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
.llmsgaa-form-group input, .llmsgaa-form-group textarea {
	width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;
}
.llmsgaa-wizard-actions { display: flex; gap: 10px; justify-content: space-between; margin-top: 30px; }
.llmsgaa-btn-primary, .llmsgaa-btn-secondary {
	padding: 10px 20px; border: 0; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all .3s ease;
}
.llmsgaa-btn-primary { background: #667eea; color: #fff; }
.llmsgaa-btn-primary:hover { background: #5a67d8; }
.llmsgaa-btn-secondary { background: #e5e7eb; color: #333; }
.llmsgaa-btn-secondary:hover { background: #d1d5db; }

.llmsgaa-success-icon {
	width: 60px; height: 60px; background: #10b981; color: #fff; border-radius: 50%;
	display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px;
}
.llmsgaa-spinner {
	width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #667eea;
	border-radius: 50%; animation: llmsgaa-spin 1s linear infinite; margin: 0 auto 20px;
}
@keyframes llmsgaa-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
#llmsgaa-wizard-loading { text-align: center; }

.llmsgaa-modal-close-success { margin: 0 auto; display: block; }
.llmsgaa-login-prompt { background: #f9fafb; padding: 15px; text-align: center; border-radius: 6px; color: #666; }

</style>


<?php        

        $user_id = get_current_user_id();
        $passes  = self::get_unused_single_passes( $user_id ); // [CHANGED]
        $count   = count( $passes );

        // Check if any pass is a dc-renew-annual product
foreach ( $passes as $p ) {
    if ( isset($p['sku']) && $p['sku'] === 'dc-renew-annual' ) {
        ob_start(); ?>
        <div class="llmsgaa-single-license-activation">
          <div class="llmsgaa-activation-notice renewal-notice"> <!-- dark green local?-->
            <p><strong>Thank you for your renewal!</strong><br>To activate your renewal, click Manage Orders to open your Account Management page. Choose a start date that matches your current subscription’s end date to ensure continuous access.</p>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}


/** Check if any unused pass is multi-seat
foreach ( $passes as $p ) {
    $items = get_post_meta( $p['pass_id'], 'llmsgaa_pass_items', true );
    if ( is_string($items) ) { $items = json_decode($items, true); }
    if ( is_array($items) && count($items) === 1 ) {
        $item = reset($items);
        if ( !empty($item['quantity']) && intval($item['quantity']) > 1 ) {
            ob_start(); ?>
            <div class="llmsgaa-single-license-activation">
              <div class="llmsgaa-activation-notice" style="background:#444;color:#fff">
                <p>Thank you for your recent order! As the purchaser you are the designated Account Admin.
                   Click "Manage Orders" to go to your Group Management page where you can activate Access Passes for distribution to colleagues.</p>
              </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }
}
*/

        // No pass(es)
        if ( $count === 0 && $atts['show_always'] !== 'true' ) {
            return '';
        }

        // Multiple passes: show info only, no CTA (per requirement)
        if ( $count > 1 ) { // [NEW]
            $msg = $atts['message'] ?: 'You have new Access Passes ready to activate.';
            ob_start(); ?>
            <div class="llmsgaa-multi-license-activation">
                <div class="llmsgaa-activation-notice" aria-live="polite">
                    <p><?php echo esc_html( $msg ); ?></p>
                    <p style="margin-top:8px;font-size:14px;opacity:.9">
Thank you for your recent order! As the purchaser you are the designated Account Admin.
                   Click "Manage Orders" to go to your Group Management page where you can activate Access Passes for distribution to colleagues.   </p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        // Exactly one pass: wizard path
        $pass_data = $count === 1 ? $passes[0] : null;

        // hide_if_active guard (unchanged)
        if ( $atts['hide_if_active'] === 'true' && $pass_data && $pass_data['group_id'] ) {
            $has_active = self::user_has_active_access( $user_id, $pass_data['group_id'] );
            if ( $has_active ) {
                return '';
            }
        }

        // Fallback if show_always = true
        if ( ! $pass_data && $atts['show_always'] === 'true' ) {
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

        self::enqueue_activation_scripts();

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
        <div id="llmsgaa-activation-modal" class="llmsgaa-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="llmsgaa-activate-title">
            <div class="llmsgaa-modal-overlay"></div>
            <div class="llmsgaa-modal-content">
                <button class="llmsgaa-modal-close" aria-label="Close dialog">&times;</button>

                <div id="llmsgaa-wizard-step-1" class="llmsgaa-wizard-step">
                    <h2 id="llmsgaa-activate-title">Activate Your Order</h2>
                    <p>You purchased: <strong><span class="product-title"><?php echo esc_html( $pass_data['product_title'] ); ?></span></strong></p>
                    <p>Is this order for you or for someone else?</p>

                    <div class="llmsgaa-wizard-options">
                        <button type="button" class="llmsgaa-wizard-option" data-choice="self">
                            <span class="option-text">
                                <strong>For Me</strong>
                                <small>I will use it myself.</small>
                            </span>
                        </button>

                        <button type="button" class="llmsgaa-wizard-option" data-choice="gift">
                            <span class="option-text">
                                <strong>For Someone Else</strong>
                                <small>Assign the Access Pass to another individual.</small> <!-- [CHANGED] -->
                            </span>
                        </button>
                    </div>
                </div>

                <div id="llmsgaa-wizard-step-2-self" class="llmsgaa-wizard-step" style="display: none;">
                    <h2>Choose Your Start Date</h2>
                    <p>When would you like your subscription to begin? Select today's date to begin your one-year access immediately, or you can begin on a future date.</p>

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
                            <button type="submit" class="llmsgaa-btn-primary">Activate</button>
                        </div>
                    </form>
                </div>

                <!-- [CHANGED] Gift step now mirrors self: requires start date; no email invite -->
                <div id="llmsgaa-wizard-step-2-gift" class="llmsgaa-wizard-step" style="display: none;">
                    <h2>Assign the Access Pass</h2>
                    <p>Enter the recipient's email, followed by the subscription start date. We'll add them to your group as an additional Admin.</p>

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
                            <label for="llmsgaa-gift-start-date">Start Date:</label>
                            <input type="date"
                                   id="llmsgaa-gift-start-date"
                                   name="start_date"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo date('Y-m-d'); ?>"
                                   required>
                        </div>
<!--
                        <div class="llmsgaa-form-group">
                            <label for="llmsgaa-recipient-message">Internal Note (optional):</label>
                            <textarea id="llmsgaa-recipient-message"
                                      name="personal_message"
                                      rows="3"
                                      placeholder="Optional note for your records (no automatic email will be sent)"></textarea>
                        </div>
-->
                        <div class="llmsgaa-wizard-actions">
                            <button type="button" class="llmsgaa-btn-secondary llmsgaa-wizard-back">Back</button>
                            <button type="submit" class="llmsgaa-btn-primary">Activate</button>
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

        <script>
        jQuery(document).ready(function($) {
            console.log('Single License Activation Script Loaded');

            const passData = <?php echo json_encode( $pass_data ); ?>;
            const ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
            const nonce   = '<?php echo wp_create_nonce( 'llmsgaa_single_activation' ); ?>';

            // [NEW] Portal modal to <body> so no ancestor stacking context can trap it
            (function portalModal(){
                const $modal = $('#llmsgaa-activation-modal');
                if ($modal.length) { $modal.appendTo(document.body); }
            })();

            // Open modal
            $('#llmsgaa-activate-license-btn').on('click', function(e) {
                e.preventDefault();
                $('#llmsgaa-activation-modal').fadeIn(300);
                $('body').css('overflow', 'hidden');
                $('.product-title').text(passData.product_title || 'Your License');
                $('#llmsgaa-wizard-step-1').show();
                $('#llmsgaa-wizard-step-2-self, #llmsgaa-wizard-step-2-gift, #llmsgaa-wizard-success, #llmsgaa-wizard-loading').hide();
            });

            // Close modal
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

            // Step navigation
            $('.llmsgaa-wizard-option').on('click', function() {
                const choice = $(this).data('choice');
                $('.llmsgaa-wizard-step').hide();
                if (choice === 'self') {
                    $('#llmsgaa-wizard-step-2-self').fadeIn(300);
                } else {
                    $('#llmsgaa-wizard-step-2-gift').fadeIn(300);
                }
            });
            $('.llmsgaa-wizard-back').on('click', function(e) {
                e.preventDefault();
                $('.llmsgaa-wizard-step').hide();
                $('#llmsgaa-wizard-step-1').fadeIn(300);
            });

            // Self activation submit
            $('#llmsgaa-self-activation-form').on('submit', function(e) {
                e.preventDefault();
                const startDate = $('#llmsgaa-start-date').val();
                if (!startDate) { alert('Please select a start date'); return; }

                $('.llmsgaa-wizard-step').hide();
                $('#llmsgaa-wizard-loading').show();

                $.ajax({
                    url: ajaxUrl, type: 'POST',
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
                                setTimeout(function(){ window.location.href = response.data.redirect; }, 3000);
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

            // Gift submit (mirrors self; adds admin; no invite email)
            $('#llmsgaa-gift-form').on('submit', function(e) {
                e.preventDefault();
                const recipientEmail = $('#llmsgaa-recipient-email').val();
                const startDate      = $('#llmsgaa-gift-start-date').val();
                const personalMsg    = $('#llmsgaa-recipient-message').val();

                if (!recipientEmail || !startDate) { alert('Email and start date are required'); return; }

                $('.llmsgaa-wizard-step').hide();
                $('#llmsgaa-wizard-loading').show();

                $.ajax({
                    url: ajaxUrl, type: 'POST',
                    data: {
                        action: 'llmsgaa_gift_single_license',
                        nonce: nonce,
                        pass_id: passData.pass_id,
                        recipient_email: recipientEmail,
                        personal_message: personalMsg,
                        start_date: startDate
                    },
                    success: function(response) {
                        $('#llmsgaa-wizard-loading').hide();
                        if (response.success) {
                            $('.llmsgaa-success-message').text(response.data.message || 'Gift activation scheduled.');
                            $('#llmsgaa-wizard-success').show();
                            $('.llmsgaa-single-license-activation').fadeOut();
                            if (response.data.redirect) {
                                setTimeout(function(){ window.location.href = response.data.redirect; }, 3000);
                            }
                        } else {
                            alert('Error: ' + (response.data || 'Failed to gift license'));
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
        if ( ! wp_script_is( 'jquery', 'enqueued' ) ) {
            wp_enqueue_script( 'jquery' );
        }
    }

    /*───────────────────────────────────────────────────────────────────────────*
     |                              AJAX HANDLERS                               |
     *───────────────────────────────────────────────────────────────────────────*/

    /**
     * AJAX handler for self-activation (minor cleanup only)
     */
    public static function ajax_activate_license() {
        if ( ! check_ajax_referer( 'llmsgaa_single_activation', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid security token' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to activate a license' );
        }

        $user_id    = get_current_user_id();
        $pass_id    = intval( $_POST['pass_id'] ?? 0 );
        $start_date = sanitize_text_field( $_POST['start_date'] ?? date('Y-m-d') );

        if ( ! $pass_id ) {
            wp_send_json_error( 'Invalid license ID' );
        }

        // Enforce single-pass eligibility against the current user [CHANGED]
        $pass_data = self::get_exactly_one_unused_pass( $user_id );
        if ( ! $pass_data || intval($pass_data['pass_id']) !== $pass_id ) {
            wp_send_json_error( 'This license is not available for activation' );
        }

        $user = get_user_by( 'ID', $user_id );

//updated v2.3e to add end_date

		 $duration = '+1 year'; // TODO: adjust if SKU defines different terms
		$end_date = date( 'Y-m-d', strtotime( $start_date . ' ' . $duration ) );
		
$order_id = wp_insert_post([
    'post_type'   => 'llms_group_order',
    'post_status' => 'publish',
    'post_title'  => sprintf( 'Self-Activated Order - %s', $user->user_email ),
    'meta_input'  => [
        'group_id'               => $pass_data['group_id'],
        'product_id'             => $pass_data['product_id'] ?? null,
        'student_id'             => $user_id,
        'student_email'          => $user->user_email,
        'start_date'             => $start_date,
        'end_date'               => $end_date,
        'status'                 => 'active',
        'seat_id'                => $pass_id,
        'source_pass_id'         => $pass_id,
        'source_pass_identifier' => $pass_data['pass_title'] ?? 'Pass-' . $pass_id,
    ],
]);
//
        if ( ! $order_id ) {
            wp_send_json_error( 'Failed to create order' );
        }

        // Mark redeemed
        update_post_meta( $pass_id, 'llmsgaa_redeemed', '1' );
        update_post_meta( $pass_id, 'llmsgaa_redeemed_by', $user_id );
        update_post_meta( $pass_id, 'llmsgaa_redeemed_date', current_time('mysql') );

        wp_send_json_success([
            'message'  => 'Your license has been successfully activated! Your access begins on ' . $start_date . '.',
            'redirect' => $pass_data['group_id'] ? get_permalink( $pass_data['group_id'] ) : home_url()
        ]);
    }

    /**
     * [CHANGED] AJAX handler for gifting: same activation semantics, add as Group Admin,
     * no invite emails, and apply FluentCRM tag 'gifted_license'.
     */
    public static function ajax_gift_single_license() {
        if ( ! check_ajax_referer( 'llmsgaa_single_activation', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid security token' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to gift a license' );
        }

        $giver_id        = get_current_user_id();
        $pass_id         = intval( $_POST['pass_id'] ?? 0 );
        $recipient_email = sanitize_email( $_POST['recipient_email'] ?? '' );
        $start_date      = sanitize_text_field( $_POST['start_date'] ?? date('Y-m-d') );
        $note            = sanitize_textarea_field( $_POST['personal_message'] ?? '' );

        if ( ! $pass_id || ! is_email( $recipient_email ) ) {
            wp_send_json_error( 'Invalid data provided' );
        }

        // Validate pass against current user (must be exactly one and match) [NEW]
        $pass_data = self::get_exactly_one_unused_pass( $giver_id );
        if ( ! $pass_data || intval( $pass_data['pass_id'] ) !== $pass_id ) {
            wp_send_json_error( 'This license is not available for gifting' );
        }

        // Ensure recipient has a WP user (silently) [NEW]
        $recipient_user = self::ensure_user_for_email( $recipient_email );
        if ( is_wp_error( $recipient_user ) ) {
            wp_send_json_error( 'Failed to create recipient user: ' . $recipient_user->get_error_message() );
        }
        $recipient_id = $recipient_user->ID;

        // Create ACTIVE order for the recipient (mirror self) [NEW]

//updated v2.3e. Note, purchaser selects start date 
		$duration = '+1 year'; // match your product’s license term
		$end_date = date( 'Y-m-d', strtotime( $start_date . ' ' . $duration ) );
		
$order_id = wp_insert_post([
    'post_type'   => 'llms_group_order',
    'post_status' => 'publish',
    'post_title'  => sprintf( 'Gifted Admin Order - %s', $recipient_email ),
    'meta_input'  => [
        'group_id'               => $pass_data['group_id'],
        'product_id'             => $pass_data['product_id'] ?? null,
        'student_id'             => $recipient_id,
        'student_email'          => $recipient_email,
        'start_date'             => $start_date,
        'end_date'               => $end_date,
        'status'                 => 'active',
        'seat_id'                => $pass_id,
        'source_pass_id'         => $pass_id,
        'source_pass_identifier' => $pass_data['pass_title'] ?? 'Pass-' . $pass_id,
        'gifted_by'              => $giver_id,
        'gift_message'           => $note,
    ],
]);
//

        if ( ! $order_id ) {
            wp_send_json_error( 'Failed to create order' );
        }

        // Add recipient to group as ADMIN via plugin wrapper [NEW]
        // Optionally suppress internal emails if your plugin honors this flag
        add_filter( 'llmsgaa_suppress_emails', '__return_true', 10, 0 );
		   $add_result = \LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::add_member(
		(int) $pass_data['group_id'],
		(string) $recipient_email,
		'admin'
		);
		if ( is_wp_error( $add_result ) ) {
			wp_send_json_error( 'Order created but could not add as group admin: ' . $add_result->get_error_message() );
		}

       
        remove_filter( 'llmsgaa_suppress_emails', '__return_true', 10 );

        // Mark pass redeemed [NEW]
        update_post_meta( $pass_id, 'llmsgaa_redeemed', '1' );
        update_post_meta( $pass_id, 'llmsgaa_redeemed_by', $recipient_id );
        update_post_meta( $pass_id, 'llmsgaa_redeemed_date', current_time('mysql') );

        // Tag in FluentCRM for your automation stream [NEW]
        self::apply_fluentcrm_tag( $recipient_id, $recipient_email, 'gifted_license', 'Gifted License' );

        wp_send_json_success([
            'message'  => sprintf( 'Access Pass for %s, starts %s. They have been given Admin access to this Group.', $recipient_email, $start_date ),
            'redirect' => $pass_data['group_id'] ? get_permalink( $pass_data['group_id'] ) : home_url(),
        ]);
    }
}

// Initialize
SingleLicenseActivation::init();
