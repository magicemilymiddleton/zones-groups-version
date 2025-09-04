<?php
/**
 * LLMS_Groups_Capabilities class.
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.12
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage user capabilities for the group post type.
 *
 * @since 1.0.0-beta.1
 */
class LLMS_Groups_Capabilities {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'llms_get_administrator_post_type_caps', array( $this, 'add_post_type_caps' ) );
		add_filter( 'llms_get_lms_manager_post_type_caps', array( $this, 'add_post_type_caps' ) );

		add_filter( 'llms_get_administrator_core_caps', array( $this, 'add_core_caps' ) );
		add_filter( 'llms_get_lms_manager_core_caps', array( $this, 'add_core_caps' ) );

		add_filter( 'user_has_cap', array( $this, 'user_has_cap' ), 10, 3 );
	}

	/**
	 * Add group core capabilities.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array $caps Array of capabilities.
	 * @return array
	 */
	public function add_core_caps( $caps ) {

		$role = current_filter();

		if ( $role ) {
			$role = str_replace( array( 'llms_get_', '_core_caps' ), '', $role );
			$core = array_keys( $this->get_pseudo_caps() );
			$caps = array_merge( $caps, array_fill_keys( $core, true ) );
		}

		return $caps;
	}

	/**
	 * Add group post capabilities.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array $caps Array of capabilities.
	 * @return array
	 */
	public function add_post_type_caps( $caps ) {

		$role = current_filter();

		if ( $role ) {
			$role               = str_replace( array( 'llms_get_', '_post_type_caps' ), '', $role );
			$post_caps          = array_values( LLMS_Post_Types::get_post_type_caps( 'group' ) );
			$caps['llms_group'] = array_fill_keys( $post_caps, true );
		}

		return $caps;
	}

	/**
	 * Retrieve an array of "pseudo" capabilities for group members.
	 *
	 * These capabilities are checked using the WP core capabilities api (eg: current_user_can()) but
	 * the caps themselves are NOT added to user roles like regular wp caps.
	 *
	 * We cannot use the standard caps api because the user will have capabilities specific to a group
	 * and may have access to multiple groups.
	 *
	 * Additionally this enables us to apply these caps to any user role instead of creating a "group_leader"
	 * role and switching users to that role.
	 *
	 * The role is stored on the lifterlms_user_postmeta table for the group and that information is used
	 * to determine if the user has the role for the required capability.
	 *
	 * @todo add a "delete_group" capability.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return array
	 */
	protected function get_pseudo_caps() {

		return array(
			'manage_group_members'     => array( 'admin', 'leader' ),
			'manage_group_information' => array( 'admin', 'leader' ),
			'view_group_reporting'     => array( 'admin', 'leader' ),
			'manage_group_managers'    => array( 'admin' ),
			'manage_group_seats'       => array( 'admin' ),
		);
	}

	/**
	 * Handle groups-related user capabilities
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.12 Add `view_grades` cap handler & move pseudo-cap handling to `handle_pseudo_caps()` method.
	 *
	 * @param array $allcaps All the capabilities of the user.
	 * @param array $cap     [0] Required capability.
	 * @param array $args    [0] Requested capability.
	 *                       [1] Current user ID.
	 *                       [2] Associated object ID.
	 * @return array
	 */
	public function user_has_cap( $allcaps, $cap, $args ) {

		$cap = array_pop( $cap );

		/**
		 * Return early if the user already has the requested capability.
		 *
		 * Admins & LMS Managers are explicitly granted group pseudo caps
		 * and the LLMS Core `view_grades` cap. We only need to proceed
		 * for users who *do not* explicitly have the cap.
		 */
		if ( ! empty( $allcaps[ $cap ] ) ) {
			return $allcaps;
		}

		if ( 'view_grades' === $cap ) {
			$allcaps = $this->handle_view_grades_cap( $allcaps, $cap, $args );
		} else {
			$allcaps = $this->handle_pseudo_caps( $allcaps, $cap, $args );
		}

		return $allcaps;
	}

	/**
	 * Determine if users have various "fake" capabilities added by the LifterLMS Groups plugin.
	 *
	 * @since 1.0.0-beta.12
	 *
	 * @param array $allcaps All the capabilities of the user.
	 * @param array $cap     Required capability.
	 * @param array $args    [0] Requested capability.
	 *                       [1] Current user ID.
	 *                       [2] Associated object ID.
	 * @return array
	 */
	private function handle_pseudo_caps( $allcaps, $cap, $args ) {

		// We have a user and a group post object.
		if ( ! empty( $args[2] ) && LLMS_Groups_Post_Type::POST_TYPE === get_post_type( $args[2] ) ) {

			$pseudo_caps = $this->get_pseudo_caps();

			// We have a user and the requested cap is one of our pseudo caps.
			if ( ! empty( $args[1] ) && in_array( $cap, array_keys( $pseudo_caps ), true ) ) {
				$group_role = LLMS_Groups_Enrollment::get_role( $args[1], $args[2] );

				// If the group has an order associated with it, don't allow the user to manage seats unless they can manage the site.
				if ( 'manage_group_seats' === $cap &&
					is_numeric( get_post_meta( $args[2], '_llms_order_id', true ) ) &&
					! current_user_can( 'manage_lifterlms' )
				) {
					return $allcaps;
				}

				$allcaps[ $cap ] = in_array( $group_role, $pseudo_caps[ $cap ], true );
			}
		}

		return $allcaps;
	}

	/**
	 * Conditionally adds the `view_grades` capability to group members who can `view_group_reporting`.
	 *
	 * This enables group leaders/admins to view graded results for quizzes/assignments through the
	 * group's reporting screen.
	 *
	 * @since 1.0.0-beta.12
	 *
	 * @param array $allcaps All the capabilities of the user.
	 * @param array $cap     Required capability.
	 * @param array $args    [0] Requested capability.
	 *                       [1] WP_User ID of the current user.
	 *                       [2] WP_User ID of the user who's grade is to be viewed.
	 *                       [3] WP_Post ID of the post object (quiz/assignment) for a grade to be viewed for.
	 * @return array
	 */
	private function handle_view_grades_cap( $allcaps, $cap, $args ) {

		// Logged out user or missing required args.
		if ( empty( $args[1] ) || empty( $args[2] ) || empty( $args[3] ) ) {
			return $allcaps;
		}

		list( $requested_cap, $current_user_id, $requested_user_id, $post_id ) = $args;

		// Load the quiz/assignment/etc.
		$llms_post = llms_get_post( $post_id );

		// Unexpected post object.
		if ( ! $llms_post || ! method_exists( $llms_post, 'get_course' ) ) {
			return $allcaps;
		}

		// Make sure the quiz isn't an orphan.
		$course = $llms_post->get_course();
		if ( ! $course ) {
			return $allcaps;
		}

		if ( $this->user_can_view_grades_via_group( $current_user_id, $requested_user_id, $course->get( 'id' ) ) ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	/**
	 * Determine if a user can view another users grades for a given course
	 *
	 * Requires the current user to have the `view_group_reporting` pseudo cap for a group
	 * that provides access to the given course and which the requested user is a member of.
	 *
	 * @since 1.0.0-beta.12
	 *
	 * @param int $current_user_id   WP_User ID of the current user who is wishing to view another user's grade.
	 * @param int $requested_user_id WP_User ID of the user's whose grades are to be viewed.
	 * @param int $course_id         WP_Post ID of the course for which grades are to be viewed.
	 * @return boolean
	 */
	private function user_can_view_grades_via_group( $current_user_id, $requested_user_id, $course_id ) {

		// Load the requested student.
		$student = llms_get_student( $requested_user_id );
		$groups  = $student ? $student->get_enrollments( 'llms_group', array( 'limit' => 500 ) ) : array();

		// Student doesn't belong to a group.
		if ( empty( $groups['results'] ) ) {
			return false;
		}

		// Loop through the student's groups.
		foreach ( $groups['results'] as $group_id ) {

			// Group exists, the user can view group reporting, and the group provides access to the quiz/assignment's course.
			if ( user_can( $current_user_id, 'view_group_reporting', $group_id ) && llms_group_has_course( $group_id, $course_id ) ) {
				// Return as soon as we find a match.
				return true;
			}
		}

		return false;
	}
}

return new LLMS_Groups_Capabilities();
