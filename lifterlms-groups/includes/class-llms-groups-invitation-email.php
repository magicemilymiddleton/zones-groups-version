<?php
/**
 * Handle sending of group invitation email notification
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Invitation_Email class
 *
 * @since 1.0.0-beta.1
 */
class LLMS_Groups_Invitation_Email {

	/**
	 * Constructor, add actions
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'llms_group_invitation_created', array( $this, 'send' ), 10, 2 );
	}

	/**
	 * Retrieve the HTML body of the invitation.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @return string
	 */
	protected function get_body() {

		ob_start();
		llms_groups_get_template( 'emails/invitation.php' );
		return ob_get_clean();
	}

	/**
	 * Get the subject of the invitation email.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  int $group_id WP Post ID of the group.
	 * @return string
	 */
	protected function get_subject( $group_id ) {

		$group_name = get_the_title( $group_id );
		$site_name  = get_bloginfo( 'name' );

		// Translators: %1$s = Group name; %2$s = Site name.
		$subject = sprintf( __( 'Your invitation to join "%1$s" on %2$s', 'lifterlms-groups' ), $group_name, $site_name );

		/**
		 * Customize the group invitation email subject.
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param string $subject    The email subject.
		 * @param int    $group_id   WP_Post ID of the group.
		 * @param string $group_name The name of the group.
		 * @param string $site_name  The name of the site.
		 */
		return apply_filters( 'llms_group_invitation_email_subject', $subject, $group_id, $group_name );
	}

	/**
	 * Send an invitation email.
	 *
	 * Only sends an email if an email address is stored on the invitation (open links won't send an email).
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  int                   $invitation_id Invitation ID.
	 * @param  LLMS_Group_Invitation $invitation    Invitation object.
	 * @return true|WP_Error|false   `true` on success
	 *                               `false` if there's no email to send (an open link)
	 *                               `WP_Error` on error.
	 */
	public function send( $invitation_id, $invitation ) {

		$email = $invitation->get( 'email' );
		if ( ! $email ) {
			return false;
		}

		$group = llms_get_post( $invitation->get( 'group_id' ) );
		if ( ! $group ) {
			return new WP_Error( 'llms_group_invite_no_group', __( 'Group does not exist.', 'lifterlms-groups' ) );
		}

		$mailer = LLMS()->mailer()->get_email( 'group_invitation' );

		$mailer->add_merge_data(
			array(
				'{group_name}' => get_the_title( $group->get( 'id' ) ),
				'{invite_url}' => $invitation->get_accept_link(),
			)
		);

		$mailer->add_recipient( $email );
		$mailer->set_subject( $this->get_subject( $group->get( 'id' ) ) );
		$mailer->set_body( $this->get_body() );

		if ( ! $mailer->send() ) {
			llms_log( sprintf( 'Error sending invitation email for group "%1$s" to "%2$s".', $group->get( 'title' ), $invitation->get( 'email' ) ), 'groups' );
			return new WP_Error( 'llms_group_invite_email_send', __( 'Unable to send the group invitation email.', 'lifterlms-groups' ), $invitation->to_array() );
		}

		return true;
	}
}

return new LLMS_Groups_Invitation_Email();
