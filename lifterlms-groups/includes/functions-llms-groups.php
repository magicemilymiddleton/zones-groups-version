<?php
/**
 * Groups core functions
 *
 * @package LifterLM_Groups/Functions
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.12
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieve the LLMS_Group object for a given post
 *
 * @since  1.0.0-beta.1
 *
 * @param  int|WP_Post|null $post WP_Post, WP_Post ID, or `null` to use the current `$post` global.
 * @return null|LLMS_Group
 */
function get_llms_group( $post = null ) {

	$post = llms_get_post( $post );
	return ! is_a( $post, 'LLMS_Group' ) ? null : $post;
}

/**
 * Determine if a given post is a LifterLMS Group post.
 *
 * @since  1.0.0-beta.1
 *
 * @param  WP_Post|int|null $post (Optional) Post ID or post object. Defaults to global $post.
 * @return boolean
 */
function is_llms_group( $post = null ) {

	$post = get_post( $post );
	return $post ? LLMS_Groups_Post_Type::POST_TYPE === $post->post_type : false;
}

/**
 * Create a new Group.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.5 Set the groups visibility to match the global setting when creating a new group.
 *
 * @param array $args Associative array of group creation arguments.
 * @return LLMS_Group
 */
function llms_create_group( $args = array() ) {

	$int = llms_groups()->get_integration();

	// Setup arguments.
	$args = wp_parse_args(
		$args,
		array(
			'post_status' => 'publish',
			// Translators: %s = Singular user-defined group name.
			'post_title'  => sprintf( __( 'New %s', 'lifterlms-groups' ), $int->get_option( 'post_name_singular' ) ),
			'meta_input'  => array(
				'_llms_visibility' => $int->get_option( 'visibility' ),
			),
		)
	);

	// Create the group.
	$group = new LLMS_Group( 'new', $args );

	// Add the post_author as the group's primary admin.
	LLMS_Groups_Enrollment::add( $group->get( 'author' ), $group->get( 'id' ), 'primary_admin', 'admin' );

	return $group;
}

/**
 * Determine if a user has the required permissions to manage a group member.
 *
 * @since  1.0.0-beta.1
 *
 * @param  int $user   WP_User ID of the acting user.
 * @param  int $member WP_User ID of the group member being managed.
 * @param  int $group  WP_Post ID of the group.
 * @return boolean
 */
function llms_groups_can_user_manage_member( $user, $member, $group ) {

	$can_manage = false;

	// Users cannot manage themselves.
	if ( absint( $user ) !== absint( $member ) ) {

		// Allow admins and lms_managers to manage everyone.
		if ( user_can( $user, 'manage_lifterlms' ) ) {
			$can_manage = true;
		} else {

			$member_role = LLMS_Groups_Enrollment::get_role( $member, $group );

			if ( 'member' === $member_role ) {
				$can_manage = user_can( $user, 'manage_group_members', $group );
			} elseif ( 'leader' === $member_role || ( 'admin' === $member_role && ! llms_group_is_user_primary_admin( $member, $group ) ) ) {
				$can_manage = user_can( $user, 'manage_group_managers', $group );
			}
		}
	}

	/**
	 * Filter whether or not a user has the required permissions to manage a group member.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param  int $can_manage Whether or not the user can manage the member.
	 * @param  int $user       WP_User ID of the acting user.
	 * @param  int $member     WP_User ID of the group member being managed.
	 * @param  int $group      WP_Post ID of the group.
	 */
	return apply_filters( 'llms_groups_can_user_manage_member', $can_manage, $user, $member, $group );
}

/**
 * Determine if a course is accessible through a group
 *
 * @since 1.0.0-beta.12
 *
 * @param int $group_id  WP_Post ID of a group.
 * @param int $course_id WP_Post ID of a course.
 * @return boolean
 */
function llms_group_has_course( $group_id, $course_id ) {

	$group = get_llms_group( $group_id );
	if ( ! $group ) {
		return false;
	}

	$assoc_id   = $group->get( 'post_id' );
	$assoc_post = $assoc_id ? llms_get_post( $assoc_id ) : false;
	if ( ! $assoc_post ) {
		return false;
	}

	$type = $assoc_post->get( 'type' );
	if ( 'course' === $type && $course_id === $assoc_id ) {
		return true;
	} elseif ( 'llms_membership' === $type ) {
		return in_array( $course_id, $assoc_post->get_associated_posts( 'course' ), true );
	}

	return false;
}

/**
 * Determine if the given user is the primary admin of a group.
 *
 * @since  1.0.0-beta.1
 *
 * @param  int $user  WP_User ID.
 * @param  int $group WP_Post ID of the group.
 * @return bool
 */
function llms_group_is_user_primary_admin( $user, $group ) {

	$group = get_llms_group( $group );
	return ( $group && absint( $user ) === absint( $group->get( 'author' ) ) );
}

/**
 * Determine what actions the current user can preform on a given group member.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.6 Changed action key for promoting to "primary admin" from "primary" to "primary_admin".
 *
 * @param int $member WP_User ID of the member.
 * @param int $group  WP_Post ID of the group.
 * @return array      Associative array of actions where the key is the new role for
 *                    the member and the value is the human-readable description of the action.
 */
function llms_groups_get_actions_for_member( $member, $group ) {

	$actions = array();

	$user = get_current_user_id();
	// If we have a logged in user and that user can manage the member and the member is not the primary admin.
	if ( $user && llms_groups_can_user_manage_member( $user, $member, $group ) && ! llms_group_is_user_primary_admin( $member, $group ) ) {

		$role = LLMS_Groups_Enrollment::get_role( $member, $group );

		// Allow site admins/lms managers & the current primary admin to make other admins the primary admin.
		if ( 'admin' === $role && ( current_user_can( 'manage_lifterlms' ) || llms_group_is_user_primary_admin( $user, $group ) ) ) {
			// Translators: %s = Group admin role name (singular).
			$actions['primary_admin'] = sprintf( __( 'Make primary %s', 'lifterlms-groups' ), llms_groups_get_role_name( 'admin' ) );
		}

		// If user can manage other users, add actions for switching to all roles except the member's current role.
		if ( user_can( $user, 'manage_group_managers', $group ) ) {

			$roles = array_reverse( llms_groups_get_roles() );

			// Remove the user's current role.
			unset( $roles[ $role ] );

			foreach ( $roles as $key => $name ) {

				// Translators: %s = Member's new role name.
				$actions[ $key ] = sprintf( __( 'Make %s', 'lifterlms-groups' ), $name );

			}
		}

		// Allow user to remove any member from the team.

		// Translators: %s = Group post type name (singular).
		$actions['remove'] = sprintf( __( 'Remove from %s', 'lifterlms-groups' ), llms_groups()->get_integration()->get_option( 'post_name_singular' ) );

	}

	/**
	 * Modify the actions that can be performed on a given group member.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array $actions Associative array of actions where the key is the new role for
	 *                       the member and the value is the human-readable description of the action.
	 * @param int   $member  WP_User ID of the member.
	 * @param int   $group   WP_Post ID of the group.
	 */
	return apply_filters( 'llms_groups_get_actions_for_member', $actions, $member, $group );
}

/**
 * Perform a query for group members.
 *
 * @since  1.0.0-beta.1
 *
 * @see LLMS_Groups_Members_Query
 *
 * @param  int   $group_id WP_Post ID of the group.
 * @param  array $args     Additional query arguments.
 * @return LLMS_Groups_Member_Query
 */
function llms_group_get_members( $group_id = null, $args = array() ) {

	return new LLMS_Groups_Member_Query( $group_id, $args );
}

/**
 * Determine if a user is a member of a group.
 *
 * @since 1.1.0
 *
 * @param $group_id
 * @param $user_id
 *
 * @return bool
 */
function llms_group_has_member( $group_id, $user_id ) {
	foreach ( llms_group_get_members( $group_id, array( 'id' => $user_id ) )->get_results() as $member ) {
		if ( intval( $member->id ) === intval( $user_id ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Retrieve the name of a give role in the requested tense
 *
 * @since  1.0.0-beta.1
 *
 * @param  strng  $role   Role id.
 * @param  string $tense Tense to return translated string in. Accepts "singular" (the default) or "plural".
 * @return string
 */
function llms_groups_get_role_name( $role, $tense = 'singular' ) {

	$roles = llms_groups_get_roles( $tense );
	return isset( $roles[ $role ] ) ? $roles[ $role ] : $role;
}

/**
 * Retrieve group role information and language.
 *
 * @since  1.0.0-beta.1
 *
 * @param  string $tense Tense to return translated string in. Accepts "singular" (the default) or "plural".
 * @return array
 */
function llms_groups_get_roles( $tense = 'singular' ) {

	$count = 'singular' === $tense ? 1 : 2;
	$int   = llms_groups()->get_integration();

	/**
	 * Filter group roles.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array  $roles Array of roles.
	 * @param string $tense Tense to return translated string in. Accepts "singular" (the default) or "plural".
	 */
	return apply_filters(
		'llms_groups_get_roles',
		array(
			'member' => _n( 'Member', 'Members', $count, 'lifterlms-groups' ),
			'leader' => $int->get_option( sprintf( 'leader_name_%s', $tense ) ),
			// Translators: %s = Group post type name (singular).
			'admin'  => sprintf( _n( '%s Administrator', '%s Administrators', $count, 'lifterlms-groups' ), $int->get_option( 'post_name_singular' ) ),
		),
		$tense
	);
}

/**
 * Check whether or not an invitation can be deleted.
 *
 * @since 1.0.0
 *
 * @param LLMS_Group_Invitation|int $invitation The invitation to be deleted, or the WP_Post ID of the invitation.
 * @param ?int                      $group_id   The WP_Post ID of the group.
 *                                              Relevant only if the it's not possible to be retrieved by the invitation.
 * @return bool
 */
function llms_groups_invitation_can_be_deleted( $invitation, $group_id = null ) {

	$invitation = is_numeric( $invitation ) ?
		llms_groups()->invitations()->get( $invitation ) : $invitation;
	$group_id   = $invitation ? $invitation->get( 'group_id' ) : $group_id;

	// Dangling invitation, can be removed.
	if ( ! $group_id ) {
		return true;
	}

	$cap = ! $invitation || 'member' === $invitation->get( 'role' ) ? 'manage_group_members' : 'manage_group_managers';
	return current_user_can( $cap, $group_id );
}
