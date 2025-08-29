<?php

namespace LLMSGAA;

use LLMSGAA\Utils;

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles meta boxes and admin UI for Licenses and Group Orders.
 */
class MetaBoxes {

    public static function init_hooks() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'register' ] );
        add_action( 'save_post',      [ __CLASS__, 'save' ], 10, 2 );
        add_action( 'admin_menu',     [ __CLASS__, 'add_settings_page' ] );
    }

    /**
     * Register the meta boxes shown in the editor for custom post types.
     */
    public static function register() {
        add_meta_box(
            'access_pass_meta',
            __( 'License Details', 'llms-groups-access-addon' ),
            [ __CLASS__, 'render_access_pass_meta' ],
            'llms_access_pass',
            'normal',
            'default'
        );

        add_meta_box(
            'group_order_meta',
            __( 'Group Order Details', 'llms-groups-access-addon' ),
            [ __CLASS__, 'render_group_order_meta' ],
            'llms_group_order',
            'normal',
            'default'
        );
    }

    /**
     * Adds a submenu page under LifterLMS Groups for License settings.
     */
    public static function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=llms_group',
            __( 'Pass Settings', 'llms-groups-access-addon' ),
            __( 'Pass Settings', 'llms-groups-access-addon' ),
            'manage_options',
            'llmsgaa-pass-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /**
     * Renders the settings page by including a separate view file.
     */
    public static function render_settings_page() {
        include LLMSGAA_DIR . 'views/settings/pass-settings.php';
    }

    // [Render and save methods remain unchanged below this point]

    /**
     * Render the License meta box fields.
     */
    public static function render_access_pass_meta( $post ) {
        wp_nonce_field( 'save_access_pass', 'access_pass_nonce' );

        // Basic fields
        $fields = [
            'group_id'         => 'Group ID',
            'buyer_id'         => 'Buyer Email',
            'shopify_order_id' => 'Shopify Order ID',
            'expiration_date'  => 'Expiration Date',
        ];

        foreach ( $fields as $key => $label ) {
            $value = get_post_meta( $post->ID, $key, true );
            $type  = $key === 'expiration_date' ? 'date' : 'text';
            echo "<p><label>{$label}<br />";
            echo "<input type='{$type}' name='{$key}' value='" . esc_attr( $value ) . "' class='widefat' />";
            echo "</label></p>";
        }

        // Pass Items JSON (Display nicely and allow editing)
        $raw_json = get_post_meta( $post->ID, 'llmsgaa_pass_items', true );
    if ( is_array( $raw_json ) ) {
       $formatted = wp_json_encode( $raw_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    } elseif ( is_string( $raw_json ) ) {
        $formatted = $raw_json;
    } else {
       $formatted = '';
    }

        echo '<p><label>Pass Items (JSON)<br />';
        echo "<textarea name='llmsgaa_pass_items' class='widefat' rows='5'>" . esc_textarea( $formatted ) . "</textarea>";
        echo '</label></p>';

        // Redeemed checkbox
        $redeemed = get_post_meta( $post->ID, 'llmsgaa_redeemed', true );
        echo '<p><label>';
        echo '<input type="checkbox" name="llmsgaa_redeemed" value="1" ' . checked( $redeemed, '1', false ) . ' /> Redeemed';
        echo '</label></p>';
    }

/**
 * Render the Group Order meta box fields - FIXED DATE HANDLING
 */
public static function render_group_order_meta( $post ) {
    wp_nonce_field( 'save_group_order', 'group_order_nonce' );

    $product_id = get_post_meta( $post->ID, 'product_id', true );
    echo '<p><label>Product ID<br />';
    echo "<input type='number' name='product_id' value='" . esc_attr( $product_id ) . "' class='widefat' />";
    if ( $product_id ) {
        $prod  = get_post( absint( $product_id ) );
        $title = $prod ? get_the_title( $prod ) : __( 'Invalid ID', 'llms-groups-access-addon' );
        echo '<em style="display:block;margin-top:4px;">' . esc_html( $title ) . '</em>';
    }
    echo '</label></p>';

    $fields = [
        'student_id'          => 'Student ID',
        'student_email'       => 'Student Email',
        'group_id'            => 'Group ID',
        'seat_id'             => 'Seat ID',
        'start_date'          => 'Start Date',
        'end_date'            => 'End Date',
        'status'              => 'Status',
        'has_accepted_invite' => 'Has Accepted Invite',
    ];

    foreach ( $fields as $key => $label ) {
        $value = get_post_meta( $post->ID, $key, true );

        if ( 'status' === $key ) {
            echo "<p><label>{$label}:<br /><select name='{$key}'>";
            foreach ( [ 'pending', 'active', 'expired' ] as $opt ) {
                printf(
                    "<option value='%s' %s>%s</option>",
                    esc_attr( $opt ),
                    selected( $value, $opt, false ),
                    esc_html( ucfirst( $opt ) )
                );
            }
            echo "</select></label></p>";

        } elseif ( 'has_accepted_invite' === $key ) {
            echo "<p><label>";
            echo "<input type='checkbox' name='{$key}' value='1' " . checked( $value, '1', false ) . " /> {$label}";
            echo "</label></p>";

        } elseif ( in_array( $key, [ 'start_date', 'end_date' ], true ) ) {
            // ENHANCED DATE HANDLING - Convert datetime to date for HTML5 input
            $date_value = '';
            if ( !empty( $value ) ) {
                // Handle both date and datetime formats
                if ( strlen( $value ) > 10 ) {
                    // DateTime format: 2024-01-15 10:30:00 -> 2024-01-15
                    $date_value = substr( $value, 0, 10 );
                } else {
                    // Already date format: 2024-01-15
                    $date_value = $value;
                }
            }
            
            echo "<p><label>{$label}:<br />";
            echo "<input type='date' name='{$key}' value='" . esc_attr( $date_value ) . "' class='widefat' />";
            
            // Show full datetime value for reference
            if ( !empty( $value ) && strlen( $value ) > 10 ) {
                echo "<small style='color: #666; display: block; margin-top: 4px;'>Full datetime: " . esc_html( $value ) . "</small>";
            }
            echo "</label></p>";

        } else {
            echo "<p><label>{$label}:<br />";
            echo "<input type='text' name='{$key}' value='" . esc_attr( $value ) . "' class='widefat' />";
            echo "</label></p>";
        }
    }
}

/**
 * Save meta box values on post save - ENHANCED DATE HANDLING
 */
public static function save( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! in_array( $post->post_type, [ 'llms_access_pass', 'llms_group_order' ], true ) ) return;

    $is_pass      = $post->post_type === 'llms_access_pass';
    $nonce_field  = $is_pass ? 'access_pass_nonce' : 'group_order_nonce';
    $nonce_action = $is_pass ? 'save_access_pass' : 'save_group_order';

    if ( empty( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( $_POST[ $nonce_field ], $nonce_action ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Save License fields
    if ( $is_pass ) {
        foreach ( [ 'group_id', 'buyer_id', 'shopify_order_id', 'expiration_date' ] as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }

        if ( isset( $_POST['llmsgaa_pass_items'] ) ) {
            $raw = trim( wp_unslash( $_POST['llmsgaa_pass_items'] ) );

            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as &$item ) {
                    $item = [
                        'sku'      => sanitize_text_field( $item['sku'] ?? '' ),
                        'quantity' => absint( $item['quantity'] ?? 0 ),
                    ];
                }
                unset( $item );
                update_post_meta( $post_id, 'llmsgaa_pass_items', $decoded );
            } else {
                // Save raw text to help debug malformed JSON
                update_post_meta( $post_id, 'llmsgaa_pass_items', $raw );
            }
        }

        $redeemed = isset( $_POST['llmsgaa_redeemed'] ) ? '1' : '0';
        update_post_meta( $post_id, 'llmsgaa_redeemed', $redeemed );
    }

    // Save Group Order fields - ENHANCED DATE HANDLING
    if ( $post->post_type === 'llms_group_order' ) {
        if ( isset( $_POST['product_id'] ) ) {
            update_post_meta( $post_id, 'product_id', absint( $_POST['product_id'] ) );
        }

        $group_fields = [
            'student_id'          => 'absint',
            'student_email'       => 'email',
            'group_id'            => 'absint',
            'seat_id'             => 'text', // Changed from absint to text to handle alphanumeric seat IDs
            'start_date'          => 'date',
            'end_date'            => 'date',
            'status'              => 'text',
            'has_accepted_invite' => 'checkbox',
        ];

        foreach ( $group_fields as $key => $type ) {
            switch ( $type ) {
                case 'absint':
                    $val = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : '';
                    break;
                    
                case 'checkbox':
                    $val = ! empty( $_POST[ $key ] ) ? '1' : '0';
                    break;
                    
                case 'email':
                    $val = isset( $_POST[ $key ] ) ? sanitize_email( $_POST[ $key ] ) : '';
                    break;
                    
                case 'date':
                    // ENHANCED DATE HANDLING
                    if ( isset( $_POST[ $key ] ) && !empty( $_POST[ $key ] ) ) {
                        $date_input = sanitize_text_field( $_POST[ $key ] );
                        
                        // If we get a date (YYYY-MM-DD), convert to datetime for consistency
                        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_input ) ) {
                            $val = $date_input . ' 00:00:00'; // Convert to datetime format
                        } else {
                            $val = $date_input; // Keep as-is if it's already datetime or other format
                        }
                    } else {
                        $val = '';
                    }
                    break;
                    
                default:
                    $val = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '';
            }
            
            update_post_meta( $post_id, $key, $val );
        }
    }
}
}