<?php

namespace LLMSGAA\Feature\Reports;

// Exit if accessed directly to protect from direct URL access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


use LifterLMS;

class Controller {

    public static function init_hooks() {
    new self();
}

    public function __construct() {
        add_action( 'init', [ $this, 'add_route' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render_report' ] );
    }

    public function add_route() {
        add_rewrite_rule(
            '^group-report/([0-9]+)/?$',
            'index.php?llms_group_report_user=$matches[1]',
            'top'
        );
        add_rewrite_tag( '%llms_group_report_user%', '([0-9]+)' );
    }

    public function maybe_render_report() {
        $user_id = get_query_var( 'llms_group_report_user' );
        if ( ! $user_id ) { return; }

        $current_user_id = get_current_user_id();
        if ( ! $this->current_user_can_view( $current_user_id, $user_id ) ) {
            status_header(403);
            nocache_headers();
            wp_die( 'Access Denied', 'Access Denied', [ 'response' => 403 ] );
        }

        // Gather data
        $courses = $this->get_enrolled_courses( $user_id );
        $course_id  = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : ( $courses ? $courses[0]->ID : 0 );

        $report_data = [];
        foreach ( $courses as $course ) {
            $report_data[ $course->ID ] = $this->build_course_report( $user_id, $course->ID );
        }

        // Load view
        include plugin_dir_path( __DIR__ ) . '/views/feature/Reports/report-page.php';
        exit;
    }

    private function current_user_can_view( $current_user_id, $student_id ) {
        if ( user_can( $current_user_id, 'manage_options' ) ) {
            return true;
        }

        // Check if current_user_id is a leader of any of student's groups.
        $memberships = get_user_meta( $student_id, '_llms_group_membership_ids', true );
        if ( empty( $memberships ) || ! is_array( $memberships ) ) {
            return false;
        }

        foreach ( $memberships as $group_id ) {
            $leader_id = get_post_meta( $group_id, '_llms_group_leader_id', true );
            if ( absint( $leader_id ) === $current_user_id ) {
                return true;
            }
        }

        return false;
    }

    private function get_enrolled_courses( $user_id ) {
        $all = llms_get_user_enrolled_courses( $user_id );
        if ( ! $all ) {
            return [];
        }
        return array_filter( $all, function( $course ) {
            return $course instanceof \WP_Post && 'publish' === $course->post_status;
        } );
    }

    private function build_course_report( $user_id, $course_id ) {
        $lessons = llms_get_course_lessons( $course_id );
        $total    = count( $lessons );
        $complete = 0;
        $rows     = [];

        foreach ( $lessons as $lesson ) {
            $lesson_id   = $lesson->ID;
            $is_complete = llms_is_complete( $user_id, $lesson_id );
            if ( $is_complete ) {
                $complete++;
                $completed_ts = $this->get_completion_date( $user_id, $lesson_id );
            } else {
                $completed_ts = '';
            }

            $grade_meta_key = "_lesson_grade_{$lesson_id}";
            $grade          = get_user_meta( $user_id, $grade_meta_key, true );
            $quiz_title     = $this->lesson_has_quiz( $lesson_id ) ? get_the_title( $lesson_id ) : '';

            $rows[] = [
                'title'          => $lesson->post_title,
                'completed_date' => $completed_ts,
                'quiz_title'     => $quiz_title,
                'grade'          => $grade,
            ];
        }

        $pct_progress = $total > 0 ? round( ( $complete / $total ) * 100 ) : 0;

        // Last activity = latest _is_complete timestamp
        global $wpdb;
        $last = $wpdb->get_var( $wpdb->prepare("
            SELECT MAX(updated_date) FROM {$wpdb->usermeta}
            WHERE user_id=%d AND meta_key='_is_complete'
        ", $user_id ) );
        $last_activity = $last ? date_i18n( get_option('date_format'), strtotime( $last ) ) : '';

        return compact( 'total', 'complete', 'pct_progress', 'last_activity', 'rows' );
    }

    private function get_completion_date( $user_id, $lesson_id ) {
        $logs = LifterLMS\Logger\Logger::instance()->get_logs(
            [ 'user' => $user_id, 'object_id' => $lesson_id, 'context' => '_is_complete' ]
        );
        if ( $logs ) {
            $ts = end( $logs )->time;
            return date_i18n( get_option('date_format'), $ts );
        }
        return '';
    }

    private function lesson_has_quiz( $lesson_id ) {
        return (bool) get_post_meta( $lesson_id, '_llms_lesson_quiz', true );
    }
}

add_action( 'plugins_loaded', function() {
    new \LLMSGAA\Feature\Reports\Controller();
});