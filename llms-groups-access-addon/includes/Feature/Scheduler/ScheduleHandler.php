<?php
namespace LLMSGAA\Feature\Scheduler;

use ActionScheduler;

class ScheduleHandler {

public static function init() {
    error_log( __METHOD__ . " 🔥 ScheduleHandler init() loaded" );

    // UNIVERSAL HOOKS - Run scheduler on ANY create/update of llms_group_order
    add_action( 'save_post_llms_group_order', [ __CLASS__, 'universal_schedule_check' ], 20, 3 );
    add_action( 'wp_insert_post',             [ __CLASS__, 'universal_schedule_check_on_insert' ], 20, 3 );
    
    // Also run when metadata is updated (covers import scenarios)
    add_action( 'added_post_meta',   [ __CLASS__, 'maybe_reschedule_on_meta_update' ], 10, 4 );
    add_action( 'updated_post_meta', [ __CLASS__, 'maybe_reschedule_on_meta_update' ], 10, 4 );
    
    // CRITICAL FIX: Add hook for deleted metadata
    add_action( 'deleted_post_meta', [ __CLASS__, 'handle_deleted_meta' ], 10, 4 );

    // The actual Action Scheduler hooks
    add_action( 'llmsggaa_enroll_user',  [ __CLASS__, 'handle_enroll' ] );
    add_action( 'llmsggaa_expire_user', [ __CLASS__, 'handle_expire' ] );
    
    // Add the delayed schedule action
    add_action( 'llmsgaa_delayed_schedule', [ __CLASS__, 'process_order_scheduling' ] );
    
    // MANUAL TRIGGER for existing orders (useful for debugging/testing)
    add_action( 'wp_ajax_trigger_order_schedule', [ __CLASS__, 'ajax_trigger_schedule' ] );

    add_action( 'llmsgaa_license_unassigned', [ __CLASS__, 'handle_license_unassigned' ], 10, 4 );

}

/**
 * Handle deleted metadata - trigger unenrollment when student_id is cleared
 */
public static function handle_deleted_meta( $meta_ids, $post_id, $meta_key, $meta_value ) {
    if ( 'llms_group_order' !== get_post_type( $post_id ) ) {
        return;
    }
    
    // Check if critical metadata was deleted
    $critical_keys = [ 'student_id', '_student_id', 'student_email', '_student_email' ];
    
    if ( in_array( $meta_key, $critical_keys, true ) ) {
        error_log( __METHOD__ . " 🗑️ Critical meta '{$meta_key}' deleted for order {$post_id}" );
        
        // If student_id or student_email was deleted, trigger immediate unenrollment
        if ( in_array( $meta_key, [ 'student_id', '_student_id' ], true ) ) {
            // Get product_id before it might be cleared too
            $product_id = get_post_meta( $post_id, 'product_id', true ) ?: get_post_meta( $post_id, '_product_id', true );
            
            if ( $meta_value && $product_id ) {
                error_log( __METHOD__ . " 🔴 Triggering immediate unenrollment for user {$meta_value} from product {$product_id}" );
                
                // Perform immediate unenrollment
                self::immediate_unenroll( $post_id, $meta_value, $product_id );
            }
        }
        
        // Re-process the order scheduling (will mark as incomplete/expired)
        self::process_order_scheduling( $post_id );
    }
}

/**
 * Immediate unenrollment when license is removed
 */
public static function immediate_unenroll( $order_id, $user_id, $product_id ) {
    error_log( __METHOD__ . " 🚨 IMMEDIATE UNENROLL for order {$order_id}, user {$user_id}, product {$product_id}" );
    
    if ( ! $user_id || ! $product_id ) {
        error_log( __METHOD__ . " ❌ Missing user_id or product_id" );
        return false;
    }
    
    // Clear any scheduled actions
    $args = [ 'order_id' => $order_id ];
    as_unschedule_all_actions( 'llmsggaa_enroll_user', $args );
    as_unschedule_all_actions( 'llmsggaa_expire_user', $args );
    
    // Check enrollment status
    if ( function_exists( 'llms_is_user_enrolled' ) ) {
        $is_enrolled = llms_is_user_enrolled( $user_id, $product_id );
        error_log( __METHOD__ . " Current enrollment status: " . ( $is_enrolled ? 'ENROLLED' : 'NOT ENROLLED' ) );
        
        if ( $is_enrolled ) {
            // CORRECTED: Use proper parameter order
            if ( function_exists( 'llms_unenroll_student' ) ) {
                // Correct order: user_id, product_id, new_status, trigger
                llms_unenroll_student( $user_id, $product_id, 'expired', 'any' );
                error_log( __METHOD__ . " Called llms_unenroll_student() with correct parameters" );
            }
            
            // Verify unenrollment
            $still_enrolled = llms_is_user_enrolled( $user_id, $product_id );
            
            if ( $still_enrolled ) {
                error_log( __METHOD__ . " ⚠️ Standard unenrollment failed, trying LLMS_Student class" );
                
                // Try alternative method with LLMS_Student class
                if ( class_exists( 'LLMS_Student' ) ) {
                    $student = new \LLMS_Student( $user_id );
                    $student->unenroll( $product_id, 'expired', 'group' );
                    error_log( __METHOD__ . " Called LLMS_Student->unenroll()" );
                }
                
                // Final check and force if needed
                $final_check = llms_is_user_enrolled( $user_id, $product_id );
                if ( $final_check ) {
                    error_log( __METHOD__ . " ⚠️ All API methods failed, using direct database manipulation" );
                    self::force_database_unenroll( $user_id, $product_id );
                }
            }
        }
    }
    
    // Update order status
    update_post_meta( $order_id, 'status', 'expired' );
    
    // Final verification
    if ( function_exists( 'llms_is_user_enrolled' ) ) {
        $final_status = llms_is_user_enrolled( $user_id, $product_id );
        error_log( __METHOD__ . " Final enrollment status: " . ( $final_status ? '❌ STILL ENROLLED' : '✅ NOT ENROLLED' ) );
    }
    
    return true;
}


public static function handle_license_unassigned( $order_id, $student_id, $student_email, $product_id ) {
    error_log( __METHOD__ . " 🎯 License unassigned hook fired for order {$order_id}" );
    
    if ( $student_id && $product_id ) {
        self::immediate_unenroll( $order_id, $student_id, $product_id );
    }
}

    /**
     * UNIVERSAL SCHEDULER - Runs on any save_post for llms_group_order
     */
    public static function universal_schedule_check( $post_id, $post, $update ) {
        error_log( __METHOD__ . " 🔥 UNIVERSAL CHECK for order {$post_id} (update=" . var_export( $update, true ) . ")" );
        
        // Always run the full scheduling logic
        self::process_order_scheduling( $post_id );
    }

    /**
     * UNIVERSAL SCHEDULER - Runs on wp_insert_post (catches imports)
     */
    public static function universal_schedule_check_on_insert( $post_id, $post, $update ) {
        if ( 'llms_group_order' !== $post->post_type ) {
            return;
        }
        
        error_log( __METHOD__ . " 🔥 INSERT CHECK for order {$post_id}" );
        
        // Delay execution slightly to ensure metadata is saved
        wp_schedule_single_event( time() + 5, 'llmsgaa_delayed_schedule', [ $post_id ] );
    }

/**
 * CORE SCHEDULING LOGIC - The main brain of the operation
 */
public static function process_order_scheduling( $post_id ) {
    error_log( __METHOD__ . " 🧠 PROCESSING order {$post_id}" );

    // Get all relevant metadata (check both with and without underscores)
    $start_date = get_post_meta( $post_id, 'start_date', true ) ?: get_post_meta( $post_id, '_start_date', true );
    $end_date = get_post_meta( $post_id, 'end_date', true ) ?: get_post_meta( $post_id, '_end_date', true );
    $student_id = get_post_meta( $post_id, 'student_id', true ) ?: get_post_meta( $post_id, '_student_id', true );
    $product_id = get_post_meta( $post_id, 'product_id', true ) ?: get_post_meta( $post_id, '_product_id', true );
    $current_status = get_post_meta( $post_id, 'status', true ) ?: get_post_meta( $post_id, '_status', true );

    error_log( __METHOD__ . " 📊 Order {$post_id} data:" );
    error_log( "   start_date: {$start_date}" );
    error_log( "   end_date: {$end_date}" );
    error_log( "   student_id: {$student_id}" );
    error_log( "   product_id: {$product_id}" );
    error_log( "   current_status: {$current_status}" );

    // STEP 1: Clear any existing scheduled actions for this order
    $args = [ 'order_id' => $post_id ];
    as_unschedule_all_actions( 'llmsggaa_enroll_user', $args );
    as_unschedule_all_actions( 'llmsggaa_expire_user', $args );
    error_log( __METHOD__ . " 🔄 Cleared existing actions for order {$post_id}" );

    // STEP 2: Check if we have minimum required data
    if ( empty( $student_id ) || empty( $product_id ) ) {
        error_log( __METHOD__ . " ⚠️ Missing student_id or product_id for order {$post_id} - no scheduling" );
        update_post_meta( $post_id, 'status', 'incomplete' );
        return;
    }

    if ( empty( $start_date ) || empty( $end_date ) ) {
        error_log( __METHOD__ . " ⚠️ Missing dates for order {$post_id} - no scheduling" );
        update_post_meta( $post_id, 'status', 'incomplete' );
        return;
    }

    // STEP 3: Parse dates and get current time
    $ts_start = strtotime( $start_date );
    $ts_end = strtotime( $end_date );
    $now = time();

    if ( $ts_start === false || $ts_end === false ) {
        error_log( __METHOD__ . " ❌ Invalid date format for order {$post_id}" );
        update_post_meta( $post_id, 'status', 'invalid_dates' );
        return;
    }

    error_log( __METHOD__ . " 🕐 Time analysis for order {$post_id}:" );
    error_log( "   Now: " . date( 'c', $now ) );
    error_log( "   Start: " . date( 'c', $ts_start ) );
    error_log( "   End: " . date( 'c', $ts_end ) );

    // STEP 4: Determine enrollment and expiry based on dates
    if ( $ts_end <= $now ) {
        // EXPIRED: Course period has already ended
        error_log( __METHOD__ . " ⏰ Course period EXPIRED for order {$post_id}" );
        
        if ( 'active' === $current_status ) {
            // If currently active, expire them now
            self::handle_expire( $post_id );
        } else {
            // Just mark as expired
            update_post_meta( $post_id, 'status', 'expired' );
        }

    } elseif ( $ts_start <= $now && $ts_end > $now ) {
        // ACTIVE PERIOD: We're currently within the course dates
        error_log( __METHOD__ . " ✅ Currently in ACTIVE period for order {$post_id}" );

        // Enroll them immediately if not already active
        if ( 'active' !== $current_status ) {
            error_log( __METHOD__ . " 🎓 Enrolling student NOW for order {$post_id}" );
            self::handle_enroll( $post_id );
        } else {
            error_log( __METHOD__ . " ⏭️ Student already active, skipping enrollment" );
        }
        
        // Schedule expiry for the future
        $expiry_time = max( $ts_end, $now + 60 ); // At least 1 minute in the future
        as_schedule_single_action( $expiry_time, 'llmsggaa_expire_user', $args );
        error_log( __METHOD__ . " 🔔 Scheduled expiry for " . date( 'c', $expiry_time ) );

    } else {
        // FUTURE: Course hasn't started yet
        error_log( __METHOD__ . " ⏳ Course is FUTURE for order {$post_id}" );

        // Schedule both enrollment and expiry
        as_schedule_single_action( $ts_start, 'llmsggaa_enroll_user', $args );
        as_schedule_single_action( $ts_end, 'llmsggaa_expire_user', $args );
        
        error_log( __METHOD__ . " 🔔 Scheduled enrollment for " . date( 'c', $ts_start ) );
        error_log( __METHOD__ . " 🔔 Scheduled expiry for " . date( 'c', $ts_end ) );
        update_post_meta( $post_id, 'status', 'scheduled' );
    }

    error_log( __METHOD__ . " ✅ Completed scheduling for order {$post_id}" );
}

/**
 * Handle enrollment - Enhanced to support both courses and memberships
 */
public static function handle_enroll( $order_id ) {
    $order_id = absint( $order_id );
    error_log( __METHOD__ . " 🎓 ENROLLING for order {$order_id}" );

    // Check both versions of meta keys
    $user_id = get_post_meta( $order_id, 'student_id', true ) ?: get_post_meta( $order_id, '_student_id', true );
    $product_id = get_post_meta( $order_id, 'product_id', true ) ?: get_post_meta( $order_id, '_product_id', true );
    $current_status = get_post_meta( $order_id, 'status', true ) ?: get_post_meta( $order_id, '_status', true );

    error_log( __METHOD__ . " Retrieved data - user_id: {$user_id}, product_id: {$product_id}, status: {$current_status}" );

    if ( ! $user_id || ! $product_id ) {
        error_log( __METHOD__ . " ❌ Missing user or product for order {$order_id}" );
        return false;
    }

    // Check if user exists
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        error_log( __METHOD__ . " ❌ User {$user_id} does not exist!" );
        return false;
    }
    error_log( __METHOD__ . " ✅ User exists: " . $user->user_email );

    // Check if product exists and determine its type
    $product = get_post( $product_id );
    if ( ! $product ) {
        error_log( __METHOD__ . " ❌ Product {$product_id} does not exist!" );
        return false;
    }
    
    $product_type = $product->post_type;
    error_log( __METHOD__ . " ✅ Product exists: " . $product->post_title . " (type: " . $product_type . ")" );

    // Validate it's a valid LifterLMS product type
    if ( ! in_array( $product_type, [ 'course', 'llms_membership' ], true ) ) {
        error_log( __METHOD__ . " ❌ Invalid product type: {$product_type}. Expected 'course' or 'llms_membership'" );
        return false;
    }

    if ( 'active' === $current_status ) {
        error_log( __METHOD__ . " ✅ User already active for order {$order_id}" );
        return true;
    }

    if ( ! function_exists( 'llms_enroll_student' ) ) {
        error_log( __METHOD__ . " ❌ llms_enroll_student() not available!" );
        return false;
    }

    // Check if already enrolled
    if ( function_exists( 'llms_is_user_enrolled' ) ) {
        $already_enrolled = llms_is_user_enrolled( $user_id, $product_id );
        error_log( __METHOD__ . " Current enrollment status: " . ( $already_enrolled ? 'ENROLLED' : 'NOT ENROLLED' ) );
        
        if ( $already_enrolled ) {
            error_log( __METHOD__ . " ✅ User is already enrolled in {$product_type} {$product_id}" );
            update_post_meta( $order_id, 'status', 'active' );
            return true;
        }
    }

    // Attempt enrollment
    error_log( __METHOD__ . " 🚀 Attempting to enroll user {$user_id} into {$product_type} {$product_id}" );
    
    // The llms_enroll_student function should handle both courses and memberships
    $result = llms_enroll_student( $user_id, $product_id );
    
    error_log( __METHOD__ . " Enrollment result: " . var_export( $result, true ) );

    // Verify enrollment actually happened
    if ( function_exists( 'llms_is_user_enrolled' ) ) {
        $now_enrolled = llms_is_user_enrolled( $user_id, $product_id );
        error_log( __METHOD__ . " Post-enrollment check: " . ( $now_enrolled ? 'ENROLLED' : 'STILL NOT ENROLLED' ) );
        
        if ( ! $now_enrolled ) {
            error_log( __METHOD__ . " ❌ Enrollment failed - user is not enrolled after llms_enroll_student() call" );
            
            // Try alternative enrollment method based on product type
            if ( class_exists( 'LLMS_Student' ) ) {
                error_log( __METHOD__ . " Trying alternative enrollment method via LLMS_Student" );
                $student = new \LLMS_Student( $user_id );
                
                if ( 'llms_membership' === $product_type ) {
                    // For memberships, we might need to use a different method
                    $alt_result = $student->enroll( $product_id, 'membership' );
                } else {
                    // For courses
                    $alt_result = $student->enroll( $product_id );
                }
                
                error_log( __METHOD__ . " Alternative enrollment result: " . var_export( $alt_result, true ) );
                
                // Check one more time
                $final_check = llms_is_user_enrolled( $user_id, $product_id );
                error_log( __METHOD__ . " Final enrollment check: " . ( $final_check ? 'ENROLLED' : 'STILL NOT ENROLLED' ) );
            }
        }
    }

    // Update status
    update_post_meta( $order_id, 'status', 'active' );
    
    error_log( __METHOD__ . " ✅ Completed enrollment process for order {$order_id}" );
    return true;
}

/**
 * Handle expiry - Enhanced with correct parameters
 */
public static function handle_expire( $order_id ) {
    $order_id = absint( $order_id );
    error_log( __METHOD__ . " ⏰ EXPIRING for order {$order_id}" );

    $user_id = get_post_meta( $order_id, 'student_id', true ) ?: get_post_meta( $order_id, '_student_id', true );
    $product_id = get_post_meta( $order_id, 'product_id', true ) ?: get_post_meta( $order_id, '_product_id', true );
    $current_status = get_post_meta( $order_id, 'status', true ) ?: get_post_meta( $order_id, '_status', true );

    if ( ! $user_id || ! $product_id ) {
        error_log( __METHOD__ . " ❌ Missing user or product for order {$order_id}" );
        return false;
    }

    if ( 'expired' === $current_status ) {
        error_log( __METHOD__ . " ✅ Order {$order_id} already expired" );
        return true;
    }

    if ( ! function_exists( 'llms_unenroll_student' ) ) {
        error_log( __METHOD__ . " ❌ llms_unenroll_student() not available!" );
        return false;
    }

    // CORRECTED: Use proper parameter order
    llms_unenroll_student( $user_id, $product_id, 'expired', 'any' );
    update_post_meta( $order_id, 'status', 'expired' );
    
    error_log( __METHOD__ . " ✅ Unenrolled user {$user_id} from product {$product_id} (order {$order_id})" );
    return true;
}

    /**
     * Re-schedule whenever key metadata changes
     */
    public static function maybe_reschedule_on_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( 'llms_group_order' !== get_post_type( $post_id ) ) {
            return;
        }

        $watch_keys = [ 
            'has_accepted_invite', '_has_accepted_invite',
            'start_date', '_start_date',
            'end_date', '_end_date',
            'student_id', '_student_id',
            'product_id', '_product_id'
        ];

        if ( in_array( $meta_key, $watch_keys, true ) ) {
            error_log( __METHOD__ . " 🔄 Meta '{$meta_key}' changed for order {$post_id} - re-scheduling" );
            self::process_order_scheduling( $post_id );
        }
    }

    /**
     * AJAX handler for manual scheduling (useful for testing)
     */
    public static function ajax_trigger_schedule() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $order_id = absint( $_POST['order_id'] ?? 0 );
        if ( $order_id && 'llms_group_order' === get_post_type( $order_id ) ) {
            self::process_order_scheduling( $order_id );
            wp_send_json_success( "Scheduled order {$order_id}" );
        } else {
            wp_send_json_error( "Invalid order ID" );
        }
    }
}