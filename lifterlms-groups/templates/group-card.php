<?php
/**
 * Group Directory Loop.
 *
 * @package LifterLMS/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 *
 * @property LLMS_Group $group Group object.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="llms-group" id="<?php printf( 'llms-group-%d', $group->get( 'id' ) ); ?>">

	<a href="<?php echo esc_url( get_permalink() ); ?>">

		<div class="llms-group-logo">
			<img src="<?php echo $group->get_logo(); ?>">
		</div>

		<h2 class="llms-group-name"><?php echo get_the_title(); ?></h2>

	</a>

</div>
