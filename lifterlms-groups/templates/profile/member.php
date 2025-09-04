<?php
/**
 * Group's member profile template part
 *
 * @package LifterLMS/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.6
 *
 * @property LLMS_Student $member  Student object representing the group members.
 * @property string       $context Card location context, either "sidebar" or "main".
 * @property string       $role    Member's role within the group.
 */

defined( 'ABSPATH' ) || exit;

$actions = llms_groups_can_user_manage_member( get_current_user_id(), $member->get( 'id' ), get_the_ID() ) ? llms_groups_get_actions_for_member( $member->get( 'id' ), get_the_ID() ) : false;
?>

<div class="llms-group-member <?php echo esc_attr( sprintf( 'group-role--%s', $role ) ); ?>" data-id="<?php echo $member->get( 'id' ); ?>">

	<?php echo $member->get_avatar( 'sidebar' === $context ? 48 : 58 ); ?>

	<?php if ( 'main' === $context ) : ?>

		<div class="llms-group-member--desc">
			<h5 class="llms-group-member--name"><?php echo $member->get_name(); ?></h5>
			<?php if ( in_array( $role, array( 'admin', 'leader' ), true ) ) : ?>
				<h6 class="llms-group-member--role">
					<i class="fa fa-star" aria-hidden="true"></i>
					<?php echo llms_groups_get_role_name( $role ); ?>
					<?php if ( llms_group_is_user_primary_admin( $member->get( 'id' ), get_the_ID() ) && current_user_can( 'manage_group_managers', get_the_ID() ) ) : ?>
						<em class="llms-group-primary-admin-identifier">(<?php _e( 'Primary', 'lifterlms-groups' ); ?>)</em>
					<?php endif; ?>
				</h6>
			<?php else : ?>
				<h6 class="llms-group-member--since">
					<?php
						printf(
							// Translators: %s time string describing the length of time from now when the member joined.
							__( 'Joined %s ago', 'lifterlms-groups' ),
							human_time_diff(
								$member->get_enrollment_date( get_the_ID(), 'enrolled', 'U' ),
								llms_current_time( 'timestamp' )
							)
						);
					?>
				</h6>
			<?php endif; ?>

			<?php if ( $actions ) : ?>
				<button class="llms-group-card-action llms-group-button llms-group-has-context-menu manage-member">
					<i class="fa fa-ellipsis-h" aria-hidden="true"></i>
					<span class="screen-reader-text"><?php _e( 'Manage member', 'lifterlms-groups' ); ?></span>
				</button>
				<ul class="llms-group-context-menu manage-member-actions">
					<?php foreach ( $actions as $key => $label ) : ?>
						<li>
							<button class="llms-group-member-action" data-action="<?php echo esc_attr( $key ); ?>" data-uid="<?php echo $member->get( 'id' ); ?>">
								<?php echo $label; ?>
								<i class="fa fa-spinner inactive" aria-hidden="true"></i>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

		</div>

	<?php endif; ?>

</div>
