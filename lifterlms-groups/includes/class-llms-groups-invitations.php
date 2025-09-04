<?php
/**
 * Group invitations CRUD
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Invitations class
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.5 Add `query()` method as a wrapper around `LLMS_Groups_Invitations_Query`.
 */
class LLMS_Groups_Invitations {

	/**
	 * Singleton instance of the class.
	 *
	 * @var obj
	 */
	private static $instance = null;

	/**
	 * Singleton Instance
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return LLMS_Groups_Invitations
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create a new group invitation
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  array $data {
	 *     Associative array of invitation data.
	 *
	 *     @type int    $group_id (Required) WP_Post ID of the group.
	 *     @type string $email    Email address of the user. When blank creates a new "open" invitation link.
	 *     @type string $role     Member role within the group. Default: `member`.
	 * }
	 * @return WP_Error|LLMS_Group_Invitation
	 */
	public function create( $data = array() ) {

		if ( empty( $data['group_id'] ) ) {
			return new WP_Error( 'llms_group_invitation_create_missing_required', __( 'Missing required parameter: "group_id".', 'lifterlms-groups' ) );
		}

		$obj = new LLMS_Group_Invitation();

		$data = wp_parse_args(
			$data,
			array(
				'invite_key' => bin2hex( random_bytes( 16 ) ),
				'role'       => 'member',
			)
		);

		if ( ! empty( $data['email'] ) ) {
			$data['email'] = sanitize_email( $data['email'] );
		}

		if ( ! $obj->setup( $data )->save() ) {
			return new WP_Error( 'llms_group_invitation_create_db', __( 'An error occurred while trying to save the invitation.', 'lifterlms-groups' ) );
		}

		return $obj;
	}

	/**
	 * Delete (revoke) an invitation.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  int $id Invitation ID.
	 * @return boolean|null     `true` on success, `false` on failure, `null` if the record doesn't exist.
	 */
	public function delete( $id ) {

		$obj = $this->get( $id );
		return $obj ? $obj->delete() : null;
	}

	/**
	 * Retrieve an Invitation record
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  int  $id      Invitation ID.
	 * @param  bool $hydrate Whether or not to hydrate the object.
	 * @return LLMS_Group_Invitation|false
	 */
	public function get( $id, $hydrate = true ) {

		$obj = new LLMS_Group_Invitation( $id, $hydrate );
		return $obj->exists() ? $obj : false;
	}

	/**
	 * Retrieve an invitation object by invite key.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  string $key     Invite key.
	 * @param  bool   $hydrate Whether or not to hydrate the record.
	 * @return LLMS_Group_Invitation|false
	 */
	public function get_by_invite_key( $key, $hydrate = true ) {

		$found = null;
		$id    = wp_cache_get( $key, 'llms_group_invite_keys', false, $found );
		if ( ! $id && ! $found ) {

			global $wpdb;
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}lifterlms_group_invitations WHERE invite_key = %s LIMIT 1", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( $key, $id, 'llms_group_invite_keys' );

		}

		return $id ? $this->get( $id, $hydrate ) : false;
	}

	/**
	 * Retrieve the "open invitation link" for a given group
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  int     $group_id WP_Post ID of the group.
	 * @param  boolean $hydrate  Whether or not to hydrate the record.
	 * @return LLMS_Group_Invitation|false
	 */
	public function get_open_link( $group_id, $hydrate = true ) {

		$found = null;
		$id    = wp_cache_get( $group_id, 'llms_group_open_links', false, $found );
		if ( ! $id && ! $found ) {

			global $wpdb;
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}lifterlms_group_invitations WHERE group_id = %d AND email = '' LIMIT 1", $group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( $group_id, $id, 'llms_group_open_links' );

		}

		return $id ? $this->get( $id, $hydrate ) : false;
	}

	/**
	 * Perform a group invitations query
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @see LLMS_Groups_Invitations_Query
	 *
	 * @param array $args Array of query arguments.
	 * @return LLMS_Groups_Invitations_Query
	 */
	public function query( $args = array() ) {
		return new LLMS_Groups_Invitations_Query( $args );
	}
}
