<?php
/**
 * Sidebar: Group Content
 *
 * @package LifterLMS_Groups/Templates
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 *
 * @property LLMS_Group    $group      Group object.
 * @property WP_Query|null $loop_query WP_Query Object used to display the course/membership "card".
 * @property string        $card_title Title string for the card.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="llms-group-card card--group-profile-content">

	<header class="llms-group-card-header">
		<h3 class="llms-group-card-title">
			<i class="fa fa-archive" aria-hidden="true"></i>
			<?php echo $card_title; ?>
		</h3>
	</header>

	<div class="llms-group-card-main">
		<?php if ( $loop_query ) : ?>
			<?php lifterlms_loop( $loop_query ); ?>
		<?php elseif ( current_user_can( 'publish_groups' ) ) : ?>
			<?php
				// Translators: %s = Group name (singular).
				printf( __( 'No content is available for this %s. Content can be added on the settings tab.', 'lifterlms-groups' ), llms_groups()->get_integration()->get_option( 'post_name_singular' ) );
			?>
		<?php endif; ?>
	</div>

</div>
