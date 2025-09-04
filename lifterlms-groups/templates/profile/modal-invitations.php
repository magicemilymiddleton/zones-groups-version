<?php
/**
 * Single Group profile members invitation management modal
 *
 * @package LifterLMS_Groups/Templates/Profile
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.8
 *
 * @property LLMS_Group $group Group object.
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( current_user_can( 'manage_group_members', $group->get( 'id' ) ) ) : ?>

	<div class="llms-group-modal llms-group-invite-modal" id="llms-group-invite-modal" aria-hidden="true">

		<div class="llms-group-modal--overlay" tabindex="-1" data-micromodal-close>

			<div class="llms-group-modal--container" role="dialog" aria-modal="true" aria-labelledby="llms-group-invite-modal-title" >

				<header class="llms-group-modal--header">
					<h2 id="llms-group-invite-modal-title"><?php _e( 'Invite Members', 'lifterlms-groups' ); ?></h2>
					<button class="llms-group-button ghost on-light-bg" data-micromodal-close>
						<span class="screen-reader-text"><?php _e( 'Close', 'lifterlms-groups' ); ?></span>
						<i class="fa fa-times" aria-hidden="true"></i>
					</button>
				</header>

				<div class="llms-group-modal--content llms-form-fields flush" id="llms-group-invite-modal-content">

					<form>

						<?php
						llms_form_field(
							array(
								'type'        => 'text',
								'id'          => 'llms_group_invitation_emails',
								'placeholder' => __( 'Email address', 'lifterlms-groups' ),
							)
						);

						if ( current_user_can( 'manage_group_managers', $group->get( 'id' ) ) ) {
							llms_form_field(
								array(
									'description' => llms_groups_get_role_descriptions_html(),
									'type'        => 'select',
									'id'          => 'llms_group_invitation_role',
									'options'     => llms_groups_get_roles(),
								)
							);
						}
						?>

						<button class="llms-button-primary button-right large llms-group-button" id="llms-group-send-invite" type="submit" disabled>
							<i class="fa fa-envelope-o" aria-hidden="true"></i>
							<?php _e( 'Send Invitation', 'lifterlms-groups' ); ?>
						</button>

						<div class="llms-group-error error--send"></div>

					</form>

				</div>

				<footer class="llms-group-modal--footer llms-form-fields flush <?php echo $group->has_open_invite() ? 'is-enabled' : ''; ?>">
					<h4><?php _e( 'Invite with Link', 'lifterlms-groups' ); ?></h4>

					<a class="llms-group-open-invite-link" id="llms-group-open-invite-link" href="#">
						<span class="link--is-enabled"><?php _e( 'Disable link', 'lifterlms-groups' ); ?></span>
						<span class="link--is-disabled"><?php _e( 'Enable link', 'lifterlms-groups' ); ?></span>
					</a>
					<p class="llms-group-error error--link"></p>

					<?php
					llms_form_field(
						array(
							'type'  => 'text',
							'id'    => 'llms_group_invitation_link',
							// Translators: %1$s = group name (singluar); %2$s = member name (singular).
							'label' => sprintf( __( 'Anyone with the link can join the %1$s as a %2$s', 'lifterlms-groups' ), strtolower( llms_groups()->get_integration()->get_option( 'post_name_singular' ) ), strtolower( llms_groups_get_role_name( 'member' ) ) ),
							'value' => $group->get_open_invite_link(),
						)
					);
					?>
					<input id="llms_group_invitation_link_id" type="hidden" value="<?php echo $group->has_open_invite() ? absint( $group->get_open_invite()->get( 'id' ) ) : ''; ?>">
				</footer>

			</div>

		</div>

	</div>

<?php endif; ?>
