<?php
/**
 * Single Group profile image uploader modal
 *
 * @package LifterLMS_Groups/Templates/Profile
 *
 * @since 1.0.0-beta.1
 * @version 1.0.1
 *
 * @property LLMS_Group $group Group object.
 * @property array      $theme Array of theme settings.
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( current_user_can( 'manage_group_information', $group->get( 'id' ) ) ) : ?>

	<div class="llms-group-modal" id="llms-group-upload-modal" aria-hidden="true">

		<div class="llms-group-modal--overlay" tabindex="-1" data-micromodal-close>

			<div class="llms-group-modal--container" role="dialog" aria-modal="true" aria-labelledby="llms-group-upload-modal-title" >

				<header class="screen-reader-text">
					<h2 id="llms-group-upload-modal-title"><?php _e( 'Upload', 'lifterlms-groups' ); ?></h2>
					<button data-micromodal-close><?php esc_attr_e( 'Close dialog', 'lifterlms-groups' ); ?></button>
				</header>

				<div class="llms-group-modal--content" id="llms-group-upload-modal-content">

					<label class="llms-group-uploader" data-file-slug="<?php echo esc_attr( $group->get( 'name' ) ); ?>" id="llms-group-uploader-zone" for="llms-group-upload-image">

						<i class="fa fa-upload" aria-hidden="true"></i>
						<h2><?php _e( 'Select or drop an image...', 'lifterlms-groups' ); ?></h2>

						<p class="llms-group-helper-text banner">
						<?php
							printf(
								// Translators: %1$s = width; %2$s = height.
								__( 'For best results, use an image at least %1$dpx wide and %2$dpx tall.', 'lifterlms-groups' ),
								$theme['banner_dimensions'][0],
								$theme['banner_dimensions'][1]
							);
						?>
						</p>
						<p class="llms-group-helper-text logo">
						<?php
							printf(
								// Translators: %1$d size.
								__( 'For best results use a %1$dpx square image.', 'lifterlms-groups' ),
								$theme['logo_dimensions']
							);
						?>
						</p>
						<input id="llms-group-upload-image" type="file" accept=".jpg,.jpeg,.jpe,.png">

						<p class="llms-group-error" id="llms-group-modal-error"></p>

					</label>

				</div>

			</div>

		</div>

	</div>

<?php endif; ?>
