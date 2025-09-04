<?php
/**
 * File Summary
 *
 * File description.
 *
 * @package LifterLMS/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="llms-group-card card--group-profile-about">

	<header class="llms-group-card-header">
		<h3 class="llms-group-card-title">
			<i class="fa fa-info-circle" aria-hidden="true"></i>
			<?php _e( 'About', 'lifterlms-groups' ); ?>
		</h3>
		<?php if ( current_user_can( 'manage_group_information', $group->get( 'id' ) ) ) : ?>
			<button class="llms-group-button ghost llms-group-card-action edit-profile" id="llms-group-edit-profile" type="button">
				<div class="state--inactive">
					<span class="llms-group-text"><?php _e( 'Edit Information', 'lifterlms-groups' ); ?></span>
					<i class="fa fa-pencil" aria-hidden="true"></i>
				</div>
				<div class="state--active">
					<span class="llms-group-text"><?php _e( 'Cancel', 'lifterlms-groups' ); ?></span>
					<i class="fa fa-times" aria-hidden="true"></i>
				</div>
			</button>
		<?php endif; ?>
	</header>

	<div class="llms-group-card-main">
		<div class="llms-group-card-text"><?php the_content(); ?></div>
	</div>

	<?php if ( current_user_can( 'manage_group_information', $group->get( 'id' ) ) ) : ?>

		<footer class="llms-group-card-footer">

			<button class="llms-button-primary button-right llms-group-button" id="llms-group-save-info">
				<i class="fa fa-floppy-o" aria-hidden="true"></i>
				<?php _e( 'Save', 'lifterlms-groups' ); ?>
			</button>
			<div class="llms-group-error"></div>

		</footer>

	<?php endif; ?>

</div>
