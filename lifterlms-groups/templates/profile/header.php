<?php
/**
 * Single Group profile header template part.
 *
 * @package LifterLMS_Groups/Templates/Profile
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.17
 *
 * @property LLMS_Group $group Group object.
 * @property array      $theme Array of theme settings.
 */

defined( 'ABSPATH' ) || exit;
?>

<header class="llms-group-profile-header" style="height:<?php printf( '%dpx', $theme['banner_dimensions'][1] ); ?>;">

	<div class="llms-group-logo">
		<div
			class="llms-logo-img<?php echo esc_attr( empty( $theme['logo_auto_fit'] ) ? '' : ' auto-fit' ); ?>"
			id="llms-logo-img" style="height:<?php printf( '%dpx', $theme['logo_dimensions'] ); ?>;width:<?php printf( '%dpx', $theme['logo_dimensions'] ); ?>;">
			<img src="<?php echo $group->get_logo( $theme['logo_dimensions'] ); ?>">
		</div>

		<?php if ( current_user_can( 'manage_group_information', $group->get( 'id' ) ) ) : ?>
			<button class="llms-group-button ghost edit-logo" id="llms-group-upload-logo" type="button">
				<i class="fa fa-camera" aria-hidden="true"></i>
				<span class="llms-group-text"><?php _e( 'Update logo', 'lifterlms-groups' ); ?></span>
			</button>
		<?php endif; ?>

	</div>

	<h2 class="llms-group-name"><?php echo get_the_title(); ?></h2>

	<div class="llms-group-banner">
		<?php if ( current_user_can( 'manage_group_information', $group->get( 'id' ) ) ) : ?>
			<button class="llms-group-button ghost edit-banner" id="llms-group-upload-banner" type="button">
				<i class="fa fa-camera" aria-hidden="true"></i>
				<span class="llms-group-text"><?php _e( 'Update banner', 'lifterlms-groups' ); ?></span>
			</button>
			<?php wp_nonce_field( 'llms_group_banner_image_upload', 'llms-group-banner-image-upload-nonce' ); ?>
		<?php endif; ?>
		<div
			class="llms-banner-img<?php echo esc_attr( empty( $theme['banner_auto_fit'] ) ? '' : ' auto-fit' ); ?>"
			id="llms-banner-img"
			style="<?php printf( empty( $theme['banner_auto_fit'] ) ? 'height:%1$dpx;width:%2$dpx' : 'height:%1$dpx;width:100%%;min-width:%2$dpx', $theme['banner_dimensions'][1], $theme['banner_dimensions'][0] ); ?>">
			<img src="<?php echo $group->get_banner( $theme['banner_dimensions'] ); ?>">
		</div>
	</div>

</header>
