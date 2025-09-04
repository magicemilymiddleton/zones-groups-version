<?php
/**
 * LLMS_Groups_Enrollment class file
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.21
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modify LifterLMS core student enrollment functions to allow users to be enrolled into groups.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.6 Add handling for setting a user as the group "primary" admin.
 */
class LLMS_Groups_Enrollment {

	/**
	 * Static constructor, adds actions.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public static function init() {

		add_filter( 'llms_user_enrollment_allowed_post_types', array( __CLASS__, 'mod_enrollment_post_types' ) );
		add_filter( 'llms_user_enrollment_status_allowed_post_types', array( __CLASS__, 'mod_enrollment_post_types' ) );

		add_action( 'llms_user_group_enrollment_created', array( __CLASS__, 'do_post_enrollment' ), 10, 2 );
		add_action( 'llms_user_group_enrollment_updated', array( __CLASS__, 'do_post_enrollment' ), 10, 2 );

		add_action( 'llms_user_removed_from_group', array( __CLASS__, 'do_post_unenrollment' ), 10, 4 );

		add_action( 'llms_user_enrollment_deleted', array( __CLASS__, 'do_post_enrollment_deletion' ), 10, 2 );
	}

	/**
	 * Add a user to a group with the specified roll
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.21 Clear the group members cache data after successful enrollment.
	 *
	 * @param int    $user_id  WP_User ID.
	 * @param int    $group_id WP_Post ID of the group.
	 * @param string $trigger  Enrollment trigger string.
	 * @param string $role     Group member pseudo roll ("primary_admin", "admin", "leader", or "member").
	 * @return boolean
	 */
	public static function add( $user_id, $group_id, $trigger = 'unspecified', $role = 'member' ) {

		$status = llms_enroll_student( $user_id, $group_id, $trigger );

		// If the enrollment was successful.
		if ( $status ) {
			self::clear_group_members_cache( $group_id );

			// Role update required.
			if ( self::get_role( $user_id, $group_id ) !== $role ) {
				self::update_role( $user_id, $group_id, $role );
			}
		}

		return $status;
	}

	/**
	 * Clears cached group members data.
	 *
	 * This method is called automatically during successful group enrollments, unenrollments, and deletions.
	 * The cache is not automatically regenerated or adjusted. It's just cleared and then the next time
	 * group member data is requested for that group the data will be queried from the database and saved
	 * to the cache for the next time.
	 *
	 * @since 1.0.0-beta.21
	 *
	 * @param int $group_id The WP_Post ID of the group.
	 * @return null|bool Returns `null` when an invalid `$group_id` is supplied, `true` on success, and `false` on failure.
	 */
	protected static function clear_group_members_cache( $group_id ) {

		$group = llms_get_post( $group_id );
		if ( ! is_a( $group, 'LLMS_Group' ) ) {
			return null;
		}

		return $group->clear_members_cache();
	}

	/**
	 * Delete a user from a group.
	 *
	 * This permanently deletes the user's association with the group.
	 *
	 * To remove a user from a group (without deleting the user's group association records) use the
	 * `remove()` method instead.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.21 Clear the group members cache data after successful deletion.
	 *
	 * @param int    $user_id  WP_User ID.
	 * @param int    $group_id WP_Post ID of the group.
	 * @param string $trigger  Enrollment trigger string.
	 * @return boolean
	 */
	public static function delete( $user_id, $group_id, $trigger = 'any' ) {

		$status = llms_delete_student_enrollment( $user_id, $group_id, $trigger );

		if ( $status ) {
			llms_delete_user_postmeta( $user_id, $group_id, '_group_role' );
			self::clear_group_members_cache( $group_id );
		}

		return $status;
	}

	/**
	 * Enroll a user into the group's related post upon group enrollment.
	 *
	 * Called via `llms_user_group_enrollment_created` and `llms_user_group_enrollment_updated` hooks called during group enrollment.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param int $student_id WP_User ID of the student.
	 * @param int $group_id   WP_Post ID of the group.
	 * @return bool|WP_Error `true` on enrollment success, `false` on enrollment failure, `WP_Error` if the group doesn't have a related post.
	 */
	public static function do_post_enrollment( $student_id, $group_id ) {

		$group = llms_get_post( $group_id );
		if ( $group && $group->get( 'post_id' ) ) {
			return llms_enroll_student( $student_id, $group->get( 'post_id' ), sprintf( 'group_%d', $group_id ) );
		}

		return new WP_Error( 'llms_group_post_enrollment', __( 'The group does not have a related course or membership.', 'lifterlms-groups' ) );
	}

	/**
	 * Delete a student's enrollment from a course or membership when their group enrollment is deleted.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param int $student_id WP_User ID of the student.
	 * @param int $post_id    WP_Post ID of the post.
	 * @return null|bool|WP_Error `null` when `$post_id` isn't an `llms_group` post, `true` on enrollment success, `false` on enrollment failure, `WP_Error` if the group doesn't have a related post.
	 */
	public static function do_post_enrollment_deletion( $student_id, $post_id ) {

		if ( 'llms_group' !== get_post_type( $post_id ) ) {
			return null;
		}

		$group = llms_get_post( $post_id );
		if ( $group && $group->get( 'post_id' ) ) {
			return llms_delete_student_enrollment( $student_id, $group->get( 'post_id' ), sprintf( 'group_%d', $post_id ) );
		}

		return new WP_Error( 'llms_group_post_enrollment_deletion', __( 'The group does not have a related course or membership.', 'lifterlms-groups' ) );
	}

	/**
	 * Remove a user from the group's related post upon group unenrollment
	 *
	 * Called via `llms_user_removed_from_group` hook during group unenrollment.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param int    $student_id WP_User ID of the student.
	 * @param int    $group_id   WP_Post ID of the group.
	 * @param string $trigger    Enrollment trigger to use during unenrollment checks.
	 * @param string $new_status New enrollment status to use.
	 * @return  bool|WP_Error `true` on unenrollment success, `false` on unenrollment failure, `WP_Error` if the group doesn't have a related post.
	 */
	public static function do_post_unenrollment( $student_id, $group_id, $trigger, $new_status ) {

		$group = llms_get_post( $group_id );
		if ( $group && $group->get( 'post_id' ) ) {
			return llms_unenroll_student( $student_id, $group->get( 'post_id' ), $new_status, $trigger );
		}

		return new WP_Error( 'llms_group_post_unenrollment', __( 'The group does not have a related course or membership.', 'lifterlms-groups' ) );
	}

	/**
	 * Remove a user from a group.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.21 Clear the group members cache data after successful removal.
	 *
	 * @param int    $user_id    WP_User ID.
	 * @param int    $group_id   WP_Post ID of the group.
	 * @param string $trigger    Enrollment trigger string.
	 * @param string $new_status New status after the removal.
	 * @return boolean
	 */
	public static function remove( $user_id, $group_id, $trigger = 'unspecified', $new_status = 'cancelled' ) {

		$status = llms_unenroll_student( $user_id, $group_id, $new_status, $trigger );
		if ( $status ) {
			self::clear_group_members_cache( $group_id );
		}
		return $status;
	}

	/**
	 * Get a user's role within a group.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param int $user_id  WP_User ID.
	 * @param int $group_id WP_Post ID of the group.
	 * @return bool
	 */
	public static function get_role( $user_id, $group_id ) {
		return llms_get_user_postmeta( $user_id, $group_id, '_group_role' );
	}

	/**
	 * Update a user's role within a group.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.6 Add handling for setting a user as the group "primary" admin.
	 *
	 * @param int    $user_id  WP_User ID.
	 * @param int    $group_id WP_Post ID of the group.
	 * @param string $role     Group member pseudo roll ("primary_admin", "admin", "leader", or "member").
	 * @return bool
	 */
	public static function update_role( $user_id, $group_id, $role ) {

		/**
		 * Additional handling when setting the primary group admin.
		 *
		 * + The primary's role is "admin".
		 * + The WP_Post's "author" is set as the user ID of the primary admin.
		 */
		if ( 'primary_admin' === $role ) {

			$role  = 'admin';
			$group = llms_get_post( $group_id );

			if ( $group ) {
				$group->set( 'author', $user_id );
			}
		}

		return llms_update_user_postmeta( $user_id, $group_id, '_group_role', $role );
	}

	/**
	 * Allow users to be enrolled into the group post type.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @see Hook: llms_groups_add_user_enrollment_post_type
	 *
	 * @param string[] $post_types Array of post type names.
	 * @return string[]
	 */
	public static function mod_enrollment_post_types( $post_types ) {

		$post_types[] = LLMS_Groups_Post_Type::POST_TYPE;
		return $post_types;
	}
}

return LLMS_Groups_Enrollment::init();
