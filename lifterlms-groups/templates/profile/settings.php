<?php
/**
 * Single Group profile settings tab
 *
 * @package LifterLMS_Groups/Templates/Profile
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.8
 *
 * @property LLMS_Group $group    Group object.
 * @property array[]    $settings Array of editable group settings.
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( current_user_can( 'manage_group_information', $group->get( 'id' ) ) ) : ?>

<div class="llms-group-card card--group-profile-settings">

	<?php do_action( 'llms_group_profile_before_settings' ); ?>

	<form>

		<?php do_action( 'llms_group_profile_before_settings_fields' ); ?>

		<div class="llms-group-card-main llms-form-fields">

			<?php foreach ( $settings as $field ) : ?>
				<?php llms_form_field( $field ); ?>
			<?php endforeach; ?>

		</div>

		<?php do_action( 'llms_group_profile_after_settings_fields' ); ?>

		<footer class="llms-group-card-footer">

			<button class="llms-button-primary button-right llms-group-button" id="llms-group-save-settings" type="submit">
				<i class="fa fa-floppy-o" aria-hidden="true"></i>
				<?php _e( 'Save', 'lifterlms-groups' ); ?>
			</button>
			<div class="llms-group-error"></div>

		</footer>

	</form>

	<?php do_action( 'llms_group_profile_after_settings' ); ?>

</div>

<?php endif; ?>
