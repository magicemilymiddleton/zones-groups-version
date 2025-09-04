<?php
/**
 * Manage Theme Support classes
 *
 * @package LifterLMS_Groups/ThemeSupport/Classes
 *
 * @since 1.0.0-beta.15
 * @version 1.0.0-beta.15
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Theme_Support class
 *
 * @since 1.0.0-beta.15
 */
class LLMS_Groups_Theme_Support {

	/**
	 * Constructor
	 *
	 * @since 1.0.0-beta.15
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'includes' ) );
	}

	/**
	 * Conditionally require additional theme support classes.
	 *
	 * @since 1.0.0-beta.15
	 *
	 * @return void
	 */
	public function includes() {

		$template = get_template();
		if ( is_readable( __DIR__ . "/class-llms-groups-{$template}.php" ) ) {
			require_once __DIR__ . "/class-llms-groups-{$template}.php";
		}
	}
}

return new LLMS_Groups_Theme_Support();
