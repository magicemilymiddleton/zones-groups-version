<?php

namespace LLMSGAA;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds and renders custom admin columns for Licenses and Group Orders.
 * Also provides bulk edit functionality for Access Passes.
 */
class AdminColumns {

    /**
     * Hook all admin column actions.
     */
    public static function init_hooks() {
        // Register columns and their renderers
        add_filter( 'manage_edit-llms_access_pass_columns',         [ __CLASS__, 'access_pass_columns' ] );
        add_action( 'manage_llms_access_pass_posts_custom_column', [ __CLASS__, 'render_access_pass_column' ], 10, 2 );

        add_filter( 'manage_edit-llms_group_order_columns',         [ __CLASS__, 'group_order_columns' ] );
        add_action( 'manage_llms_group_order_posts_custom_column', [ __CLASS__, 'render_group_order_column' ], 10, 2 );

        // Make selected columns sortable
        add_filter( 'manage_edit-llms_access_pass_sortable_columns', [ __CLASS__, 'access_pass_sortable_columns' ] );
        add_filter( 'manage_edit-llms_group_order_sortable_columns', [ __CLASS__, 'group_order_sortable_columns' ] );

        // Add bulk edit functionality
        add_action( 'bulk_edit_custom_box', [ __CLASS__, 'add_bulk_edit_redeemed_field' ], 10, 2 );
        add_action( 'save_post', [ __CLASS__, 'save_bulk_edit_redeemed_status' ] );
        add_action( 'admin_footer', [ __CLASS__, 'bulk_edit_javascript' ] );

        // Add quick edit functionality
        add_action( 'quick_edit_custom_box', [ __CLASS__, 'add_quick_edit_redeemed_field' ], 10, 2 );
        add_action( 'save_post', [ __CLASS__, 'save_quick_edit_redeemed_status' ] );
        add_action( 'admin_footer', [ __CLASS__, 'quick_edit_javascript' ] );
    }

    /**
     * Define columns for Licenses post list.
     */
    public static function access_pass_columns( $cols ) {
        return [
            'cb'                => $cols['cb'],
            'title'             => __( 'Title', 'llms-groups-access-addon' ),
            'group_id'          => __( 'Group', 'llms-groups-access-addon' ),
            'buyer_id'          => __( 'Buyer', 'llms-groups-access-addon' ),
            'quantity_total'    => __( 'Total', 'llms-groups-access-addon' ),
            'quantity_redeemed' => __( 'Redeemed', 'llms-groups-access-addon' ),
            'available'         => __( 'Available', 'llms-groups-access-addon' ),
            'redeemed_status'   => __( 'Status', 'llms-groups-access-addon' ),
            'date'              => $cols['date'],
        ];
    }

    /**
     * Render custom Licenses columns.
     */
    public static function render_access_pass_column( $col, $post_id ) {
        switch ( $col ) {
            case 'group_id':
                $id = get_post_meta( $post_id, 'group_id', true );
                echo $id ? '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>' : '—';
                break;
            case 'buyer_id':
                $buyer = get_user_by( 'email', get_post_meta( $post_id, 'buyer_id', true ) );
                echo $buyer ? esc_html( $buyer->display_name ) : '—';
                break;
            case 'quantity_total':
            case 'quantity_redeemed':
                echo esc_html( get_post_meta( $post_id, $col, true ) );
                break;
            case 'available':
                $total    = intval( get_post_meta( $post_id, 'quantity_total', true ) );
                $redeemed = intval( get_post_meta( $post_id, 'quantity_redeemed', true ) );
                echo esc_html( max( 0, $total - $redeemed ) );
                break;
            case 'redeemed_status':
                $redeemed = get_post_meta( $post_id, 'llmsgaa_redeemed', true );
                echo $redeemed ? '<span style="color: #46b450;">✓ ' . __( 'Redeemed', 'llms-groups-access-addon' ) . '</span>' : 
                                '<span style="color: #999;">✗ ' . __( 'Not Redeemed', 'llms-groups-access-addon' ) . '</span>';
                break;
        }
    }

    /**
     * Add bulk edit field for redeemed status.
     */
    public static function add_bulk_edit_redeemed_field( $column_name, $post_type ) {
        if ( $post_type !== 'llms_access_pass' ) {
            return;
        }
        
        if ( $column_name === 'redeemed_status' ) {
            ?>
            <fieldset class="inline-edit-col-right">
                <div class="inline-edit-col">
                    <div class="inline-edit-group wp-clearfix">
                        <label class="alignleft">
                            <span class="title"><?php _e( 'Redeemed Status', 'llms-groups-access-addon' ); ?></span>
                            <select name="bulk_redeemed_status">
                                <option value="-1"><?php _e( '— No Change —', 'llms-groups-access-addon' ); ?></option>
                                <option value="yes"><?php _e( 'Mark as Redeemed', 'llms-groups-access-addon' ); ?></option>
                                <option value="no"><?php _e( 'Mark as Not Redeemed', 'llms-groups-access-addon' ); ?></option>
                            </select>
                        </label>
                    </div>
                </div>
            </fieldset>
            <?php
        }
    }

    /**
     * Save bulk edit changes for redeemed status.
     */
    public static function save_bulk_edit_redeemed_status( $post_id ) {
        // Check if this is a bulk edit
        if ( ! isset( $_REQUEST['bulk_edit'] ) ) {
            return;
        }
        
        // Check post type
        if ( get_post_type( $post_id ) !== 'llms_access_pass' ) {
            return;
        }
        
        // Check nonce
        if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-posts' ) ) {
            return;
        }
        
        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Get the bulk edit value
        if ( isset( $_REQUEST['bulk_redeemed_status'] ) && $_REQUEST['bulk_redeemed_status'] !== '-1' ) {
            $redeemed_value = $_REQUEST['bulk_redeemed_status'] === 'yes' ? 1 : 0;
            update_post_meta( $post_id, 'llmsgaa_redeemed', $redeemed_value );
        }
    }

    /**
     * Add JavaScript to handle bulk edit.
     */
    public static function bulk_edit_javascript() {
        global $pagenow, $typenow;
        
        if ( $pagenow !== 'edit.php' || $typenow !== 'llms_access_pass' ) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Ensure bulk edit form is properly initialized
            $('#bulk_edit').on('click', function() {
                var $bulk_row = $('#bulk-edit');
                // Additional initialization if needed
            });
        });
        </script>
        <?php
    }

    /**
     * Add quick edit field for redeemed status.
     */
    public static function add_quick_edit_redeemed_field( $column_name, $post_type ) {
        if ( $post_type !== 'llms_access_pass' ) {
            return;
        }
        
        if ( $column_name === 'redeemed_status' ) {
            ?>
            <fieldset class="inline-edit-col-left">
                <div class="inline-edit-col">
                    <label>
                        <span class="title"><?php _e( 'Redeemed', 'llms-groups-access-addon' ); ?></span>
                        <select name="redeemed_status">
                            <option value="0"><?php _e( 'Not Redeemed', 'llms-groups-access-addon' ); ?></option>
                            <option value="1"><?php _e( 'Redeemed', 'llms-groups-access-addon' ); ?></option>
                        </select>
                    </label>
                </div>
            </fieldset>
            <?php
        }
    }

    /**
     * Save quick edit changes for redeemed status.
     */
    public static function save_quick_edit_redeemed_status( $post_id ) {
        // Check if this is a quick edit
        if ( ! isset( $_POST['_inline_edit'] ) ) {
            return;
        }
        
        // Check post type
        if ( get_post_type( $post_id ) !== 'llms_access_pass' ) {
            return;
        }
        
        // Check nonce
        if ( ! wp_verify_nonce( $_POST['_inline_edit'], 'inlineeditnonce' ) ) {
            return;
        }
        
        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Save the redeemed status
        if ( isset( $_POST['redeemed_status'] ) ) {
            update_post_meta( $post_id, 'llmsgaa_redeemed', $_POST['redeemed_status'] );
        }
    }

    /**
     * JavaScript to populate quick edit field with current value.
     */
    public static function quick_edit_javascript() {
        global $pagenow, $typenow;
        
        if ( $pagenow !== 'edit.php' || $typenow !== 'llms_access_pass' ) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Populate quick edit field when opened
            var $inline_editor = inlineEditPost.edit;
            
            inlineEditPost.edit = function(id) {
                $inline_editor.apply(this, arguments);
                
                var post_id = 0;
                if (typeof(id) == 'object') {
                    post_id = parseInt(this.getId(id));
                }
                
                if (post_id > 0) {
                    // Get the current redeemed value from the column
                    var $row = $('#post-' + post_id);
                    var redeemed_text = $row.find('.column-redeemed_status').text();
                    var is_redeemed = redeemed_text.includes('✓') ? '1' : '0';
                    
                    // Set the value in the quick edit dropdown
                    $('#edit-' + post_id + ' select[name="redeemed_status"]').val(is_redeemed);
                }
            };
        });
        </script>
        <?php
    }

    /**
     * Define columns for Group Order post list.
     */
    public static function group_order_columns( $cols ) {
        return [
            'cb'         => $cols['cb'],
            'title'      => __( 'Title', 'llms-groups-access-addon' ),
            'group_id'   => __( 'Group', 'llms-groups-access-addon' ),
            'student_id' => __( 'Student', 'llms-groups-access-addon' ),
            'status'     => __( 'Status', 'llms-groups-access-addon' ),
            'start_date' => __( 'Start', 'llms-groups-access-addon' ),
            'end_date'   => __( 'End', 'llms-groups-access-addon' ),
            'date'       => $cols['date'],
        ];
    }

    /**
     * Render custom Group Order columns.
     */
    public static function render_group_order_column( $col, $post_id ) {
        $val = get_post_meta( $post_id, $col, true );

        switch ( $col ) {
            case 'group_id':
                echo $val ? '<a href="' . esc_url( get_edit_post_link( $val ) ) . '">' . esc_html( get_the_title( $val ) ) . '</a>' : '—';
                break;
            case 'student_id':
                $user = get_user_by( 'id', $val );
                echo $user ? esc_html( $user->display_name ) : '—';
                break;
            case 'status':
            case 'start_date':
            case 'end_date':
                echo esc_html( $val );
                break;
        }
    }

    /**
     * Declare sortable columns for License list.
     */
    public static function access_pass_sortable_columns( $columns ) {
        $columns['group_id'] = 'group_id';
        $columns['buyer_id'] = 'buyer_id';
        $columns['redeemed_status'] = 'redeemed_status';
        return $columns;
    }

    /**
     * Declare sortable columns for Group Order list.
     */
    public static function group_order_sortable_columns( $columns ) {
        $columns['group_id']   = 'group_id';
        $columns['student_id'] = 'student_id';
        return $columns;
    }
}