<?php
/**
 * Main Group REST class
 *
 * Initializes and includes REST contollres
 *
 * @package LifterLMS/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_REST class
 *
 * @since 1.0.0-beta.1
 */
class LLMS_Groups_REST {

	/**
	 * Constructor
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'rest_api_init', array( $this, 'init' ), 15 );
	}

	/**
	 * Initialize group REST controllers.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return bool
	 */
	public function init() {

		// Only load if the LifterLMS REST API Core is loaded.
		if ( ! class_exists( 'LLMS_REST_Posts_Controller' ) ) {
			return false;
		}

		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/rest/class-llms-groups-rest-groups-controller.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/rest/class-llms-groups-rest-group-invitations-controller.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/rest/class-llms-groups-rest-group-members-controller.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/rest/class-llms-groups-rest-group-seats-controller.php';

		$controllers = array(
			'LLMS_Groups_REST_Groups_Controller',
			'LLMS_Groups_REST_Group_Invitations_Controller',
			'LLMS_Groups_REST_Group_Members_Controller',
			'LLMS_Groups_REST_Group_Seats_Controller',
		);

		foreach ( $controllers as $name ) {

			$controller = new $name();
			$controller->register_routes();

		}

		return true;
	}
}

return new LLMS_Groups_REST();
