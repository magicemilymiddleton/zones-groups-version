<?php
/**
 * Group Invitation Model
 *
 * @package LifterLMS_REST/Models
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.10
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Invitation class.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Add `get_group()` and `is_valid()` methods.
 */
class LLMS_Group_Invitation extends LLMS_Abstract_Database_Store {

	/**
	 * Date Created Field not implemented.
	 *
	 * @var null
	 */
	protected $date_created = null;

	/**
	 * Date Updated Field not implemented.
	 *
	 * @var null
	 */
	protected $date_updated = null;

	/**
	 * Array of table column name => format
	 *
	 * @var  array
	 */
	protected $columns = array(
		'group_id'   => '%d',
		'invite_key' => '%s',
		'email'      => '%s',
		'role'       => '%s',
	);

	/**
	 * Database Table Name
	 *
	 * @var  string
	 */
	protected $table = 'group_invitations';

	/**
	 * The record type
	 *
	 * Used for filters/actions.
	 *
	 * @var  string
	 */
	protected $type = 'group_invitation';

	/**
	 * Constructor
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param int  $id      Invitation ID.
	 * @param bool $hydrate If true, hydrates the object on instantiation if an ID is supplied.
	 */
	public function __construct( $id = null, $hydrate = true ) {

		$this->id = $id;
		if ( $this->id && $hydrate ) {
			$this->hydrate();
		}
	}

	/**
	 * Retrieve the invitation acceptance link/url
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @return string
	 */
	public function get_accept_link() {

		$link = '';
		if ( $this->exists() ) {

			$link = llms_get_page_url(
				'myaccount',
				array(
					'invite' => $this->get( 'invite_key' ),
				)
			);

		}

		/**
		 * Customize the group invitation acceptance link
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param string $link Acceptance link url.
		 * @param LLMS_Group_Inviation $invitation Group invitation object.
		 */
		return apply_filters( 'llms_group_invitation_accept_link', $link, $this );
	}


	/**
	 * Retrieve the LLMS_Group for the invitation.
	 *
	 * @since 1.0.0-beta.4
	 *
	 * @return null|LLMS_Group
	 */
	public function get_group() {
		return get_llms_group( $this->get( 'group_id' ) );
	}

	/**
	 * Determines if the invitation is valid prior to usage by a user.
	 *
	 * Ensures that the invitation's group still exists.
	 *
	 * For open invitation links, additionally ensures that the group has remaining available seats.
	 *
	 * For other links, validates that the submitted email address matches the email address stored
	 * on the invitation record.
	 *
	 * @since 1.0.0-beta.4
	 * @since 1.0.0-beta.10 Ignore email address case.
	 *
	 * @param  string $user_email User email to validate email invites.
	 * @return WP_Error|true When the invitation is invalid, returns an error object reporting why the invitation is invalid, otherwise returns `true`.
	 */
	public function is_valid( $user_email = '' ) {

		$valid = true;
		$group = $this->get_group();

		if ( $group ) {

			$email = $this->get( 'email' );
			$int   = llms_groups()->get_integration();

			if ( ! $email && ! $group->has_open_seats() ) {

				$msg = sprintf(
					// Translators: %1$s = Group name (singular); %2$s Leader name (singular).
					__( 'The invitation is no longer available. Please contact a %1$s %2$s for more information.', 'lifterlms-groups' ),
					$int->get_option( 'post_name_singular' ),
					$int->get_option( 'leader_name_singular' )
				);

				// Open invite with no available seats.
				$valid = new WP_Error( 'no-open-seats', $msg );

			} elseif ( $email && strtolower( $email ) !== strtolower( $user_email ) ) {

				// Invalid email address.

				$msg = sprintf(
					// Translators: %1$s = Group name (singular); %2$s Leader name (singular).
					__( 'The invitation was not valid for your email address. Please contact a %1$s %2$s for more information.', 'lifterlms-groups' ),
					$int->get_option( 'post_name_singular' ),
					$int->get_option( 'leader_name_singular' )
				);

				$valid = new WP_Error( 'invalid-email', $msg );

			}
		} else {

			// The group doesn't exist anymore.
			$valid = new WP_Error( 'invalid-group', __( 'Invalid group.', 'lifterlms-groups' ) );

		}

		/**
		 * Filter the validity of a given invitation prior to invitation usage by an invited user.
		 *
		 * @since 1.0.0-beta.4
		 *
		 * @param boolean|WP_Error      $valid      When the invitation is invalid, this is an error object reporting why the invitation is invalid, otherwise `true`.
		 * @param string                $user_email (Optional) email address of the user attempting to accept the invitation.
		 * @param LLMS_Group_Invitation $invitation Invitation object.
		 */
		return apply_filters( 'llms_group_invitation_is_valid', $valid, $user_email, $this );
	}
}
