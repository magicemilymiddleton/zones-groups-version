<?php
/**
 * Student Dashboard Shortcode
 * Shows granted course access for logged-in users
 
 v2.3a modified by D Stirling
 */

namespace LLMSGAA\Feature\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StudentDashboard {

    /**
     * Initialize the shortcode
     */
    public static function init() {
        add_shortcode( 'llmsgaa_student_dashboard', [ __CLASS__, 'render_dashboard' ] );
        add_shortcode( 'llmsgaa_my_course_access', [ __CLASS__, 'render_dashboard' ] ); // Alternative name
    }

    /**
     * Render the student dashboard
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_dashboard( $atts ) {
        // Parse attributes
        $atts = shortcode_atts( [
            'title' => 'Your Course Access',
            'show_expired' => 'true',
            'show_upcoming' => 'true',
            'style' => 'cards' // 'cards', 'table', or 'list'
        ], $atts );

        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return '<div class="llmsgaa-notice llmsgaa-notice-info">Please log in to view your course access.</div>';
        }

        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        // Get user's granted course access
        $course_access = self::get_user_course_access( $user_email );

        if ( empty( $course_access ) ) {
            return '<div class="llmsgaa-notice llmsgaa-notice-info">
                        <h3>' . esc_html( $atts['title'] ) . '</h3>
                        <p>You have not yet been assigned an Access Pass. Contact your Group Admin or Zones Support for assistance.</p>
                    </div>';
        }

        // Filter courses based on attributes
        $filtered_courses = self::filter_courses( $course_access, $atts );

        if ( empty( $filtered_courses ) ) {
            return '<div class="llmsgaa-notice llmsgaa-notice-info">
                        <h3>' . esc_html( $atts['title'] ) . '</h3>
                        <p>No courses match the current filter criteria.</p>
                    </div>';
        }

        // Generate output based on style
        switch ( $atts['style'] ) {
            case 'table':
                return self::render_table_style( $filtered_courses, $atts );
            case 'list':
                return self::render_list_style( $filtered_courses, $atts );
            case 'cards':
            default:
                return self::render_cards_style( $filtered_courses, $atts );
        }
    }

    /**
     * Get user's course access from all groups
     * 
     * @param string $user_email
     * @return array Course access information
     */
    private static function get_user_course_access( $user_email ) {
        global $wpdb;

        $courses = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID as order_id,
                    pm_product.meta_value as product_id,
                    pm_start.meta_value as start_date,
                    pm_end.meta_value as end_date,
                    pm_status.meta_value as status,
                    pm_group.meta_value as group_id,
                    p.post_title as order_title
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'student_email' AND pm_email.meta_value = %s
             LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = 'product_id'
             LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'start_date'
             LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = 'end_date'
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status'
             LEFT JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id AND pm_group.meta_key = 'group_id'
             WHERE p.post_type = 'llms_group_order'
             AND p.post_status = 'publish'
             ORDER BY pm_start.meta_value ASC, pm_product.meta_value ASC",
            $user_email
        ) );

        $course_access = [];

        foreach ( $courses as $course ) {
            // Get course/product info
            $course_title = 'Unknown Course';
            $course_type = 'Course';
            $course_description = '';

            if ( $course->product_id ) {
                $product = get_post( $course->product_id );
                if ( $product ) {
                    $course_title = $product->post_title;
                    $course_description = wp_trim_words( $product->post_excerpt ?: $product->post_content, 20 );
                    
                    // Determine if it's a course or membership
                    $post_type = get_post_type( $course->product_id );
                    if ( $post_type === 'llms_membership' ) {
                        $course_type = 'Membership';
                    } elseif ( $post_type === 'course' ) {
                        $course_type = 'Course';
                    }
                }
            }

            // Get group info
            $group_title = 'Unknown Group';
            if ( $course->group_id ) {
                $group = get_post( $course->group_id );
                if ( $group ) {
                    $group_title = $group->post_title;
                }
            }

            // Format dates and determine status
            $start_formatted = '';
            $end_formatted = '';
            $status_info = self::get_course_status( $course->start_date, $course->end_date, $course->status );

            if ( $course->start_date ) {
                $start_formatted = date_i18n( 'F j, Y', strtotime( $course->start_date ) );
            }

            if ( $course->end_date ) {
                $end_formatted = date_i18n( 'F j, Y', strtotime( $course->end_date ) );
            }

            $course_access[] = [
                'order_id' => $course->order_id,
                'product_id' => $course->product_id,
                'course_title' => $course_title,
                'course_type' => $course_type,
                'course_description' => $course_description,
                'group_title' => $group_title,
                'group_id' => $course->group_id,
                'start_date' => $start_formatted,
                'end_date' => $end_formatted,
                'start_date_raw' => $course->start_date,
                'end_date_raw' => $course->end_date,
                'status' => $course->status ?: 'active',
                'status_info' => $status_info
            ];
        }

        return $course_access;
    }

    /**
     * Determine course status based on dates
     * 
     * @param string $start_date
     * @param string $end_date
     * @param string $current_status
     * @return array Status information
     */
    private static function get_course_status( $start_date, $end_date, $current_status ) {
        $now = current_time( 'timestamp' );
        $start_timestamp = $start_date ? strtotime( $start_date ) : null;
        $end_timestamp = $end_date ? strtotime( $end_date ) : null;

        // Check if expired
        if ( $end_timestamp && $now > $end_timestamp ) {
            return [
                'status' => 'expired',
                'label' => 'Expired',
                'icon' => '🔴',
                'class' => 'llmsgaa-status-expired',
                'message' => 'This access has expired'
            ];
        }

        // Check if upcoming
        if ( $start_timestamp && $now < $start_timestamp ) {
            $days_until = ceil( ( $start_timestamp - $now ) / DAY_IN_SECONDS );
            return [
                'status' => 'upcoming',
                'label' => 'Not Started',
                'icon' => '🟡',
                'class' => 'llmsgaa-status-upcoming',
                'message' => "Access starts in {$days_until} day" . ( $days_until > 1 ? 's' : '' )
            ];
        }

        // Check if ending soon (within 7 days)
        if ( $end_timestamp ) {
            $days_until_end = ceil( ( $end_timestamp - $now ) / DAY_IN_SECONDS );
            if ( $days_until_end <= 30 && $days_until_end > 0 ) {
                return [
                    'status' => 'ending_soon',
                    'label' => 'Ending Soon',
                    'icon' => '🟠',
                    'class' => 'llmsgaa-status-ending-soon',
                    'message' => "Access ends in {$days_until_end} day" . ( $days_until_end > 1 ? 's' : '' )
                ];
            }
        }

        // Active
        return [
            'status' => 'active',
            'label' => 'Active',
            'icon' => '🟢',
            'class' => 'llmsgaa-status-active',
            'message' => 'You currently have access'
        ];
    }

    /**
     * Filter courses based on shortcode attributes
     * 
     * @param array $courses
     * @param array $atts
     * @return array Filtered courses
     */
    private static function filter_courses( $courses, $atts ) {
        $filtered = [];

        foreach ( $courses as $course ) {
            $status = $course['status_info']['status'];

            // Filter expired courses
            if ( $status === 'expired' && $atts['show_expired'] !== 'true' ) {
                continue;
            }

            // Filter upcoming courses
            if ( $status === 'upcoming' && $atts['show_upcoming'] !== 'true' ) {
                continue;
            }

            $filtered[] = $course;
        }

        return $filtered;
    }

    /**
     * Render courses in card style
     */
    private static function render_cards_style( $courses, $atts ) {
        ob_start();
        ?>
        <div class="llmsgaa-student-dashboard">
            <h3 class="llmsgaa-dashboard-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <div class="llmsgaa-course-cards">
                <?php foreach ( $courses as $course ): ?>
                <div class="llmsgaa-course-card <?php echo esc_attr( $course['status_info']['class'] ); ?>">
                    <div class="llmsgaa-course-header">
                        <h4 class="llmsgaa-course-title">
                            <span class="llmsgaa-course-type-badge"><?php echo esc_html( $course['course_type'] ); ?></span>
                            <?php echo esc_html( $course['course_title'] ); ?>
                        </h4>
                        <div class="llmsgaa-course-status">
                            <span class="llmsgaa-status-icon"><?php echo $course['status_info']['icon']; ?></span>
                            <span class="llmsgaa-status-label"><?php echo esc_html( $course['status_info']['label'] ); ?></span>
                        </div>
                    </div>
                            
                    <div class="llmsgaa-course-details">
                        <div class="llmsgaa-course-group">
                            <strong>Group:</strong> <?php echo esc_html( $course['group_title'] ); ?>
                        </div>
                        
                        <?php if ( $course['start_date'] || $course['end_date'] ): ?>
                        <div class="llmsgaa-course-dates">
                            <?php if ( $course['start_date'] ): ?>
                                <div class="llmsgaa-date-item">
                                    <span class="llmsgaa-date-label">📅 Starts:</span>
                                    <span class="llmsgaa-date-value"><?php echo esc_html( $course['start_date'] ); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ( $course['end_date'] ): ?>
                                <div class="llmsgaa-date-item">
                                    <span class="llmsgaa-date-label">⏰ Ends:</span>
                                    <span class="llmsgaa-date-value"><?php echo esc_html( $course['end_date'] ); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="llmsgaa-date-item">
                                    <span class="llmsgaa-date-label">⏰ Duration:</span>
                                    <span class="llmsgaa-date-value">Ongoing</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="llmsgaa-course-message">
                        <small><?php echo esc_html( $course['status_info']['message'] ); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render courses in table style
     */
    private static function render_table_style( $courses, $atts ) {
        ob_start();
        ?>
        <div class="llmsgaa-student-dashboard">
            <h3 class="llmsgaa-dashboard-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <div class="llmsgaa-table-wrapper">
                <table class="llmsgaa-course-table">
                    <thead>
                        <tr>
                            <th>Course / Membership</th>
                            <th>Group</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $courses as $course ): ?>
                        <tr class="<?php echo esc_attr( $course['status_info']['class'] ); ?>">
                            <td>
                                <div class="llmsgaa-course-info">
                                    <strong><?php echo esc_html( $course['course_title'] ); ?></strong>
                                    <small class="llmsgaa-course-type"><?php echo esc_html( $course['course_type'] ); ?></small>
                                </div>
                            </td>
                            <td><?php echo esc_html( $course['group_title'] ); ?></td>
                            <td><?php echo esc_html( $course['start_date'] ?: 'Not set' ); ?></td>
                            <td><?php echo esc_html( $course['end_date'] ?: 'Ongoing' ); ?></td>
                            <td>
                                <span class="llmsgaa-status">
                                    <?php echo $course['status_info']['icon']; ?> <?php echo esc_html( $course['status_info']['label'] ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render courses in simple list style
     */
    private static function render_list_style( $courses, $atts ) {
        ob_start();
        ?>
        <div class="llmsgaa-student-dashboard">
            <h3 class="llmsgaa-dashboard-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <ul class="llmsgaa-course-list">
                <?php foreach ( $courses as $course ): ?>
                <li class="llmsgaa-course-item <?php echo esc_attr( $course['status_info']['class'] ); ?>">
                    <div class="llmsgaa-course-summary">
                        <strong><?php echo esc_html( $course['course_title'] ); ?></strong>
                        <span class="llmsgaa-course-type">(<?php echo esc_html( $course['course_type'] ); ?>)</span>
                 	  <!-- modified llmsgaa-status to always show on new line -->
<div class="llmsgaa-status" style="display: block; width: 100%; margin-top: 2px;">
    <?php echo $course['status_info']['icon']; ?> <?php echo esc_html( $course['status_info']['label'] ); ?>
</div>
                    </div>
                    <div class="llmsgaa-course-dates-inline">
                        <?php if ( $course['start_date'] ): ?>
                            <?php echo esc_html( $course['start_date'] ); ?>
                        <?php endif; ?>
                        <?php if ( $course['end_date'] ): ?>
                            → <?php echo esc_html( $course['end_date'] ); ?>
                        <?php else: ?>
                            → Ongoing
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the shortcode
StudentDashboard::init();
?>