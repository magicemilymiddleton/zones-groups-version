<?php
/**
 * Sidebar: Manage group invitations
 *
 * @package LifterLMS_Groups/Templates
 *
 * @since 1.0.0-beta.5
 * @since 1.0.0-beta.19 Fixed access of protected LLMS_Abstract_Query properties.
 * @since 1.0.0 Hidden "Delete" button for invitations which cannot be deleted by the current user.
 * @version 1.0.0
 *
 * @var LLMS_Group                    $group Group object.
 * @var LLMS_Groups_Invitations_Query $query Query object containing a list of pending invitations for the group.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="llms-group-card card--group-profile-invitations">

	<header class="llms-group-card-header">
		<h3 class="llms-group-card-title">
			<i class="fa fa-archive" aria-hidden="true"></i>
			<?php _e( 'Pending Invitations', 'lifterlms-groups' ); ?>
		</h3>
	</header>

	<div class="llms-group-card-main" id="llms-group-pending-invitations-list">

		<div class="llms-group-pending-invitations-msg"<?php echo $query->has_results() ? ' style="display:none;"' : ''; ?>><?php _e( 'No pending invitations found.', 'lifterlms-groups' ); ?></div>

		<?php foreach ( $query->get_invitations() as $invitation ) : ?>
			<div class="llms-group-pending-invitation">
				<span><?php echo $invitation->get( 'email' ); ?></span>

				<?php
				if ( llms_groups_invitation_can_be_deleted( $invitation ) ) {
					?>
					<button class="llms-group-button button-right small ghost on-light-bg invitation--trash" title="<?php esc_attr_e( 'Delete', 'lifterlms-groups' ); ?>" data-id="<?php echo absint( $invitation->get( 'id' ) ); ?>"><i class="fa fa-trash" aria-hidden="true"></i></button>
				<?php } ?>

				<button class="llms-group-button button-right small ghost on-light-bg invitation--link" title="<?php esc_attr_e( 'Copy Link', 'lifterlms-groups' ); ?>" data-clipboard-text="<?php echo esc_attr( $invitation->get_accept_link() ); ?>"><i class="fa fa-clone" aria-hidden="true"></i></button>
			</div>
		<?php endforeach; ?>

	</div>

	<?php if ( $query->get_number_results() > 5 ) : ?>

		<footer class="llms-group-card-footer">
			<input id="llms-group-search-pending-invitations" type="search" placeholder="<?php esc_attr_e( 'Search by email', 'lifterlms-groups' ); ?>">
		</footer>

	<?php endif; ?>

</div>
