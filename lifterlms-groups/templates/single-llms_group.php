<?php
/**
 * Single Group main template
 *
 * @package LifterLMS_Groups/Templates
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.18 Content template moved in content-single-llms_group.php.
 * @version 1.0.0-beta.18
 */

defined( 'ABSPATH' ) || exit;

?>

<?php get_header( 'llms_group' ); ?>

<?php llms_groups_get_template( 'content-single-llms_group.php' ); ?>

<?php
get_footer( 'llms_group' );
