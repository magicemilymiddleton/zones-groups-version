<?php
/**
 * Student Dashboard: "My Groups" Tab
 *
 * @package LifterLMS_Groups/Templates
 *
 * @since 1.0.0-beta.4
 * @version 1.0.0-beta.4
 *
 * @property WP_Query|false $query Query object containing a list of groups or false if the student doesn't belong to any groups.
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( ! $query || $query->have_posts() ) : ?>

	<div class="llms-my-groups llms-groups-card-list">

		<?php
		while ( $query->have_posts() ) :
			$query->the_post();
			/**
			 * Outputs a single group card
			 *
			 * Hooked: llms_groups_template_group_card - 10
			 *
			 * @since 1.0.0-beta.4
			 */
			do_action( 'llms_groups_group_card' );
		endwhile;
		?>

	</div>

	<?php llms_get_template( 'loop/pagination.php' ); ?>

<?php else : ?>

	<?php _e( 'You do not belong to any groups.', 'lifterlms-groups' ); ?>

<?php endif; ?>
