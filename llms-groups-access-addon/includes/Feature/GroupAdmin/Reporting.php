<?php
namespace LLMSGAA\Feature\GroupAdmin;

defined( 'ABSPATH' ) || exit;

use LLMS_Groups_Profile;
use LLMSGAA\Feature\GroupAdmin\Controller;

class Reporting {

    public static function init_hooks() {
        add_action( 'wp_enqueue_scripts',    [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'llms_group_profile_main_reports', [ __CLASS__, 'render_reports_tab' ] );
        add_action( 'wp_ajax_llmsgaa_get_course_report', [ __CLASS__, 'ajax_get_course_report' ] );
        add_action( 'wp_ajax_llmsgaa_get_student_detail', [ __CLASS__, 'ajax_get_student_detail' ] );
        
        // Add hook to track user activity on login
        add_action( 'wp_login', [ __CLASS__, 'track_user_login' ], 10, 2 );
        
        // Add hook to track activity when users access pages
        add_action( 'init', [ __CLASS__, 'track_user_activity' ] );
    }
    
    /**
     * Track user login to ensure last_activity is set
     */
    public static function track_user_login( $user_login, $user ) {
        update_user_meta( $user->ID, '_llms_last_user_activity', current_time( 'mysql' ) );
        update_user_meta( $user->ID, '_last_login_timestamp', time() );
    }
    
    /**
     * Track user activity on page loads (throttled to once per hour)
     */
    public static function track_user_activity() {
        if ( ! is_user_logged_in() ) {
            return;
        }
        
        $user_id = get_current_user_id();
        $last_tracked = get_transient( 'llmsgaa_activity_tracked_' . $user_id );
        
        if ( ! $last_tracked ) {
            update_user_meta( $user_id, '_llms_last_user_activity', current_time( 'mysql' ) );
            set_transient( 'llmsgaa_activity_tracked_' . $user_id, true, HOUR_IN_SECONDS );
        }
    }
    
    /**
     * Get user's last activity with multiple fallbacks
     */
    private static function get_user_last_activity( $user_id ) {
        // First try the standard LLMS meta
        $last_activity = get_user_meta( $user_id, '_llms_last_user_activity', true );
        
        // If empty, check our backup timestamp
        if ( empty( $last_activity ) ) {
            $timestamp = get_user_meta( $user_id, '_last_login_timestamp', true );
            if ( $timestamp ) {
                $last_activity = date( 'Y-m-d H:i:s', $timestamp );
            }
        }
        
        // If still empty, check WordPress session tokens
        if ( empty( $last_activity ) ) {
            $sessions = \WP_Session_Tokens::get_instance( $user_id );
            $all_sessions = $sessions->get_all();
            if ( ! empty( $all_sessions ) ) {
                $latest = 0;
                foreach ( $all_sessions as $session ) {
                    if ( isset( $session['login'] ) && $session['login'] > $latest ) {
                        $latest = $session['login'];
                    }
                }
                if ( $latest > 0 ) {
                    $last_activity = date( 'Y-m-d H:i:s', $latest );
                    // Save it for next time
                    update_user_meta( $user_id, '_llms_last_user_activity', $last_activity );
                }
            }
        }
        
        // Final fallback: check user registration date
        if ( empty( $last_activity ) ) {
            $user = get_userdata( $user_id );
            if ( $user && ! empty( $user->user_registered ) ) {
                // User has registered, so they must have logged in at least once
                $last_activity = $user->user_registered;
                // Save it for next time
                update_user_meta( $user_id, '_llms_last_user_activity', $last_activity );
            }
        }
        
        return $last_activity;
    }

    public static function enqueue_assets() {
        if ( LLMS_Groups_Profile::get_current_tab() !== 'reports' ) {
            return;
        }

        $group_id = Controller::resolve_group_id();

        wp_enqueue_script(
            'llmsgaa-reports',
            LLMSGAA_URL . 'public/js/reports.js',
            [ 'jquery' ],
            '1.1.0',
            true
        );
        wp_localize_script( 'llmsgaa-reports', 'LLMSGAA_Reports', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'llmsgaa_reports_nonce' ),
            'group_id' => $group_id,
        ] );
        wp_enqueue_style(
            'llmsgaa-reports',
            LLMSGAA_URL . 'public/css/reports.css',
            [],
            '1.1.0'
        );
    }

    public static function render_reports_tab( $group_id = null ) {
        if ( ! $group_id ) {
            $group_id = Controller::resolve_group_id();
        }

        $courses = self::get_group_courses( $group_id );

        if ( empty( $courses ) ) {
            echo '<div class="llmsgaa-reports-container">
                    <div class="llmsgaa-notice llmsgaa-notice-info">
                        <h3>No Courses Available</h3>
                        <p>This group doesn\'t have access to any courses yet. Course access is granted through group orders and memberships.</p>
                    </div>
                  </div>';
            return;
        }

        echo '<div class="llmsgaa-reports-container">';
        echo '<h3 class="llmsgaa-reports-title">📊 Group Progress Reports</h3>';
        echo '<p class="llmsgaa-reports-subtitle">Select a course to view detailed progress reports for your group members.</p>';
        
        // Course selection cards
        echo '<div class="llmsgaa-course-selection">';
        echo '<div class="llmsgaa-course-cards">';
        
        foreach ( $courses as $course_id => $course_data ) {
            self::render_course_card( $course_id, $course_data, $group_id );
        }
        
        echo '</div>';
        echo '</div>';

        // Report results container
        echo '<div id="llmsgaa-report-results" class="llmsgaa-report-results" style="display: none;">
                <div class="llmsgaa-report-header">
                    <button id="llmsgaa-back-to-courses" class="llmsgaa-back-btn">
                        <span class="llmsgaa-back-icon">←</span> Back to Course Selection
                    </button>
                </div>
                <div id="llmsgaa-report-content"></div>
              </div>';
        
        echo '</div>';
    }

    /**
     * Get all courses available to this group
     */
    private static function get_group_courses( $group_id ) {
        global $wpdb;
        
        $courses = [];

        // 1. Get courses from direct group orders
        $orders = get_posts([
            'post_type'      => 'llms_group_order',
            'posts_per_page' => -1,
            'meta_query'     => [[ 'key' => 'group_id', 'value' => $group_id ]],
        ]);

        foreach ( $orders as $order ) {
            $product_id = get_post_meta( $order->ID, 'product_id', true );
            if ( ! $product_id ) continue;

            $post_type = get_post_type( $product_id );
            
            if ( $post_type === 'course' ) {
                $courses[ $product_id ] = [
                    'title' => get_the_title( $product_id ),
                    'type' => 'Course',
                    'source' => 'Direct Access',
                    'enrollment_count' => self::get_course_enrollment_count( $product_id, $group_id )
                ];
            } elseif ( $post_type === 'llms_membership' ) {
                // 2. Get auto-enrolled courses from membership
                $auto_enroll = get_post_meta( $product_id, '_llms_auto_enroll', true );
                if ( $auto_enroll && is_array( $auto_enroll ) ) {
                    foreach ( $auto_enroll as $course_id ) {
                        if ( get_post_type( $course_id ) === 'course' ) {
                            $courses[ $course_id ] = [
                                'title' => get_the_title( $course_id ),
                                'type' => 'Course',
                                'source' => 'Membership: ' . get_the_title( $product_id ),
                                'enrollment_count' => self::get_course_enrollment_count( $course_id, $group_id )
                            ];
                        }
                    }
                } else {
                    // Membership itself (if it has lessons/content)
                    $courses[ $product_id ] = [
                        'title' => get_the_title( $product_id ),
                        'type' => 'Membership',
                        'source' => 'Direct Access',
                        'enrollment_count' => self::get_course_enrollment_count( $product_id, $group_id )
                    ];
                }
            }
        }

        return $courses;
    }

/**
 * Get enrollment count for a course from this group (including ALL enrolled group members)
 */
private static function get_course_enrollment_count( $course_id, $group_id ) {
    global $wpdb;
    
    // Get ALL group members (admin + regular members)
    $group_members = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT user_id 
         FROM {$wpdb->prefix}lifterlms_user_postmeta 
         WHERE post_id = %d 
         AND meta_key = '_group_role'
         AND meta_value IN ('admin', 'member')",
        $group_id
    ) );
    
    if ( empty( $group_members ) ) {
        return 0;
    }
    
    $enrolled_count = 0;
    $meta_tbl = $wpdb->prefix . 'lifterlms_user_postmeta';
    
    // Check each group member to see if they're enrolled in the course
    foreach ( $group_members as $user_id ) {
        $enrollment_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$meta_tbl}
             WHERE user_id = %d AND post_id = %d AND meta_key = '_status'",
            $user_id, $course_id
        ) );
        
        // Count as enrolled if they have an active enrollment status
        if ( in_array( $enrollment_status, [ 'enrolled', 'active' ] ) ) {
            $enrolled_count++;
        }
    }
    
    return $enrolled_count;
}

    /**
     * Render a course selection card
     */
    private static function render_course_card( $course_id, $course_data, $group_id ) {
        $enrollment_count = $course_data['enrollment_count'];
        $status_class = $enrollment_count > 0 ? 'has-enrollments' : 'no-enrollments';
        
        echo '<div class="llmsgaa-course-report-card ' . esc_attr( $status_class ) . '" data-course-id="' . esc_attr( $course_id ) . '">';
        
        echo '<div class="llmsgaa-course-header">';
        echo '<h4 class="llmsgaa-course-title">';
        echo '<span class="llmsgaa-course-type-badge">' . esc_html( $course_data['type'] ) . '</span>';
        echo esc_html( $course_data['title'] );
        echo '</h4>';
        echo '<div class="llmsgaa-course-stats">';
        echo '<span class="llmsgaa-enrollment-count">';
        echo '<span class="llmsgaa-count-number">' . $enrollment_count . '</span>';
        echo '<span class="llmsgaa-count-label">enrolled</span>';
        echo '</span>';
        echo '</div>';
        echo '</div>';


        echo '<div class="llmsgaa-course-meta">';
        echo '<div class="llmsgaa-course-source">';
        echo '<strong>Access via:</strong> ' . esc_html( $course_data['source'] );
        echo '</div>';
        echo '</div>';

        echo '<div class="llmsgaa-course-actions">';
        if ( $enrollment_count > 0 ) {
            echo '<button class="llmsgaa-view-report-btn" data-course-id="' . esc_attr( $course_id ) . '">';
            echo '<span class="llmsgaa-btn-icon">📊</span> View Progress Report';
            echo '</button>';
        } else {
            echo '<div class="llmsgaa-no-enrollments-msg">';
            echo '<span class="llmsgaa-info-icon">ℹ️</span>';
            echo 'No group members enrolled yet';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';
    }

    public static function ajax_get_course_report() {
        check_ajax_referer( 'llmsgaa_reports_nonce', 'nonce' );
        $group_id  = intval( $_POST['group_id'] );
        $course_id = intval( $_POST['course_id'] );

        $course_title = get_the_title( $course_id );
        $course_type = get_post_type( $course_id ) === 'llms_membership' ? 'Membership' : 'Course';

        // Get enrolled students from this group
        $students = self::get_course_students( $group_id, $course_id );

        if ( empty( $students ) ) {
            echo '<div class="llmsgaa-notice llmsgaa-notice-info">
                    <h3>No Enrollments Found</h3>
                    <p>No group members are currently enrolled in this ' . strtolower( $course_type ) . '.</p>
                  </div>';
            wp_die();
        }

        // Report header
        echo '<div class="llmsgaa-report-header-info">';
        echo '<h3 class="llmsgaa-report-title">';
        echo '<span class="llmsgaa-course-type-badge">' . esc_html( $course_type ) . '</span>';
        echo esc_html( $course_title ) . ' - Progress Report';
        echo '</h3>';
        echo '<div class="llmsgaa-report-stats">';
        echo '<div class="llmsgaa-stat-item">';
        echo '<span class="llmsgaa-stat-value">' . count( $students ) . '</span>';
        echo '<span class="llmsgaa-stat-label">Total Enrolled</span>';
        echo '</div>';
        $completed_count = count( array_filter( $students, function( $s ) { return $s['progress'] >= 100; } ) );
        echo '<div class="llmsgaa-stat-item">';
        echo '<span class="llmsgaa-stat-value">' . $completed_count . '</span>';
        echo '<span class="llmsgaa-stat-label">Completed</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Students table
        echo '<div class="llmsgaa-report-table-container">';
        echo '<table class="llmsgaa-report-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Student</th>';
        echo '<th>Status</th>';
        echo '<th>Progress</th>';
        echo '<th>Last Activity</th>';
        echo '<th>Completed</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ( $students as $student ) {
            self::render_student_row( $student, $course_id );
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        wp_die();
    }

/**
 * Get students enrolled in a course from this group (ALL enrolled group members)
 */
private static function get_course_students( $group_id, $course_id ) {
    global $wpdb;
    
    // Get ALL group members
    $group_members = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT user_id 
         FROM {$wpdb->prefix}lifterlms_user_postmeta 
         WHERE post_id = %d 
         AND meta_key = '_group_role'
         AND meta_value IN ('admin', 'member')",
        $group_id
    ) );
    
    if ( empty( $group_members ) ) {
        return [];
    }
    
    $students = [];
    $meta_tbl = $wpdb->prefix . 'lifterlms_user_postmeta';
    
    foreach ( $group_members as $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) continue;
        
        // Check if this user is enrolled in the course
        $enrollment_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$meta_tbl}
             WHERE user_id = %d AND post_id = %d AND meta_key = '_status'",
            $user_id, $course_id
        ) );
        
        // Only include if they're actually enrolled
        if ( ! in_array( $enrollment_status, [ 'enrolled', 'active' ] ) ) {
            continue;
        }
        
        // Get progress using our custom calculation
        $progress = self::calculate_course_progress( $course_id, $user_id );
        
        // Get last activity with fallbacks
        $last_activity = self::get_user_last_activity( $user_id );
        
        // Get overall course completion date (only if course is 100% complete)
        $course_completed_date = null;
        if ( $progress >= 100 ) {
            $course_completed_date = get_user_meta( $user_id, "_course_{$course_id}_completed", true );
        }
        
        // Build display name
        $display_name = self::build_student_display_name( $user );
        
        // Determine how they got access (for display purposes)
        $access_source = self::determine_access_source( $group_id, $user_id, $course_id );
        
        $students[] = [
            'id' => $user_id,
            'name' => $display_name,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'status' => $enrollment_status,
            'progress' => $progress,
            'last_activity' => $last_activity,
            'course_completed_date' => $course_completed_date,
            'order_id' => null, // May not have a specific order
            'access_type' => $access_source['type'],
            'access_source' => $access_source['description']
        ];
    }
    
    return $students;
}

/**
 * Build student data array
 */
private static function build_student_data( $user, $course_id, $order_id, $access_type, $membership_id = null ) {
    global $wpdb;
    $meta_tbl = $wpdb->prefix . 'lifterlms_user_postmeta';
    
    // Get enrollment status
    $status = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$meta_tbl}
         WHERE user_id = %d AND post_id = %d AND meta_key = '_status'",
        $user->ID, $course_id
    ) ) ?: 'pending';
    
    // Get progress using our custom calculation
    $progress = self::calculate_course_progress( $course_id, $user->ID );
    
    // Get last activity with fallbacks
    $last_activity = self::get_user_last_activity( $user->ID );
    
    // Get overall course completion date (only if course is 100% complete)
    $course_completed_date = null;
    if ( $progress >= 100 ) {
        $course_completed_date = get_user_meta( $user->ID, "_course_{$course_id}_completed", true );
    }
    
    // Build display name
    $display_name = self::build_student_display_name( $user );
    
    // Determine access source for display
    $access_source = 'Direct Course Access';
    if ( $access_type === 'membership_auto_enroll' && $membership_id ) {
        $membership_title = get_the_title( $membership_id );
        $access_source = 'Membership: ' . $membership_title;
    }
    
    return [
        'id' => $user->ID,
        'name' => $display_name,
        'email' => $user->user_email,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'status' => $status,
        'progress' => $progress,
        'last_activity' => $last_activity,
        'course_completed_date' => $course_completed_date,
        'order_id' => $order_id,
        'access_type' => $access_type,
        'access_source' => $access_source
    ];
}

/**
 * Check if a student is enrolled in a course
 */
private static function is_student_enrolled_in_course( $student_id, $course_id ) {
    global $wpdb;
    $meta_tbl = $wpdb->prefix . 'lifterlms_user_postmeta';
    
    $enrollment = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$meta_tbl}
         WHERE user_id = %d AND post_id = %d AND meta_key = '_status'",
        $student_id, $course_id
    ) );
    
    return in_array( $enrollment, [ 'enrolled', 'active' ] );
}


/**
 * Determine how a user got access to a course
 */
private static function determine_access_source( $group_id, $user_id, $course_id ) {
    global $wpdb;
    
    // Check if they have a direct course order
    $direct_order = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id AND pm_group.meta_key = 'group_id' AND pm_group.meta_value = %d
         INNER JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = 'product_id' AND pm_product.meta_value = %d
         INNER JOIN {$wpdb->postmeta} pm_student ON p.ID = pm_student.post_id AND pm_student.meta_key = 'student_id' AND pm_student.meta_value = %d
         WHERE p.post_type = 'llms_group_order'
         LIMIT 1",
        $group_id, $course_id, $user_id
    ) );
    
    if ( $direct_order ) {
        return [
            'type' => 'direct_course',
            'description' => 'Direct Course Access'
        ];
    }
    
    // Check if they have membership access
    $membership_orders = get_posts([
        'post_type'      => 'llms_group_order',
        'posts_per_page' => -1,
        'meta_query'     => [
            [ 'key' => 'group_id', 'value' => $group_id ],
            [ 'key' => 'student_id', 'value' => $user_id ],
        ],
    ]);
    
    foreach ( $membership_orders as $order ) {
        $product_id = get_post_meta( $order->ID, 'product_id', true );
        if ( get_post_type( $product_id ) === 'llms_membership' ) {
            $auto_enroll = get_post_meta( $product_id, '_llms_auto_enroll', true );
            if ( $auto_enroll && is_array( $auto_enroll ) && in_array( $course_id, $auto_enroll ) ) {
                return [
                    'type' => 'membership_auto_enroll',
                    'description' => 'Membership: ' . get_the_title( $product_id )
                ];
            }
        }
    }
    
    // Check user's group role for fallback
    $user_role = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->prefix}lifterlms_user_postmeta 
         WHERE user_id = %d AND post_id = %d AND meta_key = '_group_role'",
        $user_id, $group_id
    ) );
    
    if ( $user_role === 'admin' ) {
        return [
            'type' => 'admin_access',
            'description' => 'Group Admin Access'
        ];
    }
    
    // Fallback
    return [
        'type' => 'other',
        'description' => 'Group Member Access'
    ];
}

/**
 * Build display name from user data with proper fallbacks (same logic as UnifiedMemberManager)
 * 
 * @param WP_User $user
 * @return string Display name
 */
private static function build_student_display_name( $user ) {
    $first_name = trim( $user->first_name );
    $last_name = trim( $user->last_name );
    
    // Priority 1: First Name + Last Name
    if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
        return $first_name . ' ' . $last_name;
    }
    
    // Priority 2: First Name only
    if ( ! empty( $first_name ) ) {
        return $first_name;
    }
    
    // Priority 3: Last Name only
    if ( ! empty( $last_name ) ) {
        return $last_name;
    }
    
    // Priority 4: Display Name (if set)
    if ( ! empty( $user->display_name ) && $user->display_name !== $user->user_login ) {
        return $user->display_name;
    }
    
    // Priority 5: Fallback to username (user_login)
    return $user->user_login;
}

/**
 * Render a student row in the report table
 */
private static function render_student_row( $student, $course_id ) {
    $status_class = 'llmsgaa-status-' . $student['status'];
    $progress_class = $student['progress'] >= 100 ? 'completed' : ( $student['progress'] > 0 ? 'in-progress' : 'not-started' );

    echo '<tr class="' . esc_attr( $status_class ) . '">';

    // Student name and info
    echo '<td class="llmsgaa-student-info">';
    echo '<div class="llmsgaa-student-name">';
    echo '<strong>' . esc_html( $student['name'] ) . '</strong>';
    echo '<small class="llmsgaa-student-email">' . esc_html( $student['email'] ) . '</small>';
    
    // Show access source
    if ( isset( $student['access_source'] ) ) {
        $access_class = $student['access_type'] === 'membership_auto_enroll' ? 'membership-access' : 'direct-access';
        echo '<small class="llmsgaa-access-source ' . esc_attr( $access_class ) . '">';
        if ( $student['access_type'] === 'membership_auto_enroll' ) {
            echo '🎫 ' . esc_html( $student['access_source'] );
        } else {
            echo '📚 ' . esc_html( $student['access_source'] );
        }
        echo '</small>';
    }
    echo '</div>';
    echo '</td>';

    // Status
    echo '<td>';
    echo '<span class="llmsgaa-status-badge llmsgaa-status-' . esc_attr( $student['status'] ) . '">';
    echo esc_html( ucfirst( $student['status'] ) );
    echo '</span>';
    echo '</td>';

    // Progress
    echo '<td class="llmsgaa-progress-cell">';
    echo '<div class="llmsgaa-progress-container">';
    echo '<div class="llmsgaa-progress-bar">';
    echo '<div class="llmsgaa-progress-fill ' . esc_attr( $progress_class ) . '" style="width: ' . $student['progress'] . '%"></div>';
    echo '</div>';
    echo '<span class="llmsgaa-progress-text">' . $student['progress'] . '%</span>';
    echo '</div>';
    echo '</td>';

    // Last activity - UPDATED WITH BETTER HANDLING
    echo '<td>';
    if ( ! empty( $student['last_activity'] ) ) {
        // Check if we have the UnifiedMemberManager class available
        if ( class_exists( '\LLMSGAA\Feature\Shortcodes\UnifiedMemberManager' ) ) {
            $formatted_time = \LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::format_last_login( $student['last_activity'] );
        } else {
            // Fallback formatting if class not available
            $timestamp = strtotime( $student['last_activity'] );
            $now = current_time( 'timestamp' );
            $diff = $now - $timestamp;
            
            if ( $diff < 60 ) {
                $formatted_time = 'Just now';
            } elseif ( $diff < 3600 ) {
                $formatted_time = round( $diff / 60 ) . ' min ago';
            } elseif ( $diff < 86400 ) {
                $formatted_time = round( $diff / 3600 ) . ' hours ago';
            } elseif ( $diff < 172800 ) {
                $formatted_time = 'Yesterday';
            } elseif ( $diff < 604800 ) {
                $formatted_time = round( $diff / 86400 ) . ' days ago';
            } else {
                $formatted_time = date_i18n( 'M j, Y', $timestamp );
            }
        }
        
        // Add color coding
        $color_style = '';
        $icon = '';
        
        if ( $formatted_time === 'Just now' || strpos( $formatted_time, 'min ago' ) !== false ) {
            $color_style = 'color: #059669; font-weight: 600;';
            $icon = ' 🟢';
        } elseif ( strpos( $formatted_time, 'hour' ) !== false || $formatted_time === 'Yesterday' ) {
            $color_style = 'color: #2563eb;';
        } elseif ( strpos( $formatted_time, 'days ago' ) !== false ) {
            $color_style = 'color: #6b7280;';
        } elseif ( strpos( $formatted_time, 'week' ) !== false ) {
            $color_style = 'color: #ea580c;';
        } else {
            $color_style = 'color: #6b7280;';
        }
        
        echo '<span style="' . $color_style . '">' . esc_html( $formatted_time ) . $icon . '</span>';
    } else {
        // This should rarely happen now with our fallbacks
        echo '<span class="llmsgaa-no-activity" style="color: #dc2626;">Never logged in ⚠️</span>';
    }
    echo '</td>';

    // Course completed date (only show if course is 100% complete)
    echo '<td>';
    if ( $student['course_completed_date'] && $student['progress'] >= 100 ) {
        echo '<span class="llmsgaa-completed-date">';
        echo esc_html( date_i18n( 'M j, Y', strtotime( $student['course_completed_date'] ) ) );
        echo '</span>';
    } else {
        echo '<span class="llmsgaa-not-completed">—</span>';
    }
    echo '</td>';

    // Actions
    echo '<td class="llmsgaa-actions-cell">';
    echo '<button class="llmsgaa-view-details-btn" data-student-id="' . esc_attr( $student['id'] ) . '" data-course-id="' . esc_attr( $course_id ) . '">';
    echo '<span class="llmsgaa-btn-icon">👁</span> Details';
    echo '</button>';
    echo '</td>';

    echo '</tr>';
}

public static function ajax_get_student_detail() {
    check_ajax_referer( 'llmsgaa_reports_nonce', 'nonce' );
    $student_id = intval( $_POST['student_id'] );
    $course_id = intval( $_POST['course_id'] );

    $user = get_userdata( $student_id );
    $course_title = get_the_title( $course_id );

    // Build display name consistently
    $display_name = self::build_student_display_name( $user );
    
    // Get detailed progress using our custom calculation
    $progress_info = self::get_detailed_progress_info( $course_id, $student_id );
    $progress = $progress_info['progress_percentage'];
    $progress_class = $progress >= 100 ? 'completed' : ( $progress > 0 ? 'in-progress' : 'not-started' );

    echo '<div class="llmsgaa-student-detail-modal">';
    echo '<div class="llmsgaa-modal-header">';
    echo '<h4>' . esc_html( $display_name ) . ' - ' . esc_html( $course_title ) . '</h4>';
    echo '<button class="llmsgaa-close-modal">&times;</button>';
    echo '</div>';

    echo '<div class="llmsgaa-modal-content">';
    
    // Overall Progress Bar (prominent at top)
    echo '<div class="llmsgaa-overall-progress-section">';
    echo '<div class="llmsgaa-progress-header">';
    echo '<h5 class="llmsgaa-progress-title">Overall Course Progress</h5>';
    echo '<span class="llmsgaa-progress-percentage">' . $progress . '%</span>';
    echo '</div>';
    echo '<div class="llmsgaa-progress-bar-container">';
    echo '<div class="llmsgaa-progress-bar-large">';
    echo '<div class="llmsgaa-progress-fill-large ' . esc_attr( $progress_class ) . '" style="width: ' . $progress . '%"></div>';
    echo '</div>';
    echo '</div>';
    
    // Progress status message with lesson count
    echo '<div class="llmsgaa-progress-details">';
    echo '<div class="llmsgaa-lesson-count">';
    echo '<strong>' . $progress_info['completed_lessons'] . '</strong> of <strong>' . $progress_info['total_lessons'] . '</strong> lessons completed';
    echo '</div>';
    
    if ( $progress >= 100 ) {
        echo '<div class="llmsgaa-progress-status completed">🎉 Course completed!</div>';
    } elseif ( $progress > 0 ) {
        echo '<div class="llmsgaa-progress-status in-progress">📚 Course in progress</div>';
    } else {
        echo '<div class="llmsgaa-progress-status not-started">⏳ Course not started</div>';
    }
    echo '</div>';
    echo '</div>';
    
    // Student info summary (now below progress)
    echo '<div class="llmsgaa-student-summary">';
    echo '<div class="llmsgaa-summary-item">';
    echo '<span class="llmsgaa-summary-label">Student Name:</span>';
    echo '<span class="llmsgaa-summary-value">' . esc_html( $display_name ) . '</span>';
    echo '</div>';
    echo '<div class="llmsgaa-summary-item">';
    echo '<span class="llmsgaa-summary-label">Email:</span>';
    echo '<span class="llmsgaa-summary-value">' . esc_html( $user->user_email ) . '</span>';
    echo '</div>';
    echo '</div>';

    // Lesson progress table
    echo '<div class="llmsgaa-lesson-progress">';
    echo '<h5>Detailed Lesson Progress</h5>';
    self::render_lesson_progress( $student_id, $course_id );
    echo '</div>';

    echo '</div>';
    echo '</div>';

    wp_die();
}


/**
 * Render detailed lesson progress
 */
private static function render_lesson_progress( $student_id, $course_id ) {
    global $wpdb;
    $meta_tbl = $wpdb->prefix . 'lifterlms_user_postmeta';

    // Get sections
    $sections = get_posts([
        'post_type'      => 'section',
        'posts_per_page' => -1,
        'meta_query'     => [[ 'key' => '_llms_parent_course', 'value' => $course_id ]],
        'meta_key'       => '_llms_order',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
    ]);

    if ( empty( $sections ) ) {
        echo '<p class="llmsgaa-no-lessons">No lessons found in this course.</p>';
        return;
    }

    echo '<div class="llmsgaa-lesson-table-container">';
    echo '<table class="llmsgaa-lesson-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Lesson</th>';
    echo '<th>Status</th>';
    echo '<th>Completed Date</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ( $sections as $section ) {
        // Section header
        echo '<tr class="llmsgaa-section-header">';
        echo '<th colspan="3">' . esc_html( get_the_title( $section->ID ) ) . '</th>';
        echo '</tr>';

        // Get lessons in this section
        $lessons = get_posts([
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'meta_query'     => [
                [ 'key' => '_llms_parent_section', 'value' => $section->ID ],
                [ 'key' => '_llms_parent_course', 'value' => $course_id ],
            ],
            'meta_key'       => '_llms_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
        ]);

        foreach ( $lessons as $lesson ) {
            // Get completion data using the correct meta key structure
$completion_data = $wpdb->get_row( $wpdb->prepare(
    "SELECT meta_value, updated_date 
     FROM {$meta_tbl} 
     WHERE user_id = %d 
     AND meta_key = '_completion_trigger'
     AND (meta_value = %s OR meta_value = %s)
     ORDER BY updated_date DESC 
     LIMIT 1",
    $student_id,
    'lesson_' . $lesson->ID,
    'video_' . $lesson->ID  // ← ADD THIS
) );

            $is_completed = ! empty( $completion_data );
            $completion_date = $is_completed ? $completion_data->updated_date : null;
            $status_class = $is_completed ? 'completed' : 'incomplete';

            echo '<tr class="llmsgaa-lesson-row">';
            echo '<td class="llmsgaa-lesson-title">' . esc_html( get_the_title( $lesson->ID ) ) . '</td>';
            echo '<td class="llmsgaa-lesson-status">';
            echo '<span class="llmsgaa-status-indicator llmsgaa-' . esc_attr( $status_class ) . '">';
            echo $is_completed ? '✅ Complete' : '⏳ Incomplete';
            echo '</span>';
            echo '</td>';
            echo '<td class="llmsgaa-lesson-date">';
            if ( $completion_date ) {
                echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $completion_date ) ) );
            } else {
                echo '<span class="llmsgaa-not-completed">—</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

/**
 * Calculate course progress based on completed lessons
 * 
 * @param int $course_id
 * @param int $student_id
 * @return int Progress percentage (0-100)
 */
private static function calculate_course_progress( $course_id, $student_id ) {
    global $wpdb;
    $meta_tbl = $wpdb->prefix . 'lifterlms_user_postmeta';
    
    // Get all lessons for this course
    $total_lessons = self::get_course_lesson_count( $course_id );
    
    if ( $total_lessons === 0 ) {
        return 0; // No lessons in course
    }
    
    // Get all lesson IDs for this course
    $lesson_ids = self::get_course_lesson_ids( $course_id );
    
    if ( empty( $lesson_ids ) ) {
        return 0;
    }
    
    $completed_lessons = 0;
    
    // Check completion for each lesson
    foreach ( $lesson_ids as $lesson_id ) {
$completion_data = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) 
     FROM {$meta_tbl} 
     WHERE user_id = %d 
     AND meta_key = '_completion_trigger'
     AND (meta_value = %s OR meta_value = %s)",
    $student_id,
    'lesson_' . $lesson_id,
    'video_' . $lesson_id
) );
		
		

        
        if ( $completion_data > 0 ) {
            $completed_lessons++;
        }
    }
    
    // Calculate percentage
    $progress = round( ( $completed_lessons / $total_lessons ) * 100 );
    
    return min( 100, max( 0, $progress ) ); // Ensure 0-100 range
}

/**
 * Get total number of lessons in a course
 * 
 * @param int $course_id
 * @return int Number of lessons
 */
private static function get_course_lesson_count( $course_id ) {
    $lesson_ids = self::get_course_lesson_ids( $course_id );
    return count( $lesson_ids );
}

/**
 * Get all lesson IDs for a course
 * 
 * @param int $course_id
 * @return array Array of lesson IDs
 */
private static function get_course_lesson_ids( $course_id ) {
    // Get all sections for this course
    $sections = get_posts([
        'post_type'      => 'section',
        'posts_per_page' => -1,
        'meta_query'     => [[ 'key' => '_llms_parent_course', 'value' => $course_id ]],
        'meta_key'       => '_llms_order',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'fields'         => 'ids'
    ]);
    
    if ( empty( $sections ) ) {
        return [];
    }
    
    $lesson_ids = [];
    
    // Get lessons from each section
    foreach ( $sections as $section_id ) {
        $section_lessons = get_posts([
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'meta_query'     => [
                [ 'key' => '_llms_parent_section', 'value' => $section_id ],
                [ 'key' => '_llms_parent_course', 'value' => $course_id ],
            ],
            'meta_key'       => '_llms_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'fields'         => 'ids'
        ]);
        
        $lesson_ids = array_merge( $lesson_ids, $section_lessons );
    }
    
    return array_unique( $lesson_ids );
}

/**
 * Get detailed progress information for a student
 * 
 * @param int $course_id
 * @param int $student_id
 * @return array Progress details
 */
private static function get_detailed_progress_info( $course_id, $student_id ) {
    $total_lessons = self::get_course_lesson_count( $course_id );
    $lesson_ids = self::get_course_lesson_ids( $course_id );
    
    if ( $total_lessons === 0 ) {
        return [
            'total_lessons' => 0,
            'completed_lessons' => 0,
            'progress_percentage' => 0,
            'status' => 'no_content'
        ];
    }
    
    global $wpdb;
    $meta_tbl = $wpdb->prefix . 'lifterlms_user_postmeta';
    $completed_lessons = 0;
    
    foreach ( $lesson_ids as $lesson_id ) {
$completion_data = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) 
     FROM {$meta_tbl} 
     WHERE user_id = %d 
     AND meta_key = '_completion_trigger'
     AND (meta_value = %s OR meta_value = %s)",
    $student_id,
    'lesson_' . $lesson_id,
    'video_' . $lesson_id
) );
        
        if ( $completion_data > 0 ) {
            $completed_lessons++;
        }
    }
    
    $progress = round( ( $completed_lessons / $total_lessons ) * 100 );
    
    // Determine status
    $status = 'not_started';
    if ( $progress >= 100 ) {
        $status = 'completed';
    } elseif ( $progress > 0 ) {
        $status = 'in_progress';
    }
    
    return [
        'total_lessons' => $total_lessons,
        'completed_lessons' => $completed_lessons,
        'progress_percentage' => $progress,
        'status' => $status
    ];
}


}
?>