<?php
/**
 * LifterLMS Groups Main Class
 *
 * @package LifterLMS_Groups/Main
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LifterLMS Groups class.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Bumped minimum LLMS core required version to 3.38.1.
 * @since 1.0.0-beta.5 Include `LLMS_Groups_Invitations_Query` class.
 */
final class LifterLMS_Groups {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '1.2.2';

	/**
	 * Singleton instance of the class
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Singleton Instance of the LifterLMS_Groups class
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return LifterLMS_Groups Singleton class instance.
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	private function __construct() {

		if ( ! defined( 'LLMS_GROUPS_VERSION' ) ) {
			define( 'LLMS_GROUPS_VERSION', $this->version );
		}

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		// get started.
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Access the integration class
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @example $integration = llmn_groups()->get_integration();
	 *
	 * @return LLMS_Integration_Groups
	 */
	public function get_integration() {
		return LLMS()->integrations()->get_integration( 'groups' );
	}

	/**
	 * Include files and instantiate classes.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.5 Include `LLMS_Groups_Invitations_Query` class.
	 * @since 1.0.0-beta.18 Include `LLMS_Groups_Block_Templates` class.
	 * @since 1.0.0 Include `LLMS_Groups_Blocks` class.
	 *
	 * @return void
	 */
	private function includes() {

		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-integration-groups.php';

		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/functions-llms-groups.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/functions-llms-groups-templates.php';

		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/models/class-llms-group.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/models/class-llms-group-invitation.php';

		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-assets.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-banner-image-ajax-handler.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-capabilities.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-checkout.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-checkout-ajax-handler.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-directory.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-enrollment.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-install.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-invitation-accept.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-invitation-email.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-invitations.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-invitations-query.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-member-query.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-profile.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-post-type.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-student-bulk-enroll.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-student-dashboard.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/rest/class-llms-groups-rest.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/theme-support/class-llms-groups-theme-support.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-block-templates.php';
		require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/class-llms-groups-blocks.php';

		if ( is_admin() ) {
			require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/admin/class-llms-groups-post-type-admin-ui.php';
			require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/admin/class-llms-groups-access-plan-settings-admin-ui.php';
		} else {
			require_once LLMS_GROUPS_PLUGIN_DIR . 'includes/hooks-llms-groups-templates.php';
		}
	}

	/**
	 * Include all required files and classes.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Bumped minimum LLMS core required version to 3.38.1.
	 * @since 1.0.0-beta.12 Use `llms()` in favor of `LLMS()` and bump minimum required core version to 4.21.2.
	 * @since 1.0.0-beta.17 Bump minimum required core version to 5.6.0.
	 * @since 1.0.0 Bump minimum required core version to 7.2.0.
	 *
	 * @return void
	 */
	public function init() {

		if ( function_exists( 'llms' ) && version_compare( '7.2.0', llms()->version, '<=' ) ) {

			$this->includes();

			// Register integration.
			add_filter( 'lifterlms_integrations', array( $this, 'register_integration' ), 10, 1 );

		}
	}

	/**
	 * Retrieve an instance of the LLMS_Groups_Invitations class
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return LLMS_Groups_Invitations
	 */
	public function invitations() {
		return LLMS_Groups_Invitations::instance();
	}

	/**
	 * Load Localization files
	 *
	 * The first loaded file takes priority
	 *
	 * Files can be found in the following order:
	 *      WP_LANG_DIR/lifterlms/lifterlms-groups-LOCALE.mo
	 *      WP_LANG_DIR/plugins/lifterlms-groups-LOCALE.mo
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function load_textdomain() {

		// Load locale.
		$locale = apply_filters( 'plugin_locale', get_locale(), 'lifterlms-groups' );

		// Load a lifterlms specific locale file if one exists.
		load_textdomain( 'lifterlms-groups', WP_LANG_DIR . '/lifterlms/lifterlms-groups-' . $locale . '.mo' );

		// Load localization files.
		load_plugin_textdomain( 'lifterlms-groups', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Register the integration with LifterLMS
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array $integrations Array of LifterLMS Integration Classes.
	 * @return array
	 */
	public function register_integration( $integrations ) {

		$integrations[] = 'LLMS_Integration_Groups';
		return $integrations;
	}
}
