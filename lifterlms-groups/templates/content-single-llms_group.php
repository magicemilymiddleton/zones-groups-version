<?php
/**
 * Single Group main content template
 *
 * @package LifterLMS_Groups/Templates
 *
 * @since 1.0.0-beta.18
 * @version 1.0.0-beta.18
 */

defined( 'ABSPATH' ) || exit;

$current_tab = LLMS_Groups_Profile::get_current_tab();
?>

<?php do_action( 'lifterlms_before_main_content' ); ?>

<?php if ( have_posts() ) : ?>

	<?php
	while ( have_posts() ) :
		the_post();
		?>

		<article <?php post_class( 'lifterlms llms-group llms-group-profile tab--' . $current_tab ); ?>>

			<?php
				/**
				 * Hook: llms_groups_profile_before_content
				 *
				 * @hooked llms_groups_template_profile_header - 10
				 * @hooked llms_groups_template_profile_navigation - 20
				 */
				do_action( 'llms_group_profile_before_content' );
			?>

			<div class="llms-group-content llms-group-profile-content <?php echo llms_groups_get_profile_layout_class(); ?>">

				<aside class="llms-group-sidebar llms-group-profile-sidebar">

					<?php
						/**
						 * Hook: llms_groups_profile_sidebar
						 *
						 * @hooked llms_groups_template_profile_card_members - 10
						 */
						do_action( 'llms_group_profile_sidebar' );
					?>

				</aside>

				<section class="llms-group-main llms-group-profile-main">

					<?php
						/**
						 * Hook: llms_groups_profile_main_about
						 *
						 * @hooked llms_groups_template_profile_about - 10
						 *
						 * llms_groups_profile_main_settings
						 *
						 * @hooked llms_groups_template_profile_settings - 10
						 */
						do_action( "llms_group_profile_main_{$current_tab}" );
					?>

				</section>

			</div>

			<?php do_action( 'llms_group_profile_after_content' ); ?>

		</article>

	<?php endwhile; ?>

<?php endif; ?>

<?php do_action( 'lifterlms_after_main_content' ); ?>
