<?php
/**
 * Single Group profile navigation template part
 *
 * @package LifterLMS_Groups/Templates/Profile
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.6
 */

defined( 'ABSPATH' ) || exit;
?>

<nav class="llms-group-profile-nav">

	<?php do_action( 'llms_group_profile_before_nav_menu' ); ?>

	<ul class="llms-group-menu">
		<?php foreach ( $navigation as $slug => $data ) : ?>
			<li class="llms-group-menu-item <?php printf( 'item--%s', $slug ); ?><?php echo ! empty( $data['active'] ) ? ' current' : ''; ?>">
				<a class="llms-group-menu-link" href="<?php echo esc_url( $data['url'] ); ?>"><?php echo $data['title']; ?></a>
			</li>
		<?php endforeach; ?>

		<?php if ( llms_is_user_enrolled( get_current_user_id(), get_the_ID() ) && ! llms_group_is_user_primary_admin( get_current_user_id(), get_the_ID() ) ) : ?>
			<li class="llms-group-menu-item item--right item--member-more">
				<button class="llms-group-button llms-group-has-context-menu">
					<i class="fa fa-ellipsis-h" aria-hidden="true"></i>
					<span class="screen-reader-text"><?php _e( 'More', 'lifterlms-groups' ); ?></span>
				</button>
				<ul class="llms-group-context-menu member-actions">
					<li>
						<button class="llms-group-member-action action--leave-group" id="llms-group-leave" data-uid="<?php echo get_current_user_id(); ?>">
							<?php _e( 'Leave group', 'lifterlms-groups' ); ?>
							<i class="fa fa-spinner inactive" aria-hidden="true"></i>
						</button>
					</li>
				</ul>
			</li>

		<?php endif; ?>

	</ul>

	<?php do_action( 'llms_group_profile_after_nav_menu' ); ?>

</nav>
