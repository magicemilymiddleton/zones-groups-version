<?php
/**
 * FluentCRM Integration - Tag Management for Access Pass Events
 * 
 * @package LLMSGAA
 */

namespace LLMSGAA\Feature;

defined( 'ABSPATH' ) || exit;

class FluentCRMIntegration {

    private static $events = [
        'pass_assigned'      => 'Access Pass Assigned',
        'pass_removed'       => 'Access Pass Removed',
        'enrollment_started' => 'Enrollment Starts',
        'enrollment_expired' => 'Enrollment Expires',
    ];

    public static function init() {
        // Admin page
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_page' ], 99 );
        add_action( 'admin_init', [ __CLASS__, 'save_settings' ] );

        // Only hook tag logic if FluentCRM is active
        if ( ! defined( 'FLUENTCRM' ) ) {
            return;
        }

        // Listen for our custom events
        add_action( 'llmsgaa_access_pass_assigned', [ __CLASS__, 'on_pass_assigned' ], 10, 3 );
        add_action( 'llmsgaa_access_pass_removed', [ __CLASS__, 'on_pass_removed' ], 10, 3 );
        add_action( 'llmsgaa_enrollment_started', [ __CLASS__, 'on_enrollment_started' ], 10, 3 );
        add_action( 'llmsgaa_enrollment_expired', [ __CLASS__, 'on_enrollment_expired' ], 10, 3 );
    }

    // =========================================================================
    // EVENT HANDLERS
    // =========================================================================

    public static function on_pass_assigned( $user_id, $order_id, $product_id ) {
        self::apply_tags_for_event( 'pass_assigned', $user_id );
    }

    public static function on_pass_removed( $user_id, $order_id, $product_id ) {
        self::apply_tags_for_event( 'pass_removed', $user_id );
    }

    public static function on_enrollment_started( $user_id, $order_id, $product_id ) {
        self::apply_tags_for_event( 'enrollment_started', $user_id );
    }

    public static function on_enrollment_expired( $user_id, $order_id, $product_id ) {
        self::apply_tags_for_event( 'enrollment_expired', $user_id );
    }

    // =========================================================================
    // TAG APPLICATION LOGIC
    // =========================================================================

    private static function apply_tags_for_event( $event_name, $user_id ) {
        if ( ! defined( 'FLUENTCRM' ) || ! function_exists( 'FluentCrmApi' ) ) {
            return;
        }

        $settings = get_option( 'llmsgaa_fluentcrm_settings', [] );
        
        $add_tags = $settings[ $event_name ]['add'] ?? [];
        $remove_tags = $settings[ $event_name ]['remove'] ?? [];

        // Nothing configured for this event
        if ( empty( $add_tags ) && empty( $remove_tags ) ) {
            return;
        }

        // Find the FluentCRM contact
        $contact = FluentCrmApi( 'contacts' )->getContactByUserRef( $user_id );
        
        if ( ! $contact ) {
            $user = get_user_by( 'ID', $user_id );
            if ( $user ) {
                $contact = FluentCrmApi( 'contacts' )->getContactByEmail( $user->user_email );
            }
        }

        if ( ! $contact ) {
            error_log( "[LLMSGAA FluentCRM] No contact found for user {$user_id} on event {$event_name}" );
            return;
        }

        // Apply tags
        if ( ! empty( $add_tags ) ) {
            $contact->attachTags( array_map( 'intval', $add_tags ) );
            error_log( "[LLMSGAA FluentCRM] Event '{$event_name}': Added tags [" . implode( ', ', $add_tags ) . "] to user {$user_id}" );
        }

        if ( ! empty( $remove_tags ) ) {
            $contact->detachTags( array_map( 'intval', $remove_tags ) );
            error_log( "[LLMSGAA FluentCRM] Event '{$event_name}': Removed tags [" . implode( ', ', $remove_tags ) . "] from user {$user_id}" );
        }
    }

    // =========================================================================
    // ADMIN PAGE
    // =========================================================================

    public static function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=llms_group',
            'FluentCRM Tags',
            'FluentCRM Tags',
            'manage_options',
            'llmsgaa-fluentcrm-tags',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    public static function save_settings() {
        if ( ! isset( $_POST['llmsgaa_fluentcrm_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['llmsgaa_fluentcrm_nonce'], 'llmsgaa_fluentcrm_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = [];

        foreach ( array_keys( self::$events ) as $event ) {
            $settings[ $event ] = [
                'add'    => isset( $_POST['llmsgaa_tags'][ $event ]['add'] ) 
                    ? array_map( 'intval', $_POST['llmsgaa_tags'][ $event ]['add'] ) 
                    : [],
                'remove' => isset( $_POST['llmsgaa_tags'][ $event ]['remove'] ) 
                    ? array_map( 'intval', $_POST['llmsgaa_tags'][ $event ]['remove'] ) 
                    : [],
            ];
        }

        update_option( 'llmsgaa_fluentcrm_settings', $settings );

        add_settings_error( 
            'llmsgaa_fluentcrm', 
            'settings_saved', 
            'Settings saved successfully!', 
            'success' 
        );
    }

    public static function render_admin_page() {
        $settings = get_option( 'llmsgaa_fluentcrm_settings', [] );
        
        // Get FluentCRM tags
        $all_tags = [];
        if ( defined( 'FLUENTCRM' ) && function_exists( 'FluentCrmApi' ) ) {
            $tags = FluentCrmApi( 'tags' )->all();
            foreach ( $tags as $tag ) {
                $all_tags[ $tag->id ] = $tag->title;
            }
        }

        ?>
        <div class="wrap">
            <h1>FluentCRM Tag Automation</h1>
            
            <?php settings_errors( 'llmsgaa_fluentcrm' ); ?>

            <?php if ( ! defined( 'FLUENTCRM' ) ): ?>
                <div class="notice notice-error">
                    <p><strong>FluentCRM is not active.</strong> Please install and activate FluentCRM to use this feature.</p>
                </div>
            <?php elseif ( empty( $all_tags ) ): ?>
                <div class="notice notice-warning">
                    <p><strong>No tags found in FluentCRM.</strong> Please create some tags in FluentCRM first.</p>
                </div>
            <?php endif; ?>

            <p>Configure which FluentCRM tags should be added or removed when Access Pass events occur.</p>

            <form method="post" action="">
                <?php wp_nonce_field( 'llmsgaa_fluentcrm_save', 'llmsgaa_fluentcrm_nonce' ); ?>

                <table class="widefat" style="max-width: 900px; margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Event</th>
                            <th style="width: 37%;">Add Tags</th>
                            <th style="width: 37%;">Remove Tags</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( self::$events as $event_key => $event_label ): 
                            $event_settings = $settings[ $event_key ] ?? [ 'add' => [], 'remove' => [] ];
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $event_label ); ?></strong>
                                    <p class="description" style="margin: 5px 0 0;">
                                        <?php echo esc_html( self::get_event_description( $event_key ) ); ?>
                                    </p>
                                </td>
                                <td>
                                    <select name="llmsgaa_tags[<?php echo esc_attr( $event_key ); ?>][add][]" 
                                            multiple 
                                            style="width: 100%; min-height: 100px;"
                                            <?php echo empty( $all_tags ) ? 'disabled' : ''; ?>>
                                        <?php foreach ( $all_tags as $tag_id => $tag_name ): ?>
                                            <option value="<?php echo esc_attr( $tag_id ); ?>"
                                                <?php selected( in_array( $tag_id, $event_settings['add'] ) ); ?>>
                                                <?php echo esc_html( $tag_name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Hold Ctrl/Cmd to select multiple</p>
                                </td>
                                <td>
                                    <select name="llmsgaa_tags[<?php echo esc_attr( $event_key ); ?>][remove][]" 
                                            multiple 
                                            style="width: 100%; min-height: 100px;"
                                            <?php echo empty( $all_tags ) ? 'disabled' : ''; ?>>
                                        <?php foreach ( $all_tags as $tag_id => $tag_name ): ?>
                                            <option value="<?php echo esc_attr( $tag_id ); ?>"
                                                <?php selected( in_array( $tag_id, $event_settings['remove'] ) ); ?>>
                                                <?php echo esc_html( $tag_name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Hold Ctrl/Cmd to select multiple</p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top: 20px;">
                    <input type="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>

            <hr style="margin: 40px 0 20px;">
            
            <h2>How It Works</h2>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><strong>Access Pass Assigned:</strong> Fires when an admin assigns a pass to a user (before enrollment starts)</li>
                <li><strong>Access Pass Removed:</strong> Fires when a pass is unassigned from a user</li>
                <li><strong>Enrollment Starts:</strong> Fires when the start_date arrives and user gets course access</li>
                <li><strong>Enrollment Expires:</strong> Fires when the end_date arrives and access is removed</li>
            </ul>

            <h3>Example Use Case: Renewal Reminders</h3>
            <p>To identify users who need renewal reminders:</p>
            <ol style="margin-left: 20px;">
                <li>Create a FluentCRM tag called "Has Active Access"</li>
                <li>Set <strong>Enrollment Starts</strong> → Add "Has Active Access"</li>
                <li>Set <strong>Enrollment Expires</strong> → Remove "Has Active Access"</li>
                <li>Create a dynamic segment: Users WITHOUT "Has Active Access" tag who had it before</li>
            </ol>
        </div>
        <?php
    }

    private static function get_event_description( $event_key ) {
        $descriptions = [
            'pass_assigned'      => 'When a pass is assigned to a user',
            'pass_removed'       => 'When a pass is removed from a user', 
            'enrollment_started' => 'When course access actually begins',
            'enrollment_expired' => 'When course access ends',
        ];
        return $descriptions[ $event_key ] ?? '';
    }
}

// Initialize
FluentCRMIntegration::init();