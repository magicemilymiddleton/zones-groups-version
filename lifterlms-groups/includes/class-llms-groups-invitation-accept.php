<?php
/**
 * LifterLMS Groups Invitation Acceptance Flow.
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Invitation_Accept class
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.3 Add filter `llms_groups_force_open_registration_for_invites` to allow open registration forcing to be disabled.
 * @since 1.0.0-beta.4 Add validation for open invitation links without any open seats.
 *                     Invitations with an email address are deleted after acceptance.
 * @since 1.0.0-beta.5 Fixed group name displayed in the invitation notice.
 */
class LLMS_Groups_Invitation_Accept {

	/**
	 * Invite Key Cookie Name.
	 *
	 * @var string
	 */
	protected $cookie = '';

	/**
	 * Constructor
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function __construct() {

		$this->cookie = sprintf( 'llms-group-invite-%s', COOKIEHASH );

		add_action( 'wp', array( $this, 'link_redirect' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Add field modification filters based on
	 *
	 * @since 1.0.0-beta.13
	 *
	 * @return void
	 */
	private function add_field_mod_filters() {

		if ( version_compare( llms()->version, '5.0.0', '<' ) ) {
			add_filter( 'lifterlms_person_login_fields', array( $this, 'modify_fields' ), 10 );
			add_filter( 'lifterlms_get_person_fields', array( $this, 'modify_fields' ), 10, 2 );
		} else {
			add_filter( 'llms_field_settings', array( $this, 'modify_field_settings' ), 10 );
		}
	}

	/**
	 * Process the acceptance of an invitation
	 *
	 * This method runs on `init` for logged in users only.
	 *
	 * The invitation key in the cookie is validated via $this->init() prior to calling this method so
	 * $this->get_invitation() is guaranteed to return a valid LLMS_Group_Invitation.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Add validation for acceptance of an open invitation link.
	 *                     Invitations with an email address are deleted after acceptance.
	 *
	 * @return void
	 */
	protected function accept_invitation() {

		$invitation = $this->get_invitation();
		$group_id   = $invitation->get( 'group_id' );
		$user       = wp_get_current_user();
		$valid      = $invitation->is_valid( $user->user_email );
		$msg_type   = 'error';

		if ( llms_is_user_enrolled( $user->ID, $invitation->get( 'group_id' ) ) ) {

			// Translators: %s = Group name (singular).
			$msg = sprintf( __( 'You are already a member of %s.', 'lifterlms-groups' ), get_the_title( $group_id ) );

		} elseif ( is_wp_error( $valid ) ) {

			$msg = $valid->get_error_message();

		} else {

			$enrollment = LLMS_Groups_Enrollment::add( $user->ID, $group_id, 'invitation', $invitation->get( 'role' ) );

			// If it's not an open invitation, delete it.
			if ( $invitation->get( 'email' ) ) {
				$invitation->delete();
			}

			$msg_type = 'success';
			$msg      = sprintf(
				// Translators: %1$s = Group name; %2$s = opening anchor tag; %3$s = closing anchor tag.
				__( 'Congratulations! You have joined %1$s. %2$sClick here%3$s to get started.', 'lifterlms-groups' ),
				get_the_title( $group_id ),
				'<a href="' . esc_url( get_permalink( $group_id ) ) . '">',
				'</a>'
			);

		}

		// Output a notice.
		llms_add_notice( $msg, $msg_type );

		// Delete the cookie.
		$this->setcookie();
	}

	/**
	 * Retrieve an invitation object from data stored in the invite key cookie.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return LLMS_Group_Invitation|false
	 */
	protected function get_invitation() {

		$ret = false;

		if ( isset( $_COOKIE[ $this->cookie ] ) ) {

			$key = sanitize_text_field( wp_unslash( $_COOKIE[ $this->cookie ] ) );
			$ret = llms_groups()->invitations()->get_by_invite_key( $key );

			if ( ! $ret ) {
				$ret = new WP_Error( 'llms_groups_invite_key_invalid', __( 'The invitation is invalid or expired.', 'lifterlms-groups' ), $key );
			}
		}

		return $ret;
	}

	/**
	 * Initialize acceptance actions based on the presence and validity of the cookie and the current user state.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function init() {

		$invitation = $this->get_invitation();

		if ( is_wp_error( $invitation ) ) {

			llms_add_notice( $invitation->get_error_message(), 'error' );
			$this->setcookie( '' );

		} elseif ( is_a( $invitation, 'LLMS_Group_Invitation' ) ) {

			if ( get_current_user_id() ) {

				// User is already logged in, continue to invitation acceptance.
				$this->accept_invitation();

			} else {

				// The user isn't logged in so they should see the modified dashboard.
				add_action( 'lifterlms_before_student_dashboard', array( $this, 'modify_dashboard' ) );

			}
		}
	}

	/**
	 * Store query variable invite keys in a cookie and redirect user back to the dashboard.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0 Replaced use of the deprecated `FILTER_SANITIZE_STRING` constant.
	 *
	 * @return void
	 */
	public function link_redirect() {

		if ( is_llms_account_page() && isset( $_GET['invite'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$this->setcookie( wp_unslash( llms_filter_input_sanitize_string( INPUT_GET, 'invite' ) ) );
			llms_redirect_and_exit( llms_get_page_url( 'myaccount' ) );

		}
	}

	/**
	 * Modify the student dashboard for logged out users when an invite key cookie is present.
	 *
	 * This method assumes that `$this->get_invitation()` will return a valid LLMS_Group_Invitation
	 * as it is validated in `$this->modify_dashboard()`.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.3 Add filter `llms_groups_force_open_registration_for_invites` to allow open registration forcing to be disabled.
	 * @since 1.0.0-beta.4 Output an error message when an open invitation link is used for a group with no open seats.
	 * @since 1.0.0-beta.5 Fixed group name displayed in the invitation notice.
	 * @since 1.0.0-beta.13 Modify fields using filters available in LLMS core 5.0.
	 *
	 * @return void
	 */
	public function modify_dashboard() {

		$invitation = $this->get_invitation();
		$email      = $invitation->get( 'email' );
		$valid      = ! $email ? $invitation->is_valid() : true;

		if ( is_wp_error( $valid ) ) {

			llms_add_notice( $valid->get_error_message(), 'error' );

		} else {

			// Add an invitation notice.
			$msg = sprintf(
				// Translators: %1$s = Site name; %2$s = Group Name.
				__( 'Welcome to %1$s! You have been invited to join %2$s. Sign in or register below to accept the invitation.', 'lifterlms-groups' ),
				get_bloginfo( 'name' ),
				get_the_title( $invitation->get( 'group_id' ) )
			);
			llms_add_notice( $msg, 'success' );

			/**
			 * Determines whether or not open registration is "forced" on
			 *
			 * By default, if a visitor arrives at the student dashboard using a group invitation link
			 * Open Registration is forced on regardless of the site's open registration settings.
			 *
			 * This behavior can be disabled, meaning that only existing site users can use group
			 * invitation links.
			 *
			 * @since 1.0.0-beta.3
			 *
			 * @param bool $force_open_reg If `true` (default), Open registration is forced on. Return `false` to turn off this behavior.
			 */
			$force_open_reg = apply_filters( 'llms_groups_force_open_registration_for_invites', true );
			if ( $force_open_reg ) {

				// Force Open Registration when accepting an invitation.
				add_filter( 'llms_enable_open_registration', '__return_true' );

			}

			// Not an open link, force email for login/reg.
			if ( $email ) {
				$this->add_field_mod_filters();
			}
		}
	}

	/**
	 * Modify login and registration email/login fields
	 *
	 * Outputs the invitation's email address associated with the invitation.
	 *
	 * This method assumes that `$this->get_invitation()` will return a valid LLMS_Group_Invitation
	 * as it is validated in `$this->modify_dashboard()`.
	 *
	 * @since 1.0.0-beta.13
	 *
	 * @param array $settings Field settings.
	 * @return array
	 */
	public function modify_field_settings( $settings ) {

		if ( in_array( $settings['id'], array( 'llms_login', 'email_address' ), true ) ) {
			$invitation        = $this->get_invitation();
			$settings['value'] = $invitation->get( 'email' );
		}

		return $settings;
	}

	/**
	 * Modify login and registration email/login fields
	 *
	 * Outputs the invitation's email address associated with the invitation.
	 *
	 * This method assumes that `$this->get_invitation()` will return a valid LLMS_Group_Invitation
	 * as it is validated in `$this->modify_dashboard()`.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param  array[] $fields Array of LLMS Form Field data.
	 * @param  string  $screen Form screen name. This is empty for the login form.
	 * @return array[]
	 */
	public function modify_fields( $fields, $screen = '' ) {

		$invitation = $this->get_invitation();

		$key = false;
		if ( ! $screen ) {
			$key = 'llms_login';
		} elseif ( 'registration' === $screen ) {
			$key = 'email_address';
		}

		if ( $key ) {
			foreach ( $fields as &$field ) {
				if ( ! empty( $field['id'] ) && $key === $field['id'] ) {
					$field['value'] = $invitation->get( 'email' );
					// @todo post LifterLMS 3.38 release the fields should be marked as "read only".
				}
			}
		}

		return $fields;
	}

	/**
	 * Set a cookie with the value of the invite key.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @todo After LifterLMS 3.38.0 is released this should switch to use `llms_setcookie()` so we can mock the cookie and test this method better.
	 *
	 * @param  string $value Cookie value.
	 * @return void
	 */
	private function setcookie( $value = '' ) {

		$path     = isset( $_SERVER['REQUEST_URI'] ) ? current( explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$duration = $value ? 0 : time() - YEAR_IN_SECONDS;
		$func     = function_exists( 'llms_setcookie' ) ? 'llms_setcookie' : 'setcookie';

		call_user_func( $func, $this->cookie, $value, $duration, $path, COOKIE_DOMAIN, is_ssl(), true );
	}
}

return new LLMS_Groups_Invitation_Accept();
