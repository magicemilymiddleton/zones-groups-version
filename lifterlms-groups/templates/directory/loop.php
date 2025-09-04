<?php
/**
 * Group Directory Loop.
 *
 * @package LifterLMS/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.4
 *
 * @property WP_Query $query Query object.
 */

defined( 'ABSPATH' ) || exit;
?>

<?php do_action( 'llms_groups_directory_before_loop' ); ?>

<div class="llms-group-directory" id="llms-group-directory">

	<?php if ( $query->have_posts() ) : ?>

		<div class="llms-group-directory llms-groups-card-list">

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

	<?php endif; ?>

</div><!-- #llms-group-directory -->

<?php do_action( 'llms_groups_directory_after_loop' ); ?>
