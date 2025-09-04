<?php
/**
 * LifterLMS Groups Assets loader
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Assets class
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Add styles for the student dashboard.
 *                     Add script l10n for translations.
 *                     Add RTL CSS support.
 * @since 1.0.0-beta.6 Add the "profile-general" script.
 */
class LLMS_Groups_Assets {

	/**
	 * Array of data to add to the groups profile script localization object.
	 *
	 * @var array
	 */
	protected $inline_script_data = array();

	/**
	 * Constructor
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0 Add editor styles.
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

		add_action( 'after_setup_theme', array( $this, 'add_editor_styles' ) );
	}

	/**
	 * Retrieve translation data used to localize javascript.
	 *
	 * @since 1.0.0-beta.4
	 *
	 * @return array[]
	 */
	protected function get_i18n_object() {

		$post_types = array();

		foreach ( array( 'course', 'llms_membership' ) as $post_type ) {
			$obj = get_post_type_object( $post_type );
			if ( ! $obj ) {
				continue;
			}
			$post_types[ $post_type ] = array(
				'singular' => $obj->labels->singular_name,
				'plural'   => $obj->labels->name,
			);
		}

		return compact( 'post_types' );
	}

	public function admin_enqueue() {
		$screen = get_current_screen();

		if ( 'post' === $screen->base && in_array( $screen->id, array( 'course', 'llms_membership' ) ) ) {
			$asset = include LLMS_GROUPS_PLUGIN_DIR . 'assets/js/llms-groups-admin.asset.php';

			wp_register_script(
				'llms-groups-admin',
				LLMS_GROUPS_PLUGIN_ASSETS_URL . 'js/llms-groups-admin.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
			wp_set_script_translations( 'llms-groups-admin', 'lifterlms-groups', LLMS_GROUPS_PLUGIN_DIR . 'i18n' );

			wp_enqueue_script( 'llms-groups-admin' );
		}
	}

	/**
	 * Register and maybe enqueue scripts and styles.
	 *
	 * Assets are *always* registered but are only enqueued on group pages.
	 * Always registering ensures that 3rd parties can easily add our assets
	 * as dependencies without have to manually register them on their own.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Add styles for the student dashboard.
	 *                     Add an i18n object to the localized data to reduce the necessity of duplicating existing translations.
	 *                     Add RTL CSS support.
	 * @since 1.0.0-beta.5 Add styles for the student dashboard.
	 * @since 1.0.0-beta.20 Added `llms_groups_enqueue_dashboard_style` filter.
	 * @since 1.0.0 Load styles for block and shortcode.
	 *
	 * @return bool
	 */
	public function enqueue() {

		$this->register();

		global $post;

		if ( is_llms_checkout() ) {
			wp_enqueue_script( 'llms-groups-checkout' );
		}

		wp_enqueue_script( 'llms-groups-checkout-access-plan' );

		if (
			LLMS_Groups_Directory::is_directory() ||
			has_block( 'llms/group-list' ) ||
			( is_singular() && has_shortcode( $post->post_content ?? '', LLMS_Groups_Shortcode_Group_List::TAG ) )
		) {
			$this->enqueue_style( 'llms-groups-directory' );
			return true;

		} elseif ( is_llms_group() ) {

			$this->enqueue_style( 'llms-groups-profile' );

			if ( current_user_can( 'manage_group_information', get_the_ID() ) ) {
				wp_enqueue_script( 'llms-groups-profile' );
				$this->inline_script_data['id']   = get_the_ID();
				$this->inline_script_data['i18n'] = $this->get_i18n_object();
			}

			if ( llms_is_user_enrolled( get_current_user_id(), get_the_ID() ) ) {
				wp_enqueue_script( 'llms-groups-profile-general' );
				$this->inline_script_data['id'] = get_the_ID();
			}

			$this->enqueue_inline_script_data();

			return true;

			/**
			 * Filters whether or not the groups dashboard style is enqueued.
			 *
			 * By default the style will be enqueued in the LifterLMS acccount page.
			 *
			 * @since 1.0.0-beta.20
			 *
			 * @param bool $enqueue Whether or not to enqueue the groups dashboard style.
			 */
		} elseif ( apply_filters( 'llms_groups_enqueue_dashboard_style', is_llms_account_page() ) ) {

			$this->enqueue_style( 'llms-groups-dashboard' );

		}

		return false;
	}

	/**
	 * Enqueue editor assets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {

		$this->register();

		$this->enqueue_style( 'llms-groups-editor' );
	}

	/**
	 * Add editor styles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_editor_styles(): void {
		$plugins_dir = basename( WP_PLUGIN_DIR );
		$plugin_dir  = basename( LLMS_GROUPS_PLUGIN_DIR );
		$rtl         = is_rtl() ? '-rtl' : '';
		$directory   = "../../$plugins_dir/$plugin_dir/assets/css/llms-groups-directory{$rtl}.css";

		add_editor_style( $directory );
	}

	/**
	 * Enqueue inline script data.
	 *
	 * @since 1.0.0-beta.6
	 * @since 1.0.0-beta.10 Use `LLMS_Assets::enqueue_inline()` in favor of `LLMS_Frontend_Assets::enqueue_inline_script()`.
	 *
	 * @return boolean
	 */
	protected function enqueue_inline_script_data() {

		if ( $this->inline_script_data ) {

			$func = class_exists( 'LLMS_Assets' ) ? array( llms()->assets, 'enqueue_inline' ) : array( 'LLMS_Frontend_Assets', 'enqueue_inline_script' );

			call_user_func(
				$func,
				'llms-groups-script-data',
				'window.llms_groups_data = ' . wp_json_encode( $this->inline_script_data ) . ';',
				'footer'
			);

			return true;

		}

		return false;
	}

	/**
	 * Enqueue a registered stylesheet and add RTL data
	 *
	 * @since 1.0.0-beta.4
	 *
	 * @param string $handle Stylesheet handle.
	 * @return void
	 */
	protected function enqueue_style( $handle ) {

		wp_enqueue_style( $handle );
		wp_style_add_data( $handle, 'rtl', 'replace' );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Add styles for the student dashboard.
	 * @since 1.0.0-beta.6 Register the "profile-general" script.
	 * @since 1.0.0 Register the "editor" style.
	 *
	 * @return void
	 */
	protected function register() {

		$profile_css_deps = array();

		foreach ( array( 'checkout-access-plan', 'checkout', 'profile', 'profile-general' ) as $script ) {

			$asset = include LLMS_GROUPS_PLUGIN_DIR . 'assets/js/llms-groups-' . $script . '.asset.php';

			if ( 'profile' === $script && in_array( 'llms-quill', $asset['dependencies'], true ) ) {
				LLMS_Admin_Assets::register_quill();
				$profile_css_deps[] = 'llms-quill-bubble';
			}

			wp_register_script(
				'llms-groups-' . $script,
				LLMS_GROUPS_PLUGIN_ASSETS_URL . 'js/llms-groups-' . $script . '.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
			wp_set_script_translations( 'llms-groups-' . $script, 'lifterlms-groups', LLMS_GROUPS_PLUGIN_DIR . 'i18n' );

		}

		// Register all styles.
		$styles = array(
			'dashboard' => array(),
			'directory' => array(),
			'profile'   => $profile_css_deps,
			'editor'    => array(),
		);

		foreach ( $styles as $id => $deps ) {
			wp_register_style(
				sprintf( 'llms-groups-%s', $id ),
				LLMS_GROUPS_PLUGIN_ASSETS_URL . sprintf( 'css/llms-groups-%s.css', $id ),
				$deps,
				LLMS_GROUPS_VERSION
			);
		}
	}
}

return new LLMS_Groups_Assets();
